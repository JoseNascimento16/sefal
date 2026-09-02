import { useMemo, useState } from 'react';

import { irPara, useApp } from '../app';
import { Assinatura, Marcavel, Selo, Topo, Vazio, classes } from '../componentes';
import { acharDemanda } from '../dados-demandas';
import {
    ATIVIDADES_NP,
    BENS_FREQUENTES,
    DESTINACOES_SEGUB,
    FUNDAMENTACAO,
    PRAZOS_SEGUB,
    SEGUB,
    TIPOS_EQUIPAMENTO,
    consumirNumero,
    numeroReservado,
    type ItemApreendido,
} from '../dados-documentos';
import { FISCAL, nomeRegiao, type ViaImpressa } from '../dados-prototipo';
import { Icone } from '../icones';

/* ============================================================================
   AUTO DE APREENSÃO — o documento que o cliente realmente usa.
   ----------------------------------------------------------------------------
   O protótipo anterior chamava esta etapa de "termo de destruição". O bloco de
   papel entregue pelo cliente (exemplar nº 160051) mostra que não é destruição
   nenhuma: é APREENSÃO com GUARDA. Os bens vão para o Setor de Guarda de Bens
   — SEGUB, na Av. San Martins, ficam lá por um prazo, e só depois desse prazo
   se decide o destino (devolução mediante regularização, doação, leilão ou, no
   caso de perecível, destruição). Chamar isso de destruição inverte o
   significado do ato e assusta quem assina.

   O miolo da tela é a DISCRIMINAÇÃO DO MATERIAL. No papel são três linhas em
   branco onde o agente escreve tudo apertado, e é exatamente aí que o
   documento de hoje falha: item ilegível é item que ninguém consegue devolver.
   Aqui a discriminação é uma GRADE — quantidade, unidade, descrição —, com
   atalhos para o que mais aparece, para o agente somar itens de pé sem
   escrever à mão.
   ============================================================================ */

type EstadoAssinatura = 'pendente' | 'assinada' | 'recusada';

export function TelaApreensao({ id }: { id: string | null }) {
    const { registros, anexarDocumento } = useApp();
    const registro = registros.find((r) => r.id === id);
    const demanda = acharDemanda(registro?.demandaId ?? null);

    const [nome, setNome] = useState(registro?.ambulante ?? demanda?.sgci?.nome ?? '');
    const [cpf, setCpf] = useState('');
    const [equipamento, setEquipamento] = useState('');
    const [atividade, setAtividade] = useState(demanda?.sgci?.atividade ?? '');
    const [local, setLocal] = useState(registro?.endereco ?? '');

    const [artigos, setArtigos] = useState(FUNDAMENTACAO.artigosPadrao);
    const [portaria, setPortaria] = useState(FUNDAMENTACAO.portariaPadrao);
    const [decretos, setDecretos] = useState<string[]>([FUNDAMENTACAO.decretos[4]]);

    const [itens, setItens] = useState<ItemApreendido[]>([]);
    const [descricao, setDescricao] = useState('');
    const [quantidade, setQuantidade] = useState(1);

    const [prazo, setPrazo] = useState(PRAZOS_SEGUB[0]);
    const [destinacao, setDestinacao] = useState(DESTINACOES_SEGUB[0]);

    const [notificado, setNotificado] = useState<EstadoAssinatura>('pendente');

    const [lavrado, setLavrado] = useState<string | null>(
        registro?.documentoTipo === 'aa' ? (registro.documento?.replace('AA ', '') ?? null) : null,
    );

    const numero = useMemo(() => lavrado ?? numeroReservado('aa'), [lavrado]);

    if (!registro) {
        return (
            <div className="pw-tela">
                <Topo titulo="Auto de Apreensão" aoVoltar={() => irPara('registros')} />
                <div className="pw-corpo">
                    <Vazio
                        icone="📦"
                        titulo="Registro não encontrado"
                        texto="A apreensão se lavra sobre uma fiscalização já registrada."
                    />
                </div>
            </div>
        );
    }

    const somar = (desc: string, unidade: string, qtd: number) => {
        if (!desc.trim()) {
            return;
        }

        setItens((atuais) => [
            ...atuais,
            {
                id: `item-${Date.now()}-${atuais.length}`,
                quantidade: qtd,
                unidade,
                descricao: desc.trim(),
            },
        ]);
        setDescricao('');
        setQuantidade(1);
    };

    const totalDeItens = itens.reduce((soma, i) => soma + i.quantidade, 0);
    const podeLavrar = nome.trim().length > 2 && itens.length > 0;

    const montarVia = (emitido: string): ViaImpressa => ({
        tipo: 'aa',
        numero: emitido,
        titulo: 'AUTO DE APREENSÃO',
        campos: [
            { rotulo: 'Data / hora', valor: `${registro.dataBr} às ${registro.hora}` },
            { rotulo: 'Sr.(a)', valor: nome.trim() || '—' },
            { rotulo: 'CPF nº', valor: cpf.trim() || '—' },
            { rotulo: 'Equipamento tipo', valor: equipamento.trim() || '—' },
            { rotulo: 'Como atividade', valor: atividade.trim() || '—' },
            { rotulo: 'Localizado na', valor: local.trim() || '—' },
            { rotulo: 'Fundamento', valor: FUNDAMENTACAO.lei },
            { rotulo: 'Decreto(s)', valor: decretos.join('; ') || '—' },
            { rotulo: 'Art(s) nºs', valor: artigos.trim() || '—' },
            { rotulo: 'Portaria nº', valor: portaria.trim() || '—' },
            { rotulo: 'Guarda', valor: `${SEGUB.nome} — ${SEGUB.endereco}` },
            { rotulo: 'Prazo máximo', valor: `${prazo.rotulo} (${prazo.extenso})` },
            { rotulo: 'Após o prazo', valor: destinacao },
            { rotulo: 'Agente fiscal', valor: `${FISCAL.nome} — matrícula ${FISCAL.matricula}` },
        ],
        listas: [
            {
                titulo: 'Discriminação do material / mercadoria apreendido(a)',
                itens: itens.map((i) => `${i.quantidade} ${i.unidade} — ${i.descricao}`),
            },
        ],
        assinaturas: [{ rotulo: 'Notificado', estado: notificado }],
        rodape: `${totalDeItens} volume(s) recolhido(s) e encaminhado(s) ao SEGUB — Av. San Martins, s/n`,
    });

    const lavrar = () => {
        if (!podeLavrar || lavrado) {
            return;
        }

        const emitido = consumirNumero('aa');
        anexarDocumento(registro.id, montarVia(emitido), nome.trim() || null);
        setLavrado(emitido);
        irPara(`via/${registro.id}`);
    };

    const alternarDecreto = (d: string) =>
        setDecretos((atuais) => (atuais.includes(d) ? atuais.filter((x) => x !== d) : [...atuais, d]));

    return (
        <div className="pw-tela">
            <Topo
                titulo="Auto de Apreensão"
                subtitulo={`Nº ${numero}`}
                aoVoltar={() => irPara(`documentos/${registro.id}`)}
            />

            <div className="pw-corpo">
                <div className="pw-card pw-cabeca-documento">
                    <div className="pw-linha-espalha">
                        <div style={{ minWidth: 0 }}>
                            <p className="pw-forte" style={{ margin: 0, fontSize: 12.5, letterSpacing: '0.06em' }}>
                                PREFEITURA MUNICIPAL DE SALVADOR · SEMOP
                            </p>
                            <p className="pw-fraco" style={{ margin: '2px 0 0', fontSize: 12.5 }}>
                                Setor de Fiscalização em Logradouro Público — SEFAL
                            </p>
                        </div>
                        <span className="pw-numero-documento">{numero}</span>
                    </div>

                    <p style={{ margin: '12px 0 0', fontSize: 14, lineHeight: 1.55 }}>
                        Em <span className="pw-forte">{registro.dataBr}</span>, às{' '}
                        <span className="pw-forte">{registro.hora}</span>, a fiscalização municipal
                        procedeu a apreensão do material / mercadoria abaixo discriminado, comercializando
                        irregularmente em poder do(a) notificado(a).
                    </p>
                </div>

                {/* 1 · Quem ------------------------------------------------- */}
                <section className="pw-passo" style={{ marginTop: 22 }}>
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">1</span>
                        <span className="pw-passo-titulo">De quem foi apreendido</span>
                        {demanda?.sgci && <span className="pw-passo-opcional">Do SGCI</span>}
                    </div>

                    <div className="pw-card">
                        <label className="pw-campo">
                            <span>Sr.(a)</span>
                            <input
                                className="pw-entrada"
                                value={nome}
                                onChange={(e) => setNome(e.target.value)}
                                placeholder="Nome completo"
                            />
                        </label>

                        <label className="pw-campo">
                            <span>Portador(a) do CPF nº</span>
                            <input
                                className="pw-entrada"
                                value={cpf}
                                onChange={(e) => setCpf(e.target.value)}
                                placeholder="000.000.000-00"
                                inputMode="numeric"
                            />
                        </label>

                        <label className="pw-campo">
                            <span>Com equipamento tipo</span>
                            <input
                                className="pw-entrada"
                                value={equipamento}
                                onChange={(e) => setEquipamento(e.target.value)}
                                placeholder="Ex.: barraca de chapa"
                            />
                            <div className="pw-chips" style={{ marginTop: 8, gap: 8 }}>
                                {TIPOS_EQUIPAMENTO.map((t) => (
                                    <button
                                        key={t}
                                        type="button"
                                        className={classes('pw-chip', 'pw-chip-mini', equipamento === t && 'pw-chip-ligado')}
                                        onClick={() => setEquipamento(t)}
                                    >
                                        {t}
                                    </button>
                                ))}
                            </div>
                        </label>

                        <label className="pw-campo">
                            <span>Como atividade</span>
                            <input
                                className="pw-entrada"
                                value={atividade}
                                onChange={(e) => setAtividade(e.target.value)}
                                placeholder="Ex.: Comércio Informal"
                            />
                            <div className="pw-chips" style={{ marginTop: 8, gap: 8 }}>
                                {ATIVIDADES_NP.map((a) => (
                                    <button
                                        key={a}
                                        type="button"
                                        className={classes('pw-chip', 'pw-chip-mini', atividade === a && 'pw-chip-ligado')}
                                        onClick={() => setAtividade(a)}
                                    >
                                        {a}
                                    </button>
                                ))}
                            </div>
                        </label>

                        <label className="pw-campo" style={{ marginBottom: 0 }}>
                            <span>Localizado na</span>
                            <input
                                className="pw-entrada"
                                value={local}
                                onChange={(e) => setLocal(e.target.value)}
                                placeholder="Logradouro e ponto de referência"
                            />
                            <p className="pw-fraco" style={{ margin: '6px 0 0', fontSize: 12.5 }}>
                                <Icone nome="alvo" tamanho={12} /> {nomeRegiao(registro.regiao)} ·{' '}
                                {registro.lat.toFixed(5)}, {registro.lng.toFixed(5)}
                            </p>
                        </label>
                    </div>
                </section>

                {/* 2 · Fundamentação -------------------------------------- */}
                <section className="pw-passo">
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">2</span>
                        <span className="pw-passo-titulo">Fundamentação legal</span>
                        <span className="pw-passo-opcional">Vem pronta</span>
                    </div>

                    <div className="pw-card">
                        <div className="pw-linha-espalha" style={{ marginBottom: 10 }}>
                            <span className="pw-forte" style={{ fontSize: 15 }}>
                                {FUNDAMENTACAO.lei}
                            </span>
                            <Selo tom="info">Lei de base</Selo>
                        </div>

                        <p className="pw-fraco" style={{ margin: '0 0 8px', fontSize: 13 }}>
                            Decretos aplicáveis — assinale os que fundamentam o ato:
                        </p>

                        <div className="pw-marcaveis">
                            {FUNDAMENTACAO.decretos.map((d) => (
                                <Marcavel
                                    key={d}
                                    marcado={decretos.includes(d)}
                                    aoAlternar={() => alternarDecreto(d)}
                                >
                                    {d}
                                </Marcavel>
                            ))}
                        </div>

                        <label className="pw-campo" style={{ marginTop: 14 }}>
                            <span>Art(s) nºs</span>
                            <input
                                className="pw-entrada"
                                value={artigos}
                                onChange={(e) => setArtigos(e.target.value)}
                            />
                        </label>

                        <label className="pw-campo" style={{ marginBottom: 0 }}>
                            <span>Portaria nº</span>
                            <input
                                className="pw-entrada"
                                value={portaria}
                                onChange={(e) => setPortaria(e.target.value)}
                            />
                        </label>
                    </div>
                </section>

                {/* 3 · Discriminação ------------------------------------- */}
                <section className="pw-passo">
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">3</span>
                        <span className="pw-passo-titulo">Discriminação do material</span>
                        <span className="pw-passo-opcional">
                            {totalDeItens === 1 ? '1 volume' : `${totalDeItens} volumes`}
                        </span>
                    </div>

                    <div className="pw-card">
                        <div className="pw-linha" style={{ gap: 8, alignItems: 'flex-end' }}>
                            <label className="pw-campo" style={{ marginBottom: 0, width: 88, flex: '0 0 88px' }}>
                                <span>Qtd.</span>
                                <input
                                    className="pw-entrada"
                                    type="number"
                                    min={1}
                                    value={quantidade}
                                    onChange={(e) => setQuantidade(Math.max(1, Number(e.target.value) || 1))}
                                    inputMode="numeric"
                                />
                            </label>
                            <label className="pw-campo" style={{ marginBottom: 0, flex: 1, minWidth: 0 }}>
                                <span>Descrição do bem</span>
                                <input
                                    className="pw-entrada"
                                    value={descricao}
                                    onChange={(e) => setDescricao(e.target.value)}
                                    placeholder="O que está sendo recolhido"
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            somar(descricao, 'un', quantidade);
                                        }
                                    }}
                                />
                            </label>
                        </div>

                        <button
                            type="button"
                            className="pw-btn pw-btn-contorno"
                            style={{ marginTop: 12 }}
                            onClick={() => somar(descricao, 'un', quantidade)}
                        >
                            <Icone nome="mais" tamanho={18} />
                            Somar à lista
                        </button>

                        <p className="pw-fraco" style={{ margin: '14px 0 8px', fontSize: 13 }}>
                            Atalhos do que mais aparece:
                        </p>
                        <div className="pw-chips" style={{ gap: 8 }}>
                            {BENS_FREQUENTES.map((b) => (
                                <button
                                    key={b.descricao}
                                    type="button"
                                    className="pw-chip pw-chip-mini"
                                    onClick={() => somar(b.descricao, b.unidade, 1)}
                                >
                                    <Icone nome="mais" tamanho={13} />
                                    {b.descricao}
                                </button>
                            ))}
                        </div>
                    </div>

                    {itens.length > 0 && (
                        <div className="pw-card" style={{ marginTop: 12 }}>
                            <p className="pw-forte" style={{ margin: '0 0 10px', fontSize: 14.5 }}>
                                {itens.length === 1 ? '1 item apreendido' : `${itens.length} itens apreendidos`}
                            </p>
                            <ul className="pw-lista-limpa">
                                {itens.map((item) => (
                                    <li key={item.id} className="pw-item-bem">
                                        <span className="pw-item-qtd">
                                            {item.quantidade} {item.unidade}
                                        </span>
                                        <span style={{ flex: 1, minWidth: 0 }}>{item.descricao}</span>
                                        <button
                                            type="button"
                                            className="pw-foto-remove pw-remove-linha"
                                            onClick={() => setItens((a) => a.filter((x) => x.id !== item.id))}
                                            aria-label={`Remover ${item.descricao}`}
                                        >
                                            <Icone nome="lixeira" tamanho={14} />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </section>

                {/* 4 · Guarda ------------------------------------------- */}
                <section className="pw-passo">
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">4</span>
                        <span className="pw-passo-titulo">Guarda dos bens</span>
                    </div>

                    <div className="pw-card">
                        <div className="pw-linha" style={{ gap: 10, marginBottom: 12 }}>
                            <span className="pw-doc-icone" aria-hidden="true">
                                📦
                            </span>
                            <div style={{ minWidth: 0 }}>
                                <p className="pw-forte" style={{ margin: 0, fontSize: 15 }}>
                                    {SEGUB.nome}
                                </p>
                                <p className="pw-fraco" style={{ margin: 0 }}>
                                    {SEGUB.endereco}
                                </p>
                            </div>
                        </div>

                        <p className="pw-fraco" style={{ margin: '0 0 8px', fontSize: 13 }}>
                            Prazo máximo de permanência:
                        </p>
                        <div className="pw-periodo">
                            {PRAZOS_SEGUB.map((p) => (
                                <button
                                    key={p.id}
                                    type="button"
                                    className={p.id === prazo.id ? 'pw-periodo-ativo' : undefined}
                                    onClick={() => setPrazo(p)}
                                >
                                    {p.rotulo}
                                </button>
                            ))}
                        </div>

                        <p className="pw-fraco" style={{ margin: '14px 0 8px', fontSize: 13 }}>
                            Vencido o prazo, os bens serão:
                        </p>
                        <div className="pw-marcaveis">
                            {DESTINACOES_SEGUB.map((d) => (
                                <Marcavel key={d} marcado={destinacao === d} aoAlternar={() => setDestinacao(d)}>
                                    {d}
                                </Marcavel>
                            ))}
                        </div>

                        <p className="pw-fraco" style={{ margin: '12px 0 0', fontSize: 13 }}>
                            Os bens apreendidos serão encaminhados ao {SEGUB.nome}, permanecendo pelo prazo
                            máximo de {prazo.extenso}, quando serão {destinacao}, de acordo com a
                            Legislação Municipal.
                        </p>
                    </div>
                </section>

                {/* 5 · Assinaturas ------------------------------------- */}
                <section className="pw-passo">
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">5</span>
                        <span className="pw-passo-titulo">Assinaturas</span>
                        <span className="pw-passo-opcional">Recusa vale</span>
                    </div>

                    <div className="pw-card" style={{ marginBottom: 10 }}>
                        <p className="pw-forte" style={{ margin: 0, fontSize: 14.5 }}>
                            Agente fiscal
                        </p>
                        <p className="pw-fraco" style={{ margin: '2px 0 0' }}>
                            {FISCAL.nome} · matrícula {FISCAL.matricula}
                        </p>
                        <div className="pw-rabisco" style={{ marginTop: 10 }} aria-hidden="true" />
                    </div>

                    <Assinatura
                        rotulo="Notificado"
                        estado={notificado}
                        aoAssinar={() => setNotificado('assinada')}
                        aoRecusar={() => setNotificado('recusada')}
                    />
                </section>

                {lavrado ? (
                    <div className="pw-card" style={{ marginTop: 8, borderColor: 'var(--pw-ok)' }}>
                        <p className="pw-forte" style={{ margin: 0 }}>
                            <Icone nome="certo" tamanho={16} /> Auto de Apreensão Nº {lavrado} lavrado
                        </p>
                        <p className="pw-fraco" style={{ margin: '4px 0 12px' }}>
                            Anexado ao registro {registro.protocolo}. Uma via fica com o notificado e outra
                            acompanha os bens até o SEGUB.
                        </p>
                        <button
                            type="button"
                            className="pw-btn pw-btn-acao"
                            onClick={() => irPara(`via/${registro.id}`)}
                        >
                            <Icone nome="imprimir" tamanho={18} />
                            Imprimir as vias
                        </button>
                    </div>
                ) : (
                    <>
                        <button
                            type="button"
                            className="pw-btn pw-btn-acao"
                            style={{ minHeight: 60, fontSize: 17, opacity: podeLavrar ? 1 : 0.5 }}
                            onClick={lavrar}
                            disabled={!podeLavrar}
                        >
                            <Icone nome="pacote" tamanho={20} />
                            Lavrar apreensão Nº {numero}
                        </button>
                        {!podeLavrar && (
                            <p className="pw-fraco" style={{ textAlign: 'center', marginTop: 10, fontSize: 13 }}>
                                Falta o nome de quem sofreu a apreensão e ao menos um bem discriminado.
                            </p>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
