import openai
import json
import numpy as np
import tiktoken
import re

OPENAI_API_KEY = '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'  # <--- SUBSTITUA pela sua chave da OpenAI!
MODEL_EMB = 'text-embedding-3-small'
MODEL_CHAT = 'gpt-3.5-turbo'
EMBEDDINGS_PATH = '../embeddings/embeddings.json'

openai.api_key = OPENAI_API_KEY

def cosine_similarity(a, b):
    a = np.array(a)
    b = np.array(b)
    return np.dot(a, b) / (np.linalg.norm(a) * np.linalg.norm(b))

def limpa_html(texto):
    return re.sub(r'<[^>]*>', '', texto)

# Carrega embeddings
with open(EMBEDDINGS_PATH, 'r', encoding='utf-8') as f:
    artigos = json.load(f)

def buscar_artigos_semelhantes(pergunta, topn=3):
    resp = openai.embeddings.create(input=pergunta, model=MODEL_EMB)
    emb_pergunta = resp.data[0].embedding
    resultados = []
    for artigo_id, dados in artigos.items():
        score = cosine_similarity(emb_pergunta, dados['embedding'])
        resultados.append({
            'id': artigo_id,
            'titulo': dados['titulo'],
            'conteudo': dados['conteudo'],
            'score': score
        })
    resultados.sort(key=lambda x: x['score'], reverse=True)
    return resultados[:topn]

def gerar_pergunta_triagem(pergunta_usuario, artigos):
    prompt = f"""Você é um agente de suporte técnico experiente.
O usuário escreveu: "{pergunta_usuario}"

Eu tenho três possíveis artigos de solução:

"""
    for idx, art in enumerate(artigos, 1):
        resumo = limpa_html(art['conteudo'][:200].replace('\n', ' ').replace('\r', ' '))
        prompt += f"{idx}. {art['titulo']}: {resumo}\n"
    prompt += """
Analise as três opções acima e:
- Se existirem nomes de produtos, sistemas ou módulos diferentes nos títulos ou resumos, faça UMA pergunta objetiva, curta e clara para que o usuário informe para qual deles ele precisa da solução (exemplo: Clipp Pro, Clipp Store, NF-e, NFC-e, Zweb).
- Se não houver diferença de sistema/produto, pergunte sobre outra diferença chave entre as opções (contexto, causa, sintoma, procedimento).
- Nunca cite códigos de artigo, IDs, números ou frases irrelevantes para o usuário final.
- A pergunta nunca deve conter números ou códigos estranhos; apenas termos reais de sistema, produto ou processo.
- Exemplo de pergunta: "Sua dúvida é sobre a NF-e, NFC-e ou Balança Zweb?", "O erro aparece ao emitir a NF-e ou ao imprimir a etiqueta?", etc.
Pergunta:
"""
    resposta = openai.chat.completions.create(
        model=MODEL_CHAT,
        messages=[{"role": "user", "content": prompt}],
        temperature=0.1,
        max_tokens=80
    )
    return resposta.choices[0].message.content.strip()

def apresentar_artigo(artigo):
    resumo_limpo = limpa_html(artigo['conteudo'][:400].replace('\n', ' ').replace('\r', ' '))
    print(f"\nSolução encontrada:\n")
    print(f"Título: {artigo['titulo']}\n")
    print(f"Resumo: {resumo_limpo}...\n")
    print(f"Link: https://suporte.clipp.com.br/artigos/{artigo['id']}")

if __name__ == "__main__":
    pergunta = input("Digite sua dúvida: ")
    top_artigos = buscar_artigos_semelhantes(pergunta, topn=3)
    
    # Critério para resposta direta (ajuste conforme experiência)
    if top_artigos[0]['score'] >= 0.90 and (top_artigos[0]['score'] - top_artigos[1]['score'] > 0.03):
        apresentar_artigo(top_artigos[0])
    else:
        print("\nPreciso de mais detalhes para identificar o artigo certo.")
        pergunta_triagem = gerar_pergunta_triagem(pergunta, top_artigos)
        print(f"\nPergunta para você: {pergunta_triagem}")
        resposta_usuario = input("\nSua resposta: ")
        
        # Refaz busca com pergunta + resposta
        nova_pergunta = pergunta + " " + resposta_usuario
        novo_artigo = buscar_artigos_semelhantes(nova_pergunta, topn=1)[0]
        apresentar_artigo(novo_artigo)
