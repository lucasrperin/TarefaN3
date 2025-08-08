import os
import sys
import re
import unicodedata
import openai
import json
import yt_dlp
from datetime import datetime
from dotenv import load_dotenv
from pydub import AudioSegment

load_dotenv()
openai.api_key = os.getenv("OPENAI_API_KEY")
FFMPEG_PATH = os.getenv("FFMPEG_PATH", None)

# Diretório temporário fixo
TEMP_DIR = os.path.join(os.path.dirname(__file__), "temp")
os.makedirs(TEMP_DIR, exist_ok=True)
if not os.access(TEMP_DIR, os.W_OK):
    raise Exception(f"Sem permissão de escrita em {TEMP_DIR}")

def baixar_video(link_url):
    output_template = os.path.join(TEMP_DIR, "video_downloaded.%(ext)s")
    ydl_opts = {
        'format': 'bestaudio/best',
        'outtmpl': output_template,
        'quiet': True,
        'noplaylist': True,
        'postprocessors': [{
            'key': 'FFmpegExtractAudio',
            'preferredcodec': 'mp3',
            'preferredquality': '192',
        }]
    }
    if FFMPEG_PATH:
        ydl_opts['ffmpeg_location'] = FFMPEG_PATH

    with yt_dlp.YoutubeDL(ydl_opts) as ydl:
        info = ydl.extract_info(link_url, download=True)
        downloaded_path = ydl.prepare_filename(info)
        mp3_path = os.path.splitext(downloaded_path)[0] + ".mp3"
        if not os.path.exists(mp3_path):
            raise Exception("Arquivo .mp3 não foi gerado. Verifique o ffmpeg.")
        if os.path.getsize(mp3_path) < 10 * 1024:
            raise Exception("Arquivo de áudio gerado está vazio ou corrompido.")
        return mp3_path

def transcrever_audio_em_partes(caminho_audio, duracao_maxima=600000):
    audio = AudioSegment.from_file(caminho_audio)
    partes = len(audio) // duracao_maxima + 1
    transcricao_final = ""
    print("Dividindo áudio em partes...")

    for i in range(partes):
        inicio = i * duracao_maxima
        fim = min((i + 1) * duracao_maxima, len(audio))
        parte = audio[inicio:fim]
        parte_path = os.path.join(TEMP_DIR, f"parte_{i}.mp3")
        parte.export(parte_path, format="mp3")

        with open(parte_path, "rb") as f:
            transcript = openai.audio.transcriptions.create(
                model="whisper-1",
                file=f,
                response_format="text"
            )
            transcricao_final += transcript.strip() + "\n"

        os.remove(parte_path)

    return transcricao_final.strip()

def gerar_embedding(texto):
    if len(texto) > 8190:
        texto = texto[:8190]
    response = openai.embeddings.create(
        input=texto,
        model="text-embedding-ada-002"
    )
    return response.data[0].embedding

def slugify(nome: str) -> str:
    """
    Translitera acentos para ASCII (á->a, ã->a, ç->c, ó->o, etc.),
    troca sequências inválidas por '_', colapsa múltiplos '_' e remove
    pontas com '.', '_' e '-'.
    """
    nome = unicodedata.normalize('NFKD', nome)
    nome = nome.encode('ascii', 'ignore').decode('ascii')
    nome = nome.replace("'", "").replace('"', '')
    nome = re.sub(r'[^A-Za-z0-9._-]+', '_', nome)
    nome = re.sub(r'_{2,}', '_', nome)
    nome = nome.strip('._-')
    return nome or "transcricao"

def salvar_json(titulo, texto, embedding, link):
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
                print("Arquivo de vídeo não encontrado.")
                sys.exit(1)

        print("Transcrevendo conteúdo...")
        texto = transcrever_audio_em_partes(caminho)

        print("Gerando embedding...")
        emb = gerar_embedding(texto)

        print("Salvando transcrição...")
        caminho_saida = salvar_json(titulo_custom, texto, emb, source_url)

        print(f"Concluído com sucesso!\nArquivo gerado: {caminho_saida}")
    except Exception as e:
        print(f"Erro na transcrição: {e}")
        sys.exit(1)
    finally:
        if caminho and os.path.exists(caminho) and "temp" in caminho:
            try:
                os.remove(caminho)
            except Exception:
                pass

if __name__ == "__main__":
    main()
