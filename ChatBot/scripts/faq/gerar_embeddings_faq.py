import os
import json
import openai
import firebirdsql
from dotenv import load_dotenv

# Carrega variáveis do .env
load_dotenv()
openai.api_key = os.getenv("OPENAI_API_KEY")

# Config Firebird (ajusta no .env)
FB_HOST = os.getenv("FB_HOST", "localhost")
FB_PORT = int(os.getenv("FB_PORT", 3050))
FB_DB   = os.getenv("FB_DB")   # caminho completo da base .fdb
FB_USER = os.getenv("FB_USER")
FB_PASS = os.getenv("FB_PASS")

if not FB_DB:
    raise RuntimeError("Variável de ambiente FB_DB não definida (caminho da base Firebird).")

# Conexão com Firebird 2.5 via firebirdsql (sem DLL fbclient)
con = firebirdsql.connect(
    host=FB_HOST,
    port=FB_PORT,
    database=FB_DB,
    user=FB_USER,
    password=FB_PASS,
    charset="UTF8"
)
cur = con.cursor()

# SQL solicitado
cur.execute("""
    SELECT
        ah.solicitacao,
        ah.procedimento
    FROM tb_atendimento_historico ah
    WHERE ah.data > '01.09.2025'
      AND ah.procedimento CONTAINING 'CHATBOT'
""")
rows = cur.fetchall()

def gerar_embedding(texto: str):
    """
    Gera embedding usando OpenAI.
    Mantendo o mesmo padrão do gerar_embeddings.py original.
    """
    response = openai.embeddings.create(
        input=texto,
        model="text-embedding-ada-002"
    )
    return response.data[0].embedding

embeddings = {}

for idx, (solicitacao, procedimento) in enumerate(rows, start=1):
    solicitacao = solicitacao or ""
    procedimento = procedimento or ""

    # Texto final que vai ser embedado
    content = f"Solicitação: {solicitacao}\nProcedimento: {procedimento}"
    titulo = f"Solicitação: {solicitacao}"

    # Trunca para evitar estouro de tokens (bem conservador)
    texto = content[:8190]

    try:
        emb = gerar_embedding(texto)
        embeddings[str(idx)] = {
            "content": titulo,
            "metadata": {
                "procedimento": procedimento
            },
            "embedding": emb
        }
    except Exception as e:
        print(f"Erro ao gerar embedding para registro {idx}: {e}")

# === Caminho para o JSON: ChatBot/embeddings/embeddings_fac.json ===
base_dir = os.path.dirname(os.path.abspath(__file__))  # ChatBot/scripts/fac
emb_dir = os.path.join(base_dir, "..", "..", "embeddings", "faq")         # ChatBot/embeddings/faq
os.makedirs(emb_dir, exist_ok=True)

output_path = os.path.join(emb_dir, "embeddings_faq.json")

with open(output_path, "w", encoding="utf-8") as f:
    json.dump(embeddings, f, ensure_ascii=False)

print(f"Embeddings gerados para {len(embeddings)} registros e salvos em {output_path}")

cur.close()
con.close()
