# TarefaN3

Este repositório contém a aplicação "Painel N3" — um painel PHP para gestão de análises, clientes e um módulo de ChatBot (IA) integrado via um microserviço Python/FastAPI.

Este documento descreve como preparar e executar o projeto localmente em Windows (PowerShell). As instruções cobrem dependências PHP/Composer, o banco de dados MySQL e o microserviço Python usado pelo ChatBot.

## Requisitos

- PHP 8.x (linha de comando e/ou servidor Apache/IIS)
- Composer (para instalar dependências PHP, se necessário)
- MySQL/MariaDB
- Python 3.10+ (recomendado) para o serviço do ChatBot
- (Opcional) XAMPP ou similar para rodar o PHP + MySQL localmente

## Passos rápidos para rodar localmente (Windows PowerShell)

1) Instalar dependências PHP (composer)

	 Abra o PowerShell na raiz do projeto e execute:

	 composer install

2) Configurar o banco de dados

	 - O arquivo de criação do schema está em `Config/TarefaN3.sql`.
	 - Ajuste as credenciais em `Config/Database.php` se necessário (por padrão: host=localhost, user=root, pass=, db=TarefaN3).

	 Exemplos (PowerShell):

	 # cria o banco (opcional)
	 mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS TarefaN3 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
	 # importa o schema
	 mysql -u root -p TarefaN3 < .\Config\TarefaN3.sql

	 Se preferir, abra o phpMyAdmin ou uma GUI (MySQL Workbench) e importe `Config/TarefaN3.sql`.

3) Rodar a aplicação PHP (modo rápido com servidor embutido)

	 Para evitar conflito de porta com o microserviço Python (que usa por padrão 8000), rode o servidor PHP em outra porta, por exemplo 8080:

	 php -S localhost:8080 -t .

	 Acesse então: http://localhost:8080/index.php

	 Observação: Em produção ou para funcionalidades mais avançadas (upload, rewrite), prefira usar Apache/IIS com configuração adequada.

4) Rodar o microserviço ChatBot (FastAPI)

	 O backend do ChatBot fica em `ChatBot/` e expõe endpoints que o frontend usa (ex.: `/consultar`, `/upload-imagem`, `/upload-audio`, `/avaliacao`, `/media-avaliacoes`).

	 - Entre na pasta `ChatBot` e crie um ambiente virtual Python:

		 python -m venv .venv
		 .\.venv\Scripts\Activate.ps1
		 pip install -r requirements.txt

	 - Executar o servidor (porta 8000):

		 uvicorn agente_api:app --reload --host 0.0.0.0 --port 8000

	 O frontend (`ChatBot/webchat/index.php`) faz pedidos para `http://192.168.0.201:3310` — portanto este serviço deve estar ativo.

5) Gerar embeddings (opcional)

	 Há scripts Python para gerar embeddings a partir de uma base Postgres (em `ChatBot/scripts/gerar_embeddings.py`). Eles usam a API da OpenAI e exigem variáveis de ambiente:

	 - `OPENAI_API_KEY`, `PG_HOST`, `PG_PORT`, `PG_USER`, `PG_PASS`, `PG_DB`

	 No Windows PowerShell, defina as variáveis ou crie um arquivo `.env` e rode o script dentro do ambiente virtual:

		 python gerar_embeddings.py

	 Observação: o script salva o arquivo `embeddings.json` em `ChatBot/embeddings/`.

## Observações importantes

- O serviço Python (`ChatBot/agente_api.py`) encaminha as perguntas para uma instância n8n definida em `N8N_WEBHOOK_URL`. Se sua instalação não tiver esse webhook, atualize a variável dentro do arquivo para apontar ao seu fluxo do n8n ou substitua o comportamento conforme necessário.
- `ChatBot/scripts/agente_api.py` usa credenciais MySQL embutidas (DB_CONFIG). Ajuste-as se seu banco MySQL tiver senha diferente.
- `ChatBot/scripts/gerar_embeddings.py` usa PostgreSQL para ler artigos. Ajuste e exporte as variáveis de ambiente necessárias.

## Rodando com Docker (recomendado)

Forneci um `docker-compose.yml` que sobe 3 serviços:
- db (MySQL 8.0)
- php (Apache + PHP)
- chatbot (microserviço Python FastAPI)

Passos:

1. Copie `.env.example` para `.env` e ajuste as variáveis (MYSQL_ROOT_PASSWORD, N8N_WEBHOOK_URL, etc.).

2. Suba os serviços:

	docker compose up --build -d

3. Verifique os serviços:

	docker compose ps

4. Acesse:

	- Frontend PHP: http://localhost:8080/
	- ChatBot API: http://192.168.0.201:3310/

Logs úteis:

	docker compose logs -f chatbot
	docker compose logs -f php

Observações:

- O container `db` monta `Config/TarefaN3.sql` em `/docker-entrypoint-initdb.d/` — no primeiro start o schema será importado automaticamente pelo MySQL.
- Ajuste `N8N_WEBHOOK_URL` no `.env` para apontar ao seu fluxo n8n (ou deixe vazio e adapte o serviço se quiser processar localmente).


## Verificação rápida

- Validar composer.json:
	composer validate
- Verificar versão PHP:
	php -v
- Verificar Python:
	python --version

## Próximos passos / melhorias sugeridas

- Externalizar configurações sensíveis para `.env` e alterar `Config/Database.php` e `agente_api.py` para ler variáveis de ambiente.
- Adicionar um `docker-compose.yml` para facilitar orquestração local (MySQL + PHP + Python).

---
Se quiser, eu atualizo também o `ChatBot/README.md` com instruções específicas e crio um `requirements.txt` no diretório `ChatBot` para simplificar a instalação Python.