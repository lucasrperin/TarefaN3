import os
import json
from supabase import create_client, Client
from dotenv import load_dotenv

# 1) Carrega variáveis de ambiente do .env
load_dotenv()
SUPABASE_URL             = os.getenv("SUPABASE_URL")
SUPABASE_SERVICE_ROLE_KEY = os.getenv("SUPABASE_SERVICE_ROLE_KEY")

# 2) Inicializa cliente Supabase
supabase: Client = create_client(SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY)

# 3) Ajuste de path: chega em ChatBot/embeddings/transcricoes
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
CHATBOT_DIR = os.path.abspath(os.path.join(SCRIPT_DIR, os.pardir, os.pardir))
TRANS_DIR = os.path.join(CHATBOT_DIR, "embeddings", "transcricoes")

print(f"📂 Procurando JSONs em: {TRANS_DIR}")

# 4) Lista apenas os .json
all_files = [
    fn for fn in os.listdir(TRANS_DIR)
    if fn.lower().endswith(".json")
]

# 5) Limpa a tabela inteira (se preferir, pode comentar esta parte)
print("🗑️ Limpando tabela `videos`...")
supabase.from_("videos").delete().neq("id", 0).execute()

# 6) Para cada JSON, insere um novo registro
for fn in all_files:
    path = os.path.join(TRANS_DIR, fn)
    try:
        with open(path, encoding="utf-8") as f:
            data = json.load(f)
    except Exception as e:
        print(f"[Erro leitura] '{fn}' — {e}")
        continue

    rec = {
        "content": data.get("titulo", ""),
        "metadata": {
            "conteudo": data.get("conteudo", ""),
            "link":     data.get("link", ""),
            "filename": fn
        },
        "embedding": data.get("embeddings") or data.get("embedding")
    }

    try:
        supabase.from_("videos").insert(rec).execute()
        print(f"[OK] Inserido '{fn}'")
    except Exception as e:
        print(f"[ERRO insert] '{fn}' — {e}")
