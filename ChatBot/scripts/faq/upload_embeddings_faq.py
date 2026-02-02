import os
import json
from supabase import create_client, Client
from dotenv import load_dotenv

# Carrega variáveis de ambiente do .env
load_dotenv()
SUPABASE_URL              = os.getenv("SUPABASE_URL")
SUPABASE_SERVICE_ROLE_KEY = os.getenv("SUPABASE_SERVICE_ROLE_KEY")

if not SUPABASE_URL or not SUPABASE_SERVICE_ROLE_KEY:
    raise RuntimeError("SUPABASE_URL ou SUPABASE_SERVICE_ROLE_KEY não definidos no .env.")

# Inicializa cliente Supabase
supabase: Client = create_client(SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY)

# === Caminho para o JSON: ChatBot/embeddings/faq/embeddings_faq.json ===
base_dir = os.path.dirname(os.path.abspath(__file__))        # ChatBot/scripts/faq
embeddings_file = os.path.join(base_dir, "..", "..", "embeddings", "faq", "embeddings_faq.json")

# 1) Carrega o novo embeddings_faq.json
try:
    with open(embeddings_file, encoding="utf-8") as f:
        embeddings = json.load(f)
except Exception as e:
    print(f"[ERRO] Falha ao carregar {embeddings_file} — {e}")
    embeddings = {}

# IDs que estão no JSON
new_ids = set(int(rec_id) for rec_id in embeddings.keys())

# 2) Busca todos os IDs atuais na tabela "fac"
existing_ids = set()
try:
    resp = supabase.from_("fac").select("id").execute()
    existing_ids = set(item["id"] for item in (resp.data or []))
except Exception as e:
    print(f"[ERRO] Falha ao buscar IDs existentes na tabela fac — {e}")

# 3) Deleta registros que não estão mais no novo embeddings_fac.json
for del_id in existing_ids - new_ids:
    try:
        supabase.from_("fac").delete().eq("id", del_id).execute()
        print(f"[DELETE] id={del_id}")
    except Exception as e:
        print(f"[ERRO DELETE] id={del_id} — {e}")

# 4) Upsert de cada registro
for rec_id, data in embeddings.items():
    try:
        rec = {
            "id":        int(rec_id),                  # bigserial aceita valor manual
            "content":   data.get("content", ""),
            "metadata":  data.get("metadata", {}),     # jsonb
            "embedding": data.get("embedding"),
        }

        supabase.from_("fac").upsert(rec, on_conflict="id").execute()
        print(f"[OK] id={rec_id}")
    except Exception as e:
        print(f"[ERRO UPSERT] id={rec_id} — {e}")
