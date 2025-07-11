import json
import openai
import numpy as np
import re
from html import unescape
import os
from dotenv import load_dotenv

# Carrega as variáveis do .env
load_dotenv()
openai.api_key = os.getenv("OPENAI_API_KEY")

# Carregar embeddings
with open("../embeddings/embeddings.json", "r", encoding="utf-8") as f:
    embeddings = json.load(f)

def limpa_html(texto):
    if not isinstance(texto, str):
        return ""
    texto = re.sub(r'<[^>]+>', '', texto)
    texto = unescape(texto)
    texto = re.sub(r'\s+', ' ', texto)
    return texto.strip()

def gerar_embedding(texto):
    response = openai.embeddings.create(
        input=texto,
        model="text-embedding-ada-002"
    )
    return np.array(response.data[0].embedding)

def busca_artigos(consulta, top_n=5):
    consulta_emb = gerar_embedding(consulta)
    resultados = []
    for id_, artigo in embeddings.items():
        art_emb = np.array(artigo['embedding'])
        sim = np.dot(consulta_emb, art_emb) / (np.linalg.norm(consulta_emb) * np.linalg.norm(art_emb))
        boost = 0
        for termo in consulta.lower().split():
            if isinstance(artigo['titulo'], str) and termo in artigo['titulo'].lower():
                boost += 0.05
            if isinstance(artigo['conteudo'], str) and termo in artigo['conteudo'].lower():
                boost += 0.05
        resultados.append({
            "id": id_,
            "titulo": limpa_html(artigo['titulo']),
            "conteudo": artigo['conteudo'],
            "score": sim + boost
        })
    resultados.sort(key=lambda x: x['score'], reverse=True)
    return resultados[:top_n]

SISTEMAS = [
    "ClippPRO", "ClippStore", "ClippFacil", "Clipp360", "ZWEB",
    "ClippCheff", "ClippService", "ClippMEI", "ClippPedidos",
    "ZPOS", "AppsCloud", "MinhasNotas", "MeuClipp"
]
DOCUMENTOS = [
    "NF-e", "NFC-e", "CT-e", "MDF-e", "Venda Gerencial",
    "Pedidos de Venda", "Orçamento", "Nota de Compra", "Nota de venda(NF-e)"
]

def eh_saudacao(txt):
    txt = txt.lower()
    return any(x in txt for x in [
        "bom dia", "boa tarde", "boa noite", "olá", "ola", "oi", "saudações"
    ])

def eh_resolvido(txt):
    txt = txt.lower()
    return any(x in txt for x in [
        "obrigado", "obrigada", "resolveu", "resolvido", "era isso", "agradecido", "ok", "show", "deu certo", "valeu"
    ])

def gerar_pergunta_diferenciadora(duvida_usuario, artigos, historico):
    artigos_resumo = [
        f"- {limpa_html(a['conteudo'])[:400]}" for a in artigos
    ]
    artigos_str = "\n".join(artigos_resumo)
    lista_sistemas = "Sistemas existentes: " + ", ".join(SISTEMAS)
    lista_documentos = "Documentos fiscais possíveis: " + ", ".join(DOCUMENTOS)
    historico_str = ""
    if historico:
        for h in historico:
            if isinstance(h, dict) and 'pergunta' in h and 'resposta' in h:
                historico_str += f"- {h['pergunta']} {h['resposta']}\n"
            elif isinstance(h, str):
                historico_str += f"- {h}\n"

    system_prompt = (
        f"Você é um assistente técnico especialista em sistemas de automação comercial.\n"
        f"{lista_sistemas}\n{lista_documentos}\n"
        f"O usuário descreveu: '{duvida_usuario}'.\n"
        f"Ele já respondeu:\n{historico_str}\n\n"
        f"Abaixo estão trechos dos artigos candidatos. Seu objetivo é encontrar a MELHOR PERGUNTA para DIFERENCIAR e ELIMINAR artigos irrelevantes, chegando em apenas um artigo correto:\n"
        f"{artigos_str}\n\n"
        f"Sempre que possível, forneça opções claras na pergunta (ex: múltipla escolha).\n"
        f"Evite perguntas genéricas como 'qual procedimento está descrito?'. Seja objetivo e sempre proponha diferenciação REAL entre os artigos listados.\n"
        f"NUNCA repita perguntas já feitas. Não cite títulos ou IDs de artigos. NÃO peça para o usuário escolher o artigo. Exemplo de perguntas eficazes:\n"
        f"- 'O problema ocorre na transmissão, importação ou impressão do documento?'\n"
        f"- 'A empresa é optante pelo Simples Nacional?'\n"
        f"- 'O erro aparece em NF-e ou NFC-e?'\n"
        f"Seu objetivo é que a resposta do usuário elimine o máximo de artigos possíveis."
    )

    user_prompt = (
        "Com base nos artigos acima e no histórico, qual pergunta você faria agora para diferenciar essas situações?"
    )

    response = openai.chat.completions.create(
        model="gpt-4o",
        messages=[
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_prompt},
        ],
        temperature=0.2,
        max_tokens=120,
    )
    return response.choices[0].message.content.strip()

def consultar_chat(pergunta, historico=None):
    historico_formatado = historico if historico else []
    respostas = {}

    # Só faz saudação se for a PRIMEIRA interação e a entrada for saudação
    if (not historico_formatado or len(historico_formatado) == 0) and eh_saudacao(pergunta):
        return {
            "finalizado": False,
            "pergunta": "Olá! Como posso ajudar você hoje? Por favor, descreva sua dúvida ou situação."
        }

    # FINALIZAÇÃO (encerramento)
    if eh_resolvido(pergunta):
        return {
            "finalizado": True,
            "artigo": {
                "id": None,
                "titulo": "Atendimento finalizado",
                "resumo": "Que bom que conseguimos resolver! Se precisar de mais alguma coisa, estou à disposição.",
                "link": ""
            }
        }

    respostas["duvida"] = pergunta

    # Checa perguntas já feitas
    perguntas_feitas = set()
    if historico_formatado:
        for h in historico_formatado:
            if isinstance(h, dict) and 'pergunta' in h:
                perguntas_feitas.add(h['pergunta'].strip().lower())

    contexto = " ".join(
        [pergunta] + [h['resposta'] if isinstance(h, dict) and 'resposta' in h else "" for h in historico_formatado]
    )
    top_resultados = busca_artigos(contexto, top_n=5)

    if len(top_resultados) == 1 or (top_resultados[0]['score'] - top_resultados[1]['score'] > 0.10):
        artigo = top_resultados[0]
        return {
            "finalizado": True,
            "artigo": {
                "id": artigo['id'],
                "titulo": artigo['titulo'],
                "resumo": limpa_html(artigo['conteudo'])[:500],
                "link": f"https://suporte.clipp.com.br/artigos/{artigo['id']}"
            }
        }

    pergunta_ia = gerar_pergunta_diferenciadora(
        pergunta, top_resultados, historico_formatado
    )

    # NÃO repete perguntas
    if pergunta_ia.strip().lower() in perguntas_feitas:
        return {
            "finalizado": False,
            "pergunta": "Já pedi essa informação anteriormente. Por favor, tente detalhar de outra forma ou descreva um novo aspecto do problema."
        }
    return {
        "finalizado": False,
        "pergunta": pergunta_ia
    }

# CLI opcional para testes
def main():
    respostas = {}
    pergunta_idx = 0
    historico = []

    respostas["duvida"] = input("Digite sua dúvida: ").strip()

    while True:
        contexto = " ".join([v for v in respostas.values() if v])
        top_resultados = busca_artigos(contexto, top_n=5)

        print("\nDEBUG: Artigos encontrados nesta rodada:")
        for idx, artigo in enumerate(top_resultados):
            print(f"  [{idx+1}] ID: {artigo['id']} | Título: {artigo['titulo']} | Score: {artigo['score']:.4f}")
        print("-" * 60)

        if len(top_resultados) == 1 or (top_resultados[0]['score'] - top_resultados[1]['score'] > 0.10):
            artigo = top_resultados[0]
            print("\nArtigo encontrado:")
            print(f"Título: {artigo['titulo']}")
            print(f"Resumo: {limpa_html(artigo['conteudo'])[:500]}...")
            print(f"Link: https://suporte.clipp.com.br/artigos/{artigo['id']}")
            break

        pergunta = gerar_pergunta_diferenciadora(
            respostas["duvida"], top_resultados, historico
        )
        print("\nPreciso de mais detalhes para identificar o artigo certo.")
        print(pergunta)
        resposta = input("Sua resposta: ").strip()
        historico.append({"pergunta": pergunta, "resposta": resposta})
        respostas[f"triagem_{pergunta_idx}"] = resposta
        pergunta_idx += 1

if __name__ == "__main__":
    main()
