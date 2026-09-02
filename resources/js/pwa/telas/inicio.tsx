import * as L from 'leaflet';
import { useEffect, useRef } from 'react';

import { irPara, useApp } from '../app';
import { Selo, Topo, atalhoDoPerfil } from '../componentes';
import {
    AMBULANTES,
    CENTRO_SALVADOR,
    DATA_POR_EXTENSO,
    FISCAL,
    HOJE_BR,
    OPERACOES,
    RETORNOS_PENDENTES,
    emDias,
    nomeRegiao,
    saudacao,
    type Operacao,
} from '../dados-prototipo';
import { Icone } from '../icones';
import { criarMapa, pinoAmbulante } from '../mapa';

/* ============================================================================
   INÍCIO — a primeira tela do plantão.
   ----------------------------------------------------------------------------
   O fiscal abre o aplicativo antes de sair da base: ele quer saber o que tem
   pela frente, não operar nada ainda. Então esta tela RESPONDE, não pergunta —
   quantos registros ele já fez hoje, quantos retornos venceram, o que está na
   fila para subir e quais operações a chefia marcou.

   O mapa aparece aqui como PRÉVIA: mostra a região com os pontos, não aceita
   arrasto nem zoom, e o toque leva à aba do mapa. É um convite, não uma
   ferramenta — a ferramenta é a tela ao lado.
   ============================================================================ */

export function TelaInicio() {
    const { registros, pendentes } = useApp();
    const doDia = registros.filter((r) => r.dataBr === HOJE_BR);
    const irregulares = doDia.filter((r) => r.status === 'irregular').length;

    return (
        <div className="pw-tela">
            <Topo
                titulo={`${saudacao()}, ${FISCAL.nome.split(' ')[0]}`}
                subtitulo={DATA_POR_EXTENSO}
                perfil={atalhoDoPerfil(() => irPara('perfil'), FISCAL.iniciais)}
            />

            <div className="pw-corpo">
                <div className="pw-numeros">
                    <Numero
                        valor={doDia.length}
                        rotulo={doDia.length === 1 ? 'registro hoje' : 'registros hoje'}
                        icone="lista"
                        tom="var(--pw-primaria)"
                        aoTocar={() => irPara('registros')}
                    />
                    <Numero
                        valor={RETORNOS_PENDENTES.length}
                        rotulo={
                            RETORNOS_PENDENTES.length === 1 ? 'retorno vencido' : 'retornos vencidos'
                        }
                        icone="relogio"
                        tom="var(--pw-perigo)"
                        aoTocar={() => irPara('mapa')}
                    />
                    <Numero
                        valor={pendentes}
                        rotulo={pendentes === 1 ? 'aguarda envio' : 'aguardam envio'}
                        icone="nuvem"
                        tom="var(--pw-alerta)"
                        aoTocar={() => irPara('sincronizacao')}
                    />
                </div>

                <p className="pw-titulo-secao">Sua rua agora</p>

                <PreviaDoMapa />

                <div className="pw-linha" style={{ gap: 8, marginTop: 10 }}>
                    <Selo tom="alerta">
                        <Icone nome="alerta" tamanho={13} />
                        {irregulares === 1
                            ? '1 local irregular hoje'
                            : `${irregulares} locais irregulares hoje`}
                    </Selo>
                    <Selo tom="neutro">
                        {AMBULANTES.length} pontos conhecidos
                    </Selo>
                </div>

                <p className="pw-titulo-secao">Operações agendadas</p>

                {OPERACOES.map((operacao) => (
                    <CartaoOperacao key={operacao.id} operacao={operacao} />
                ))}

                <button
                    type="button"
                    className="pw-btn pw-btn-acao"
                    style={{ marginTop: 18, minHeight: 58 }}
                    onClick={() => irPara('registrar')}
                >
                    <Icone nome="mais" tamanho={20} />
                    Registrar fiscalização
                </button>
            </div>
        </div>
    );
}

function Numero({
    valor,
    rotulo,
    icone,
    tom,
    aoTocar,
}: {
    valor: number;
    rotulo: string;
    icone: 'lista' | 'relogio' | 'nuvem';
    tom: string;
    aoTocar: () => void;
}) {
    return (
        <button type="button" className="pw-numero" onClick={aoTocar}>
            <span className="pw-numero-icone" style={{ color: tom }}>
                <Icone nome={icone} tamanho={18} />
            </span>
            <span className="pw-numero-valor" style={{ color: tom }}>
                {valor}
            </span>
            <span className="pw-fraco" style={{ fontSize: 12, lineHeight: 1.25 }}>
                {rotulo}
            </span>
        </button>
    );
}

/**
 * Prévia do mapa: mesmo desenho da aba do mapa, com os controles desligados.
 * Ela não é uma imagem — os pontos são os mesmos —, mas se comporta como uma:
 * o toque em qualquer lugar leva ao mapa de verdade.
 */
function PreviaDoMapa() {
    const caixa = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!caixa.current) {
            return;
        }

        const mapa = criarMapa(caixa.current, [CENTRO_SALVADOR.lat, CENTRO_SALVADOR.lng], 14, {
            controles: false,
        });

        mapa.dragging.disable();
        mapa.scrollWheelZoom.disable();
        mapa.doubleClickZoom.disable();
        mapa.touchZoom.disable();
        mapa.keyboard.disable();

        for (const ambulante of AMBULANTES.slice(0, 12)) {
            const vencido = ambulante.retornoHaDias !== null;

            L.marker([ambulante.lat, ambulante.lng], {
                icon: pinoAmbulante(ambulante.emoji, vencido ? 'retorno' : ambulante.situacao),
                interactive: false,
            }).addTo(mapa);
        }

        return () => {
            mapa.remove();
        };
    }, []);

    return (
        <button type="button" className="pw-previa" onClick={() => irPara('mapa')}>
            <span ref={caixa} className="pw-previa-mapa" />
            <span className="pw-previa-veu">
                <span className="pw-pilula">
                    <Icone nome="mapa" tamanho={16} />
                    Abrir o mapa
                </span>
            </span>
        </button>
    );
}

function CartaoOperacao({ operacao }: { operacao: Operacao }) {
    const tom = operacao.tom === 'agora' ? 'perigo' : operacao.tom === 'proxima' ? 'alerta' : 'info';
    const rotulo =
        operacao.tom === 'agora' ? 'Hoje' : operacao.tom === 'proxima' ? 'Amanhã' : 'Programada';

    return (
        <button type="button" className="pw-card pw-card-toque" onClick={() => irPara(`mapa/${operacao.regiao}`)}>
            <div className="pw-linha-espalha" style={{ marginBottom: 6 }}>
                <span className="pw-forte" style={{ fontSize: 16.5 }}>
                    {operacao.nome}
                </span>
                <Selo tom={tom}>{rotulo}</Selo>
            </div>

            <p style={{ margin: 0, fontSize: 14.5 }}>{operacao.local}</p>
            <p className="pw-fraco" style={{ margin: '2px 0 10px' }}>
                {nomeRegiao(operacao.regiao)} · {operacao.quando} · {operacao.horario}
            </p>

            <div className="pw-linha" style={{ gap: 8 }}>
                <Selo tom="neutro">
                    <Icone nome="pessoa" tamanho={13} />
                    {operacao.fiscais === 1 ? '1 fiscal' : `${operacao.fiscais} fiscais`}
                </Selo>
                <span className="pw-fraco" style={{ fontSize: 13 }}>
                    {operacao.observacao}
                </span>
            </div>
        </button>
    );
}

/** Usado pela aba do mapa para dizer, em uma linha, o que está vencido. */
export const resumoDeRetornos = (): string =>
    RETORNOS_PENDENTES.length === 0
        ? 'Nenhum retorno vencido'
        : `Retorno mais antigo: ${RETORNOS_PENDENTES[0].apelido}, ${emDias(RETORNOS_PENDENTES[0].retornoHaDias ?? 0)}`;
