import os
import json
import re
from supabase import create_client, Client
from dotenv import load_dotenv

# 1) Carrega variáveis de ambiente do .env
load_dotenv()
SUPABASE_URL             = os.getenv("SUPABASE_URL")
SUPABASE_SERVICE_ROLE_KEY = os.getenv("SUPABASE_SERVICE_ROLE_KEY")

# 2) Inicializa cliente Supabase
supabase: Client = create_client(SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY)

# 3) Ajuste de path: sobe dois níveis de 'scripts/video' até 'ChatBot'
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
CHATBOT_DIR = os.path.abspath(os.path.join(SCRIPT_DIR, os.pardir, os.pardir))
TRANS_DIR = os.path.join(CHATBOT_DIR, "embeddings", "transcricoes")

print("Procurando JSONs em:", TRANS_DIR)  # DEBUG: confirme aqui o caminho

# Helper: extrai um ID inteiro do nome do arquivo
def extract_id_from_filename(fname: str) -> int:
    base   = os.path.splitext(fname)[0]
    digits = re.findall(r'\d+', base)
    return int("".join(digits)) if digits else None

# 4) Lista arquivos .json na pasta correta
try:
    all_files = [fn for fn in os.listdir(TRANS_DIR) if fn.lower().endswith(".json")]
except FileNotFoundError as e:
    raise RuntimeError(f"Pasta de transcricoes não encontrada: {TRANS_DIR}") from e

# 5) Constrói set de IDs novos a partir dos arquivos
new_ids = set()
for fn in all_files:
    vid = extract_id_from_filename(fn)
    if vid is not None:
        new_ids.add(vid)

# 6) Busca IDs já existentes em 'videos'
existing_ids = set()
resp = supabase.from_("videos").select("id").execute()
existing_ids = set(item["id"] for item in (resp.data or []))

# 7) Deleta registros obsoletos
for stale_id in existing_ids - new_ids:
    supabase.from_("videos").delete().eq("id", stale_id).execute()
    print(f"[DELETE] id={stale_id}")

# 8) Upsert de cada JSON
for fn in all_files:
    vid = extract_id_from_filename(fn)
    if vid is None:
        print(f"[PULA ] não consegui extrair ID de '{fn}'")
        continue

    path = os.path.join(TRANS_DIR, fn)
    with open(path, encoding="utf-8") as f:
        data = json.load(f)

    rec = {
        "id":        vid,
        "content":   data.get("titulo", ""),
        "metadata":  {
            "conteudo": data.get("conteudo", ""),
            "filename": fn
        },
        "embedding": data.get("embeddings") or data.get("embedding")
    }
    supabase.from_("videos").upsert(rec, on_conflict="id").execute()
    print(f"[OK    ] id={vid} arquivo='{fn}'")
