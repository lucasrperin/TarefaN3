FROM python:3.12-slim
WORKDIR /app
COPY requirements.txt /app/requirements.txt
# Instala dependências do sistema necessárias para compilar pacotes Python nativos (ex: psycopg2)
RUN apt-get update && apt-get install -y --no-install-recommends \
	build-essential \
	libpq-dev \
	gcc \
	libc6-dev \
	&& rm -rf /var/lib/apt/lists/*

RUN python -m pip install --upgrade pip && pip install --no-cache-dir -r /app/requirements.txt
COPY . /app
ENV PYTHONUNBUFFERED=1
EXPOSE 8000
CMD ["python", "-m", "uvicorn", "scripts.agente_api:app", "--host", "0.0.0.0", "--port", "8000"]
