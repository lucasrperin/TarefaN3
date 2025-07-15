import os
import json
from supabase import create_client, Client
from dotenv import load_dotenv

load_dotenv()
SUPABASE_URL             = os.getenv("SUPABASE_URL")
SUPABASE_SERVICE_ROLE_KEY = os.getenv("SUPABASE_SERVICE_ROLE_KEY")
supabase: Client = create_client(SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY)

with open("../embeddings/embeddings.json", encoding="utf-8") as f:
    embeddings = json.load(f)

records = [
    {
        "id":       int(art_id),
        "content":   data["titulo"],
        "metadata": data["conteudo"],
        "embedding":    data["embedding"],
    }
    for art_id, data in embeddings.items()
]

# se quiser por lote, ou um a um:
for rec in records:
    try:
        # upsert (insere ou atualiza) pelo id
        supabase.from_("documents").upsert(rec, on_conflict="id").execute()
        print(f"[OK] id={rec['id']}")
    except Exception as e:
        # vai capturar qualquer falha e continuar
        print(f"[ERRO] id={rec['id']}  —  {e}")
