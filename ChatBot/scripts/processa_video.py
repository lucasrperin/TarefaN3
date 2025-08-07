import os
import sys
import openai
import json
import yt_dlp
from datetime import datetime
from dotenv import load_dotenv
from pydub import AudioSegment

load_dotenv()
openai.api_key = os.getenv("OPENAI_API_KEY")
FFMPEG_PATH = os.getenv("FFMPEG_PATH", None)

# Cria diretório temporário fixo no projeto
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
            raise Exception("Arquivo .mp3 não foi gerado. Verifique se o ffmpeg está funcionando corretamente.")

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

def salvar_json(titulo, texto, embedding):
    dir_saida = os.path.join(os.path.dirname(__file__), "../embeddings/transcricoes")
    os.makedirs(dir_saida, exist_ok=True)
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    arquivo = os.path.join(dir_saida, f"transc_{timestamp}.json")
    with open(arquivo, "w", encoding="utf-8") as f:
        json.dump({
            "titulo": titulo,
            "conteudo": texto,
            "embedding": embedding
        }, f, ensure_ascii=False)
    return arquivo

def main():
    if len(sys.argv) < 2:
        print("Erro: Forneça o caminho do arquivo de vídeo ou link como argumento.")
        sys.exit(1)

    arg = sys.argv[1]
    caminho = None
    print("Iniciando processamento...")

    try:
        if arg.startswith("http://") or arg.startswith("https://"):
            print("Baixando vídeo do link...")
            caminho = baixar_video(arg)
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
        caminho_saida = salvar_json("Transcrição de vídeo", texto, emb)

        print(f"Concluído com sucesso!\nArquivo gerado: {caminho_saida}")
    except Exception as e:
        print(f"Erro na transcrição: {e}")
        sys.exit(1)
    finally:
        # Limpeza do arquivo baixado, se veio de link
        if caminho and os.path.exists(caminho) and "temp" in caminho:
            try:
                os.remove(caminho)
            except Exception:
                pass

if __name__ == "__main__":
    main()
