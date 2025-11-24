import os
from fastapi import FastAPI, Request, Query, UploadFile, File
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from mysql.connector import Error
from fastapi import HTTPException
from dotenv import load_dotenv

import re
import uvicorn
import httpx
import json
import mysql.connector

# Carrega variáveis do .env
load_dotenv()

MYSQL_HOST = os.getenv("MYSQL_HOST")
MYSQL_USER = os.getenv("MYSQL_USER")
MYSQL_PASSWORD = os.getenv("MYSQL_PASSWORD")
MYSQL_DATABASE = os.getenv("MYSQL_DATABASE")
MYSQL_PORT = os.getenv("MYSQL_PORT", "3306")

N8N_WEBHOOK_URL = os.getenv("N8N_WEBHOOK_URL")
N8N_WEBHOOK_URL_REFORMA = os.getenv("N8N_WEBHOOK_URL_REFORMA")

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# converte URLs em tags <a>
def linkify(text: str) -> str:
    def _repl(match):
        url = match.group(0)
        return f'<a href="{url}" target="_blank" rel="noopener noreferrer">{url}</a>'
    return re.sub(r'(https?://[^\s]+)', _repl, text)

DB_CONFIG = {
    "host": MYSQL_HOST,
    "port": int(MYSQL_PORT),
    "user": MYSQL_USER,
    "password": MYSQL_PASSWORD,
    "database": MYSQL_DATABASE
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
    
@app.post("/consultar-reforma")
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
                N8N_WEBHOOK_URL_REFORMA,
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
    # lê o arquivo
    conteudo = await imagem.read()
    files = {
        "data": (imagem.filename, conteudo, imagem.content_type),
        "user_id": (None, user_id),
    }

    # envia para o n8n
    async with httpx.AsyncClient() as client:
        resp = await client.post(
            N8N_WEBHOOK_URL,
            files=files,
            timeout=90
        )

    # 413 do Nginx/n8n → devolve JSON consistente
    if resp.status_code == 413:
        return JSONResponse(
            status_code=413,
            content={"erro": "Arquivo muito grande. Reduza o tamanho da imagem e tente novamente."}
        )

    # outros erros HTTP
    if resp.status_code != 200:
        return JSONResponse(
            status_code=resp.status_code,
            content={"erro": f"Erro ao consultar n8n: {resp.status_code}. Detalhe: {resp.text[:200]}"}
        )

    text = resp.text or ""
    if not text.strip():
        return JSONResponse(
            status_code=502,
            content={"erro": "Resposta vazia do serviço. Tente novamente em instantes."}
        )

    # parse do JSON do n8n
    try:
        resposta_n8n = resp.json()
    except Exception as e:
        return JSONResponse(
            status_code=502,
            content={"erro": f"Resposta do n8n não é JSON válido. Erro: {e}"}
        )

    # extrai o campo "output"
    output = None
    if isinstance(resposta_n8n, list) and len(resposta_n8n) > 0:
        item = resposta_n8n[0]
        output = item.get("response", {}).get("body", {}).get("output")
    elif isinstance(resposta_n8n, dict):
        output = resposta_n8n.get("output")

    # devolve ao front
    if output:
        return {"resposta": linkify(output)}
    else:
        return JSONResponse(
            status_code=502,
            content={"erro": "Formato inesperado na resposta do n8n."}
        )
    
# ENVIO DE IMAGEM BINÁRIO (multipart/form-data) - Agente Reforma
@app.post("/upload-imagem-reforma")
async def upload_imagem(imagem: UploadFile = File(...), user_id: str = Query(...)):
    # lê o arquivo
    conteudo = await imagem.read()
    files = {
        "data": (imagem.filename, conteudo, imagem.content_type),
        "user_id": (None, user_id),
    }

    # envia para o n8n
    async with httpx.AsyncClient() as client:
        resp = await client.post(
            N8N_WEBHOOK_URL_REFORMA,
            files=files,
            timeout=90
        )

    # 413 do Nginx/n8n → devolve JSON consistente
    if resp.status_code == 413:
        return JSONResponse(
            status_code=413,
            content={"erro": "Arquivo muito grande. Reduza o tamanho da imagem e tente novamente."}
        )

    # outros erros HTTP
    if resp.status_code != 200:
        return JSONResponse(
            status_code=resp.status_code,
            content={"erro": f"Erro ao consultar n8n: {resp.status_code}. Detalhe: {resp.text[:200]}"}
        )

    text = resp.text or ""
    if not text.strip():
        return JSONResponse(
            status_code=502,
            content={"erro": "Resposta vazia do serviço. Tente novamente em instantes."}
        )

    # parse do JSON do n8n
    try:
        resposta_n8n = resp.json()
    except Exception as e:
        return JSONResponse(
            status_code=502,
            content={"erro": f"Resposta do n8n não é JSON válido. Erro: {e}"}
        )

    # extrai o campo "output"
    output = None
    if isinstance(resposta_n8n, list) and len(resposta_n8n) > 0:
        item = resposta_n8n[0]
        output = item.get("response", {}).get("body", {}).get("output")
    elif isinstance(resposta_n8n, dict):
        output = resposta_n8n.get("output")

    # devolve ao front
    if output:
        return {"resposta": linkify(output)}
    else:
        return JSONResponse(
            status_code=502,
            content={"erro": "Formato inesperado na resposta do n8n."}
        )

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
    
# NOVO: ENVIO DE ÁUDIO BINÁRIO (multipart/form-data) - Agente Reforma
@app.post("/upload-audio-reforma")
async def upload_audio(audio: UploadFile = File(...), user_id: str = Query(...)):
    try:
        conteudo = await audio.read()

        files = {
            "data": (audio.filename, conteudo, audio.content_type),
            "user_id": (None, user_id)
        }

        async with httpx.AsyncClient() as client:
            resp = await client.post(
                N8N_WEBHOOK_URL_REFORMA,
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
            "INSERT INTO TB_AVALIACOES_CHATBOT (user_id, nota, Linha) VALUES (%s, %s, %s)",
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
                FROM TB_AVALIACOES_CHATBOT
                WHERE Linha = %s AND data >= CURDATE() - INTERVAL %s DAY
            """, ("Clipp", dias))
        else:
            cur.execute("""
                SELECT ROUND(AVG(nota),2) AS media, COUNT(*) AS total
                FROM TB_AVALIACOES_CHATBOT
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
