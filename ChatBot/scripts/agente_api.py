import re
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

def linkify(text: str) -> str:
    """
    Envolve URLs em <a> com target="_blank" para abrir em nova guia.
    """
    def _repl(match):
        url = match.group(0)
        return f'<a href="{url}" target="_blank" rel="noopener noreferrer">{url}</a>'
    # captura http:// ou https:// até espaço ou final de string
    return re.sub(r'(https?://[^\s]+)', _repl, text)

@app.post("/consultar")
async def consultar(request: Request):
    data = await request.json()
    pergunta = data.get("pergunta")
    user_id = data.get("user_id")

    # Validações básicas
    if not pergunta:
        return {"erro": "Campo 'pergunta' obrigatório."}
    if not user_id:
        return {"erro": "Campo 'user_id' obrigatório."}

    try:
        async with httpx.AsyncClient() as client:
            # Monta payload com pergunta e user_id
            payload = {
                "pergunta": pergunta,
                "user_id": user_id
            }
            resp = await client.post(
                N8N_WEBHOOK_URL,
                json=payload,
                timeout=90
            )
            if resp.status_code != 200:
                return {
                    "erro": f"Erro ao consultar n8n: {resp.status_code}. Detalhe: {resp.text}"
                }

            resposta_n8n = resp.json()

            # Extrai o campo de saída ("output") do webhook
            output = None
            if isinstance(resposta_n8n, list) and len(resposta_n8n) > 0:
                item = resposta_n8n[0]
                output = item.get("response", {}).get("body", {}).get("output")
            elif isinstance(resposta_n8n, dict) and "output" in resposta_n8n:
                output = resposta_n8n["output"]

            if output:
                # Pós-processa para adicionar target="_blank" aos links
                output = linkify(output)
                return {"resposta": output}

            return {
                "erro": (
                    "Formato inesperado da resposta do n8n: "
                    f"{json.dumps(resposta_n8n, ensure_ascii=False)}"
                )
            }

    except httpx.RequestError as e:
        return {"erro": f"Erro de conexão com o n8n: {str(e)}"}
    except Exception as e:
        return {"erro": f"Erro ao tratar resposta do n8n: {str(e)}"}

@app.get("/")
def home():
    return {"status": "ok"}

if __name__ == "__main__":
    uvicorn.run("agente_api:app", host="0.0.0.0", port=8000, reload=True)
