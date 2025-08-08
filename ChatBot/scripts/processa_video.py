import os
import sys
import re
import math
import unicodedata
import json
from datetime import datetime

import yt_dlp
from dotenv import load_dotenv
from pydub import AudioSegment
import openai

# ===== Setup básico =====
load_dotenv()

# Garante stdout/stderr em UTF-8 (útil no Windows)
try:
    sys.stdout.reconfigure(encoding='utf-8')  # type: ignore[attr-defined]
    sys.stderr.reconfigure(encoding='utf-8')  # type: ignore[attr-defined]
except Exception:
    pass

openai.api_key = os.getenv("OPENAI_API_KEY")
if not openai.api_key:
    print("Erro na transcrição: OPENAI_API_KEY não definida no ambiente.")
    sys.exit(1)

FFMPEG_PATH = os.getenv("FFMPEG_PATH", None)

# Diretório temporário fixo
TEMP_DIR = os.path.join(os.path.dirname(__file__), "temp")
os.makedirs(TEMP_DIR, exist_ok=True)
if not os.access(TEMP_DIR, os.W_OK):
    print(f"Erro na transcrição: Sem permissão de escrita em {TEMP_DIR}")
    sys.exit(1)

# Se houver FFMPEG_PATH, tenta usar no pydub também
if FFMPEG_PATH:
    # Pode ser diretório (contendo ffmpeg/ffmpeg.exe) ou caminho direto para o executável
    if os.path.isdir(FFMPEG_PATH):
        ffmpeg_exec = os.path.join(FFMPEG_PATH, "ffmpeg.exe" if os.name == "nt" else "ffmpeg")
    else:
        ffmpeg_exec = FFMPEG_PATH
    if os.path.exists(ffmpeg_exec):
        AudioSegment.converter = ffmpeg_exec  # pydub usa este binário

# ===== Helpers =====
class _QuietLogger:
    def debug(self, msg): pass
    def warning(self, msg): pass
    def error(self, msg): print(f"yt_dlp error: {msg}")

def baixar_video(link_url: str) -> str:
    """Baixa o áudio do link e retorna caminho do mp3."""
    output_template = os.path.join(TEMP_DIR, "video_downloaded.%(ext)s")
    ydl_opts = {
        "format": "bestaudio/best",
        "outtmpl": output_template,
        "quiet": True,
        "no_warnings": True,
        "noprogress": True,
        "noplaylist": True,
        "logger": _QuietLogger(),
        "postprocessors": [{
            "key": "FFmpegExtractAudio",
            "preferredcodec": "mp3",
            "preferredquality": "192",
        }],
    }
    if FFMPEG_PATH:
        ydl_opts["ffmpeg_location"] = FFMPEG_PATH

    with yt_dlp.YoutubeDL(ydl_opts) as ydl:
        info = ydl.extract_info(link_url, download=True)
        downloaded_path = ydl.prepare_filename(info)
        mp3_path = os.path.splitext(downloaded_path)[0] + ".mp3"
        if not os.path.exists(mp3_path):
            raise Exception("Arquivo .mp3 não foi gerado. Verifique o ffmpeg.")
        if os.path.getsize(mp3_path) < 10 * 1024:
            raise Exception("Arquivo de áudio gerado está vazio ou corrompido.")
        return mp3_path

def transcrever_audio_em_partes(caminho_audio: str, duracao_maxima_ms: int = 10 * 60 * 1000) -> str:
    """
    Corta o áudio em partes de até duracao_maxima_ms (default: 10min) e transcreve via Whisper.
    """
    audio = AudioSegment.from_file(caminho_audio)
    total = len(audio)
    partes = max(1, math.ceil(total / duracao_maxima_ms))
    transcricao_final = ""

    for i in range(partes):
        inicio = i * duracao_maxima_ms
        fim = min((i + 1) * duracao_maxima_ms, total)
        parte = audio[inicio:fim]
        parte_path = os.path.join(TEMP_DIR, f"parte_{i}.mp3")
        parte.export(parte_path, format="mp3")

        try:
            with open(parte_path, "rb") as f:
                transcript = openai.audio.transcriptions.create(
                    model="whisper-1",
                    file=f,
                    response_format="text",
                )
                transcricao_final += (transcript.strip() if isinstance(transcript, str) else str(transcript)).strip() + "\n"
        finally:
            try:
                os.remove(parte_path)
            except Exception:
                pass

    return transcricao_final.strip()

def gerar_embedding(texto: str):
    # Limita o texto para o tamanho suportado pelo modelo escolhido
    if len(texto) > 8190:
        texto = texto[:8190]
    resp = openai.embeddings.create(
        input=texto,
        model="text-embedding-ada-002"  # mantenho para compatibilidade do seu pipeline
    )
    return resp.data[0].embedding

def slugify(nome: str) -> str:
    """
    Translitera acentos para ASCII (á->a, ã->a, ç->c, ó->o, etc.),
    mantém apenas [A-Za-z0-9._-], colapsa múltiplos '_' e remove pontas com '.', '_' e '-'.
    """
    nome = unicodedata.normalize('NFKD', nome)
    nome = nome.encode('ascii', 'ignore').decode('ascii')
    nome = nome.replace("'", "").replace('"', '')
    nome = re.sub(r'[^A-Za-z0-9._-]+', '_', nome)
    nome = re.sub(r'_{2,}', '_', nome)
    nome = nome.strip('._-')
    return nome or "transcricao"

def salvar_json(titulo: str, texto: str, embedding, link: str | None) -> str:
    dir_saida = os.path.join(os.path.dirname(__file__), "../embeddings/transcricoes")
    os.makedirs(dir_saida, exist_ok=True)

    base = slugify(titulo)
    arquivo = os.path.join(dir_saida, f"{base}.json")
    if os.path.exists(arquivo):
        ts = datetime.now().strftime("%Y%m%d_%H%M%S")
        arquivo = os.path.join(dir_saida, f"{base}_{ts}.json")

    payload = {
        "titulo": titulo,
        "conteudo": texto,
        "embedding": embedding,
        "link": link if link else None
    }

    with open(arquivo, "w", encoding="utf-8") as f:
        json.dump(payload, f, ensure_ascii=False)
    return arquivo

def main():
    if len(sys.argv) < 2:
        print("Erro: Forneça o caminho do arquivo de vídeo ou link como 1º argumento.")
        sys.exit(1)

    arg = sys.argv[1]
    titulo_custom = sys.argv[2].strip() if len(sys.argv) >= 3 and sys.argv[2].strip() else "Transcrição de vídeo"
    source_url = arg if (arg.startswith("http://") or arg.startswith("https://")) else ""
    caminho = None

    print("Iniciando processamento...")
    try:
        if source_url:
            print("Baixando vídeo do link...")
            caminho = baixar_video(source_url)
        else:
            caminho = arg
            if not os.path.exists(caminho):
                print("Erro na transcrição: Arquivo de vídeo não encontrado.")
                sys.exit(1)

        print("Transcrevendo conteúdo...")
        texto = transcrever_audio_em_partes(caminho)

        print("Gerando embedding...")
        emb = gerar_embedding(texto)

        print("Salvando transcrição...")
        caminho_saida = salvar_json(titulo_custom, texto, emb, source_url)

        # Linha essencial para o PHP capturar
        print("Concluído com sucesso!")
        print(f"Arquivo gerado: {caminho_saida}")
    except Exception as e:
        print(f"Erro na transcrição: {e}")
        sys.exit(1)
    finally:
        # Limpeza do arquivo baixado, se veio de link e foi salvo em temp
        try:
            if source_url and caminho and os.path.exists(caminho) and os.path.dirname(caminho) == TEMP_DIR:
                os.remove(caminho)
        except Exception:
            pass

if __name__ == "__main__":
    main()
