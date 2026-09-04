import { useState } from 'react';

import { irPara, useApp, voltar } from '../app';
import { Aviso, Selo, Topo, Vazio, classes } from '../componentes';
import {
    EQUIPE,
    ORIGENS,
    RECOMENDACOES,
    acharDemanda,
    impedimentoParaConcluir,
    rotuloDaRecomendacao,
} from '../dados-demandas';
import { nomeDoDocumento } from '../dados-documentos';
import { OCORRENCIAS, nomeRegiao, type Registro } from '../dados-prototipo';
import { Icone } from '../icones';

/**
 * A CONCLUSÃO do registro — onde o fiscal despacha o que viu e o que recomenda.
 *
 * Esta tela era um recibo: provava que o registro pegou (protocolo, hora, lugar,
 * decisão) e acabava ali. Ela virou a conclusão porque o registro NÃO morre no
 * aparelho — ele vai para a caixa de entrada do **Chefe de Setor** da área da
 * equipe, e é lá que alguém decide o encaminhamento.
 *
 * Daí as três coisas que a tela passou a fazer:
 *
 * 1. Dizer PARA ONDE vai, e de qual área: despacho sem destino declarado é o
 *    mesmo que jogar o registro num buraco.
 * 2. Recolher as CONSIDERAÇÕES FINAIS — texto livre e/ou atalhos de recomendação,
 *    que convivem. É a parte importante da entrega: é por elas que o Chefe de
 *    Setor e o Coordenador entendem a recomendação de quem esteve no ponto.
 *    Opcionais, porque a maioria das abordagens se explica pelo desfecho.
 * 3. IMPEDIR a conclusão quando o desfecho lavra documento e o documento não foi
 *    lavrado — dizendo o motivo e pondo o formulário a um toque, nunca em
 *    silêncio.
 *
 * A ORIGEM abre a lista de campos. Num registro dirigido, é o que prova que a
 * demanda foi atendida — e é o número que o Coordenador vai procurar.
 */
export function TelaRecibo({ id }: { id: string | null }) {
    const { registros } = useApp();
    const registro = registros.find((r) => r.id === id);

    if (!registro) {
        return (
            <div className="pw-tela">
                <Topo titulo="Registro" aoVoltar={voltar} />
                <div className="pw-corpo">
                    <Vazio
                        icone="🧾"
                        titulo="Registro não encontrado"
                        texto="Ele pode ter sido aberto noutra sessão do protótipo."
                    />
                </div>
            </div>
        );
    }

    return <Conclusao registro={registro} />;
}

/* O corpo mora num componente próprio porque ele tem ESTADO (o rascunho das
   considerações) e o registro pode não existir: estado declarado depois de um
   `return` condicional é o erro clássico de ordem de hooks. */
function Conclusao({ registro }: { registro: Registro }) {
    const { despachar } = useApp();

    const regular = registro.status === 'regular';
    const nomesDasOcorrencias = registro.ocorrencias
        .map((o) => OCORRENCIAS.find((x) => x.id === o)?.rotulo ?? o)
        .join(' · ');
    const demanda = acharDemanda(registro.demandaId);

    /** Já despachado = tela de LEITURA: o que foi escrito no ato é o que vale. */
    const despachado = registro.despachadoBr !== null;

    const [consideracoes, setConsideracoes] = useState(registro.consideracoes);
    const [escolhidas, setEscolhidas] = useState<string[]>(registro.recomendacoes);
    const [concluindo, setConcluindo] = useState(false);

    /* A régua da regra vem de uma função só, derivada do documento do desfecho
       (ver `impedimentoParaConcluir`, em `dados-demandas.ts`): desfecho que lavra
       papel não conclui sem o papel. */
    const impedimento = impedimentoParaConcluir(registro.desfecho, registro.documento !== null);

    const alternar = (id: string) =>
        setEscolhidas((atuais) =>
            atuais.includes(id) ? atuais.filter((e) => e !== id) : [...atuais, id],
        );

    /* Guarda de duplo clique: quem toca de pé, com a tela sob sol, toca de novo
       achando que não pegou. */
    const concluir = () => {
        if (impedimento || concluindo) {
            return;
        }

        setConcluindo(true);
        despachar(registro.id, consideracoes.trim(), escolhidas);

        /* O fiscal volta para onde o trabalho está: a fila, quando o registro veio
           de uma denúncia; a lista do turno, quando ele achou o ponto andando. */
        irPara(demanda ? 'demandas' : 'registros');
    };

    /* "Voltar" é a tela ANTERIOR, como o dono pediu — com UMA exceção: depois de
       despachar, a tela anterior é o formulário de registro, que já foi enviado
       e voltaria em branco. Sair de um registro despachado para um formulário
       zerado parece que o trabalho se perdeu. Nesse caso o Voltar vai para onde
       o registro está: a fila, se ele veio de uma denúncia; a lista do turno, se
       o fiscal achou o ponto andando. */
    const voltarDaConclusao = () => {
        if (despachado) {
            irPara(demanda ? 'demandas' : 'registros');

            return;
        }

        voltar();
    };

    return (
        <div className="pw-tela">
            <Topo
                titulo="Conclusão do registro"
                subtitulo={
                    despachado ? `Despachado em ${registro.despachadoBr}` : 'Confira e despache'
                }
                aoVoltar={voltarDaConclusao}
            />

            <div className="pw-corpo">
                <div className="pw-recibo">
                    <div
                        className="pw-recibo-selo"
                        style={{
                            background: regular ? 'var(--pw-ok-suave)' : 'var(--pw-alerta-suave)',
                            color: regular ? 'var(--pw-ok)' : 'var(--pw-alerta)',
                        }}
                    >
                        <Icone nome={regular ? 'certo' : 'alerta'} tamanho={38} />
                    </div>

                    {/* O que se lê primeiro é o DESFECHO, não uma paráfrase dele:
                        é a palavra que a Retaguarda vai somar e que fecha o passo
                        do trâmite da denúncia. */}
                    <p
                        className="pw-forte"
                        style={{ margin: 0, textAlign: 'center', fontSize: 19, letterSpacing: '-0.02em', lineHeight: 1.25 }}
                    >
                        {registro.desfecho}
                    </p>
                    <p className="pw-fraco" style={{ margin: '2px 0 0', textAlign: 'center' }}>
                        Protocolo {registro.protocolo}
                    </p>

                    <hr className="pw-tracejado" />

                    <dl>
                        <dt>Origem</dt>
                        <dd>
                            {demanda ? (
                                <>
                                    <span className="pw-selo pw-selo-origem">
                                        <span>{ORIGENS[demanda.origem].emoji}</span>
                                        {ORIGENS[demanda.origem].curto}
                                    </span>
                                    <br />
                                    <span className="pw-fraco">
                                        Denúncia {demanda.protocolo} · {demanda.bairro}
                                    </span>
                                </>
                            ) : (
                                'Avulsa — encontrada em ronda'
                            )}
                        </dd>
                        <dt>Como o local ficou</dt>
                        <dd>
                            <Selo tom={regular ? 'ok' : 'alerta'}>
                                {regular ? 'Ponto liberado' : 'Ocorrência registrada'}
                            </Selo>
                        </dd>
                        <dt>Quando</dt>
                        <dd>
                            {registro.dataBr} às {registro.hora}
                        </dd>
                        <dt>Onde</dt>
                        <dd>
                            {registro.endereco}
                            <br />
                            <span className="pw-fraco">
                                {nomeRegiao(registro.regiao)} · {registro.lat.toFixed(5)},{' '}
                                {registro.lng.toFixed(5)}
                            </span>
                        </dd>
                        <dt>Ambulante</dt>
                        <dd>{registro.ambulante ?? 'Não identificado'}</dd>
                        <dt>Ocorrências</dt>
                        <dd>{nomesDasOcorrencias || 'Sem marcação'}</dd>
                        <dt>Fotos</dt>
                        <dd>
                            {registro.fotos === 0
                                ? 'Nenhuma'
                                : registro.fotos === 1
                                  ? '1 foto'
                                  : `${registro.fotos} fotos`}
                        </dd>
                        {registro.relato && (
                            <>
                                <dt>Relato</dt>
                                <dd style={{ fontWeight: 400 }}>{registro.relato}</dd>
                            </>
                        )}
                        {registro.retornoBr && (
                            <>
                                <dt>Retorno</dt>
                                <dd>
                                    <Selo tom="info">
                                        <Icone nome="relogio" tamanho={13} /> {registro.retornoBr}
                                    </Selo>
                                </dd>
                            </>
                        )}
                        <dt>Envio</dt>
                        <dd>
                            <Selo tom={registro.envio === 'enviado' ? 'ok' : 'neutro'}>
                                {registro.envio === 'enviado'
                                    ? 'Enviado ao Chefe de Setor'
                                    : 'Na fila — sai no sinal'}
                            </Selo>
                        </dd>
                    </dl>
                </div>

                {/* Para onde vai — declarado, não subentendido. O fiscal precisa
                    saber quem vai ler o que ele escreveu, e de qual área é essa
                    caixa: a denúncia chega para a EQUIPE, e o despacho sobe ao
                    Chefe de Setor daquela mesma área. */}
                <div className="pw-card pw-card-vinculo" style={{ marginTop: 18 }}>
                    <div className="pw-linha-espalha" style={{ marginBottom: 6 }}>
                        <span className="pw-forte" style={{ fontSize: 15.5 }}>
                            <Icone nome="caixa-entrada" tamanho={16} /> Destino do despacho
                        </span>
                        <Selo tom={despachado ? 'ok' : 'info'}>
                            {despachado ? 'Despachado' : 'A despachar'}
                        </Selo>
                    </div>
                    <p style={{ margin: 0, fontSize: 15, color: 'var(--pw-texto)' }}>
                        Caixa de entrada do <strong>Chefe de Setor</strong> · {EQUIPE.area} —{' '}
                        {EQUIPE.areaNome}
                    </p>
                    <p className="pw-fraco" style={{ margin: '6px 0 0', fontSize: 13 }}>
                        Todo registro concluído entra nessa caixa, com as considerações finais que
                        você escrever aqui. É por elas que o Chefe de Setor e o Coordenador entendem a
                        sua recomendação e sabem para onde direcionar.
                    </p>
                </div>

                <p className="pw-titulo-secao">
                    Considerações finais{despachado ? '' : ' · opcionais'}
                </p>

                {despachado ? (
                    <div className="pw-card">
                        {registro.recomendacoes.length === 0 && !registro.consideracoes ? (
                            <p className="pw-fraco" style={{ margin: 0, fontSize: 14.5 }}>
                                Despachado sem considerações — o desfecho já dizia o que precisava ser
                                dito.
                            </p>
                        ) : (
                            <>
                                {registro.recomendacoes.length > 0 && (
                                    <div className="pw-linha" style={{ flexWrap: 'wrap', gap: 6 }}>
                                        {registro.recomendacoes.map((r) => (
                                            <Selo key={r} tom="info">
                                                {rotuloDaRecomendacao(r)}
                                            </Selo>
                                        ))}
                                    </div>
                                )}
                                {registro.consideracoes && (
                                    <p
                                        style={{
                                            margin:
                                                registro.recomendacoes.length > 0 ? '12px 0 0' : 0,
                                            fontSize: 14.5,
                                            lineHeight: 1.55,
                                            color: 'var(--pw-texto-corpo)',
                                        }}
                                    >
                                        {registro.consideracoes}
                                    </p>
                                )}
                            </>
                        )}
                    </div>
                ) : (
                    <>
                        {/* Os atalhos são o caminho curto de quem está de pé: um
                            toque cada, lista fechada, e somam entre si e com o
                            texto livre. Recomendação digitada de sete maneiras
                            diferentes não vira fila de trabalho do outro lado. */}
                        <div className="pw-chips">
                            {RECOMENDACOES.map((r) => (
                                <button
                                    key={r.id}
                                    type="button"
                                    className={classes(
                                        'pw-chip',
                                        escolhidas.includes(r.id) && 'pw-chip-ligado',
                                    )}
                                    onClick={() => alternar(r.id)}
                                    aria-pressed={escolhidas.includes(r.id)}
                                >
                                    <span>{r.emoji}</span>
                                    {r.rotulo}
                                </button>
                            ))}
                        </div>

                        <textarea
                            className="pw-entrada"
                            style={{ marginTop: 14 }}
                            value={consideracoes}
                            onChange={(e) => setConsideracoes(e.target.value)}
                            placeholder="O que o Chefe de Setor precisa saber (opcional)"
                        />
                    </>
                )}

                {/* O IMPEDIMENTO fica à vista ANTES de o dedo chegar ao botão:
                    dizer o motivo só depois do toque faria o fiscal descobrir a
                    regra por tentativa. E o caminho vem junto — o formulário do
                    documento que falta, a um toque. */}
                {!despachado && impedimento && (
                    <div style={{ marginTop: 18 }}>
                        <Aviso tom="alerta">
                            <strong>Ainda não é possível concluir.</strong> {impedimento.motivo}{' '}
                            {impedimento.comoResolver}
                        </Aviso>
                        <button
                            type="button"
                            className="pw-btn pw-btn-acao"
                            style={{ marginTop: 12, minHeight: 58 }}
                            onClick={() =>
                                irPara(
                                    `${impedimento.documento === 'np' ? 'notificacao' : 'apreensao'}/${registro.id}`,
                                )
                            }
                        >
                            <Icone nome="documento" tamanho={19} />
                            Lavrar {impedimento.nomeComArtigo}
                        </button>
                    </div>
                )}

                <div style={{ marginTop: 18, display: 'grid', gap: 10 }}>
                    {!despachado && (
                        <button
                            type="button"
                            className="pw-btn pw-btn-acao"
                            style={{ minHeight: 58, fontSize: 17, opacity: impedimento ? 0.5 : 1 }}
                            onClick={concluir}
                            disabled={impedimento !== null || concluindo}
                        >
                            <Icone
                                nome={concluindo ? 'atualizar' : 'certo'}
                                tamanho={20}
                                className={concluindo ? 'pw-girando' : undefined}
                            />
                            {concluindo ? 'Concluindo…' : 'Concluir registro'}
                        </button>
                    )}
                    <button
                        type="button"
                        className="pw-btn pw-btn-contorno"
                        onClick={voltarDaConclusao}
                    >
                        Voltar
                    </button>
                </div>

                {/* Impedido, o bloco do "caminho excepcional" sai de cena: o botão
                    do documento que falta já está acima, e dois botões parecidos
                    fazendo quase a mesma coisa é exatamente o que faz o fiscal
                    tocar no errado. */}
                {!impedimento && (
                    <p className="pw-titulo-secao">
                        {registro.documento ? 'Documento lavrado' : 'Caminho excepcional'}
                    </p>
                )}

                {impedimento ? null : registro.documentoTipo && registro.documento ? (
                    <div className="pw-card" style={{ borderColor: 'var(--pw-ok)' }}>
                        <div className="pw-linha-espalha" style={{ marginBottom: 6 }}>
                            <span className="pw-forte" style={{ fontSize: 16 }}>
                                {nomeDoDocumento(registro.documentoTipo)}
                            </span>
                            <Selo tom="ok">Nº {registro.documento.replace(/^\w+\s/, '')}</Selo>
                        </div>
                        <p className="pw-fraco" style={{ margin: '0 0 12px' }}>
                            {registro.documentoTipo === 'aa'
                                ? 'Bens encaminhados ao SEGUB. Uma via fica com o notificado.'
                                : 'A via fica com o notificado — é ela que faz o prazo correr.'}
                        </p>
                        <button
                            type="button"
                            className="pw-btn pw-btn-contorno"
                            onClick={() => irPara(`via/${registro.id}`)}
                        >
                            <Icone nome="imprimir" tamanho={18} />
                            Imprimir a via
                        </button>
                    </div>
                ) : (
                    <div className="pw-card">
                        <p style={{ margin: '0 0 10px', fontSize: 14.5, color: 'var(--pw-texto-corpo)' }}>
                            A maior parte das abordagens termina aqui. Lavrar Notificação Preliminar
                            ou Auto de Apreensão só quando houver irregularidade a sanar em prazo, ou
                            material a recolher.
                        </p>
                        <button
                            type="button"
                            className="pw-btn pw-btn-fantasma"
                            onClick={() => irPara(`documentos/${registro.id}`)}
                        >
                            <Icone nome="documento" tamanho={18} />
                            Lavrar documento de campo
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}
