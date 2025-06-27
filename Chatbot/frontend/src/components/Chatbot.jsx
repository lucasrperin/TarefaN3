import React, { useState, useRef, useEffect } from 'react';
import axios from 'axios';
import MenuConfig from './MenuConfig';
import './Chatbot.css';

const apiUrl = 'http://localhost:3001/chat';
const BOT_AVATAR = "/logo-zucchetti.png";

export default function ChatBot() {
  const [chat, setChat] = useState([
    { from: 'bot', text: 'Olá! Como posso ajudar? Descreva seu erro ou dúvida.' }
  ]);
  const [input, setInput] = useState('');
  const [waiting, setWaiting] = useState(false);
  const [file, setFile] = useState(null);
  const [showMenu, setShowMenu] = useState(false);

  const refinamentos = useRef({});
  const perguntaOriginal = useRef('');
  const triagem = useRef({ artigos: [], turnos: 0 });
  const bottomRef = useRef();

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
    document.body.style.overflowX = 'hidden';
    return () => { document.body.style.overflowX = ''; };
  }, [chat, waiting]);

  useEffect(() => {
    // Keyframes para animações
    if (!document.getElementById("chatbot-keyframes")) {
      const style = document.createElement("style");
      style.id = "chatbot-keyframes";
      style.innerHTML = `
        @keyframes fadeIn { from { opacity:0; transform:translateY(15px);} to { opacity:1; transform:none; } }
        @keyframes dotty { 0%,80%,100%{opacity:.3;} 40%{opacity:1;} }
      `;
      document.head.appendChild(style);
    }
  }, []);

  async function sendMessage({ text, artigoEscolhido, triagemResposta, fileToSend } = {}) {
    setWaiting(true);

    try {
      let payload = {};

      if (!perguntaOriginal.current && text) {
        perguntaOriginal.current = text;
        refinamentos.current = {};
        triagem.current = { artigos: [], turnos: 0 };
      }
      payload.question = perguntaOriginal.current;

      if (triagemResposta) {
        payload.triagemResposta = triagemResposta;
        payload.refinamentos = { ...refinamentos.current };
      } else if (typeof artigoEscolhido === 'number') {
        payload.artigoEscolhido = artigoEscolhido;
        payload.refinamentos = { ...refinamentos.current };
      } else {
        const lastBotMsg = chat[chat.length - 1];
        if (lastBotMsg && lastBotMsg.refinement && lastBotMsg.opcoes) {
          if (lastBotMsg.opcoes.sistemas) {
            refinamentos.current.sistema = text;
          }
          if (lastBotMsg.opcoes.categorias) {
            refinamentos.current.categoria = text;
          }
        }
        payload.refinamentos = { ...refinamentos.current };
      }

      if (fileToSend) {
        const data = new FormData();
        data.append("file", fileToSend);
        await axios.post('http://localhost:3001/upload', data);
      }

      const response = await axios.post(apiUrl, payload);
      const data = response.data;

      if (
        typeof artigoEscolhido === 'number' ||
        (!data.refinement && chat.length > 0 && chat[chat.length - 1].refinement)
      ) {
        refinamentos.current = {};
        perguntaOriginal.current = '';
        triagem.current = { artigos: [], turnos: 0 };
      }

      if (data.refinement === 'triagem' && data.artigos) {
        triagem.current.artigos = data.artigos;
        triagem.current.turnos = (triagem.current.turnos || 0) + 1;
      }

      setChat(c => [
        ...c,
        { from: 'bot', text: data.answer, refinement: data.refinement, opcoes: data.opcoes, artigos: data.artigos }
      ]);
    } catch (e) {
      setChat(c => [...c, { from: 'bot', text: 'Erro ao consultar IA.' }]);
    }
    setWaiting(false);
  }

  function handleSend(e) {
    e.preventDefault();
    if (!input.trim() && !file) return;

    const lastBotMsg = chat[chat.length - 1];
    if (lastBotMsg && lastBotMsg.refinement === 'triagem' && triagem.current.artigos.length) {
      setChat(c => [...c, { from: 'user', text: input, file: file ? file.name : null }]);
      sendMessage({
        triagemResposta: {
          resposta: input,
          artigos: triagem.current.artigos,
          turnos: triagem.current.turnos || 1
        },
        fileToSend: file
      });
      setInput('');
      setFile(null);
      return;
    }

    setChat(c => [...c, { from: 'user', text: input, file: file ? file.name : null }]);
    sendMessage({ text: input, fileToSend: file });
    setInput('');
    setFile(null);
  }

  function handleEscolhaArtigo(index) {
    setChat(c => [...c, { from: 'user', text: `${index + 1}` }]);
    sendMessage({ artigoEscolhido: index });
  }

  // Digitando...
  const renderTyping = () => (
    <div className="chatbot-message-row bot" style={{ marginBottom: 16 }}>
      <img src={BOT_AVATAR} className="chatbot-avatar" alt="Bot" />
      <div className="chatbot-bubble" style={{
        background: "var(--bot-bubble-light)",
        color: "#fff",
        opacity: .7,
        fontStyle: "italic",
        display: "flex", alignItems: "center"
      }}>
        <span style={{display:"inline-block"}}>Digitando</span>
        <span style={{ display: "inline-flex", gap:1, marginLeft:4 }}>
          <span style={{ animation: "dotty 1s infinite" }}>.</span>
          <span style={{ animation: "dotty 1s infinite .2s" }}>.</span>
          <span style={{ animation: "dotty 1s infinite .4s" }}>.</span>
        </span>
      </div>
    </div>
  );

  return (
    <div className="chatbot-container">
      <header className="chatbot-header">
        <div style={{ display: "flex", alignItems: "center" }}>
          <img src={BOT_AVATAR} className="chatbot-header-logo" alt="Zucchetti" />
          Suporte Zucchetti
        </div>
        <button
          onClick={() => setShowMenu(true)}
          className="chatbot-config-btn"
          title="Configurações"
        >⚙️</button>
      </header>
      <main className="chatbot-main">
        <section className="chatbot-section">
          <div className="chatbot-messages">
            {chat.map((msg, i) => (
              <div
                key={i}
                className={`chatbot-message-row ${msg.from}`}
              >
                {msg.from === "bot" && (
                  <img src={BOT_AVATAR} className="chatbot-avatar" alt="Bot" />
                )}
                <div className="chatbot-bubble">
                  <div style={{ whiteSpace: "pre-line" }}>{msg.text}</div>
                  {msg.file && (
                    <div style={{ fontSize: 13, marginTop: 8, color: "var(--azul-sec)", fontWeight: 600 }}>
                      📎 {msg.file}
                    </div>
                  )}
                  {msg.refinement === 'choose' && msg.artigos && (
                    <div style={{ marginTop: 10 }}>
                      {msg.artigos.map((a, idx) => (
                        <button
                          key={a.id}
                          style={{
                            display: "block",
                            width: "100%",
                            margin: "6px 0",
                            padding: "10px 16px",
                            borderRadius: 12,
                            background: "#fff",
                            color: "var(--texto-azul)",
                            border: `1px solid var(--azul-sec)`,
                            fontWeight: 600,
                            textAlign: "left",
                            cursor: "pointer",
                            fontSize: 16,
                            transition: "all .18s"
                          }}
                          onClick={() => handleEscolhaArtigo(idx)}
                        >
                          {idx + 1}. {a.titulo}
                        </button>
                      ))}
                    </div>
                  )}
                </div>
                {msg.from === "user" && (
                  <div className="chatbot-avatar user">U</div>
                )}
              </div>
            ))}
            {waiting && renderTyping()}
            <div ref={bottomRef} />
          </div>
          <form className="chatbot-input-form" onSubmit={handleSend}>
            <label className="chatbot-input-filelabel" title="Enviar arquivo">
              <input type="file" style={{ display: "none" }} onChange={e => setFile(e.target.files[0])} />
              <span role="img" aria-label="Anexar arquivo">📎</span>
            </label>
            <input
              value={input}
              onChange={e => setInput(e.target.value)}
              disabled={waiting}
              className="chatbot-input-box"
              placeholder="Digite sua dúvida ou envie arquivo..."
            />
            <button
              disabled={waiting || (!input.trim() && !file)}
              className="chatbot-send-btn"
              type="submit"
            >Enviar</button>
          </form>
        </section>
      </main>
      {showMenu && <MenuConfig onClose={() => setShowMenu(false)} />}
    </div>
  );
}
