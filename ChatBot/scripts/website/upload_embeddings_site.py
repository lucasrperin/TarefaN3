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

def walk_jsons() -> Dict[str, dict]:
    data: Dict[str, dict] = {}
    if not os.path.isdir(SITES_DIR):
        print(f"[SYNC] Diretório não existe: {SITES_DIR}")
        return data
    for root, _, files in os.walk(SITES_DIR):
        for fn in files:
            if not fn.lower().endswith(".json"): continue
            p = os.path.join(root, fn)
            try:
                with open(p, encoding="utf-8") as f:
                    j = json.load(f)
            except Exception as e:
                print(f"[WARN] {p}: {e}")
                continue
            uid   = j.get("uid")
            title = j.get("titulo") or j.get("content") or ""
            text  = j.get("conteudo") or ""
            link  = j.get("link") or ""
            emb   = j.get("embedding") or j.get("embeddings")
            if not uid:
                # fallback se vier sem uid (não deveria)
                base = "url:" + (link or norm_spaces(text))[:100].lower()
                uid = hashlib.sha1(base.encode("utf-8")).hexdigest()
            rec = {
                "uid": uid,
                "content": title,
                "metadata": {
                    "conteudo": text,
                    "link": link,
                    "filename": fn
                },
                "embedding": emb
            }
            data[uid] = rec
    return data

def fetch_remote() -> Tuple[Dict[str, int], Dict[str, dict]]:
    start, step = 0, 1000
    uid_to_id: Dict[str, int] = {}
    uid_to_row: Dict[str, dict] = {}
    while True:
        resp = supabase.table("websites").select("id, uid, content").range(start, start+step-1).execute()
        rows = getattr(resp, "data", resp)
        if not rows: break
        for r in rows:
            uid = r.get("uid")
            if uid:
                uid_to_id[uid] = r.get("id")
                uid_to_row[uid] = r
        if len(rows) < step: break
        start += step
    return uid_to_id, uid_to_row

def chunk(xs: List, n: int):
    for i in range(0, len(xs), n):
        yield xs[i:i+n]

def main():
    local = walk_jsons()
    if not local:
        print("[SYNC] Nenhum JSON local encontrado.")
        return

    uid_to_id, uid_to_row = fetch_remote()
    local_uids  = set(local.keys())
    remote_uids = set(uid_to_id.keys())

    to_insert = sorted(local_uids - remote_uids)
    to_update = sorted(local_uids & remote_uids)
    to_delete = sorted(remote_uids - local_uids)

    print(f"[SYNC] Inserir: {len(to_insert)} | Atualizar: {len(to_update)} | Remover: {len(to_delete)}")

    # UPDATE
    upd_ok = 0
    for uid in to_update:
        rid = uid_to_id[uid]
        rec = local[uid]
        try:
            supabase.table("websites").update({
                "content":  rec["content"],
                "metadata": rec["metadata"],
                "embedding": rec["embedding"],
            }).eq("id", rid).execute()
            upd_ok += 1
        except Exception as e:
            print(f"[ERRO update uid={uid} id={rid}] {e}")

    # INSERT em lotes
    ins_ok = 0
    for batch_uids in chunk(to_insert, 200):
        batch = [local[u] for u in batch_uids]
        try:
            supabase.table("websites").insert(batch).execute()
            ins_ok += len(batch)
        except Exception as e:
            print(f"[ERRO insert batch] {e}")

    # DELETE em lotes
    del_ok = 0
    ids_to_del = [uid_to_id[u] for u in to_delete]
    for batch_ids in chunk(ids_to_del, 200):
        try:
            supabase.table("websites").delete().in_("id", batch_ids).execute()
            del_ok += len(batch_ids)
        except Exception as e:
            print(f"[ERRO delete batch] {e}")

    print(f"[RESUMO] Inseridos={ins_ok} | Atualizados={upd_ok} | Removidos={del_ok}")

if __name__ == "__main__":
    main()
