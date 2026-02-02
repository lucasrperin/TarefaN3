import os
import json
from tqdm import tqdm
import openai
import psycopg2
from dotenv import load_dotenv

# Carrega variáveis do .env
load_dotenv()
openai.api_key = os.getenv("OPENAI_API_KEY")

PG_HOST = os.getenv("PG_HOST")
PG_PORT = int(os.getenv("PG_PORT", 5432))
PG_USER = os.getenv("PG_USER")
PG_PASS = os.getenv("PG_PASS")
PG_DB   = os.getenv("PG_DB")

# Conexão com PostgreSQL
conn = psycopg2.connect(
    host=PG_HOST,
    port=PG_PORT,
    user=PG_USER,
    password=PG_PASS,
    dbname=PG_DB
)
cur = conn.cursor()

# QUERY AJUSTADA
cur.execute("""
    SELECT id, titulo, conteudo FROM public.artigos
    WHERE interno = 'false' and titulo like('%Reforma%')
    ORDER BY id ASC
""")
rows = cur.fetchall()

def gerar_embedding(texto):
    response = openai.embeddings.create(
        input=texto,
        model="text-embedding-ada-002"
    )
    return response.data[0].embedding

embeddings = {}
for row in tqdm(rows):
    artigo_id, titulo, conteudo = row
    # Novo título concatenado
    titulo_formatado = f"Artigo {artigo_id} - {titulo}"
    texto = f"{titulo_formatado} {conteudo}" if conteudo else titulo_formatado
    try:
        emb = gerar_embedding(texto[:8190])
        embeddings[str(artigo_id)] = {
            "titulo": titulo_formatado,
            "conteudo": str(conteudo) if conteudo else "",
            "embedding": emb
        }
    except Exception as e:
        print(f"Erro no artigo {artigo_id}: {e}")

with open("../embeddings/embeddings.json", "w", encoding="utf-8") as f:
    json.dump(embeddings, f, ensure_ascii=False)

print(f"Embeddings gerados para {len(embeddings)} artigos e salvos em ../embeddings/embeddings.json")

cur.close()
conn.close()
