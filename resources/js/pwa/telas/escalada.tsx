import { useState } from 'react';

import { irPara, useApp } from '../app';
import { Aviso, Topo, Vazio, classes } from '../componentes';
import { FISCAL, MOTIVOS_DOCUMENTO, nomeRegiao } from '../dados-prototipo';
import { Icone } from '../icones';

/* ============================================================================
   A ESCALADA — o caminho excepcional.
   ----------------------------------------------------------------------------
   Aqui se identifica a pessoa e se emite documento (NT ou AI). É de propósito
   que esta tela seja a segunda, e não a primeira: a fiscalização é educativa, e
   o registro rápido já resolveu a esmagadora maioria dos casos. Chega-se aqui
   quando o ambulante insiste em permanecer, quando reincide, ou quando ele
   mesmo pede o papel.
   ============================================================================ */

export function TelaEscalada({ id }: { id: string | null }) {
    const { registros, anexarDocumento } = useApp();
    const registro = registros.find((r) => r.id === id);

    const [nome, setNome] = useState('');
    const [apelido, setApelido] = useState(registro?.ambulante ?? '');
    const [documentoPessoal, setDocumentoPessoal] = useState('');
    const [temFoto, setTemFoto] = useState(false);
    const [tipo, setTipo] = useState(MOTIVOS_DOCUMENTO[0]);
    const [emitido, setEmitido] = useState<string | null>(registro?.documento ?? null);

    if (!registro) {
        return (
            <div className="pw-tela">
                <Topo titulo="Escalada" aoVoltar={() => irPara('mapa')} />
                <div className="pw-corpo">
                    <Vazio
                        icone="📄"
                        titulo="Registro não encontrado"
                        texto="Abra um registro pela lista e escolha a escalada por lá."
                    />
                </div>
            </div>
        );
    }

    const numero = `${tipo.sigla} 2026/${String(500 + registros.length).padStart(4, '0')}`;

    const emitir = () => {
        anexarDocumento(registro.id, numero, apelido.trim() || nome.trim() || null);
        setEmitido(numero);
    };

    return (
        <div className="pw-tela">
            <Topo
                titulo="Identificação e documento"
                subtitulo="Caminho excepcional"
                aoVoltar={() => irPara(`recibo/${registro.id}`)}
            />

            <div className="pw-corpo">
                <Aviso>
                    Esta etapa não é o padrão: use só quando o ambulante insistir em permanecer, quando
                    houver reincidência ou quando ele pedir o documento.
                </Aviso>

                <p className="pw-titulo-secao">Quem foi abordado</p>

                <div className="pw-card">
                    <label className="pw-campo">
                        <span>Nome</span>
                        <input
                            className="pw-entrada"
                            value={nome}
                            onChange={(e) => setNome(e.target.value)}
                            placeholder="Como consta no documento"
                        />
                    </label>

                    <label className="pw-campo">
                        <span>Apelido pelo qual é conhecido no ponto</span>
                        <input
                            className="pw-entrada"
                            value={apelido}
                            onChange={(e) => setApelido(e.target.value)}
                            placeholder="Como o ponto o chama"
                        />
                    </label>

                    <label className="pw-campo">
                        <span>CPF ou CNPJ — opcional</span>
                        <input
                            className="pw-entrada"
                            value={documentoPessoal}
                            onChange={(e) => setDocumentoPessoal(e.target.value)}
                            placeholder="Deixe em branco se não apresentou"
                        />
                    </label>

                    <button
                        type="button"
                        className={classes('pw-btn', temFoto ? 'pw-btn-contorno' : 'pw-btn-fantasma')}
                        onClick={() => setTemFoto((f) => !f)}
                    >
                        <Icone nome={temFoto ? 'certo' : 'camera'} tamanho={18} />
                        {temFoto ? 'Foto do rosto capturada' : 'Fotografar o rosto'}
                    </button>
                </div>

                <p className="pw-titulo-secao">Documento a emitir</p>

                <div className="pw-duas-colunas">
                    {MOTIVOS_DOCUMENTO.map((m) => (
                        <button
                            key={m.id}
                            type="button"
                            className="pw-card pw-card-toque"
                            style={{
                                borderColor: m.id === tipo.id ? 'var(--pw-primaria)' : undefined,
                                borderWidth: m.id === tipo.id ? 2 : 1,
                                background: m.id === tipo.id ? 'var(--pw-primaria-suave)' : undefined,
                            }}
                            onClick={() => setTipo(m)}
                        >
                            <p className="pw-forte" style={{ margin: 0 }}>
                                {m.sigla} · {m.titulo}
                            </p>
                            <p className="pw-fraco" style={{ margin: '4px 0 0' }}>
                                {m.descricao}
                            </p>
                        </button>
                    ))}
                </div>

                <p className="pw-titulo-secao">Prévia do documento</p>

                <div className="pw-card" style={{ fontSize: 14, lineHeight: 1.6 }}>
                    <p className="pw-forte" style={{ margin: 0, fontSize: 15 }}>
                        PREFEITURA MUNICIPAL DO SALVADOR · SEMOP
                    </p>
                    <p className="pw-fraco" style={{ margin: '0 0 12px' }}>
                        {tipo.titulo} — {emitido ?? numero}
                    </p>
                    <p style={{ margin: 0 }}>
                        Aos {registro.dataBr}, às {registro.hora}, na {registro.endereco} —{' '}
                        {nomeRegiao(registro.regiao)}, o agente {FISCAL.nome} (matrícula {FISCAL.matricula})
                        realizou fiscalização no ponto de comércio ambulante, tendo identificado{' '}
                        {nome.trim() || apelido.trim() || 'pessoa não identificada'}
                        {documentoPessoal.trim() ? `, documento ${documentoPessoal.trim()}` : ''}.
                    </p>
                    <p style={{ margin: '10px 0 0' }}>
                        {registro.relato ||
                            'Ocorrência registrada no local, com orientação sobre as regras de ocupação da via.'}
                    </p>
                    <p className="pw-fraco" style={{ margin: '14px 0 0' }}>
                        Documento de demonstração — sem valor legal.
                    </p>
                </div>

                {emitido ? (
                    <div className="pw-card" style={{ marginTop: 16, borderColor: 'var(--pw-ok)' }}>
                        <p className="pw-forte" style={{ margin: 0 }}>
                            <Icone nome="certo" tamanho={16} /> {emitido} anexado ao registro{' '}
                            {registro.protocolo}
                        </p>
                        <p className="pw-fraco" style={{ margin: '4px 0 12px' }}>
                            A via do ambulante sai no envio, junto com o registro.
                        </p>
                        <button
                            type="button"
                            className="pw-btn pw-btn-contorno"
                            onClick={() => irPara(`recibo/${registro.id}`)}
                        >
                            Voltar ao recibo
                        </button>
                    </div>
                ) : (
                    <button
                        type="button"
                        className="pw-btn pw-btn-acao"
                        style={{ marginTop: 16, minHeight: 58 }}
                        onClick={emitir}
                    >
                        <Icone nome="documento" tamanho={20} />
                        Emitir {tipo.sigla}
                    </button>
                )}
            </div>
        </div>
    );
}
