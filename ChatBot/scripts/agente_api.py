from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
import uvicorn
import sys
import os

# Importa a função consultar_chat do seu módulo busca_semantica_boost
sys.path.append(os.path.dirname(os.path.abspath(__file__)))
from busca_semantica_boost import consultar_chat

app = FastAPI()

# Configura CORS para permitir frontends locais ou qualquer origem (ajuste conforme necessário)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Defina seu frontend aqui, ex: ["http://localhost"]
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.post("/consultar")
async def consultar(request: Request):
    data = await request.json()
    pergunta = data.get("pergunta")
    historico = data.get("historico", [])  # Espera lista de dicts: [{"pergunta": "...", "resposta": "..."}]

    if not pergunta:
        return {"erro": "Campo 'pergunta' obrigatório."}

    # Garante que historico seja lista de dict (caso venha diferente)
    if not isinstance(historico, list):
        historico = []

    resultado = consultar_chat(pergunta, historico)
    return resultado

@app.get("/")
def home():
    return {"status": "ok"}

if __name__ == "__main__":
    # Para rodar diretamente: python agente_api.py
    uvicorn.run("agente_api:app", host="0.0.0.0", port=8000, reload=True)
