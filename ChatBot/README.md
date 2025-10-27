IA / ChatBot - Instruções

Este diretório contém um microserviço Python (FastAPI) que atende o frontend PHP localizado em `ChatBot/webchat`.

O serviço expõe endpoints importantes:
- POST /consultar -> recebe JSON { pergunta, user_id } e repassa para um webhook n8n
- POST /upload-imagem -> recebe multipart/form-data com imagem e user_id e encaminha ao n8n
- POST /upload-audio -> recebe multipart/form-data com áudio e user_id e encaminha ao n8n
- POST /avaliacao -> salva nota de avaliação no MySQL
- GET  /media-avaliacoes -> retorna média e total de avaliações

Como executar (Windows PowerShell)

1) Criar e ativar ambiente virtual

	cd ChatBot
	python -m venv .venv
	.\.venv\Scripts\Activate.ps1

2) Instalar dependências

	pip install -r requirements.txt

3) Ajustar configurações

	- Abra `agente_api.py` e verifique `DB_CONFIG` (host, user, password, database).
	- Atualize `N8N_WEBHOOK_URL` para apontar ao seu webhook n8n se necessário.

4) Rodar o serviço

	uvicorn agente_api:app --reload --host 0.0.0.0 --port 8000

5) Testar endpoints

	- Health: http://localhost:8000/
	- Consultar (exemplo):

	  curl -X POST http://localhost:8000/consultar -H "Content-Type: application/json" -d '{"pergunta":"Olá","user_id":1}'

Gerar embeddings (scripts)

- `scripts/gerar_embeddings.py` lê artigos de um PostgreSQL e gera `ChatBot/embeddings/embeddings.json` usando a API da OpenAI.
- Defina as variáveis de ambiente em um `.env` (ex.: OPENAI_API_KEY, PG_HOST, PG_USER, PG_PASS, PG_DB) ou exporte-as no PowerShell antes de executar.

Observações

- O frontend `webchat/index.php` faz requisições para `http://localhost:8000`. Se mudar a porta, atualize o front ou um proxy reverso.
- Os scripts Python assumem diferentes bancos (MySQL para avaliações e PostgreSQL para artigos/embeddings). Adapte conforme sua instalação.

Se quiser, posso criar um `docker-compose.yml` que sobe MySQL + PHP (Apache) + Python para facilitar testes locais.
