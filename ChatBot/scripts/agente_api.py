from fastapi import FastAPI, Request, Query, UploadFile, File
from fastapi.middleware.cors import CORSMiddleware
import re
import uvicorn
import httpx
import json
import mysql.connector
from mysql.connector import Error

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

N8N_WEBHOOK_URL = "https://n8n.zucchetti.com.br/webhook/4ccf11a7-8170-48d4-8ee1-ce8355ce1c52"

def linkify(text: str) -> str:
    def _repl(match):
        url = match.group(0)
        return f'<a href="{url}" target="_blank" rel="noopener noreferrer">{url}</a>'
    return re.sub(r'(https?://[^\s]+)', _repl, text)

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "TarefaN3"
}

@app.post("/consultar")
async def consultar(request: Request):
    data = await request.json()
    pergunta = data.get("pergunta")
    user_id = data.get("user_id")

    if not pergunta:
        return {"erro": "Campo 'pergunta' obrigatório."}
    if not user_id:
        return {"erro": "Campo 'user_id' obrigatório."}

    try:
        async with httpx.AsyncClient() as client:
            payload = {
                "pergunta": pergunta,
                "user_id": user_id
            }
            resp = await client.post(
                N8N_WEBHOOK_URL,
                json=payload,
                timeout=90
            )
            text = resp.text

            if resp.status_code != 200:
                return {
                    "erro": (
                        f"Erro ao consultar n8n: {resp.status_code}. "
                        f"Detalhe: {text[:200]}"
                    )
                }

            if not text.strip():
                return {
                    "erro": (
                        "O sistema estava inativo ou ocorreu uma falha de conexão. "
                        "Aguarde alguns segundos e tente novamente."
                    )
                }

            try:
                resposta_n8n = resp.json()
            except Exception as e:
                return {
                    "erro": (
                        "A resposta do n8n não é JSON válido. "
                        f"Conteúdo: '{text[:200]}'. Erro: {str(e)}"
                    )
                }

            output = None
            if isinstance(resposta_n8n, list) and len(resposta_n8n) > 0:
                item = resposta_n8n[0]
                output = item.get("response", {}).get("body", {}).get("output")
            elif isinstance(resposta_n8n, dict) and "output" in resposta_n8n:
                output = resposta_n8n["output"]

            if output:
                output = linkify(output)
                return {"resposta": output}

            return {
                "erro": (
                    "Formato inesperado da resposta do n8n: "
                    f"{json.dumps(resposta_n8n, ensure_ascii=False)[:500]}"
                )
            }

    except httpx.RequestError as e:
        return {"erro": f"Erro de conexão com o n8n: {str(e)}"}
    except Exception as e:
        return {"erro": f"Erro ao tratar resposta do n8n: {str(e)}"}

# ENVIO DE IMAGEM BINÁRIO (multipart/form-data)
@app.post("/upload-imagem")
async def upload_imagem(imagem: UploadFile = File(...), user_id: str = Query(...)):
    try:
        conteudo = await imagem.read()

        # Monta payload multipart para o n8n
        files = {
            "data": (imagem.filename, conteudo, imagem.content_type),
            "user_id": (None, user_id)
        }

        async with httpx.AsyncClient() as client:
            resp = await client.post(
                N8N_WEBHOOK_URL,
                files=files,
                timeout=90
            )

        text = resp.text

        if resp.status_code != 200:
            return {
                "erro": (
                    f"Erro ao consultar n8n: {resp.status_code}. "
                    f"Detalhe: {text[:200]}"
                )
            }

        if not text.strip():
            return {"erro": "O sistema estava inativo ou ocorreu uma falha de conexão. Aguarde e tente novamente."}

        try:
            resposta_n8n = resp.json()
        except Exception as e:
            return {
                "erro": (
                    "A resposta do n8n não é JSON válido. "
                    f"Conteúdo: '{text[:200]}'. Erro: {str(e)}"
                )
            }

        output = None
        if isinstance(resposta_n8n, list) and len(resposta_n8n) > 0:
            item = resposta_n8n[0]
            output = item.get("response", {}).get("body", {}).get("output")
        elif isinstance(resposta_n8n, dict) and "output" in resposta_n8n:
            output = resposta_n8n["output"]

        if output:
            output = linkify(output)
            return {"resposta": output}

        return {
            "erro": (
                "Formato inesperado da resposta do n8n: "
                f"{json.dumps(resposta_n8n, ensure_ascii=False)[:500]}"
            )
        }

    except Exception as e:
        return {"erro": f"Erro ao processar imagem: {str(e)}"}

# NOVO: ENVIO DE ÁUDIO BINÁRIO (multipart/form-data)
@app.post("/upload-audio")
async def upload_audio(audio: UploadFile = File(...), user_id: str = Query(...)):
    try:
        conteudo = await audio.read()

        files = {
            "data": (audio.filename, conteudo, audio.content_type),
            "user_id": (None, user_id)
        }

        async with httpx.AsyncClient() as client:
            resp = await client.post(
                N8N_WEBHOOK_URL,
                files=files,
                timeout=90
            )

        text = resp.text

        if resp.status_code != 200:
            return {
                "erro": (
                    f"Erro ao consultar n8n: {resp.status_code}. "
                    f"Detalhe: {text[:200]}"
                )
            }

        if not text.strip():
            return {"erro": "O sistema estava inativo ou ocorreu uma falha de conexão. Aguarde e tente novamente."}

        try:
            resposta_n8n = resp.json()
        except Exception as e:
            return {
                "erro": (
                    "A resposta do n8n não é JSON válido. "
                    f"Conteúdo: '{text[:200]}'. Erro: {str(e)}"
                )
            }

        output = None
        if isinstance(resposta_n8n, list) and len(resposta_n8n) > 0:
            item = resposta_n8n[0]
            output = item.get("response", {}).get("body", {}).get("output")
        elif isinstance(resposta_n8n, dict) and "output" in resposta_n8n:
            output = resposta_n8n["output"]

        if output:
            output = linkify(output)
            return {"resposta": output}

        return {
            "erro": (
                "Formato inesperado da resposta do n8n: "
                f"{json.dumps(resposta_n8n, ensure_ascii=False)[:500]}"
            )
        }

    except Exception as e:
        return {"erro": f"Erro ao processar áudio: {str(e)}"}

@app.post("/avaliacao")
async def avaliacao(request: Request):
    data = await request.json()
    user_id = data.get("user_id")
    nota = data.get("nota")
    if not user_id or not nota:
        return {"erro": "user_id e nota obrigatórios"}

    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cur = conn.cursor()
        cur.execute(
            "INSERT INTO tb_avaliacoes_chatbot (user_id, nota, Linha) VALUES (%s, %s, %s)",
            (user_id, nota, "Clipp")
        )
        conn.commit()
        cur.close()
        conn.close()
        return {"ok": True}
    except Error as e:
        return {"erro": f"Erro ao salvar avaliação: {str(e)}"}

@app.get("/media-avaliacoes")
def media_avaliacoes(dias: int = Query(default=0, description="Filtrar pelos últimos X dias (0 = todas)")):
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cur = conn.cursor()
        if dias > 0:
            cur.execute("""
                SELECT ROUND(AVG(nota),2) AS media, COUNT(*) AS total
                FROM tb_avaliacoes_chatbot
                WHERE Linha = %s AND data >= CURDATE() - INTERVAL %s DAY
            """, ("Clipp", dias))
        else:
            cur.execute("""
                SELECT ROUND(AVG(nota),2) AS media, COUNT(*) AS total
                FROM tb_avaliacoes_chatbot
                WHERE Linha = %s
            """, ("Clipp",))
        row = cur.fetchone()
        cur.close()
        conn.close()
        media = float(row[0]) if row[0] is not None else 0.0
        total = row[1]
        return {"media": media, "total": total}
    except Exception as e:
        return {"erro": f"Erro ao calcular média: {str(e)}"}

@app.get("/")
def home():
    return {"status": "ok"}

if __name__ == "__main__":
    uvicorn.run("agente_api:app", host="0.0.0.0", port=8000, reload=True)
