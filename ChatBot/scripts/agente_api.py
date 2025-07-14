from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
import uvicorn
import httpx
import json

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

N8N_WEBHOOK_URL = "https://n8n.zucchetti.com.br/webhook/4ccf11a7-8170-48d4-8ee1-ce8355ce1c52"

@app.post("/consultar")
async def consultar(request: Request):
    data = await request.json()
    pergunta = data.get("pergunta")
    if not pergunta:
        return {"erro": "Campo 'pergunta' obrigatório."}

    async with httpx.AsyncClient() as client:
        resp = await client.post(N8N_WEBHOOK_URL, json={"pergunta": pergunta})
        if resp.status_code != 200:
            return {"erro": f"Erro ao consultar n8n: {resp.status_code}"}

        try:
            resposta_n8n = resp.json()
            # DEBUG: print("Resposta do n8n:", resposta_n8n)

            # Pode vir lista
            if isinstance(resposta_n8n, list) and len(resposta_n8n) > 0:
                item = resposta_n8n[0]
                output = (
                    item.get("response", {})
                    .get("body", {})
                    .get("output")
                )
                if output:
                    return {"resposta": output}
            # Ou pode vir dict direto
            if isinstance(resposta_n8n, dict) and "output" in resposta_n8n:
                return {"resposta": resposta_n8n["output"]}

            return {"erro": f"Formato inesperado da resposta do n8n: {resposta_n8n}"}
        except Exception as e:
            return {"erro": f"Erro ao tratar resposta do n8n: {str(e)}"}


@app.get("/")
def home():
    return {"status": "ok"}

if __name__ == "__main__":
    uvicorn.run("agente_api:app", host="0.0.0.0", port=8000, reload=True)
