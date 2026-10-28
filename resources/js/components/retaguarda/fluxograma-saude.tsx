// Fluxograma da Saúde dos Fluxos — desenho PRÓPRIO em SVG, sem biblioteca de diagrama (veio do
// CODECON, 27/10/2026). A posição de cada quadradinho vem do catálogo do fluxo (coluna/linha na
// grade); aqui só se desenha: caixa, setas e a cor da situação. As setas andam só pelos corredores
// entre as caixas, sem cortar nenhuma e sem uma por cima da outra (ver `fluxograma-roteamento`).
import { Minus, Plus } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { rotear, type Caixa } from './fluxograma-roteamento';

/**
 * A situação de cada quadradinho. Hoje o SEFAL só diz se o trecho é ATENDIDO pelo sistema ou só
 * PREVISTO; os estados de saúde (ok, falha, aviso…) ficam prontos para quando houver verificação
 * do ambiente, como no CODECON.
 */
export type EstadoNo = 'atendido' | 'previsto' | 'ok' | 'falha' | 'aviso' | 'nao_verificado' | 'sem_cobertura';

export interface NoFluxo {
    id: string;
    rotulo: string;
    tipo: 'entrada' | 'etapa' | 'decisao' | 'fim' | 'trava';
    setor: string;
    col: number;
    lin: number;
}

export interface ArestaFluxo {
    de: string;
    para: string;
    rotulo?: string;
}

const COL_W = 216;
const ROW_H = 98;
const NODE_W = 168;
const NODE_H = 50;
const VAO_X = COL_W - NODE_W;
const VAO_Y = ROW_H - NODE_H;
const ORIGEM_X = 40;
const ORIGEM_Y = 34;

/** Cor de cada situação — só variáveis do tema do SEFAL (o modo escuro troca sozinho). */
export const COR_ESTADO: Record<EstadoNo, { traco: string; fundo: string; texto: string; rotulo: string }> = {
    atendido: { traco: 'var(--sm-primaria)', fundo: 'var(--sm-superficie)', texto: 'var(--sm-texto)', rotulo: 'Atendido hoje' },
    // Cinza pontilhado: o que o sistema ainda não atende (dono, 28/10/2026).
    previsto: { traco: 'var(--sm-texto-fraco)', fundo: 'var(--sm-superficie-2)', texto: 'var(--sm-texto-fraco)', rotulo: 'Previsto (ainda não atendido)' },
    ok: { traco: 'var(--sm-ok)', fundo: 'var(--sm-ok-suave)', texto: 'var(--sm-texto)', rotulo: 'Funcionando' },
    falha: { traco: 'var(--sm-perigo)', fundo: 'var(--sm-perigo-suave)', texto: 'var(--sm-texto)', rotulo: 'Quebrado' },
    aviso: { traco: 'var(--sm-aviso)', fundo: 'var(--sm-aviso-suave)', texto: 'var(--sm-texto)', rotulo: 'Atenção' },
    nao_verificado: { traco: 'var(--sm-texto-fraco)', fundo: 'var(--sm-superficie)', texto: 'var(--sm-texto-fraco)', rotulo: 'Não verificado' },
    sem_cobertura: { traco: 'var(--sm-borda-forte)', fundo: 'var(--sm-superficie)', texto: 'var(--sm-texto-fraco)', rotulo: 'Sem verificação' },
};

/** Estados desenhados com contorno TRACEJADO: o trecho ainda não existe ou não foi verificado. */
const TRACEJADO: EstadoNo[] = ['previsto', 'nao_verificado', 'sem_cobertura'];

/** O traço de cada estado tracejado — o previsto é PONTILHADO, para não se confundir com os outros. */
const traco = (e: EstadoNo): string | undefined => (e === 'previsto' ? '1.5 3.5' : TRACEJADO.includes(e) ? '5 4' : undefined);

/** Estados cujas setas ganham os tons frios (cada seta com o seu), em vez da cor da situação. */
const SETA_FRIA: EstadoNo[] = ['atendido', 'ok'];

/**
 * Setas de trecho atendido em tons FRIOS (verde, azul, turquesa) — cada uma com o seu, para o olho
 * seguir uma seta sem confundi-la com a vizinha. Laranja, vermelho e cinza ficam reservados à
 * situação do trecho.
 */
const TONS_FRIOS = ['#0f9d77', '#0284c7', '#0d9488', '#2563eb', '#15803d', '#0891b2', '#4f46e5', '#047857'];

interface Props {
    nos: NoFluxo[];
    arestas: ArestaFluxo[];
    estados: Record<string, EstadoNo>;
    selecionado: string | null;
    onSelecionar: (id: string) => void;
}

const posicao = (n: NoFluxo) => ({ x: ORIGEM_X + n.col * COL_W, y: ORIGEM_Y + n.lin * ROW_H });

/** Limites e passo do zoom do fluxograma. */
const ZOOM_MIN = 0.4;
const ZOOM_MAX = 3;
const ZOOM_PASSO = 0.2;

/**
 * O fluxograma com ZOOM próprio (pedido do dono, 27/10/2026): o zoom do navegador não amplia o
 * desenho, porque ele se encaixa na largura do card. Botões −/Ajustar/+ e Shift + rolagem do mouse,
 * que amplia em torno do ponto sob o cursor. "Ajustar" volta ao encaixe na largura.
 */
export function FluxogramaSaude(props: Props) {
    const moldura = useRef<HTMLDivElement>(null);
    // `null` = ajustado à largura do card (o padrão); número = escala sobre o tamanho natural.
    const [zoom, setZoom] = useState<number | null>(null);
    const zoomRef = useRef<number | null>(null);
    zoomRef.current = zoom;

    // A escala que está na tela agora — no modo ajustado, é a do encaixe.
    const escalaAtual = (): number => {
        if (zoomRef.current !== null) {
            return zoomRef.current;
        }
        const svg = moldura.current?.querySelector('svg');
        const natural = Number(svg?.getAttribute('width')) || 1;

        return Math.min(1, (svg?.getBoundingClientRect().width ?? natural) / natural);
    };
    const limitar = (z: number) => Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, Math.round(z * 100) / 100));
    // A nova escala de um passo — e nunca no sentido contrário: numa tela estreita o encaixe fica
    // abaixo do mínimo, e "diminuir" até o mínimo AMPLIARIA o desenho. `null` = nada a fazer.
    const passo = (sentido: 1 | -1): number | null => {
        const antes = escalaAtual();
        const depois = limitar(antes + sentido * ZOOM_PASSO);

        return (depois - antes) * sentido > 0 ? depois : null;
    };
    const aplicarPasso = (sentido: 1 | -1) => {
        const depois = passo(sentido);
        if (depois !== null) {
            setZoom(depois);
        }
    };

    // Shift + rolagem: listener nativo (o do React é passivo e não consegue impedir a rolagem da página).
    useEffect(() => {
        const el = moldura.current;
        if (!el) {
            return;
        }
        const aoRolar = (ev: WheelEvent) => {
            if (!ev.shiftKey) {
                return;
            }
            ev.preventDefault();
            const antes = escalaAtual();
            const depois = passo(ev.deltaY + ev.deltaX < 0 ? 1 : -1);
            if (depois === null) {
                return;
            }
            // Mantém sob o cursor o mesmo ponto do desenho.
            const caixa = el.getBoundingClientRect();
            const cx = ev.clientX - caixa.left;
            const cy = ev.clientY - caixa.top;
            const px = (el.scrollLeft + cx) / antes;
            const py = (el.scrollTop + cy) / antes;
            setZoom(depois);
            requestAnimationFrame(() => {
                el.scrollLeft = px * depois - cx;
                el.scrollTop = py * depois - cy;
            });
        };
        el.addEventListener('wheel', aoRolar, { passive: false });

        return () => el.removeEventListener('wheel', aoRolar);
    }, []);

    // ARRASTE com o mouse quando ampliado (pedido do dono, 27/10/2026): segurar e puxar move o
    // desenho, com a mãozinha "pegando". Um arraste de verdade (mais de alguns pixels) não vira clique
    // no quadradinho onde começou — senão soltar o mouse abriria o painel do trecho.
    const arraste = useRef<{ x: number; y: number; esquerda: number; topo: number; moveu: boolean } | null>(null);
    const engoliClique = useRef(false);
    const [arrastando, setArrastando] = useState(false);
    const comecarArraste = (ev: React.PointerEvent<HTMLDivElement>) => {
        const el = moldura.current;
        if (zoom === null || !el || ev.pointerType !== 'mouse' || ev.button !== 0) {
            return;
        }
        arraste.current = { x: ev.clientX, y: ev.clientY, esquerda: el.scrollLeft, topo: el.scrollTop, moveu: false };
        const mover = (e: PointerEvent) => {
            const a = arraste.current;
            if (!a) {
                return;
            }
            const dx = e.clientX - a.x;
            const dy = e.clientY - a.y;
            if (!a.moveu && Math.abs(dx) + Math.abs(dy) < 5) {
                return;
            }
            if (!a.moveu) {
                a.moveu = true;
                setArrastando(true);
            }
            e.preventDefault();
            el.scrollLeft = a.esquerda - dx;
            el.scrollTop = a.topo - dy;
        };
        const soltar = () => {
            engoliClique.current = arraste.current?.moveu ?? false;
            arraste.current = null;
            setArrastando(false);
            window.removeEventListener('pointermove', mover);
            window.removeEventListener('pointerup', soltar);
            window.removeEventListener('pointercancel', soltar);
        };
        window.addEventListener('pointermove', mover);
        window.addEventListener('pointerup', soltar);
        window.addEventListener('pointercancel', soltar);
    };
    const talvezEngolirClique = (ev: React.MouseEvent) => {
        if (engoliClique.current) {
            engoliClique.current = false;
            ev.stopPropagation();
            ev.preventDefault();
        }
    };

    const botao = { width: 30, height: 28, padding: 0, display: 'inline-grid', placeItems: 'center' } as const;

    return (
        <div data-fluxograma-moldura>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: 6, marginBottom: 6, fontSize: 12, color: 'var(--sm-texto-fraco)' }}>
                <span>{zoom === null ? 'Shift + rolagem do mouse amplia' : 'Shift + rolagem amplia · arraste para mover'}</span>
                <button type="button" className="btn btn-secondary btn-sm" style={botao} data-zoom="menos" title="Diminuir" aria-label="Diminuir o fluxograma" onClick={() => aplicarPasso(-1)} disabled={zoom !== null && zoom <= ZOOM_MIN}>
                    <Minus size={15} aria-hidden />
                </button>
                <button type="button" className="btn btn-secondary btn-sm" data-zoom="ajustar" title="Encaixar na largura" onClick={() => setZoom(null)} style={{ minWidth: 64 }}>
                    {zoom === null ? 'Ajustado' : `${Math.round(zoom * 100)}%`}
                </button>
                <button type="button" className="btn btn-secondary btn-sm" style={botao} data-zoom="mais" title="Ampliar" aria-label="Ampliar o fluxograma" onClick={() => aplicarPasso(1)} disabled={zoom !== null && zoom >= ZOOM_MAX}>
                    <Plus size={15} aria-hidden />
                </button>
            </div>
            {/* A mãozinha "pegando" vale também sobre os quadradinhos (que têm cursor de clique). */}
            <style>{'[data-fluxograma-area][data-arrastando="1"], [data-fluxograma-area][data-arrastando="1"] * { cursor: grabbing !important; }'}</style>
            <div
                ref={moldura}
                data-fluxograma-area
                data-arrastando={arrastando ? '1' : '0'}
                onPointerDown={comecarArraste}
                onClickCapture={talvezEngolirClique}
                style={{ overflow: 'auto', maxHeight: zoom === null ? undefined : '78vh', cursor: zoom === null ? undefined : 'grab', userSelect: arrastando ? 'none' : undefined }}
            >
                <Desenho {...props} zoom={zoom} />
            </div>
        </div>
    );
}

function Desenho({ nos, arestas, estados, selecionado, onSelecionar, zoom }: Props & { zoom: number | null }) {
    const linhas = Math.max(...nos.map((n) => n.lin)) + 1;
    const colunas = Math.max(...nos.map((n) => n.col)) + 1;
    const corredorDireito = ORIGEM_X + colunas * COL_W - VAO_X / 2;
    const largura = corredorDireito + 2 * VAO_X;
    const altura = ORIGEM_Y + linhas * ROW_H + VAO_Y / 2;

    const rotas = useMemo(() => {
        const caixas: Caixa[] = nos.map((n) => ({ id: n.id, ...posicao(n), w: NODE_W, h: NODE_H }));
        const corredoresX = [...Array.from({ length: colunas + 1 }, (_, c) => ORIGEM_X + c * COL_W - VAO_X / 2), corredorDireito + VAO_X];
        const corredoresY = Array.from({ length: linhas + 1 }, (_, r) => ORIGEM_Y + r * ROW_H - VAO_Y / 2);

        return rotear({ caixas, corredoresX, corredoresY, vaoX: VAO_X, vaoY: VAO_Y }, arestas);
    }, [nos, arestas, colunas, linhas, corredorDireito]);

    const estado = (id: string): EstadoNo => estados[id] ?? 'atendido';
    const corDaSeta = (i: number, e: EstadoNo) => (SETA_FRIA.includes(e) ? TONS_FRIOS[i % TONS_FRIOS.length] : COR_ESTADO[e].traco);
    const marcadores = [...(Object.keys(COR_ESTADO) as EstadoNo[]).filter((e) => !SETA_FRIA.includes(e)).map((e) => ({ id: `seta-${e}`, cor: COR_ESTADO[e].traco })), ...TONS_FRIOS.map((cor, i) => ({ id: `seta-fria-${i}`, cor }))];

    return (
        <svg
            data-fluxograma
            width={largura}
            height={altura}
            viewBox={`0 0 ${largura} ${altura}`}
            role="img"
            aria-label="Fluxograma do trabalho, colorido pela situação de cada trecho"
            // Ajustado: cabe na largura do card (encolhe junto), sem esticar além do natural. Com zoom:
            // o tamanho natural vezes a escala, e a moldura rola.
            style={zoom === null
                ? { display: 'block', fontFamily: 'inherit', width: '100%', maxWidth: largura, height: 'auto' }
                : { display: 'block', fontFamily: 'inherit', width: largura * zoom, maxWidth: 'none', height: altura * zoom }}
        >
            <defs>
                {marcadores.map((m) => (
                    <marker key={m.id} id={m.id} viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                        <path d="M 0 0 L 10 5 L 0 10 z" fill={m.cor} />
                    </marker>
                ))}
            </defs>

            {arestas.map((a, i) => {
                const rota = rotas[i];
                if (!rota) {
                    return null;
                }
                const e = estado(a.para);
                const cor = corDaSeta(i, e);
                const d = rota.pontos.map((p, k) => `${k === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');

                return (
                    <g key={`${a.de}-${a.para}-${i}`} data-aresta={`${a.de}>${a.para}`} data-estado={e}>
                        <path d={d} fill="none" stroke={cor} strokeWidth={e === 'falha' ? 2.4 : 1.7} strokeLinejoin="round" strokeDasharray={traco(e)} strokeLinecap={e === 'previsto' ? 'round' : undefined} markerEnd={`url(#${SETA_FRIA.includes(e) ? `seta-fria-${i % TONS_FRIOS.length}` : `seta-${e}`})`} />
                        {a.rotulo && (
                            <text x={rota.rotulo.x} y={rota.rotulo.y} textAnchor={rota.rotulo.ancora} style={{ fontSize: 10.5, fill: SETA_FRIA.includes(e) ? cor : 'var(--sm-texto-fraco)', fontWeight: 600, paintOrder: 'stroke', stroke: 'var(--sm-superficie)', strokeWidth: 4 }}>
                                {a.rotulo}
                            </text>
                        )}
                    </g>
                );
            })}

            {nos.map((n) => {
                const { x, y } = posicao(n);
                const e = estado(n.id);
                const cor = COR_ESTADO[e];
                const ativo = selecionado === n.id;
                const raio = n.tipo === 'decisao' || n.tipo === 'entrada' ? 25 : 8;
                const linhasTexto = n.rotulo.split('\n');

                return (
                    <g
                        key={n.id}
                        data-no={n.id}
                        data-estado={e}
                        role="button"
                        tabIndex={0}
                        aria-label={`${n.rotulo.replace('\n', ' — ')}: ${cor.rotulo}`}
                        aria-pressed={ativo}
                        onClick={() => onSelecionar(n.id)}
                        onKeyDown={(ev) => {
                            if (ev.key === 'Enter' || ev.key === ' ') {
                                ev.preventDefault();
                                onSelecionar(n.id);
                            }
                        }}
                        style={{ cursor: 'pointer', outline: 'none' }}
                    >
                        <title>{`${n.rotulo.replace('\n', ' — ')} · ${cor.rotulo}`}</title>
                        <rect x={x} y={y} width={NODE_W} height={NODE_H} rx={raio} fill={cor.fundo} stroke={ativo ? 'var(--sm-acao-forte)' : cor.traco} strokeWidth={ativo ? 3 : e === 'falha' ? 2.4 : 1.6} strokeDasharray={traco(e)} strokeLinecap={e === 'previsto' ? 'round' : undefined} />
                        {n.tipo === 'fim' && <rect x={x + 3} y={y + 3} width={NODE_W - 6} height={NODE_H - 6} rx={raio - 2} fill="none" stroke={cor.traco} strokeWidth={1} />}
                        {n.tipo === 'trava' && <rect x={x} y={y} width={6} height={NODE_H} rx={3} fill={cor.traco} />}
                        <text x={x + NODE_W / 2} y={y + NODE_H / 2 - (linhasTexto.length - 1) * 6.5 + 4} textAnchor="middle" style={{ fontSize: 11.5, fontWeight: 600, fill: cor.texto }}>
                            {linhasTexto.map((t, i) => (
                                <tspan key={i} x={x + NODE_W / 2} dy={i === 0 ? 0 : 13} style={i > 0 ? { fontWeight: 400, fontSize: 10.5 } : undefined}>
                                    {t}
                                </tspan>
                            ))}
                        </text>
                        {e === 'falha' && <circle cx={x + NODE_W - 8} cy={y + 8} r={5} fill={cor.traco} />}
                    </g>
                );
            })}
        </svg>
    );
}
