# scripts/sites/upload_sites.py
import os, sys, json, re, hashlib
from typing import Dict, List, Tuple, Optional

try:
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stderr.reconfigure(encoding="utf-8")
except Exception:
    pass

from dotenv import load_dotenv, find_dotenv
load_dotenv(find_dotenv())

from supabase import create_client, Client

SUPABASE_URL = os.getenv("SUPABASE_URL")
SUPABASE_KEY = os.getenv("SUPABASE_SERVICE_ROLE_KEY")
if not SUPABASE_URL or not SUPABASE_KEY:
    print("Erro: SUPABASE_URL/SUPABASE_SERVICE_ROLE_KEY ausentes no .env")
    sys.exit(1)

supabase: Client = create_client(SUPABASE_URL, SUPABASE_KEY)

SCRIPT_DIR  = os.path.dirname(os.path.abspath(__file__))
CHATBOT_DIR = os.path.abspath(os.path.join(SCRIPT_DIR, os.pardir, os.pardir))
SITES_DIR   = os.path.join(CHATBOT_DIR, "embeddings", "sites")

def norm_spaces(s: str) -> str:
    return re.sub(r"\s+", " ", (s or "").strip())

def make_uid(link: Optional[str], texto: str) -> str:
    # UID determinístico: prioriza link; se não houver, usa texto normalizado
    if link:
        base = "url:" + link.strip().lower()
    else:
        base = "txt:" + norm_spaces(texto).lower()
    return hashlib.sha1(base.encode("utf-8")).hexdigest()

def read_local() -> Dict[str, dict]:
    """
    Caminha em embeddings/sites/<site>/<batch>/*.json
    e monta o dicionário {uid: registro}.
    """
    data: Dict[str, dict] = {}
    if not os.path.isdir(SITES_DIR):
        print(f"Erro: diretório não existe: {SITES_DIR}")
        return data

    for root, _, files in os.walk(SITES_DIR):
        rel = os.path.relpath(root, SITES_DIR)
        parts = [] if rel == "." else rel.split(os.sep)
        site  = parts[0] if len(parts) >= 1 else ""
        batch = parts[1] if len(parts) >= 2 else ""

        for fn in sorted(files):
            if not fn.lower().endswith(".json"):
                continue
            p = os.path.join(root, fn)
            try:
                with open(p, encoding="utf-8") as f:
                    j = json.load(f)
            except Exception as e:
                print(f"[WARN] '{p}': {e}")
                continue

            title = j.get("titulo") or j.get("content") or ""
            texto = j.get("conteudo") or ""
            link  = j.get("link") or ""
            emb   = j.get("embeddings") or j.get("embedding")
            uid   = j.get("uid") or make_uid(link or None, texto)

            rec = {
                "uid": uid,
                "link": link,            # coluna própria na tabela websites
                "content": title,        # título vai em content (igual aos vídeos)
                "metadata": {
                    "conteudo": texto,
                    "link": link,
                    "filename": fn,
                    "site": site,
                    "batch": batch
                },
                "embedding": emb
            }
            data[uid] = rec

    return data

def fetch_db() -> Tuple[Dict[str, int], Dict[str, dict], Dict[str, int]]:
    """
    Carrega mapas atuais do banco:
      - uid_to_id: uid -> id
      - uid_to_row: uid -> linha (id, uid, content)
      - title_to_id: título normalizado -> id  (p/ migração de rows antigas sem uid)
    """
    start, step = 0, 1000
    uid_to_id: Dict[str, int] = {}
    uid_to_row: Dict[str, dict] = {}
    title_to_id: Dict[str, int] = {}
    while True:
        resp = supabase.table("websites").select("id, uid, content").range(start, start+step-1).execute()
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

    # --- MIGRAÇÃO: para linhas do banco SEM uid, tenta casar por título e setar uid (1ª execução) ---
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
                supabase.table("websites").update({"uid": row["uid"]}).eq("id", row["id"]).execute()
            if batch:
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
            supabase.table("websites").update({
                "link":     rec["link"],
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
            supabase.table("websites").insert(batch).execute()
            ins_ok += len(batch)
        except Exception as e:
            print(f"[ERRO insert batch] {e}")

    # DELETE em lotes por id de uids que não vieram
    del_ok = 0
    ids_to_del = [uid_to_id[u] for u in to_delete_uids]
    for batch_ids in chunk(ids_to_del, 200):
        try:
            supabase.table("websites").delete().in_("id", batch_ids).execute()
            del_ok += len(batch_ids)
        except Exception as e:
            print(f"[ERRO delete batch] {e}")

    print(f"[RESUMO] Inseridos={ins_ok} | Atualizados={upd_ok} | Removidos={del_ok} ")

if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(f"[FATAL] {e}")
        sys.exit(1)
