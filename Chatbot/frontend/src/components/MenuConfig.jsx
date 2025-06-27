import React, { useState, useEffect } from 'react';
import axios from 'axios';

export default function MenuConfig({ onClose }) {
  const [tab, setTab] = useState('ia');
  const [instrucoes, setInstrucoes] = useState('');
  const [msg, setMsg] = useState('');

  useEffect(() => {
    axios.get('http://localhost:3001/instrucoes')
      .then(res => setInstrucoes(res.data.instrucoes || ''))
      .catch(() => {});
  }, []);

  function salvar() {
    axios.post('http://localhost:3001/instrucoes', { instrucoes })
      .then(() => setMsg("Instruções salvas com sucesso!"))
      .catch(() => setMsg("Erro ao salvar."));
  }

  return (
    <div style={{
      position: 'fixed',
      top: 0, left: 0, right: 0, bottom: 0,
      zIndex: 999999,
      background: 'rgba(8,16,24,0.78)',
      display: 'flex', alignItems: 'center', justifyContent: 'center'
    }}>
      <div style={{
        width: 500, minHeight: 400, background: '#fff', borderRadius: 16,
        boxShadow: '0 4px 40px #00336633', border: '3px solid #fff',
        display: 'flex', flexDirection: 'column', position: 'relative'
      }}>
        <button onClick={onClose} style={{
          position: "absolute", top: 14, right: 18, background: "none", border: "none", fontSize: 28, color: "#003366", cursor: "pointer"
        }}>×</button>
        <div style={{ display: "flex", borderBottom: "1px solid #eee" }}>
          <button onClick={() => setTab('ia')}
            style={{ flex: 1, padding: 14, border: 'none', borderBottom: tab === 'ia' ? '3px solid #2675C7' : 'none', background: 'none', fontWeight: tab === 'ia' ? 700 : 400, color: "#003366", fontSize: 17, cursor: "pointer" }}>Instruções da IA</button>
          <button onClick={() => setTab('geral')}
            style={{ flex: 1, padding: 14, border: 'none', borderBottom: tab === 'geral' ? '3px solid #2675C7' : 'none', background: 'none', fontWeight: tab === 'geral' ? 700 : 400, color: "#003366", fontSize: 17, cursor: "pointer" }}>Geral</button>
        </div>
        <div style={{ flex: 1, padding: 24 }}>
          {tab === 'ia' && (
            <div>
              <div style={{ fontWeight: 600, marginBottom: 8, fontSize: 16 }}>Instruções globais da IA</div>
              <textarea rows={10} value={instrucoes}
                onChange={e => setInstrucoes(e.target.value)}
                style={{ width: "100%", fontSize: 15, borderRadius: 8, border: "1px solid #e0e6ed", padding: 12, background: "#f8fafc", color: "#003366", marginBottom: 18 }} />
              <button onClick={salvar} style={{
                background: "#2675C7", color: "#fff", border: "none", padding: "8px 24px", borderRadius: 9, fontWeight: 600, fontSize: 16, cursor: "pointer"
              }}>Salvar instruções</button>
              <div style={{ minHeight: 24, color: "#2675C7", fontWeight: 500, marginTop: 8 }}>{msg}</div>
            </div>
          )}
          {tab === 'geral' && (
            <div style={{ color: "#555" }}>Aqui futuramente outras configurações gerais.</div>
          )}
        </div>
      </div>
    </div>
  );
}
