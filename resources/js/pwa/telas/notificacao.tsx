import { useMemo, useState } from 'react';

import { irPara, useApp } from '../app';
import { Assinatura, Marcavel, Selo, Topo, Vazio, classes } from '../componentes';
import { acharDemanda } from '../dados-demandas';
import {
    ATIVIDADES_NP,
    MOTIVOS_NP,
    PRAZOS_NP,
    SANCOES_NP,
    consumirNumero,
    numeroReservado,
} from '../dados-documentos';
import { FISCAL, dataBrDaqui, nomeRegiao, type ViaImpressa } from '../dados-prototipo';
import { Icone } from '../icones';

/* ============================================================================
   NOTIFICAÇÃO PRELIMINAR — o formulário do cliente, campo por campo.
   ----------------------------------------------------------------------------
   Esta tela é a transcrição do bloco de papel (o exemplar entregue traz o nº
   194901) somada ao "Manual para o NTI" que o cliente escreveu. A ordem dos
   passos é a que o manual recomenda, e não uma invenção nossa:

      identificar o responsável → registrar o local → marcar os motivos
      → indicar a legislação → definir o prazo → informar as penalidades
      → colher as assinaturas → entregar a via ao notificado

   Três decisões que vêm do papel, não do gosto:

   • Os motivos são VINTE caixas de assinalar, e ficam todos numa lista rolável
     em vez de escondidos atrás de um seletor. No papel, o agente lê a coluna e
     marca com "X"; se a tela esconder a lista, ele perde o motivo que existe e
     ele não lembrava.
   • Data, hora e agente NÃO são digitados — o manual diz, com essas palavras,
     que o sistema deve fornecê-los. Digitá-los é o que faz o papel de hoje sair
     com hora errada.
   • As duas testemunhas e a assinatura do notificado admitem RECUSA. Recusar
     assinar é fato jurídico corriqueiro; um campo que só aceita assinatura
     obriga o agente a mentir ou a deixar em branco.
   ============================================================================ */

type EstadoAssinatura = 'pendente' | 'assinada' | 'recusada';

export function TelaNotificacao({ id }: { id: string | null }) {
    const { registros, anexarDocumento } = useApp();
    const registro = registros.find((r) => r.id === id);
    const demanda = acharDemanda(registro?.demandaId ?? null);

    const [nome, setNome] = useState(registro?.ambulante ?? demanda?.sgci?.nome ?? '');
    const [enderecoNotificado, setEnderecoNotificado] = useState(registro?.endereco ?? '');
    const [inscricao, setInscricao] = useState(demanda?.sgci?.inscricao ?? '');
    const [atividade, setAtividade] = useState(demanda?.sgci?.atividade ?? '');
    const [localAtividade, setLocalAtividade] = useState(registro?.endereco ?? '');
    const [equipamento, setEquipamento] = useState(demanda?.sgci?.equipamento ?? '');

    const [motivos, setMotivos] = useState<string[]>([]);
    const [complementos, setComplementos] = useState<Record<string, string>>({});
    const [outros, setOutros] = useState('');
    const [prazo, setPrazo] = useState(PRAZOS_NP[3]);
    const [sancoes, setSancoes] = useState<string[]>(['autuacao', 'apreensao']);

    const [notificado, setNotificado] = useState<EstadoAssinatura>('pendente');
    const [t1, setT1] = useState<EstadoAssinatura>('pendente');
    const [nomeT1, setNomeT1] = useState('');
    const [t2, setT2] = useState<EstadoAssinatura>('pendente');
    const [nomeT2, setNomeT2] = useState('');

    const [lavrado, setLavrado] = useState<string | null>(
        registro?.documentoTipo === 'np' ? (registro.documento?.replace('NP ', '') ?? null) : null,
    );

    const numero = useMemo(() => lavrado ?? numeroReservado('np'), [lavrado]);
    const vencimento = dataBrDaqui(prazo.dias);

    if (!registro) {
        return (
            <div className="pw-tela">
                <Topo titulo="Notificação Preliminar" aoVoltar={() => irPara('registros')} />
                <div className="pw-corpo">
                    <Vazio
                        icone="📋"
                        titulo="Registro não encontrado"
                        texto="A notificação se lavra sobre uma fiscalização já registrada."
                    />
                </div>
            </div>
        );
    }

    const alternar = (lista: string[], id: string): string[] =>
        lista.includes(id) ? lista.filter((x) => x !== id) : [...lista, id];

    const podeLavrar = nome.trim().length > 2 && (motivos.length > 0 || outros.trim().length > 2);

    /**
     * A via sai CONGELADA no momento da lavratura.
     *
     * Não se remonta o documento a partir dos campos da tela na hora de
     * imprimir: o que valeu foi o que o notificado assinou. Por isso o texto
     * vai inteiro para o registro, e a impressão só o repete.
     */
    const montarVia = (emitido: string): ViaImpressa => ({
        tipo: 'np',
        numero: emitido,
        titulo: 'NOTIFICAÇÃO PRELIMINAR',
        campos: [
            { rotulo: 'Referência', valor: registro.referencia ?? '—' },
            { rotulo: 'Nome', valor: nome.trim() || '—' },
            { rotulo: 'Endereço', valor: enderecoNotificado.trim() || '—' },
            { rotulo: 'Inscrição/Processo', valor: inscricao.trim() || '—' },
            { rotulo: 'Atividade', valor: atividade.trim() || '—' },
            { rotulo: 'Local da atividade', valor: localAtividade.trim() || '—' },
            { rotulo: 'Barraca/Box/Lote/Qda', valor: equipamento.trim() || '—' },
            { rotulo: 'Data / hora', valor: `${registro.dataBr} às ${registro.hora}` },
            {
                rotulo: 'Vencimento',
                valor: prazo.dias === 0 ? 'Imediato' : `${vencimento} (${prazo.rotulo})`,
            },
            { rotulo: 'Agente fiscal', valor: `${FISCAL.nome} — matrícula ${FISCAL.matricula}` },
        ],
        listas: [
            {
                titulo: 'Motivo(s) assinalado(s)',
                itens: [
                    ...MOTIVOS_NP.filter((m) => motivos.includes(m.id)).map((m) =>
                        m.complemento && complementos[m.id]?.trim()
                            ? `${m.texto}: ${complementos[m.id].trim()}`
                            : m.texto,
                    ),
                    ...(outros.trim() ? [`Outros: ${outros.trim()}`] : []),
                ],
            },
            {
                titulo: 'Penalidades previstas',
                itens: SANCOES_NP.filter((s) => sancoes.includes(s.id)).map((s) => s.rotulo),
            },
        ],
        assinaturas: [
            { rotulo: 'Notificado', estado: notificado },
            { rotulo: '1ª testemunha', estado: t1, nome: nomeT1.trim() || undefined },
            { rotulo: '2ª testemunha', estado: t2, nome: nomeT2.trim() || undefined },
        ],
        rodape: 'Rua 28 de Setembro, nº 26 — Baixa dos Sapateiros — CEP 40020-240 — Salvador/BA',
    });

    const lavrar = () => {
        if (!podeLavrar || lavrado) {
            return;
        }

        const emitido = consumirNumero('np');
        anexarDocumento(registro.id, montarVia(emitido), nome.trim() || null);
        setLavrado(emitido);
        irPara(`via/${registro.id}`);
    };

    return (
        <div className="pw-tela">
            <Topo
                titulo="Notificação Preliminar"
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

                    <div className="pw-linha" style={{ gap: 8, marginTop: 12, flexWrap: 'wrap' }}>
                        <Selo tom="neutro">
                            <Icone nome="relogio" tamanho={13} /> {registro.dataBr} às {registro.hora}
                        </Selo>
                        <Selo tom="neutro">
                            <Icone nome="pessoa" tamanho={13} /> {FISCAL.nome} · {FISCAL.matricula}
                        </Selo>
                    </div>
                    <p className="pw-fraco" style={{ margin: '10px 0 0', fontSize: 12.5 }}>
                        Data, hora, agente e matrícula vêm do sistema — não se digitam em campo.
                    </p>
                </div>

                {/* Referência ------------------------------------------------ */}
                <p className="pw-titulo-secao">Referência</p>

                <div className="pw-card">
                    {registro.referencia ? (
                        <>
                            <p className="pw-forte" style={{ margin: 0, fontSize: 15.5 }}>
                                {registro.referencia}
                            </p>
                            <p className="pw-fraco" style={{ margin: '2px 0 0' }}>
                                {demanda ? demanda.assunto : 'Processo encaminhado à equipe'}
                            </p>
                        </>
                    ) : (
                        <p style={{ margin: 0, fontSize: 14.5 }}>
                            Fiscalização avulsa — sem processo de referência. O campo sai em branco na via,
                            como no bloco de papel.
                        </p>
                    )}
                </div>

                {/* 1 · Notificado -------------------------------------------- */}
                <section className="pw-passo" style={{ marginTop: 22 }}>
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">1</span>
                        <span className="pw-passo-titulo">Quem está sendo notificado</span>
                        {demanda?.sgci && <span className="pw-passo-opcional">Do SGCI</span>}
                    </div>

                    {demanda?.sgci && (
                        <p className="pw-fraco" style={{ margin: '0 0 10px', fontSize: 13 }}>
                            <Icone nome="prancheta" tamanho={13} /> Preenchido com os dados do cadastro
                            SGCI. Corrija se o que está no local for diferente.
                        </p>
                    )}

                    <div className="pw-card">
                        <label className="pw-campo">
                            <span>Nome do ambulante, responsável ou ocupante</span>
                            <input
                                className="pw-entrada"
                                value={nome}
                                onChange={(e) => setNome(e.target.value)}
                                placeholder="Nome completo"
                            />
                        </label>

                        <label className="pw-campo">
                            <span>Endereço onde exerce a atividade</span>
                            <input
                                className="pw-entrada"
                                value={enderecoNotificado}
                                onChange={(e) => setEnderecoNotificado(e.target.value)}
                                placeholder="Logradouro, número"
                            />
                        </label>

                        <label className="pw-campo">
                            <span>Inscrição / Processo nº</span>
                            <input
                                className="pw-entrada"
                                value={inscricao}
                                onChange={(e) => setInscricao(e.target.value)}
                                placeholder="Inscrição municipal ou processo, se houver"
                            />
                        </label>

                        <label className="pw-campo">
                            <span>Atividade</span>
                            <input
                                className="pw-entrada"
                                value={atividade}
                                onChange={(e) => setAtividade(e.target.value)}
                                placeholder="Ex.: Barraca de Chapa"
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

                        <label className="pw-campo">
                            <span>Local da atividade</span>
                            <input
                                className="pw-entrada"
                                value={localAtividade}
                                onChange={(e) => setLocalAtividade(e.target.value)}
                                placeholder="Local exato onde a atividade é exercida"
                            />
                            <p className="pw-fraco" style={{ margin: '6px 0 0', fontSize: 12.5 }}>
                                <Icone nome="alvo" tamanho={12} /> {nomeRegiao(registro.regiao)} ·{' '}
                                {registro.lat.toFixed(5)}, {registro.lng.toFixed(5)} — coordenada do
                                registro.
                            </p>
                        </label>

                        <label className="pw-campo" style={{ marginBottom: 0 }}>
                            <span>Barraca / Box / Lote / Quadra</span>
                            <input
                                className="pw-entrada"
                                value={equipamento}
                                onChange={(e) => setEquipamento(e.target.value)}
                                placeholder="Identificação do equipamento"
                            />
                        </label>
                    </div>
                </section>

                {/* 2 · Motivos ---------------------------------------------- */}
                <section className="pw-passo">
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">2</span>
                        <span className="pw-passo-titulo">Motivos assinalados</span>
                        <span className="pw-passo-opcional">
                            {motivos.length === 1 ? '1 marcado' : `${motivos.length} marcados`}
                        </span>
                    </div>

                    <div className="pw-marcaveis">
                        {MOTIVOS_NP.map((m) => (
                            <div key={m.id}>
                                <Marcavel
                                    marcado={motivos.includes(m.id)}
                                    aoAlternar={() => setMotivos((a) => alternar(a, m.id))}
                                >
                                    {m.texto}
                                </Marcavel>
                                {m.complemento && motivos.includes(m.id) && (
                                    <input
                                        className="pw-entrada"
                                        style={{ marginTop: 6 }}
                                        value={complementos[m.id] ?? ''}
                                        onChange={(e) =>
                                            setComplementos((c) => ({ ...c, [m.id]: e.target.value }))
                                        }
                                        placeholder={m.complemento}
                                    />
                                )}
                            </div>
                        ))}

                        <div>
                            <Marcavel
                                marcado={outros.trim().length > 0}
                                aoAlternar={() => setOutros((o) => (o ? '' : ' '))}
                            >
                                Outros
                            </Marcavel>
                            {outros.length > 0 && (
                                <textarea
                                    className="pw-entrada"
                                    style={{ marginTop: 6 }}
                                    value={outros}
                                    onChange={(e) => setOutros(e.target.value)}
                                    placeholder="Descreva detalhadamente a irregularidade encontrada"
                                    autoFocus
                                />
                            )}
                        </div>
                    </div>
                </section>

                {/* 3 · Prazo ------------------------------------------------ */}
                <section className="pw-passo">
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">3</span>
                        <span className="pw-passo-titulo">Prazo para sanar</span>
                    </div>

                    <div className="pw-card">
                        <p style={{ margin: '0 0 12px', fontSize: 14.5, lineHeight: 1.5 }}>
                            Solicitamos sanar as irregularidades assinaladas no prazo de{' '}
                            <span className="pw-forte">{prazo.rotulo.toLowerCase()}</span>, para evitar a
                            aplicação das penalidades previstas.
                        </p>

                        <div className="pw-grade-prazos">
                            {PRAZOS_NP.map((p) => (
                                <button
                                    key={p.id}
                                    type="button"
                                    className={classes('pw-chip', p.id === prazo.id && 'pw-chip-ligado')}
                                    onClick={() => setPrazo(p)}
                                    aria-pressed={p.id === prazo.id}
                                >
                                    {p.rotulo}
                                </button>
                            ))}
                        </div>

                        <p className="pw-fraco" style={{ margin: '12px 0 0' }}>
                            {prazo.dias === 0
                                ? 'Vencimento imediato — para quem não possui cadastro.'
                                : `Vencimento em ${vencimento}.`}
                            {prazo.nota ? ` ${prazo.nota}.` : ''}
                        </p>
                    </div>
                </section>

                {/* 4 · Penalidades ------------------------------------------ */}
                <section className="pw-passo">
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">4</span>
                        <span className="pw-passo-titulo">Penalidades previstas</span>
                        <span className="pw-passo-opcional">Tais como</span>
                    </div>

                    <div className="pw-marcaveis">
                        {SANCOES_NP.map((s) => (
                            <Marcavel
                                key={s.id}
                                marcado={sancoes.includes(s.id)}
                                aoAlternar={() => setSancoes((a) => alternar(a, s.id))}
                            >
                                {s.rotulo}
                            </Marcavel>
                        ))}
                    </div>

                    <p className="pw-fraco" style={{ margin: '10px 0 0', fontSize: 13 }}>
                        São as penalidades que poderão ser aplicadas se a notificação não for cumprida —
                        assinalar não as aplica.
                    </p>
                </section>

                {/* 5 · Assinaturas ----------------------------------------- */}
                <section className="pw-passo">
                    <div className="pw-passo-cabeca">
                        <span className="pw-passo-numero">5</span>
                        <span className="pw-passo-titulo">Assinaturas</span>
                        <span className="pw-passo-opcional">Recusa vale</span>
                    </div>

                    <div style={{ display: 'grid', gap: 10 }}>
                        <Assinatura
                            rotulo="Assinatura do notificado"
                            estado={notificado}
                            aoAssinar={() => setNotificado('assinada')}
                            aoRecusar={() => setNotificado('recusada')}
                        />
                        <Assinatura
                            rotulo="1ª testemunha"
                            estado={t1}
                            aoAssinar={() => setT1('assinada')}
                            aoRecusar={() => setT1('recusada')}
                            nome={nomeT1}
                            aoNome={setNomeT1}
                            lugarNome="Nome da testemunha"
                        />
                        <Assinatura
                            rotulo="2ª testemunha"
                            estado={t2}
                            aoAssinar={() => setT2('assinada')}
                            aoRecusar={() => setT2('recusada')}
                            nome={nomeT2}
                            aoNome={setNomeT2}
                            lugarNome="Nome da testemunha"
                        />
                    </div>

                    {notificado === 'recusada' && (
                        <p className="pw-fraco" style={{ margin: '10px 0 0', fontSize: 13 }}>
                            A recusa do notificado sai impressa na via, e é por isso que as duas
                            testemunhas existem no formulário.
                        </p>
                    )}
                </section>

                {lavrado ? (
                    <div className="pw-card" style={{ marginTop: 8, borderColor: 'var(--pw-ok)' }}>
                        <p className="pw-forte" style={{ margin: 0 }}>
                            <Icone nome="certo" tamanho={16} /> Notificação Nº {lavrado} lavrada
                        </p>
                        <p className="pw-fraco" style={{ margin: '4px 0 12px' }}>
                            Anexada ao registro {registro.protocolo}. A via do notificado sai na
                            impressora de bolso.
                        </p>
                        <button
                            type="button"
                            className="pw-btn pw-btn-acao"
                            onClick={() => irPara(`via/${registro.id}`)}
                        >
                            <Icone nome="imprimir" tamanho={18} />
                            Imprimir a via do notificado
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
                            <Icone nome="documento" tamanho={20} />
                            Lavrar notificação Nº {numero}
                        </button>
                        {!podeLavrar && (
                            <p className="pw-fraco" style={{ textAlign: 'center', marginTop: 10, fontSize: 13 }}>
                                Falta o nome do notificado e ao menos um motivo assinalado.
                            </p>
                        )}
                    </>
                )}

                <p className="pw-fraco" style={{ marginTop: 18, fontSize: 12.5, textAlign: 'center' }}>
                    Rua 28 de Setembro, nº 26 · Baixa dos Sapateiros · CEP 40020-240 · Salvador — BA
                </p>
            </div>
        </div>
    );
}
