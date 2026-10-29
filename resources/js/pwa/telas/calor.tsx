import * as L from 'leaflet';
import { useEffect, useMemo, useRef, useState } from 'react';

import { irPara, useApp } from '../app';
import { Topo, atalhoDoPerfil } from '../componentes';
import {
    CENTRO_SALVADOR,
    FISCAL,
    pontosDoCalor,
    regioesMaisQuentes,
    type CamadaDoCalor,
} from '../dados-prototipo';
import { Icone } from '../icones';
import { camadaDeCalor, criarMapa, type CamadaCalor } from '../mapa';
import { centroDaArea } from '../sessao';

/* ============================================================================
   MAPA DE CALOR — o registro virando inteligência.
   ----------------------------------------------------------------------------
   Cada abordagem rápida é uma linha; mil linhas são um DESENHO. Esta tela é a
   razão de o registro ser tão barato de fazer: é aqui que ele volta ao fiscal
   dizendo onde a rua está pedindo presença. A leitura em uma frase vem antes do
   ranking de propósito — quem abre no meio do turno tem trinta segundos.
   ============================================================================ */

const JANELAS = [
    { dias: 7, rotulo: '7 dias' },
    { dias: 30, rotulo: '30 dias' },
    { dias: 90, rotulo: '90 dias' },
];

/* As duas camadas e o que cada uma conta — a frase da tela muda junto. */
const CAMADAS: Record<CamadaDoCalor, { rotulo: string; subtitulo: string; conta: [string, string]; doQue: string }> = {
    irregularidade: {
        rotulo: 'Irregularidade',
        subtitulo: 'Incidência de registros irregulares',
        conta: ['registro', 'registros'],
        doQue: 'dos registros irregulares',
    },
    presenca: {
        rotulo: 'Presença (censo)',
        subtitulo: 'Ambulantes que o censo encontrou',
        conta: ['ambulante', 'ambulantes'],
        doQue: 'dos ambulantes levantados',
    },
};

const GRADIENTE = {
    0.2: '#2b83ba',
    0.4: '#7fcdbb',
    0.6: '#ffffbf',
    0.8: '#fdae61',
    1.0: '#d7191c',
};

export function TelaCalor() {
    const caixa = useRef<HTMLDivElement>(null);
    const mapaRef = useRef<L.Map | null>(null);
    const camadaRef = useRef<CamadaCalor | null>(null);
    const [dias, setDias] = useState(30);
    const [camada, setCamada] = useState<CamadaDoCalor>('irregularidade');
    const texto = CAMADAS[camada];
    const contar = (n: number) => `${n} ${n === 1 ? texto.conta[0] : texto.conta[1]}`;

    const { versao } = useApp();
    const pontos = useMemo(() => pontosDoCalor(dias, camada), [dias, camada, versao]);
    const regioes = useMemo(() => regioesMaisQuentes(dias, camada), [dias, camada, versao]);
    const lider = regioes[0];

    useEffect(() => {
        if (!caixa.current || mapaRef.current) {
            return;
        }

        /* Abre na área da equipe, como o mapa principal — e não num ponto fixo. */
        const centro = centroDaArea() ?? CENTRO_SALVADOR;
        const mapa = criarMapa(caixa.current, [centro.lat, centro.lng], 12);
        mapaRef.current = mapa;

        return () => {
            mapa.remove();
            mapaRef.current = null;
            camadaRef.current = null;
        };
    }, []);

    /* A camada de calor é carregada sob demanda (o plugin só vem para quem abre
       esta tela), e depois apenas trocada de pontos a cada filtro. */
    useEffect(() => {
        let vivo = true;

        const desenhar = async () => {
            const mapa = mapaRef.current;

            if (!mapa) {
                return;
            }

            if (camadaRef.current) {
                camadaRef.current.setLatLngs(pontos);

                return;
            }

            const camada = await camadaDeCalor(pontos, {
                radius: 28,
                blur: 22,
                maxZoom: 16,
                minOpacity: 0.35,
                gradient: GRADIENTE,
            });

            if (!vivo || !mapaRef.current) {
                return;
            }

            camada.addTo(mapaRef.current);
            camadaRef.current = camada;
        };

        void desenhar();

        return () => {
            vivo = false;
        };
    }, [pontos]);

    return (
        <div className="pw-tela">
            <Topo
                titulo="Onde a rua está quente"
                subtitulo={texto.subtitulo}
                perfil={atalhoDoPerfil(() => irPara('perfil'), FISCAL.iniciais)}
            />

            <div className="pw-mapa-area" style={{ flex: '0 0 46vh', minHeight: 280 }}>
                <div ref={caixa} className="pw-mapa" />
                <div className="pw-mapa-flutua" style={{ justifyContent: 'flex-end' }}>
                    <span className="pw-pilula" style={{ cursor: 'default', gap: 10 }}>
                        Menos
                        <span className="pw-legenda-calor" style={{ width: 84 }} />
                        Mais
                    </span>
                </div>
            </div>

            <div className="pw-corpo" style={{ paddingTop: 14 }}>
                {/* Lado a lado, as duas camadas mostram onde há muitos ambulantes
                    e pouca fiscalização. */}
                <div className="pw-periodo" style={{ marginBottom: 10 }}>
                    {(Object.keys(CAMADAS) as CamadaDoCalor[]).map((c) => (
                        <button
                            key={c}
                            type="button"
                            className={c === camada ? 'pw-periodo-ativo' : undefined}
                            onClick={() => setCamada(c)}
                            aria-pressed={c === camada}
                        >
                            {CAMADAS[c].rotulo}
                        </button>
                    ))}
                </div>

                <div className="pw-periodo">
                    {JANELAS.map((j) => (
                        <button
                            key={j.dias}
                            type="button"
                            className={j.dias === dias ? 'pw-periodo-ativo' : undefined}
                            onClick={() => setDias(j.dias)}
                        >
                            {j.rotulo}
                        </button>
                    ))}
                </div>

                {lider && (
                    <p className="pw-leitura" style={{ marginTop: 14 }}>
                        <strong>{lider.nome}</strong> concentra <strong>{lider.fatia}%</strong> {texto.doQue}{' '}
                        dos últimos {dias} dias — {lider.registros} de {pontos.length} pontos.
                        {regioes[1] && (
                            <>
                                {' '}
                                Em seguida vem {regioes[1].nome}, com {regioes[1].fatia}%.
                            </>
                        )}
                    </p>
                )}

                {!lider && (
                    <p className="pw-fraco" style={{ marginTop: 14 }}>
                        {camada === 'presenca'
                            ? `Nenhum levantamento de rua com GPS nos últimos ${dias} dias.`
                            : `Nenhum registro irregular com GPS nos últimos ${dias} dias.`}
                    </p>
                )}

                <p className="pw-titulo-secao">Regiões mais quentes</p>

                {regioes.map((r, i) => (
                    <div key={r.id} className="pw-card">
                        <div className="pw-linha-espalha">
                            <div style={{ minWidth: 0 }}>
                                <p className="pw-forte" style={{ margin: 0, fontSize: 16 }}>
                                    {i + 1}. {r.nome}
                                </p>
                                <p className="pw-fraco" style={{ margin: 0 }}>
                                    {contar(r.registros)} ·{' '}
                                    {r.fatia}% do período
                                </p>
                            </div>
                            <button
                                type="button"
                                className="pw-btn pw-btn-fantasma pw-btn-pequeno"
                                onClick={() => irPara(`mapa/${r.id}`)}
                            >
                                <Icone nome="mapa" tamanho={16} />
                                Fiscalizar
                            </button>
                        </div>
                        <div className="pw-barra-regiao">
                            <div style={{ width: `${Math.max(r.fatia, 4)}%` }} />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
