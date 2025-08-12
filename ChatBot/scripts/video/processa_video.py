import os
import sys
import hashlib
import re
import math
import unicodedata
import json
from datetime import datetime

# ==== stdout/stderr UTF-8 (Windows/Apache) ====
try:
    sys.stdout.reconfigure(encoding='utf-8')  # type: ignore[attr-defined]
    sys.stderr.reconfigure(encoding='utf-8')  # type: ignore[attr-defined]
except Exception:
    pass

# ==== Env ====
from dotenv import load_dotenv, find_dotenv
# Garante que o .env seja encontrado mesmo se o CWD variar
load_dotenv(find_dotenv())

# ==== Dependências externas ====
# yt_dlp só é necessário para LINK; upload de arquivo funciona sem ele
# (lazy import feito dentro da função baixar_video)
try:
    from pydub import AudioSegment
except ModuleNotFoundError:
    print("Erro: pydub não encontrado. Instale no venv: .venv\\Scripts\\python.exe -m pip install pydub")
    sys.exit(1)

import openai

# ====== OPENAI KEY ======
openai.api_key = os.getenv("OPENAI_API_KEY")
if not openai.api_key:
    print("Erro na transcrição: OPENAI_API_KEY não definida no ambiente.")
    sys.exit(1)

# ====== Caminhos base ======
SCRIPT_DIR  = os.path.dirname(os.path.abspath(__file__))               # .../ChatBot/scripts/video
CHATBOT_DIR = os.path.abspath(os.path.join(SCRIPT_DIR, os.pardir, os.pardir))  # .../ChatBot
TRANS_DIR   = os.path.join(CHATBOT_DIR, "embeddings", "transcricoes")  # .../ChatBot/embeddings/transcricoes

# ====== FFMPEG ======
FFMPEG_PATH_RAW = os.getenv("FFMPEG_PATH", "").strip()

def _configure_ffmpeg(path_hint: str):
    """
    Aceita tanto pasta (ex: C:\\ffmpeg\\bin) quanto caminho do executável (ex: C:\\ffmpeg\\bin\\ffmpeg.exe).
    Configura pydub para usar ffmpeg e ffprobe e injeta a pasta no PATH.
    """
    ffmpeg_exec = None
    ffprobe_exec = None

    if path_hint:
        if os.path.isdir(path_hint):
            ffmpeg_exec  = os.path.join(path_hint, "ffmpeg.exe" if os.name == "nt" else "ffmpeg")
            ffprobe_exec = os.path.join(path_hint, "ffprobe.exe" if os.name == "nt" else "ffprobe")
        else:
            # user passou o binário do ffmpeg
            ffmpeg_exec = path_hint
            base_dir    = os.path.dirname(path_hint)
            ffprobe_exec = os.path.join(base_dir, "ffprobe.exe" if os.name == "nt" else "ffprobe")

    # Se não achar, tenta via PATH normal (pode estar instalado no sistema)
    def _is_exec(p): return bool(p) and os.path.exists(p)

    if _is_exec(ffmpeg_exec):
        AudioSegment.converter = ffmpeg_exec
        # injeta pasta do ffmpeg no PATH para processos filhos (yt_dlp/pydub)
        ffdir = os.path.dirname(ffmpeg_exec)
        os.environ["PATH"] = ffdir + os.pathsep + os.environ.get("PATH", "")
    if _is_exec(ffprobe_exec):
        AudioSegment.ffprobe = ffprobe_exec
        ffdir = os.path.dirname(ffprobe_exec)
        if ffdir not in os.environ.get("PATH", ""):
            os.environ["PATH"] = ffdir + os.pathsep + os.environ.get("PATH", "")

    # Validação amigável
    conv = getattr(AudioSegment, "converter", None)
    prob = getattr(AudioSegment, "ffprobe", None)
    if not conv or not os.path.exists(conv):
        print("Aviso: ffmpeg não encontrado. Defina FFMPEG_PATH no .env apontando para a pasta ou o executável do ffmpeg.")
    if not prob or not os.path.exists(prob):
        print("Aviso: ffprobe não encontrado. Coloque o ffprobe na mesma pasta e/ou ajuste FFMPEG_PATH.")

_configure_ffmpeg(FFMPEG_PATH_RAW)

# ===== Diretório temporário =====
TEMP_DIR = os.path.join(SCRIPT_DIR, "temp")
os.makedirs(TEMP_DIR, exist_ok=True)
if not os.access(TEMP_DIR, os.W_OK):
    print(f"Erro na transcrição: Sem permissão de escrita em {TEMP_DIR}")
    sys.exit(1)

# ===== Helpers =====
class _QuietLogger:
    def debug(self, msg): pass
    def warning(self, msg): pass
    def error(self, msg): print(f"yt_dlp error: {msg}")

def baixar_video(link_url: str) -> str:
    """Baixa o áudio do link e retorna caminho do mp3."""
    try:
        import yt_dlp  # lazy import: só exigido para link
    except ModuleNotFoundError:
        raise RuntimeError("yt_dlp não está instalado no venv. Instale: .venv\\Scripts\\python.exe -m pip install yt-dlp")

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

    # Se soubermos a pasta do ffmpeg, indique para o yt_dlp também
    if FFMPEG_PATH_RAW:
        ydl_opts["ffmpeg_location"] = FFMPEG_PATH_RAW

    with yt_dlp.YoutubeDL(ydl_opts) as ydl:
        info = ydl.extract_info(link_url, download=True)
        downloaded_path = ydl.prepare_filename(info)
        mp3_path = os.path.splitext(downloaded_path)[0] + ".mp3"
        if not os.path.exists(mp3_path):
            raise Exception("Arquivo .mp3 não foi gerado. Verifique o ffmpeg (FFMPEG_PATH) ou o link.")
        if os.path.getsize(mp3_path) < 10 * 1024:
            raise Exception("Arquivo de áudio gerado está vazio ou corrompido.")
        return mp3_path

def transcrever_audio_em_partes(caminho_audio: str, duracao_maxima_ms: int = 10 * 60 * 1000) -> str:
    """
    Corta o áudio em partes de até duracao_maxima_ms (default: 10min) e transcreve via Whisper.
    """
    # Esta chamada invoca ffmpeg/ffprobe; se não estiverem acessíveis, dará WinError 2.
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
        model="text-embedding-ada-002"  # compatível com seu pipeline atual
    )
    return resp.data[0].embedding

def slugify(nome: str) -> str:
    """
    Translitera acentos para ASCII, mantém apenas [A-Za-z0-9._-],
    colapsa múltiplos '_' e remove pontas com '.', '_' e '-'.
    """
    nome = unicodedata.normalize('NFKD', nome)
    nome = nome.encode('ascii', 'ignore').decode('ascii')
    nome = nome.replace("'", "").replace('"', '')
    nome = re.sub(r'[^A-Za-z0-9._-]+', '_', nome)
    nome = re.sub(r'_{2,}', '_', nome)
    nome = nome.strip('._-')
    return nome or "transcricao"

def make_uid(link: str | None, texto: str) -> str:
    """
    Gera um identificador estável:
    - se houver link, usa hash do link
    - senão, usa hash do texto transcrito normalizado
    """
    if link:
        base = "url:" + link.strip().lower()
    else:
        norm = re.sub(r"\s+", " ", (texto or "").strip().lower())
        base = "txt:" + norm
    return hashlib.sha1(base.encode("utf-8")).hexdigest()

def salvar_json(titulo: str, texto: str, embedding, link: str | None) -> str:
    os.makedirs(TRANS_DIR, exist_ok=True)

    uid = make_uid(link, texto)  # <<< NOVO

    base = slugify(titulo)
    arquivo = os.path.join(TRANS_DIR, f"{base}.json")
    if os.path.exists(arquivo):
        ts = datetime.now().strftime("%Y%m%d_%H%M%S")
        arquivo = os.path.join(TRANS_DIR, f"{base}_{ts}.json")

    payload = {
        "uid": uid,                     # <<< NOVO
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
