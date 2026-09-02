import * as L from 'leaflet';
import { useEffect, useMemo, useRef, useState } from 'react';

import { irPara } from '../app';
import { Selo, SeloSituacao, Topo, atalhoDoPerfil, classes } from '../componentes';
import { EQUIPE } from '../dados-demandas';
import { centroDaArea } from '../sessao';
import {
    AMBULANTES,
    CENTRO_SALVADOR,
    FISCAL,
    RETORNOS_PENDENTES,
    emDias,
    nomeRegiao,
    type Ambulante,
} from '../dados-prototipo';
import { Icone } from '../icones';
import { camadaDaArea, criarMapa, dentroDoAnel, pinoAmbulante, pinoDoFiscal } from '../mapa';

/**
 * O ponto cai fora da área da equipe?
 *
 * Só faz sentido perguntar quando a equipe se descreve por bloco de bairros: a
 * Itinerante percorre corredores e a Noturna cobre a cidade inteira, e nessas
 * duas NADA está fora — apagar pinos ali seria mentira.
 */
const foraDaArea = (lat: number, lng: number): boolean =>
    EQUIPE.recorte === 'bairros' && EQUIPE.contorno !== null
        ? !dentroDoAnel(lat, lng, EQUIPE.contorno)
        : false;

/* ============================================================================
   Tela principal — o MAPA.
   ----------------------------------------------------------------------------
   O aplicativo abre no mapa porque é isso que o fiscal está fazendo quando abre
   o aplicativo: olhando a rua. Os pontos conhecidos aparecem por cor; os que
   têm RETORNO marcado e vencido pulsam com o selo de quantos dias já se
   passaram — é a única informação da tela que grita, e grita de propósito.
   ============================================================================ */

export function TelaMapa({ regiaoFoco }: { regiaoFoco: string | null }) {
    const caixa = useRef<HTMLDivElement>(null);
    const mapaRef = useRef<L.Map | null>(null);
    const [selecionado, setSelecionado] = useState<Ambulante | null>(null);
    const [modo, setModo] = useState<'mapa' | 'lista'>('mapa');
    const [posicao, setPosicao] = useState<{ lat: number; lng: number; precisao: number } | null>(null);
    const [posicaoReal, setPosicaoReal] = useState(false);
    /* A delimitação nasce ACESA: a primeira pergunta de quem abre o mapa da
       equipe é "até onde é meu", e responder isso só depois de um toque
       esconderia a resposta. O interruptor existe para quem quer ver a rua
       limpa por baixo do contorno. */
    const [mostrarArea, setMostrarArea] = useState(true);

    /* O mapa abre na ÁREA DA EQUIPE, e não num ponto fixo da cidade: para o
       fiscal da Itinerante, um centro fixo na Boca do Rio deixaria os corredores
       dele fora do quadro. A região focada, quando existe, manda em tudo. */
    const centroPadrao = centroDaArea() ?? CENTRO_SALVADOR;

    const centro = useMemo<[number, number]>(() => {
        const foco = regiaoFoco ? AMBULANTES.find((a) => a.regiao === regiaoFoco) : null;

        return foco ? [foco.lat, foco.lng] : [centroPadrao.lat, centroPadrao.lng];
    }, [regiaoFoco, centroPadrao.lat, centroPadrao.lng]);

    /* Posição do aparelho — de verdade quando o navegador deixa, e a coordenada
       do meio da área da equipe quando não deixa. O protótipo precisa desenhar o
       "eu estou aqui" mesmo num computador de mesa sem GPS. */
    useEffect(() => {
        if (!navigator.geolocation) {
            setPosicao({ ...centroPadrao, precisao: 45 });

            return;
        }

        navigator.geolocation.getCurrentPosition(
            (p) => {
                setPosicao({
                    lat: p.coords.latitude,
                    lng: p.coords.longitude,
                    precisao: Math.round(p.coords.accuracy),
                });
                setPosicaoReal(true);
            },
            () => setPosicao({ ...centroPadrao, precisao: 45 }),
            { enableHighAccuracy: true, timeout: 6000 },
        );
    }, []);

    useEffect(() => {
        if (modo !== 'mapa' || !caixa.current || mapaRef.current) {
            return;
        }

        const mapa = criarMapa(caixa.current, centro, regiaoFoco ? 16 : 15);
        mapaRef.current = mapa;

        /* O mapa nasce antes de o navegador terminar de distribuir a altura da
           tela. Sem esta remedida, ele desenha metade das imagens e deixa uma
           faixa cinza no rodapé — que foi exatamente o que apareceu no celular. */
        const ajuste = window.setTimeout(() => mapa.invalidateSize(), 60);

        for (const ambulante of AMBULANTES) {
            const vencido = ambulante.retornoHaDias !== null;
            const tipo = vencido ? 'retorno' : ambulante.situacao;
            const selo = vencido ? emDias(ambulante.retornoHaDias ?? 0) : undefined;
            const fora = foraDaArea(ambulante.lat, ambulante.lng);

            L.marker([ambulante.lat, ambulante.lng], {
                icon: pinoAmbulante(ambulante.emoji, tipo, selo, fora),
                zIndexOffset: vencido ? 500 : 0,
            })
                .addTo(mapa)
                .on('click', () => {
                    /* Tocar o pino aproxima e sobe a gaveta. O deslocamento para
                       cima é de propósito: sem ele, a gaveta cobriria justamente
                       o ponto que o fiscal acabou de escolher. */
                    mapa.flyTo([ambulante.lat - 0.0016, ambulante.lng], 17, { duration: 0.6 });
                    setSelecionado(ambulante);
                });
        }

        return () => {
            window.clearTimeout(ajuste);
            mapa.remove();
            mapaRef.current = null;
        };
    }, [modo, centro, regiaoFoco]);

    /* A delimitação em efeito PRÓPRIO, e não junto com os pinos: assim acender e
       apagar o contorno não remonta os 25 marcadores do mapa. */
    useEffect(() => {
        const mapa = mapaRef.current;

        if (!mapa || !mostrarArea) {
            return;
        }

        const camada = camadaDaArea({
            contorno: EQUIPE.contorno,
            corredores: EQUIPE.corredores,
            rotulo: `${EQUIPE.area} · ${EQUIPE.areaNome} — ${EQUIPE.nome}`,
        }).addTo(mapa);

        return () => {
            camada.remove();
        };
    }, [modo, centro, regiaoFoco, mostrarArea]);

    /* O pino do fiscal entra quando a posição chega — que costuma ser depois de
       o mapa já estar desenhado. */
    useEffect(() => {
        const mapa = mapaRef.current;

        if (!mapa || !posicao) {
            return;
        }

        const eu = L.marker([posicao.lat, posicao.lng], { icon: pinoDoFiscal(), zIndexOffset: 900 }).addTo(mapa);

        if (posicaoReal && !regiaoFoco) {
            mapa.setView([posicao.lat, posicao.lng], 16);
        }

        return () => {
            eu.remove();
        };
    }, [posicao, posicaoReal, regiaoFoco]);

    const centralizar = () => {
        if (mapaRef.current && posicao) {
            mapaRef.current.setView([posicao.lat, posicao.lng], 17, { animate: true });
        }
    };

    return (
        <div className="pw-tela">
            <Topo
                titulo={regiaoFoco ? `Roteiro · ${nomeRegiao(regiaoFoco)}` : 'Fiscalização em rua'}
                subtitulo={`${EQUIPE.nome} · ${EQUIPE.area} — ${AMBULANTES.length} pontos conhecidos · ${RETORNOS_PENDENTES.length} retornos vencidos`}
                acao={
                    <button
                        type="button"
                        className="pw-topo-botao"
                        onClick={() => setModo((m) => (m === 'mapa' ? 'lista' : 'mapa'))}
                        aria-label={modo === 'mapa' ? 'Ver em lista' : 'Ver no mapa'}
                    >
                        <Icone nome={modo === 'mapa' ? 'lista' : 'mapa'} />
                    </button>
                }
                perfil={atalhoDoPerfil(() => irPara('perfil'), FISCAL.iniciais)}
            />

            {modo === 'mapa' ? (
                <div className="pw-mapa-area pw-mapa-cheio pw-mapa-com-painel">
                    <div ref={caixa} className="pw-mapa" />

                    <div className="pw-mapa-flutua">
                        <button type="button" className="pw-pilula" onClick={centralizar}>
                            <Icone nome="alvo" tamanho={16} />
                            {posicao ? `±${posicao.precisao} m` : 'Localizando…'}
                        </button>
                        <button
                            type="button"
                            className={classes('pw-pilula', mostrarArea && 'pw-pilula-ligada')}
                            onClick={() => setMostrarArea((v) => !v)}
                            aria-pressed={mostrarArea}
                        >
                            <Icone nome="equipe" tamanho={16} />
                            {EQUIPE.area}
                        </button>
                        {!posicaoReal && (
                            <span className="pw-pilula" style={{ cursor: 'default' }}>
                                Posição simulada
                            </span>
                        )}
                    </div>

                    {/* A legenda diz o que o contorno É — e, principalmente, o que
                        ele NÃO é. Contorno em mapa parece exato; este é
                        aproximado, e o fiscal precisa saber disso antes de
                        decidir se um ponto de fronteira é dele. */}
                    {mostrarArea && (
                        <p className="pw-legenda-area">
                            {EQUIPE.recorte === 'cidade'
                                ? `Cobertura da ${EQUIPE.nome}: toda a cidade — o recorte é o turno (${EQUIPE.turno.toLowerCase()}).`
                                : EQUIPE.recorte === 'corredores'
                                  ? `Em azul, os corredores da ${EQUIPE.nome}. Traçado aproximado dos eixos que a equipe percorre.`
                                  : `Em azul, a área da ${EQUIPE.nome} (${EQUIPE.bairros.length} bairros). Contorno aproximado; pontos fora dele são de outra equipe.`}
                        </p>
                    )}

                    <PainelLateral aoSelecionar={setSelecionado} />

                    <button type="button" className="pw-fab" onClick={() => irPara('registrar')}>
                        <Icone nome="mais" tamanho={24} />
                        Fiscalizar
                    </button>

                    {selecionado && (
                        <Gaveta ambulante={selecionado} aoFechar={() => setSelecionado(null)} />
                    )}
                </div>
            ) : (
                <div className="pw-corpo">
                    <Lista aoSelecionar={setSelecionado} />
                    {selecionado && (
                        <Gaveta ambulante={selecionado} aoFechar={() => setSelecionado(null)} />
                    )}
                </div>
            )}
        </div>
    );
}

/* --------------------------- Painel lateral (tablet) --------------------------- */

function PainelLateral({ aoSelecionar }: { aoSelecionar: (a: Ambulante) => void }) {
    return (
        <aside className="pw-painel-lateral">
            <p className="pw-titulo-secao" style={{ marginTop: 4 }}>
                Retornos vencidos
            </p>
            {RETORNOS_PENDENTES.map((a) => (
                <CartaoAmbulante key={a.id} ambulante={a} aoSelecionar={aoSelecionar} />
            ))}
            <p className="pw-titulo-secao">Outros pontos por perto</p>
            {AMBULANTES.filter((a) => a.retornoHaDias === null)
                .slice(0, 8)
                .map((a) => (
                    <CartaoAmbulante key={a.id} ambulante={a} aoSelecionar={aoSelecionar} />
                ))}
        </aside>
    );
}

/* --------------------------------- Lista --------------------------------- */

function Lista({ aoSelecionar }: { aoSelecionar: (a: Ambulante) => void }) {
    const [busca, setBusca] = useState('');

    const semAcento = (texto: string) =>
        texto
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .toLowerCase();

    const filtrados = useMemo(() => {
        const termos = semAcento(busca).split(/\s+/).filter(Boolean);

        return AMBULANTES.filter((a) => {
            const alvo = semAcento(
                `${a.nome} ${a.apelido} ${a.atividade} ${nomeRegiao(a.regiao)} ${a.endereco} ${a.situacao}`,
            );

            return termos.every((t) => alvo.includes(t));
        });
    }, [busca]);

    return (
        <>
            <div style={{ position: 'relative', marginBottom: 14 }}>
                <span
                    style={{
                        position: 'absolute',
                        left: 14,
                        top: 15,
                        color: 'var(--pw-texto-fraco)',
                        pointerEvents: 'none',
                    }}
                >
                    <Icone nome="busca" tamanho={20} />
                </span>
                <input
                    className="pw-entrada"
                    style={{ paddingLeft: 44 }}
                    value={busca}
                    onChange={(e) => setBusca(e.target.value)}
                    placeholder="Buscar por nome, apelido, atividade ou região"
                />
            </div>

            <p className="pw-fraco" style={{ marginTop: 0 }}>
                {filtrados.length === 1 ? '1 ponto encontrado' : `${filtrados.length} pontos encontrados`}
            </p>

            {filtrados.map((a) => (
                <CartaoAmbulante key={a.id} ambulante={a} aoSelecionar={aoSelecionar} />
            ))}
        </>
    );
}

function CartaoAmbulante({
    ambulante,
    aoSelecionar,
}: {
    ambulante: Ambulante;
    aoSelecionar: (a: Ambulante) => void;
}) {
    return (
        <button type="button" className="pw-card pw-card-toque" onClick={() => aoSelecionar(ambulante)}>
            <div className="pw-linha">
                <span style={{ fontSize: 26 }}>{ambulante.emoji}</span>
                <span style={{ flex: 1, minWidth: 0 }}>
                    <span className="pw-forte" style={{ display: 'block' }}>
                        {ambulante.apelido}
                    </span>
                    <span className="pw-fraco">
                        {ambulante.atividade} · {nomeRegiao(ambulante.regiao)}
                    </span>
                </span>
                {ambulante.retornoHaDias !== null ? (
                    <Selo tom="perigo">
                        <Icone nome="relogio" tamanho={13} /> {emDias(ambulante.retornoHaDias ?? 0)}
                    </Selo>
                ) : (
                    <SeloSituacao situacao={ambulante.situacao} />
                )}
            </div>
        </button>
    );
}

/* ------------------------------ Gaveta do ponto ------------------------------ */

function Gaveta({ ambulante, aoFechar }: { ambulante: Ambulante; aoFechar: () => void }) {
    const vencido = ambulante.retornoHaDias !== null;

    return (
        <div className="pw-gaveta" role="dialog" aria-label={`Ponto de ${ambulante.apelido}`}>
            <div className="pw-gaveta-alca" />

            <div className="pw-linha-espalha" style={{ marginBottom: 10 }}>
                <div className="pw-linha">
                    <span style={{ fontSize: 30 }}>{ambulante.emoji}</span>
                    <div>
                        <p className="pw-forte" style={{ margin: 0, fontSize: 18 }}>
                            {ambulante.apelido}
                        </p>
                        <p className="pw-fraco" style={{ margin: 0 }}>
                            {ambulante.nome}
                        </p>
                    </div>
                </div>
                <button type="button" className="pw-btn pw-btn-fantasma pw-btn-pequeno" onClick={aoFechar}>
                    Fechar
                </button>
            </div>

            <div className="pw-linha" style={{ flexWrap: 'wrap', gap: 8, marginBottom: 12 }}>
                <SeloSituacao situacao={ambulante.situacao} />
                {ambulante.permissao ? (
                    <Selo tom="info">
                        <Icone nome="documento" tamanho={13} /> {ambulante.permissao}
                    </Selo>
                ) : (
                    <Selo tom="neutro">Sem permissão registrada</Selo>
                )}
                {ambulante.retornoHaDias !== null && (
                    <Selo tom="perigo">
                        <Icone nome="relogio" tamanho={13} /> Retorno vencido {emDias(ambulante.retornoHaDias ?? 0)}
                    </Selo>
                )}
            </div>

            <p className="pw-fraco" style={{ margin: '0 0 4px' }}>
                {ambulante.endereco} · {nomeRegiao(ambulante.regiao)}
            </p>
            <p className="pw-fraco" style={{ margin: '0 0 12px' }}>
                Última fiscalização em {ambulante.ultimaEm}
            </p>

            <ul className="pw-lista-limpa" style={{ marginBottom: 14 }}>
                {ambulante.historico.map((evento, i) => (
                    <li key={i} className="pw-linha" style={{ alignItems: 'flex-start', marginBottom: 8 }}>
                        <span
                            style={{
                                marginTop: 6,
                                width: 8,
                                height: 8,
                                flex: '0 0 8px',
                                borderRadius: 8,
                                background:
                                    evento.status === 'regular'
                                        ? 'var(--pw-ok)'
                                        : 'var(--pw-irregular)',
                            }}
                        />
                        <span style={{ fontSize: 14 }}>
                            <strong>{evento.data}</strong> — {evento.resumo}
                        </span>
                    </li>
                ))}
            </ul>

            <button
                type="button"
                className="pw-btn pw-btn-acao"
                style={vencido ? { minHeight: 60, fontSize: 17.5 } : undefined}
                onClick={() => irPara(`registrar/${ambulante.id}`)}
            >
                <Icone nome={vencido ? 'alerta' : 'mais'} tamanho={20} />
                {vencido ? 'Abrir fiscalização' : 'Fiscalizar este ponto'}
            </button>

            {vencido && (
                <p className="pw-fraco" style={{ margin: '8px 0 0', textAlign: 'center' }}>
                    O registro já abre com este ambulante e este local preenchidos.
                </p>
            )}
        </div>
    );
}
