import { useMemo, useState } from 'react';

import { irPara, useApp } from '../app';
import { Selo, Topo, Vazio, atalhoDoPerfil, classes } from '../componentes';
import {
    DEMANDAS_ABERTAS,
    EQUIPE,
    ORIGENS,
    prazoEmPalavras,
    tomDoPrazo,
    type Demanda,
    type Origem,
} from '../dados-demandas';
import { FISCAL } from '../dados-prototipo';
import { Icone } from '../icones';

/* ============================================================================
   MINHAS DEMANDAS — a fila do trabalho DIRIGIDO.
   ----------------------------------------------------------------------------
   Esta tela é nova no protótipo e é a metade do serviço que faltava. Até aqui o
   aplicativo só sabia do trabalho que o fiscal descobre andando; o cliente
   mostrou que o outro caminho é igualmente rotineiro: o administrativo recebe
   um processo do e-Salvador, uma reclamação do 156 ou um pedido de licença
   nova, e encaminha para a EQUIPE da área onde fica o endereço.

   Duas escolhas de tela que vêm direto disso:

   1. O cabeçalho diz DE QUEM é a fila — "Equipe C2 · Área 1 — Centro". Não é
      enfeite: a demanda não chega para o fiscal, chega para a equipe, e
      qualquer um dela pode atender. Sem esse rótulo, o fiscal não entende por
      que aparece na lista um endereço que não é o dele.
   2. A ordenação é por PRAZO, sempre — vencido primeiro. Numa fila de prazos,
      qualquer outra ordem é uma opinião; esta é a única que evita perder data.

   O filtro por origem existe porque as três origens pedem posturas diferentes:
   licença nova é vistoria prévia (ninguém está irregular ainda), 156 é
   reclamação de vizinho, e-Salvador é processo com peça juntada.
   ============================================================================ */

type Filtro = 'todas' | Origem;

export function TelaDemandas() {
    const { registros } = useApp();
    const [filtro, setFiltro] = useState<Filtro>('todas');

    /* Uma demanda já atendida no turno não desaparece da fila — ela ganha o
       selo de atendida. Sumir seria pior: o fiscal perde a confirmação de que
       fez, e o encarregado perde a prova. */
    const atendidas = useMemo(
        () => new Set(registros.map((r) => r.demandaId).filter((d): d is string => Boolean(d))),
        [registros],
    );

    const lista = useMemo(
        () => (filtro === 'todas' ? DEMANDAS_ABERTAS : DEMANDAS_ABERTAS.filter((d) => d.origem === filtro)),
        [filtro],
    );

    const vencidas = lista.filter((d) => d.prazoDias < 0).length;
    const hoje = lista.filter((d) => d.prazoDias === 0).length;

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
                    O administrativo encaminha cada processo para a equipe da área do endereço. Toda a
                    equipe vê esta fila.
                </p>

                <div className="pw-linha" style={{ gap: 8, flexWrap: 'wrap', margin: '14px 0 0' }}>
                    <Selo tom={vencidas > 0 ? 'perigo' : 'ok'}>
                        <Icone nome="relogio" tamanho={13} />
                        {vencidas === 1 ? '1 vencida' : `${vencidas} vencidas`}
                    </Selo>
                    <Selo tom="alerta">{hoje === 1 ? '1 vence hoje' : `${hoje} vencem hoje`}</Selo>
                    <Selo tom="neutro">
                        {lista.length === 1 ? '1 na fila' : `${lista.length} na fila`}
                    </Selo>
                </div>

                <div className="pw-chips" style={{ marginTop: 14, gap: 8 }}>
                    <button
                        type="button"
                        className={classes('pw-chip', filtro === 'todas' && 'pw-chip-ligado')}
                        onClick={() => setFiltro('todas')}
                        aria-pressed={filtro === 'todas'}
                    >
                        Todas
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

                <p className="pw-titulo-secao">Encaminhadas à equipe</p>

                {lista.length === 0 ? (
                    <Vazio
                        icone="📭"
                        titulo="Nada nesta origem"
                        texto="Toque em Todas para ver a fila inteira da equipe."
                    />
                ) : (
                    lista.map((demanda) => (
                        <CartaoDemanda
                            key={demanda.id}
                            demanda={demanda}
                            atendida={atendidas.has(demanda.id)}
                        />
                    ))
                )}
            </div>
        </div>
    );
}

export function CartaoDemanda({
    demanda,
    atendida,
    compacto,
}: {
    demanda: Demanda;
    atendida: boolean;
    /** Na tela de Início o cartão entra menor: lá ele é chamada, não trabalho. */
    compacto?: boolean;
}) {
    const origem = ORIGENS[demanda.origem];

    return (
        <div className={classes('pw-card', 'pw-card-demanda', demanda.prazoDias < 0 && 'pw-card-vencido')}>
            <div className="pw-linha-espalha" style={{ gap: 10, marginBottom: 8 }}>
                <span className="pw-selo pw-selo-origem">
                    <span>{origem.emoji}</span>
                    {origem.curto}
                </span>
                <Selo tom={tomDoPrazo(demanda.prazoDias)}>
                    <Icone nome="relogio" tamanho={13} />
                    {prazoEmPalavras(demanda.prazoDias)}
                </Selo>
            </div>

            <p className="pw-forte" style={{ margin: 0, fontSize: 16.5, lineHeight: 1.3 }}>
                {demanda.assunto}
            </p>
            <p className="pw-fraco" style={{ margin: '4px 0 0', fontSize: 12.5, letterSpacing: '0.02em' }}>
                {demanda.protocolo}
            </p>

            <p style={{ margin: '10px 0 0', fontSize: 14.5 }}>
                <Icone nome="mapa" tamanho={14} /> {demanda.endereco}
            </p>
            <p className="pw-fraco" style={{ margin: '2px 0 0' }}>
                {demanda.bairro} · prazo {demanda.prazoBr}
            </p>

            {!compacto && (
                <p style={{ margin: '10px 0 0', fontSize: 14, color: 'var(--pw-texto-corpo)' }}>
                    {demanda.detalhe}
                </p>
            )}

            {demanda.sgci && (
                <p className="pw-fraco" style={{ margin: '10px 0 0', fontSize: 13 }}>
                    <Icone nome="prancheta" tamanho={13} /> Permissionário no cadastro SGCI ·{' '}
                    {demanda.sgci.equipamento}
                </p>
            )}

            <div className="pw-linha" style={{ gap: 8, marginTop: 14 }}>
                <button
                    type="button"
                    className="pw-btn pw-btn-acao pw-btn-pequeno"
                    onClick={() => irPara(`registrar/${demanda.id}`)}
                >
                    <Icone nome="mais" tamanho={17} />
                    Fiscalizar
                </button>
                <button
                    type="button"
                    className="pw-btn pw-btn-contorno pw-btn-pequeno"
                    onClick={() => irPara(`demanda/${demanda.id}`)}
                >
                    Detalhes
                </button>
            </div>

            {atendida && (
                <p className="pw-fraco" style={{ margin: '10px 0 0' }}>
                    <Selo tom="ok">
                        <Icone nome="certo" tamanho={13} /> Já fiscalizada neste turno
                    </Selo>
                </p>
            )}
        </div>
    );
}
