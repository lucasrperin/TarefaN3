import os
import json
from supabase import create_client, Client
from dotenv import load_dotenv

# Carrega variáveis de ambiente do arquivo .env
load_dotenv()
SUPABASE_REFORMA_URL             = os.getenv("SUPABASE_REFORMA_URL")
SUPABASE_SERVICE_ROLE_KEY = os.getenv("SUPABASE_SERVICE_ROLE_KEY")

# Inicializa cliente Supabase
supabase: Client = create_client(SUPABASE_REFORMA_URL, SUPABASE_SERVICE_ROLE_KEY)

# Caminho para o arquivo embeddings.json
embeddings_file = os.path.join(os.path.dirname(__file__), "../embeddings/embeddings.json")

# 1) Carrega o novo embeddings.json
try:
    with open(embeddings_file, encoding="utf-8") as f:
        embeddings = json.load(f)
except Exception as e:
    print(f"[ERRO] Falha ao carregar embeddings.json — {e}")
    embeddings = {}

new_ids = set(int(art_id) for art_id in embeddings.keys())

# 2) Busca todos os IDs atuais na tabela "documents"
existing_ids = set()
try:
    resp = supabase.from_("documents").select("id").execute()
    # resp.data deve ser lista de dicts
    existing_ids = set(item["id"] for item in (resp.data or []))
except Exception as e:
    print(f"[ERRO] Falha ao buscar IDs existentes — {e}")

# 3) Deleta registros que não estão mais no novo embeddings.json
for del_id in existing_ids - new_ids:
    try:
        supabase.from_("documents").delete().eq("id", del_id).execute()
        print(f"[DELETE] id={del_id}")
    except Exception as e:
        print(f"[ERRO DELETE] id={del_id} — {e}")

# 4) Insere ou atualiza cada embedding via upsert
for art_id, data in embeddings.items():
    rec = {
        "id":        int(art_id),
        "content":   data.get("titulo", ""),
        "metadata":  data.get("conteudo", ""),
        "embedding": data.get("embedding"),
    }
    try:
        supabase.from_("documents").upsert(rec, on_conflict="id").execute()
        print(f"[OK] id={art_id}")
    except Exception as e:
        print(f"[ERRO UPSERT] id={art_id} — {e}")
