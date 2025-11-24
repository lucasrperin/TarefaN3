import os, sys, json, re, hashlib
from typing import Dict, List, Tuple

try:
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stderr.reconfigure(encoding="utf-8")
except Exception:
    pass

from dotenv import load_dotenv, find_dotenv
load_dotenv(find_dotenv())

from supabase import create_client, Client

SUPABASE_REFORMA_URL  = os.getenv("SUPABASE_REFORMA_URL")
SUPABASE_REFORMA_SERVICE_ROLE_KEY  = os.getenv("SUPABASE_REFORMA_SERVICE_ROLE_KEY")
if not SUPABASE_REFORMA_URL or not SUPABASE_REFORMA_SERVICE_ROLE_KEY:
    print("Erro: SUPABASE_REFORMA_URL/SUPABASE_REFORMA_SERVICE_ROLE_KEY ausentes no .env")
    sys.exit(1)

supabase: Client = create_client(SUPABASE_REFORMA_URL, SUPABASE_REFORMA_SERVICE_ROLE_KEY)

SCRIPT_DIR  = os.path.dirname(os.path.abspath(__file__))               # .../ChatBot/scripts/video
CHATBOT_DIR = os.path.abspath(os.path.join(SCRIPT_DIR, os.pardir, os.pardir))  # .../ChatBot
TRANS_DIR   = os.path.join(CHATBOT_DIR, "embeddings", "transcricoes")

def norm_spaces(s: str) -> str:
    return re.sub(r"\s+", " ", (s or "").strip())

def make_uid(link: str | None, texto: str) -> str:
    if link:
        base = "url:" + link.strip().lower()
    else:
        norm = norm_spaces(texto).lower()
        base = "txt:" + norm
    return hashlib.sha1(base.encode("utf-8")).hexdigest()

def read_local() -> Dict[str, dict]:
    if not os.path.isdir(TRANS_DIR):
        print(f"Erro: diretório não existe: {TRANS_DIR}")
        sys.exit(1)
    files = sorted(fn for fn in os.listdir(TRANS_DIR) if fn.lower().endswith(".json"))
    res: Dict[str, dict] = {}
    for fn in files:
        p = os.path.join(TRANS_DIR, fn)
        try:
            with open(p, encoding="utf-8") as f:
                j = json.load(f)
        except Exception as e:
            print(f"[WARN] '{fn}': {e}")
            continue
        titulo = j.get("titulo") or j.get("content") or ""
        texto  = j.get("conteudo", "")
        link   = j.get("link") or None
        uid    = j.get("uid") or make_uid(link, texto)  # compat para JSONs antigos

        rec = {
            "uid": uid,
            "content": titulo,
            "metadata": {
                "conteudo": texto,
                "link": link or "",
                "filename": fn
            },
            "embedding": j.get("embeddings") or j.get("embedding"),
        }
        res[uid] = rec
    return res

def fetch_db() -> Tuple[Dict[str, int], Dict[str, dict], Dict[str, int]]:
    # retorna mapas por uid e fallback por titulo (para migração)
    start, step = 0, 1000
    uid_to_id: Dict[str, int] = {}
    title_to_id: Dict[str, int] = {}
    uid_to_row: Dict[str, dict] = {}
    while True:
        resp = supabase.table("videos").select("id, uid, content").range(start, start+step-1).execute()
        rows = getattr(resp, "data", resp)
        if not rows:
            break
        for r in rows:
            rid = r.get("id")
            uid = r.get("uid")
            title = norm_spaces(r.get("content", ""))
            if uid:
                uid_to_id[uid] = rid
                uid_to_row[uid] = r
            if title and title not in title_to_id:
                title_to_id[title] = rid
        if len(rows) < step:
            break
        start += step
    return uid_to_id, uid_to_row, title_to_id

def chunk(xs: List, n: int):
    for i in range(0, len(xs), n):
        yield xs[i:i+n]

def main():
    local = read_local()
    if not local:
        print("[SYNC] Nenhum JSON local encontrado.")
        return

    uid_to_id, uid_to_row, title_to_id = fetch_db()

    # --- MIGRAÇÃO: para linhas do banco SEM uid, tenta casar por título e setar uid (1a execução) ---
    to_set_uid = []
    for uid, rec in local.items():
        if uid in uid_to_id:
            continue
        title = norm_spaces(rec["content"])
        rid = title_to_id.get(title)
        if rid:
            to_set_uid.append({"id": rid, "uid": uid})

    for batch in chunk(to_set_uid, 200):
        try:
            for row in batch:
                supabase.table("videos").update({"uid": row["uid"]}).eq("id", row["id"]).execute()
            print(f"[MIGRACAO] Definidos {len(batch)} uid(s) por título")
        except Exception as e:
            print(f"[ERRO migracao uid] {e}")

    # Recarrega mapas após migração
    uid_to_id, uid_to_row, _ = fetch_db()

    local_uids  = set(local.keys())
    remote_uids = set(uid_to_id.keys())

    to_insert_uids = sorted(local_uids - remote_uids)
    to_update_uids = sorted(local_uids & remote_uids)
    to_delete_uids = sorted(remote_uids - local_uids)

    print(f"[SYNC] Inserir: {len(to_insert_uids)} | Atualizar: {len(to_update_uids)} | Remover: {len(to_delete_uids)}")

    # UPDATE por id (preserva id mesmo se título mudou)
    upd_ok = 0
    for uid in to_update_uids:
        rec = local[uid]
        rid = uid_to_id[uid]
        try:
            supabase.table("videos").update({
                "content":  rec["content"],
                "metadata": rec["metadata"],
                "embedding": rec["embedding"],
            }).eq("id", rid).execute()
            upd_ok += 1
        except Exception as e:
            print(f"[ERRO update uid={uid} id={rid}] {e}")

    # INSERT em lotes
    ins_ok = 0
    for batch_uids in chunk(to_insert_uids, 200):
        batch = [local[u] for u in batch_uids]
        try:
            supabase.table("videos").insert(batch).execute()
            ins_ok += len(batch)
        except Exception as e:
            print(f"[ERRO insert batch] {e}")

    # DELETE em lotes por id de uids que não vieram
    del_ok = 0
    ids_to_del = [uid_to_id[u] for u in to_delete_uids]
    for batch_ids in chunk(ids_to_del, 200):
        try:
            supabase.table("videos").delete().in_("id", batch_ids).execute()
            del_ok += len(batch_ids)
        except Exception as e:
            print(f"[ERRO delete batch] {e}")

    print(f"[RESUMO] Inseridos={ins_ok} | Atualizados={upd_ok} | Removidos={del_ok}")

if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(f"[FATAL] {e}")
        sys.exit(1)
