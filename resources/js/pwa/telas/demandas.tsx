import { useMemo, useState } from 'react';

import { irPara, useApp } from '../app';
import { Selo, Topo, Vazio, atalhoDoPerfil, classes } from '../componentes';
import {
    EQUIPE,
    ORIGENS,
    TOM_DA_SITUACAO,
    demandasAVistoriar,
    demandasDeOutrasEquipes,
    demandasEmRegularizacao,
    demandasEncerradas,
    podeRegistrarRetorno,
    prazoDoRetornoEmPalavras,
    prazoEmPalavras,
    tomDoPrazo,
    type Demanda,
    type Desfecho,
    type Origem,
} from '../dados-demandas';
import { FISCAL } from '../dados-prototipo';
import { Icone } from '../icones';

/* ============================================================================
   MINHAS DEMANDAS — a fila do trabalho DIRIGIDO.
   ----------------------------------------------------------------------------
   Metade do serviço do fiscal não é descoberta andando a rua: chega dirigida.
   A ouvidoria entrega a denúncia, o Coordenador TRIA e a encaminha à área, e
   o CHEFE DE SETOR daquela área decide como o trabalho acontece — equipe ou
   operação.
   Só então ela aparece aqui.

   Quatro escolhas de tela que vêm direto disso:

   1. O cabeçalho diz DE QUEM é a fila — "Equipe C1 · Área 5 — Boca do Rio". A
      denúncia não chega para o fiscal, chega para a EQUIPE, e qualquer um dela
      pode atender. Sem esse rótulo, o fiscal não entende por que aparece na
      lista um endereço que não é o dele.
   2. A fila é dividida pelo ATO DEVIDO, e não numa lista corrida: o que pede
      vistoria, o que está em prazo de regularização (a bola é do notificado) e
      o que a equipe já encerrou. Numa lista só, a vistoria de hoje ficaria ao
      lado de uma denúncia concluída na semana passada.
   3. Dentro de cada bloco, a ordem é por PRAZO — vencido primeiro. Numa fila de
      prazos, qualquer outra ordem é uma opinião.
   4. O filtro é o CANAL, porque os dois pedem posturas diferentes: o e-Salvador
      chega com foto e requerente identificado; o 156 é a transcrição de uma
      ligação, às vezes anônima e com endereço apurado por telefone.

   ⚠️ O fiscal NUNCA vê denúncia em triagem. Elas existem no dado do protótipo
   (para a fila poder provar o recorte), e a régua está em `dados-demandas.ts`:
   só as situações que já estão na mão da equipe chegam aqui.
   ============================================================================ */

type Filtro = 'todas' | Origem;

export function TelaDemandas() {
    const { registros } = useApp();
    const [filtro, setFiltro] = useState<Filtro>('todas');

    /* O que o fiscal registrou NESTE aparelho, por denúncia. Uma demanda já
       atendida no turno não desaparece da fila — ela ganha o selo do desfecho
       que ela recebeu. Sumir seria pior: o fiscal perde a confirmação de que
       fez, e o encarregado perde a prova. */
    const registrado = useMemo(() => {
        const mapa = new Map<string, Desfecho>();

        for (const r of registros) {
            if (r.demandaId) {
                mapa.set(r.demandaId, r.desfecho);
            }
        }

        return mapa;
    }, [registros]);

    const aVistoriar = useMemo(() => demandasAVistoriar(), []);
    const emRegularizacao = useMemo(() => demandasEmRegularizacao(), []);
    const encerradas = useMemo(() => demandasEncerradas(), []);
    const deOutras = useMemo(() => demandasDeOutrasEquipes(), []);

    /* O filtro por canal recorta os três blocos com a mesma régua — filtrar só
       o primeiro deixaria os números do topo brigando com a lista de baixo. */
    const recortar = (lista: Demanda[]): Demanda[] =>
        filtro === 'todas' ? lista : lista.filter((d) => d.origem === filtro);

    const fila = recortar(aVistoriar);
    const emPrazo = recortar(emRegularizacao);
    const fechadas = recortar(encerradas);

    const vencidas = fila.filter((d) => d.prazoDias < 0).length;
    const hoje = fila.filter((d) => d.prazoDias === 0).length;
    const nada = fila.length === 0 && emPrazo.length === 0 && fechadas.length === 0;

    return (
        <div className="pw-tela">
            <Topo
                titulo="Minhas demandas"
                subtitulo={`${EQUIPE.nome} · ${EQUIPE.area} — ${EQUIPE.areaNome}`}
                perfil={atalhoDoPerfil(() => irPara('perfil'), FISCAL.iniciais)}
            />

            <div className="pw-corpo">
                <div className="pw-card pw-cartao-equipe">
                    <span className="pw-equipe-selo">
                        <Icone nome="equipe" tamanho={20} />
                    </span>
                    <div style={{ minWidth: 0 }}>
                        <p className="pw-forte" style={{ margin: 0, fontSize: 15.5 }}>
                            {EQUIPE.nome} · {EQUIPE.area} — {EQUIPE.areaNome}
                        </p>
                        <p className="pw-fraco" style={{ margin: '2px 0 0' }}>
                            Encarregado {EQUIPE.encarregado} · {EQUIPE.bairros.length} bairros na área
                        </p>
                    </div>
                </div>

                <p className="pw-fraco" style={{ margin: '10px 0 0', fontSize: 13.5 }}>
                    O Chefe de Setor direciona a denúncia à equipe, ou a anexa a uma operação. Toda a
                    equipe vê esta fila — e só ela.
                    {deOutras > 0 && (
                        <>
                            {' '}
                            Outras{' '}
                            <strong>
                                {deOutras === 1 ? '1 denúncia' : `${deOutras} denúncias`}
                            </strong>{' '}
                            estão com equipes diferentes e não aparecem aqui.
                        </>
                    )}
                </p>

                <div className="pw-linha" style={{ gap: 8, flexWrap: 'wrap', margin: '14px 0 0' }}>
                    <Selo tom={vencidas > 0 ? 'perigo' : 'ok'}>
                        <Icone nome="relogio" tamanho={13} />
                        {vencidas === 1 ? '1 vencida' : `${vencidas} vencidas`}
                    </Selo>
                    <Selo tom="alerta">{hoje === 1 ? '1 vence hoje' : `${hoje} vencem hoje`}</Selo>
                    <Selo tom="neutro">
                        {fila.length === 1 ? '1 a vistoriar' : `${fila.length} a vistoriar`}
                    </Selo>
                </div>

                <div className="pw-chips" style={{ marginTop: 14, gap: 8 }}>
                    <button
                        type="button"
                        className={classes('pw-chip', filtro === 'todas' && 'pw-chip-ligado')}
                        onClick={() => setFiltro('todas')}
                        aria-pressed={filtro === 'todas'}
                    >
                        Todos os canais
                    </button>
                    {(Object.keys(ORIGENS) as Origem[]).map((o) => (
                        <button
                            key={o}
                            type="button"
                            className={classes('pw-chip', filtro === o && 'pw-chip-ligado')}
                            onClick={() => setFiltro(o)}
                            aria-pressed={filtro === o}
                        >
                            <span>{ORIGENS[o].emoji}</span>
                            {ORIGENS[o].curto}
                        </button>
                    ))}
                </div>

                {nada ? (
                    /* Fila vazia da EQUIPE é diferente de fila vazia do canal, e
                       o texto tem de dizer qual dos dois é: sem isso, o fiscal
                       de uma equipe sem direcionamento acha que o aplicativo
                       quebrou. */
                    <>
                        <p className="pw-titulo-secao">A vistoriar</p>
                        <Vazio
                            icone="📭"
                            titulo="Nenhuma denúncia direcionada à sua equipe"
                            texto={`O Chefe de Setor ainda não direcionou nada à ${EQUIPE.nome} · ${EQUIPE.area}. As denúncias de outras equipes não aparecem aqui — cada equipe vê o trabalho da área dela.`}
                        />
                    </>
                ) : (
                    <>
                        <p className="pw-titulo-secao">A vistoriar</p>

                        {fila.length === 0 ? (
                            <Vazio
                                icone="✅"
                                titulo="Nada a vistoriar neste recorte"
                                texto="Toque em Todos os canais para ver a fila inteira da equipe."
                            />
                        ) : (
                            fila.map((demanda) => (
                                <CartaoDemanda
                                    key={demanda.id}
                                    demanda={demanda}
                                    registrado={registrado.get(demanda.id) ?? null}
                                />
                            ))
                        )}

                        {emPrazo.length > 0 && (
                            <>
                                <p className="pw-titulo-secao">Aguardando regularização</p>
                                <p className="pw-fraco" style={{ margin: '0 0 10px', fontSize: 13.5 }}>
                                    Notificação lavrada e prazo correndo: a bola está com o notificado. A
                                    equipe volta ao ponto quando o prazo vencer.
                                </p>
                                {emPrazo.map((demanda) => (
                                    <CartaoDemanda
                                        key={demanda.id}
                                        demanda={demanda}
                                        registrado={registrado.get(demanda.id) ?? null}
                                    />
                                ))}
                            </>
                        )}

                        {fechadas.length > 0 && (
                            <>
                                <p className="pw-titulo-secao">Já encerradas</p>
                                {fechadas.map((demanda) => (
                                    <CartaoDemanda
                                        key={demanda.id}
                                        demanda={demanda}
                                        registrado={registrado.get(demanda.id) ?? null}
                                    />
                                ))}
                            </>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}

export function CartaoDemanda({
    demanda,
    registrado,
    compacto,
}: {
    demanda: Demanda;
    /** O desfecho que este aparelho registrou nesta denúncia, se houve. */
    registrado: Desfecho | null;
    /** Na tela de Início o cartão entra menor: lá ele é chamada, não trabalho. */
    compacto?: boolean;
}) {
    const origem = ORIGENS[demanda.origem];
    const emRetorno = podeRegistrarRetorno(demanda);
    const encerrada = demanda.situacao === 'Concluída' || demanda.situacao === 'Retorno vencido';
    const documento = demanda.documento;

    return (
        <div
            className={classes(
                'pw-card',
                'pw-card-demanda',
                !encerrada && demanda.prazoDias < 0 && 'pw-card-vencido',
            )}
        >
            <div className="pw-linha-espalha" style={{ gap: 10, marginBottom: 8 }}>
                <span className="pw-selo pw-selo-origem">
                    <span>{origem.emoji}</span>
                    {origem.curto}
                </span>
                {/* Encerrada não tem prazo a vencer: mostrar "vencida há 6 dias"
                    num caso concluído cobraria um ato que já foi feito. */}
                {encerrada ? (
                    <Selo tom={TOM_DA_SITUACAO[demanda.situacao]}>{demanda.situacao}</Selo>
                ) : (
                    <Selo tom={tomDoPrazo(demanda.prazoDias)}>
                        <Icone nome="relogio" tamanho={13} />
                        {prazoEmPalavras(demanda.prazoDias)}
                    </Selo>
                )}
            </div>

            <p className="pw-forte" style={{ margin: 0, fontSize: 16.5, lineHeight: 1.3 }}>
                {demanda.assunto}
            </p>
            <p className="pw-fraco" style={{ margin: '4px 0 0', fontSize: 12.5, letterSpacing: '0.02em' }}>
                {demanda.protocolo} · {origem.curto} {demanda.documentoOrigem}
            </p>

            <p style={{ margin: '10px 0 0', fontSize: 14.5 }}>
                <Icone nome="mapa" tamanho={14} /> {demanda.endereco}
            </p>
            <p className="pw-fraco" style={{ margin: '2px 0 0' }}>
                {demanda.bairro} · prazo {demanda.prazoBr}
            </p>

            {/* A situação e a operação dizem POR QUE esta denúncia é da equipe e
                o que se espera dela agora. Sem a operação à vista, o fiscal
                trataria como ida isolada o que é varredura planejada. */}
            <div className="pw-linha" style={{ gap: 8, flexWrap: 'wrap', marginTop: 10 }}>
                {!encerrada && (
                    <Selo tom={TOM_DA_SITUACAO[demanda.situacao]}>{demanda.situacao}</Selo>
                )}
                {demanda.operacao && <Selo tom="info">{demanda.operacao}</Selo>}
                {demanda.desfecho && <Selo tom="neutro">{demanda.desfecho}</Selo>}
            </div>

            {emRetorno && documento?.venceEmDias !== null && documento?.venceBr && (
                <p className="pw-fraco" style={{ margin: '10px 0 0', fontSize: 13 }}>
                    <Icone nome="documento" tamanho={13} /> {documento.rotulo} ·{' '}
                    {prazoDoRetornoEmPalavras(documento.venceEmDias ?? 0)} ({documento.venceBr})
                </p>
            )}

            {!compacto && (
                <p style={{ margin: '10px 0 0', fontSize: 14, color: 'var(--pw-texto-corpo)' }}>
                    {demanda.detalhe}
                </p>
            )}

            {/* O ATRIBUTO, dito nos dois casos. Antes o cartão só falava quando
                havia ficha do SGCI, e o silêncio no outro caso se lia como
                "não sei" — quando na verdade é "não tem permissão", que é o
                que muda o prazo do documento lavrado na hora. */}
            <p className="pw-fraco" style={{ margin: '10px 0 0', fontSize: 13 }}>
                <Icone nome="prancheta" tamanho={13} />{' '}
                {demanda.sgci
                    ? `Ambulante · permissionário SEMOP · ${demanda.sgci.equipamento}`
                    : 'Ambulante · sem permissão registrada'}
            </p>

            <div className="pw-linha" style={{ gap: 8, marginTop: 14 }}>
                {!encerrada && (
                    <button
                        type="button"
                        className="pw-btn pw-btn-acao pw-btn-pequeno"
                        onClick={() => irPara(`registrar/${demanda.id}`)}
                    >
                        <Icone nome={emRetorno ? 'relogio' : 'mais'} tamanho={17} />
                        {emRetorno
                            ? 'Registrar retorno'
                            : demanda.situacao === 'Em campo'
                              ? 'Continuar'
                              : 'Fiscalizar'}
                    </button>
                )}
                <button
                    type="button"
                    className="pw-btn pw-btn-contorno pw-btn-pequeno"
                    onClick={() => irPara(`demanda/${demanda.id}`)}
                >
                    Detalhes
                </button>
            </div>

            {registrado && (
                <p className="pw-fraco" style={{ margin: '10px 0 0' }}>
                    <Selo tom="ok">
                        <Icone nome="certo" tamanho={13} /> Neste aparelho: {registrado}
                    </Selo>
                </p>
            )}
        </div>
    );
}
