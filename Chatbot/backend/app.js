const express = require('express');
const multer  = require('multer');
const fs = require('fs');
const cors = require('cors');
const pdfParse = require('pdf-parse');
const csvParse = require('csv-parse/sync');
const path = require('path');
const stringSimilarity = require('string-similarity');
require('dotenv').config();

const app = express();
app.use(cors());
app.use(express.json());

const storage = multer.diskStorage({
  destination: function (req, file, cb) {
    cb(null, 'uploads/');
  },
  filename: function (req, file, cb) {
    cb(null, file.originalname);
  }
});
const upload = multer({ storage: storage });

// Gemini
const { GoogleGenerativeAI } = require('@google/generative-ai');
const genAI = new GoogleGenerativeAI(process.env.GEMINI_API_KEY);

// OpenAI v4.x
const OpenAI = require('openai');
const openai = new OpenAI({
  apiKey: process.env.OPENAI_API_KEY
});

let ultimaPerguntaTriagem = '';

async function gerarRespostaOpenAI(prompt) {
  try {
    const completion = await openai.chat.completions.create({
      model: "gpt-3.5-turbo", // use "gpt-4o" se preferir (e puder)
      messages: [{ role: "system", content: prompt }],
      max_tokens: 800,
      temperature: 0.2
    });
    return completion.choices[0].message.content.trim();
  } catch (error) {
    console.error('Erro OpenAI:', error?.response?.data || error.message);
    return "Não foi possível consultar a IA reserva (OpenAI) no momento.";
  }
}

function fuzzyMatch(target, input) {
  if (!input) return true;
  target = (target || '').normalize("NFD").replace(/[\u0300-\u036f]/g, '').toLowerCase();
  input  = (input  || '').normalize("NFD").replace(/[\u0300-\u036f]/g, '').toLowerCase();
  return (
    target.includes(input) ||
    input.includes(target) ||
    stringSimilarity.compareTwoStrings(target, input) > 0.3
  );
}

function carregarArtigosCSV(caminhoCSV) {
  const csvData = fs.readFileSync(caminhoCSV, 'utf8');
  const records = csvParse.parse(csvData, {
    columns: true,
    skip_empty_lines: true,
    delimiter: ',',
    relax_column_count: true,
    relax_quotes: true,
    trim: true
  });
  return records;
}

function buscarArtigosMaisRelevantes(pergunta, refinamentos, pastaUploads) {
  const arquivosCSV = fs.readdirSync(pastaUploads).filter(f => f.endsWith('.csv'));
  let artigosMatchCompleto = [];
  const perguntaLC = pergunta.toLowerCase().trim();
  const tokensPergunta = perguntaLC.split(/\s+/).filter(Boolean);
  let sistemaSelecionado = refinamentos && refinamentos.sistema ? refinamentos.sistema : null;
  let categoriaSelecionada = refinamentos && refinamentos.categoria ? refinamentos.categoria : null;
  arquivosCSV.forEach(csv => {
    let artigos;
    try {
      artigos = carregarArtigosCSV(path.join(pastaUploads, csv));
    } catch (e) {
      return;
    }
    artigos.forEach(a => {
      const titulo = (a['Título'] || a['titulo'] || a['TITULO'] || "").toLowerCase();
      const sistema = (a['Versão / Sistema'] || a['Sistema'] || "").toLowerCase();
      const categoria = (a['Categoria'] || "").toLowerCase();
      const allTokensMatch = tokensPergunta.every(token => titulo.includes(token));
      const sistemaOk = fuzzyMatch(sistema, sistemaSelecionado);
      const categoriaOk = fuzzyMatch(categoria, categoriaSelecionada);
      if (allTokensMatch && sistemaOk && categoriaOk && tokensPergunta.length > 0) {
        artigosMatchCompleto.push({ artigo: a, arquivo: csv });
      }
    });
  });
  artigosMatchCompleto.sort((a, b) => a.artigo['Título'].length - b.artigo['Título'].length);
  return artigosMatchCompleto;
}

function buscarArtigoMaisSimilarFuzzy(pergunta, refinamentos, pastaUploads) {
  const arquivosCSV = fs.readdirSync(pastaUploads).filter(f => f.endsWith('.csv'));
  let melhorArtigo = null;
  let melhorScore = 0;
  let melhorArquivo = null;
  let sistemaSelecionado = refinamentos && refinamentos.sistema ? refinamentos.sistema : null;
  let categoriaSelecionada = refinamentos && refinamentos.categoria ? refinamentos.categoria : null;
  arquivosCSV.forEach(csv => {
    let artigos;
    try {
      artigos = carregarArtigosCSV(path.join(pastaUploads, csv));
    } catch (e) { return; }
    artigos.forEach(a => {
      const titulo = (a['Título'] || a['titulo'] || a['TITULO'] || "").toLowerCase();
      const sistema = (a['Versão / Sistema'] || a['Sistema'] || "").toLowerCase();
      const categoria = (a['Categoria'] || "").toLowerCase();
      const sistemaOk = fuzzyMatch(sistema, sistemaSelecionado);
      const categoriaOk = fuzzyMatch(categoria, categoriaSelecionada);
      if (sistemaOk && categoriaOk) {
        const score = stringSimilarity.compareTwoStrings(
          pergunta.toLowerCase(),
          titulo
        );
        if (score > melhorScore) {
          melhorScore = score;
          melhorArtigo = a;
          melhorArquivo = csv;
        }
      }
    });
  });
  return melhorArtigo ? [{ artigo: melhorArtigo, arquivo: melhorArquivo, score: melhorScore }] : [];
}

app.post('/upload', upload.single('file'), (req, res) => {
  if (!req.file) return res.status(400).json({ error: 'Nenhum arquivo enviado.' });
  res.json({ filename: req.file.filename, original: req.file.originalname });
});

app.get('/artigos-csv', (req, res) => {
  const arquivos = fs.readdirSync('./uploads').filter(f => f.endsWith('.csv'));
  res.json({ arquivos });
});

async function gerarRespostaIA(prompt) {
  // Primeiro tenta Gemini
  try {
    const model = genAI.getGenerativeModel({ model: "gemini-1.5-flash" });
    const result = await model.generateContent(prompt);
    const response = await result.response;
    return response.text().trim();
  } catch (e) {
    if (e.status === 429 || (e.message && e.message.includes('Too Many Requests'))) {
      console.log('Limite do Gemini atingido, tentando OpenAI...');
      return await gerarRespostaOpenAI(prompt);
    } else {
      throw e;
    }
  }
}

app.post('/chat', async (req, res) => {
  let { question, refinamentos = {}, artigoEscolhido, triagemResposta } = req.body;
  if (!question) return res.status(400).json({ error: 'Pergunta não enviada.' });

  // --- DETECÇÃO AUTOMÁTICA DE SISTEMA ---
  if (!refinamentos.sistema) {
    const arquivosCSV = fs.readdirSync('./uploads').filter(f => f.endsWith('.csv'));
    let sistemasDisponiveis = [];
    arquivosCSV.forEach(csv => {
      let artigos;
      try {
        artigos = carregarArtigosCSV(path.join('./uploads', csv));
      } catch (e) { return; }
      artigos.forEach(a => {
        const sistema = (a['Versão / Sistema'] || a['Sistema'] || "").toLowerCase();
        if (sistema && !sistemasDisponiveis.includes(sistema)) {
          sistemasDisponiveis.push(sistema);
        }
      });
    });
    const matchesSistema = sistemasDisponiveis.filter(sis =>
      fuzzyMatch(question, sis)
    );
    if (matchesSistema.length === 1) {
      refinamentos.sistema = matchesSistema[0];
    }
  }

  // --- Triagem aprimorada: match único no título tem prioridade ---
  if (triagemResposta && Array.isArray(triagemResposta.artigos)) {
    const ans = (triagemResposta.resposta || '').toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, '');
    let matchesTitulo = [];
    let matchesConteudo = [];
    for (let i = 0; i < triagemResposta.artigos.length; i++) {
      const artigo = triagemResposta.artigos[i];
      const titulo = (artigo.titulo || '').toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, '');
      if (
        titulo.includes(ans) ||
        ans.includes(titulo) ||
        stringSimilarity.compareTwoStrings(titulo, ans) > 0.3
      ) {
        matchesTitulo.push(i);
      }
    }
    if (matchesTitulo.length === 1) {
      artigoEscolhido = matchesTitulo[0];
    } else if (matchesTitulo.length === 0) {
      for (let i = 0; i < triagemResposta.artigos.length; i++) {
        const artigo = triagemResposta.artigos[i];
        const pdfPath = path.join('./uploads', `Artigo_${artigo.id}.pdf`);
        if (fs.existsSync(pdfPath)) {
          const pdf = await pdfParse(fs.readFileSync(pdfPath));
          const pdfText = (pdf.text || '').toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, '');
          if (
            pdfText.includes(ans) ||
            ans.includes(pdfText) ||
            stringSimilarity.compareTwoStrings(pdfText, ans) > 0.3
          ) {
            matchesConteudo.push(i);
          }
        }
      }
      if (matchesConteudo.length === 1) {
        artigoEscolhido = matchesConteudo[0];
      } else if ((triagemResposta.turnos || 0) >= 2) {
        const opcoes = triagemResposta.artigos.map((a, idx) =>
          `${idx + 1}. ${a.titulo}`
        ).join('\n');
        return res.json({
          answer: `Não consegui identificar com precisão o artigo desejado. Por favor, escolha o artigo abaixo que mais se encaixa:\n\n${opcoes}`,
          refinement: "choose",
          artigos: triagemResposta.artigos
        });
      }
    }
  }

  // --- Artigo final escolhido
  if (typeof artigoEscolhido === 'number') {
    let artigosPossiveis = buscarArtigosMaisRelevantes(question, refinamentos, './uploads');
    if (artigosPossiveis.length === 0) {
      artigosPossiveis = buscarArtigoMaisSimilarFuzzy(question, refinamentos, './uploads');
    }
    const resultado = artigosPossiveis[artigoEscolhido];
    if (resultado) {
      let artigoId = (resultado.artigo['ID'] || resultado.artigo['﻿ID'] || resultado.artigo['id'] || resultado.artigo['Id'] || "").toString().replace(/\D/g, '');
      const filename = `Artigo_${artigoId}.pdf`;
      const pathPDF = path.join('./uploads', filename);
      let context = '';
      if (fs.existsSync(pathPDF)) {
        try {
          const dataBuffer = fs.readFileSync(pathPDF);
          const data = await pdfParse(dataBuffer);
          context = data.text;
        } catch (e) {
          context = `[Erro ao ler PDF ${filename}]`;
        }
      } else {
        context = "Artigo encontrado na planilha, mas PDF não localizado.";
      }
      const prompt = `
Você é um assistente técnico. Sempre que o usuário falar algo, primeiro avalie:
Se for claramente uma saudação simples (ex: 'bom dia', 'boa tarde', 'oi', 'olá') ou agradecimento, apenas cumprimente de volta e convide o usuário a digitar sua dúvida.
Se a mensagem do usuário for qualquer outra coisa — mesmo que seja só o nome de um sistema, um erro, um termo técnico ou um teste — tente ajudar normalmente, iniciando a triagem ou busca na base de conhecimento.
Nunca fique preso em respostas sociais, a não ser que a mensagem seja EXPLICITAMENTE uma saudação/agradecimento/despedida, sem nenhum outro conteúdo.

Baseando-se apenas no conteúdo abaixo, responda a dúvida do usuário (se houver). Se não souber, diga 'Não encontrado na base de conhecimento'.

${context}

Pergunta do usuário: ${question}
      `;
      try {
        let answer = await gerarRespostaIA(prompt);

        if (
          /bom dia|boa tarde|boa noite|olá|oi|como posso ajudar/i.test(answer) &&
          !/base de conhecimento|não encontrado/i.test(answer)
        ) {
          return res.json({ answer, refinement: false });
        }
        if (artigoId && fs.existsSync(path.join('./uploads', `Artigo_${artigoId}.pdf`))) {
          answer += `\n\nTítulo do artigo encontrado: ${resultado.artigo['Título'] || resultado.artigo['titulo']}`;
          answer += `\nArtigo: https://suporte.clipp.com.br/artigos/${artigoId}`;
        }
        return res.json({ answer, refinement: false });
      } catch (e) {
        console.error(e);
        return res.status(500).json({ error: "Erro ao consultar IA: " + e.message });
      }
    } else {
      return res.json({ answer: "Artigo não encontrado.", refinement: false });
    }
  }

  // Busca normal
  let artigosPossiveis = buscarArtigosMaisRelevantes(question, refinamentos, './uploads');
  if (artigosPossiveis.length === 0) {
    artigosPossiveis = buscarArtigoMaisSimilarFuzzy(question, refinamentos, './uploads');
  }

  if (artigosPossiveis.length === 1) {
    const resultado = artigosPossiveis[0];
    let artigoId = (resultado.artigo['ID'] || resultado.artigo['﻿ID'] || resultado.artigo['id'] || resultado.artigo['Id'] || "").toString().replace(/\D/g, '');
    const filename = `Artigo_${artigoId}.pdf`;
    const pathPDF = path.join('./uploads', filename);
    let context = '';
    if (fs.existsSync(pathPDF)) {
      try {
        const dataBuffer = fs.readFileSync(pathPDF);
        const data = await pdfParse(dataBuffer);
        context = data.text;
      } catch (e) {
        context = `[Erro ao ler PDF ${filename}]`;
      }
    } else {
      context = "Artigo encontrado na planilha, mas PDF não localizado.";
    }
    const prompt = `
Você é um assistente técnico. Sempre que o usuário falar algo, primeiro avalie:
Se for claramente uma saudação simples (ex: 'bom dia', 'boa tarde', 'oi', 'olá') ou agradecimento, apenas cumprimente de volta e convide o usuário a digitar sua dúvida.
Se a mensagem do usuário for qualquer outra coisa — mesmo que seja só o nome de um sistema, um erro, um termo técnico ou um teste — tente ajudar normalmente, iniciando a triagem ou busca na base de conhecimento.
Nunca fique preso em respostas sociais, a não ser que a mensagem seja EXPLICITAMENTE uma saudação/agradecimento/despedida, sem nenhum outro conteúdo.

Baseando-se apenas no conteúdo abaixo, responda a dúvida do usuário (se houver). Se não souber, diga 'Não encontrado na base de conhecimento'.

${context}

Pergunta do usuário: ${question}
    `;
    try {
      let answer = await gerarRespostaIA(prompt);

      if (
        /bom dia|boa tarde|boa noite|olá|oi|como posso ajudar/i.test(answer) &&
        !/base de conhecimento|não encontrado/i.test(answer)
      ) {
        return res.json({ answer, refinement: false });
      }
      if (artigoId && fs.existsSync(path.join('./uploads', `Artigo_${artigoId}.pdf`))) {
        answer += `\n\nTítulo do artigo encontrado: ${resultado.artigo['Título'] || resultado.artigo['titulo']}`;
        answer += `\nArtigo: https://suporte.clipp.com.br/artigos/${artigoId}`;
      }
      return res.json({ answer, refinement: false });
    } catch (e) {
      console.error(e);
      return res.status(500).json({ error: "Erro ao consultar IA: " + e.message });
    }
  }

  // Triagem automática: IA faz pergunta diferenciadora
  if (artigosPossiveis.length > 1 && artigosPossiveis.length <= 3) {
    const artigosDetalhados = [];
    for (const a of artigosPossiveis) {
      let artigoId = (a.artigo['ID'] || a.artigo['﻿ID'] || a.artigo['id'] || a.artigo['Id'] || "").toString().replace(/\D/g, '');
      const filename = `Artigo_${artigoId}.pdf`;
      const pathPDF = path.join('./uploads', filename);
      let context = '';
      if (fs.existsSync(pathPDF)) {
        try {
          const dataBuffer = fs.readFileSync(pathPDF);
          const data = await pdfParse(dataBuffer);
          context = data.text;
        } catch (e) {
          context = '';
        }
      }
      artigosDetalhados.push({
        titulo: a.artigo['Título'] || a.artigo['titulo'],
        id: artigoId,
        texto: context
      });
    }
    const prompt = `
Você é um assistente técnico. Sempre que o usuário falar algo, primeiro avalie:
Se for claramente uma saudação simples (ex: 'bom dia', 'boa tarde', 'oi', 'olá') ou agradecimento, apenas cumprimente de volta e convide o usuário a digitar sua dúvida.
Se a mensagem do usuário for qualquer outra coisa — mesmo que seja só o nome de um sistema, um erro, um termo técnico ou um teste — tente ajudar normalmente, iniciando a triagem ou busca na base de conhecimento.
Nunca fique preso em respostas sociais, a não ser que a mensagem seja EXPLICITAMENTE uma saudação/agradecimento/despedida, sem nenhum outro conteúdo.

Agora, analise as opções de artigos abaixo e gere uma pergunta objetiva para o usuário decidir qual caso se aplica ao cenário dele.

Artigo 1: ${artigosDetalhados[0].titulo}\n${artigosDetalhados[0].texto.substring(0, 600)}
Artigo 2: ${artigosDetalhados[1].titulo}\n${artigosDetalhados[1].texto.substring(0, 600)}
${artigosDetalhados[2] ? `Artigo 3: ${artigosDetalhados[2].titulo}\n${artigosDetalhados[2].texto.substring(0, 600)}` : ''}

Pergunta do usuário: ${question}
    `;
    try {
      let pergunta = await gerarRespostaIA(prompt);

      if (
        /bom dia|boa tarde|boa noite|olá|oi|como posso ajudar/i.test(pergunta) &&
        !/base de conhecimento|não encontrado/i.test(pergunta)
      ) {
        return res.json({ answer: pergunta, refinement: false });
      }

      if (pergunta !== ultimaPerguntaTriagem) {
        ultimaPerguntaTriagem = pergunta;
        return res.json({
          answer: pergunta,
          refinement: "triagem",
          artigos: artigosPossiveis.map((a, idx) => ({
            index: idx + 1,
            titulo: a.artigo['Título'] || a.artigo['titulo'],
            id: a.artigo['ID'] || a.artigo['id'] || a.artigo['﻿ID'],
          })),
        });
      } else {
        const opcoes = artigosPossiveis.map((a, idx) =>
          `${idx + 1}. ${a.artigo['Título'] || a.artigo['titulo']}`
        ).join('\n');
        return res.json({
          answer: `Encontrei os seguintes artigos. Por favor, responda com o número do artigo desejado:\n\n${opcoes}`,
          refinement: "choose",
          artigos: artigosPossiveis.map((a, idx) => ({
            index: idx + 1,
            titulo: a.artigo['Título'] || a.artigo['titulo'],
            id: a.artigo['ID'] || a.artigo['id'] || a.artigo['﻿ID'],
          })),
        });
      }
    } catch (e) {
      const opcoes = artigosPossiveis.map((a, idx) =>
        `${idx + 1}. ${a.artigo['Título'] || a.artigo['titulo']}`
      ).join('\n');
      return res.json({
        answer: `Encontrei os seguintes artigos. Por favor, responda com o número do artigo desejado:\n\n${opcoes}`,
        refinement: "choose",
        artigos: artigosPossiveis.map((a, idx) => ({
          index: idx + 1,
          titulo: a.artigo['Título'] || a.artigo['titulo'],
          id: a.artigo['ID'] || a.artigo['id'] || a.artigo['﻿ID'],
        })),
      });
    }
  }

  // Refinamento dinâmico: só pergunta o que falta
  const sistemas = [...new Set(artigosPossiveis.map(a => a.artigo['Versão / Sistema'] || a.artigo['Sistema']).filter(Boolean))];
  const categorias = [...new Set(artigosPossiveis.map(a => a.artigo['Categoria']).filter(Boolean))];

  if (!refinamentos.sistema && sistemas.length > 1) {
    return res.json({
      answer: `Encontrei mais de um artigo possível para sua dúvida. Por favor, informe qual sistema está utilizando: ${sistemas.join(', ')}.`,
      refinement: true,
      opcoes: { sistemas }
    });
  }
  if (refinamentos.sistema && !refinamentos.categoria && !refinamentos.perguntaAbertaRespondida) {
    refinamentos.perguntaAbertaRespondida = true;
    return res.json({
      answer: `Qual sua dúvida ou situação referente ao sistema ${refinamentos.sistema.charAt(0).toUpperCase() + refinamentos.sistema.slice(1)}?`,
      refinement: true,
      opcoes: {}
    });
  }
  if (!refinamentos.categoria && categorias.length > 1) {
    return res.json({
      answer: `Encontrei mais de um artigo possível para sua dúvida. Qual o tipo de documento fiscal? (${categorias.join(', ')})`,
      refinement: true,
      opcoes: { categorias }
    });
  }

  if (artigosPossiveis.length > 1 && artigosPossiveis.length <= 5) {
    const opcoes = artigosPossiveis.map((a, idx) =>
      `${idx + 1}. ${a.artigo['Título'] || a.artigo['titulo']}`
    ).join('\n');
    return res.json({
      answer:
        `Encontrei os seguintes artigos. Por favor, responda com o número do artigo desejado:\n\n${opcoes}`,
      refinement: "choose",
      artigos: artigosPossiveis.map((a, idx) => ({
        index: idx + 1,
        titulo: a.artigo['Título'] || a.artigo['titulo'],
        id: a.artigo['ID'] || a.artigo['id'] || a.artigo['﻿ID'],
      })),
    });
  }

  return res.json({ answer: "Não encontrado na base de conhecimento.", refinement: false });
});

if (!fs.existsSync('./uploads')) fs.mkdirSync('./uploads');

const PORT = process.env.PORT || 3001;
app.listen(PORT, () => console.log('Backend Gemini/OpenAI rodando na porta ' + PORT));
