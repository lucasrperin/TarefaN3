import openai
import json
import numpy as np

OPENAI_API_KEY = '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'  # <--- SUBSTITUA pela sua chave da OpenAI!
MODEL = 'text-embedding-3-small'
EMBEDDINGS_PATH = '../embeddings/embeddings.json'

openai.api_key = OPENAI_API_KEY

def cosine_similarity(a, b):
    a = np.array(a)
    b = np.array(b)
    return np.dot(a, b) / (np.linalg.norm(a) * np.linalg.norm(b))

# Carrega os embeddings
with open(EMBEDDINGS_PATH, 'r', encoding='utf-8') as f:
    artigos = json.load(f)

def buscar_artigo_pergunta(pergunta, topn=3, limiar_alto=0.90, limiar_baixo=0.80):
    # 1. Gera embedding da dúvida
    resp = openai.embeddings.create(input=pergunta, model=MODEL)
    emb_pergunta = resp.data[0].embedding

    # 2. Calcula similaridade com cada artigo
    resultados = []
    for artigo_id, dados in artigos.items():
        score = cosine_similarity(emb_pergunta, dados['embedding'])
        resultados.append({
            'id': artigo_id,
            'titulo': dados['titulo'],
            'conteudo': dados['conteudo'],
            'score': score
        })
    # 3. Ordena por similaridade (maior primeiro)
    resultados.sort(key=lambda x: x['score'], reverse=True)
    top_resultados = resultados[:topn]

    # 4. Decide lógica de resposta
    if top_resultados[0]['score'] >= limiar_alto and (top_resultados[0]['score'] - top_resultados[1]['score'] > 0.05):
        # Um resultado bem destacado -> retorna esse
        return {
            'tipo': 'direto',
            'artigo': {
                'id': top_resultados[0]['id'],
                'titulo': top_resultados[0]['titulo'],
                'resumo': top_resultados[0]['conteudo'][:300].replace('\n', ' ').replace('\r', ' '),
                'link': f"https://suporte.clipp.com.br/artigos/{top_resultados[0]['id']}"
            }
        }
    else:
        # Resultados parecidos, faz pergunta ao usuário
        artigos_lista = []
        for art in top_resultados:
            artigos_lista.append({
                'id': art['id'],
                'titulo': art['titulo'],
                'resumo': art['conteudo'][:300].replace('\n', ' ').replace('\r', ' '),
                'link': f"https://suporte.clipp.com.br/artigos/{art['id']}"
            })
        return {
            'tipo': 'multiplo',
            'artigos': artigos_lista
        }

# ===== EXEMPLO DE USO =====
if __name__ == "__main__":
    pergunta = input("Digite sua dúvida: ")
    resposta = buscar_artigo_pergunta(pergunta)
    if resposta['tipo'] == 'direto':
        print("\nEncontrei uma solução bem precisa para sua dúvida:")
        print(f"\nTítulo: {resposta['artigo']['titulo']}")
        print(f"Resumo: {resposta['artigo']['resumo']}")
        print(f"Link: {resposta['artigo']['link']}")
    else:
        print("\nEncontrei mais de uma possibilidade. Qual desses artigos descreve melhor seu caso?")
        for idx, art in enumerate(resposta['artigos'], 1):
            print(f"\n[{idx}] Título: {art['titulo']}\nResumo: {art['resumo']}\nLink: {art['link']}")
        escolha = int(input("\nDigite o número do artigo que melhor representa seu caso: "))
        art = resposta['artigos'][escolha - 1]
        print(f"\nVocê escolheu: {art['titulo']}")
        print(f"Resumo: {art['resumo']}")
        print(f"Link: {art['link']}")
