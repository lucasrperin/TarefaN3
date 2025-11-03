# Microserviço ChatBot (Python/FastAPI)

Este diretório contém o microserviço Python que fornece endpoints REST para o módulo de ChatBot.

## Endpoints disponíveis

- POST `/consultar`       
  Recebe JSON com `{ pergunta: string, user_id: int }` e encaminha ao webhook n8n.

- POST `/upload-imagem`   
  Envia imagem via `multipart/form-data` ({ `data`: arquivo, `user_id`: valor }) para o webhook.

- POST `/upload-audio`    
  Envia áudio via `multipart/form-data` ({ `data`: arquivo, `user_id`: valor }) para o webhook.

- POST `/avaliacao`       
  Salva nota de avaliação (chatbot) no MySQL: `{ user_id: int, nota: int }`.

- GET  `/media-avaliacoes`
  Retorna JSON `{ media: float, total: int }` com estatísticas de avaliações.

- GET `/` (healthcheck)  
  Retorna `{ status: "ok" }` para verificar se o serviço está ativo.

## Pré-requisitos

- Python 3.10+
- Recomenda-se criar e ativar um ambiente virtual (`venv`).

## Instruções de instalação (Windows PowerShell)

1. Abra o PowerShell na pasta `ChatBot` e crie/ative o venv:

   ```powershell
   python -m venv .venv
   .\.venv\Scripts\Activate.ps1
   ```

2. Instale as dependências:

   ```powershell
   pip install -r requirements.txt
   ```

3. Ajuste variáveis de ambiente (opcional, via `.env`):

   - `N8N_WEBHOOK_URL`  
   - Configurações de banco (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`)

4. Execute o servidor:

   ```powershell
   uvicorn agente_api:app --reload --host 0.0.0.0 --port 8000
   ```

## Como usar

O frontend (ex.: `ChatBot/webchat/index.php`) deve apontar para este microserviço no endereço `http://<host>:8000`.

Exemplo de requisição `curl`:
```bash
curl -X POST http://localhost:8000/consultar \
     -H "Content-Type: application/json" \
     -d '{"pergunta":"Olá","user_id":1}'
```

## Observações

- Para geração de *embeddings* ou outras tarefas auxiliares, veja os scripts em `scripts/`.
- Certifique-se de que o webhook n8n esteja configurado em `agente_api.py`.
