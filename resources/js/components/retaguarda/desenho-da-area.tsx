import * as L from 'leaflet';
import { Eraser, Plus, Trash2, Undo2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { CENTRO_SALVADOR, criarMapa } from '@/lib/mapa';

/**
 * O DESENHO da área no mapa (dono, 13/10/2026: "a geometria das áreas").
 *
 * Bloco de bairros desenha um CONTORNO; corredores desenham LINHAS com nome;
 * cidade inteira não tem desenho. Leaflet puro, sem plugin de edição: clicar no
 * mapa acrescenta o próximo ponto, cada ponto pode ser arrastado, e "Desfazer
 * último ponto" volta um passo. É o que o fiscal vê no aplicativo para saber
 * onde termina o trabalho da equipe dele — aproximado, não limite oficial.
 */

export type Ponto = [number, number];
export type Corredor = { nome: string; linha: Ponto[] };

const COR = '#0066B2';

const vertice = (ativo: boolean) =>
    L.divIcon({
        className: '',
        html: `<span style="display:block;width:14px;height:14px;border-radius:50%;background:${ativo ? COR : '#fff'};border:3px solid ${COR};box-shadow:0 1px 3px rgba(0,0,0,.35)"></span>`,
        iconSize: [14, 14],
        iconAnchor: [7, 7],
    });

const centroDe = (pontos: Ponto[]): Ponto | null =>
    pontos.length === 0
        ? null
        : [pontos.reduce((t, p) => t + p[0], 0) / pontos.length, pontos.reduce((t, p) => t + p[1], 0) / pontos.length];

export function DesenhoDaArea({
    recorte,
    contorno,
    corredores,
    somenteLeitura,
    aoMudar,
}: {
    recorte: string;
    contorno: Ponto[] | null;
    corredores: Corredor[] | null;
    somenteLeitura: boolean;
    aoMudar: (contorno: Ponto[] | null, corredores: Corredor[] | null) => void;
}) {
    const caixa = useRef<HTMLDivElement>(null);
    const mapaRef = useRef<L.Map | null>(null);
    const camada = useRef<L.LayerGroup | null>(null);
    const [ativo, setAtivo] = useState(0);

    const pontos = useMemo(() => contorno ?? [], [contorno]);
    const linhas = useMemo(() => corredores ?? [], [corredores]);

    /* O estado mais novo, para os eventos do mapa (criados uma vez só). */
    const atual = useRef({ recorte, pontos, linhas, ativo, somenteLeitura, aoMudar });

    useEffect(() => {
        atual.current = { recorte, pontos, linhas, ativo, somenteLeitura, aoMudar };
    });

    useEffect(() => {
        if (!caixa.current || recorte === 'cidade') {
            return;
        }

        const inicio = centroDe(pontos.length > 0 ? pontos : linhas.flatMap((c) => c.linha));
        const mapa = criarMapa(caixa.current, inicio ?? CENTRO_SALVADOR, inicio ? 13 : 12);
        mapaRef.current = mapa;
        camada.current = L.layerGroup().addTo(mapa);

        mapa.on('click', (e: L.LeafletMouseEvent) => {
            const a = atual.current;

            if (a.somenteLeitura) {
                return;
            }

            const novo: Ponto = [Number(e.latlng.lat.toFixed(6)), Number(e.latlng.lng.toFixed(6))];

            if (a.recorte === 'bairros') {
                a.aoMudar([...a.pontos, novo], null);

                return;
            }

            if (a.linhas.length === 0) {
                a.aoMudar(null, [{ nome: '', linha: [novo] }]);

                return;
            }

            a.aoMudar(
                null,
                a.linhas.map((c, i) => (i === a.ativo ? { ...c, linha: [...c.linha, novo] } : c)),
            );
        });

        return () => {
            mapa.remove();
            mapaRef.current = null;
            camada.current = null;
        };
        // O mapa nasce uma vez por recorte; o desenho é refeito no efeito abaixo.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [recorte]);

    /* Redesenha o contorno ou as linhas a cada mudança. */
    useEffect(() => {
        const grupo = camada.current;

        if (!grupo) {
            return;
        }

        grupo.clearLayers();

        const arrastavel = (ponto: Ponto, mover: (p: Ponto) => void, destaque: boolean) => {
            const marcador = L.marker(ponto, { icon: vertice(destaque), draggable: !somenteLeitura, keyboard: false });

            marcador.on('dragend', () => {
                const p = marcador.getLatLng();
                mover([Number(p.lat.toFixed(6)), Number(p.lng.toFixed(6))]);
            });
            grupo.addLayer(marcador);
        };

        if (recorte === 'bairros') {
            if (pontos.length >= 3) {
                L.polygon(pontos, { color: COR, weight: 2, fillOpacity: 0.12 }).addTo(grupo);
            } else if (pontos.length === 2) {
                L.polyline(pontos, { color: COR, weight: 2, dashArray: '4 6' }).addTo(grupo);
            }

            if (!somenteLeitura) {
                pontos.forEach((p, i) =>
                    arrastavel(p, (novo) => aoMudar(pontos.map((q, j) => (j === i ? novo : q)), null), i === pontos.length - 1),
                );
            }

            return;
        }

        linhas.forEach((c, ci) => {
            if (c.linha.length >= 2) {
                L.polyline(c.linha, { color: COR, weight: ci === ativo ? 6 : 4, opacity: ci === ativo ? 0.9 : 0.55 })
                    .bindTooltip(c.nome || `Corredor ${ci + 1}`)
                    .addTo(grupo);
            }

            if (!somenteLeitura && ci === ativo) {
                c.linha.forEach((p, i) =>
                    arrastavel(
                        p,
                        (novo) =>
                            aoMudar(
                                null,
                                linhas.map((k, kj) => (kj === ci ? { ...k, linha: k.linha.map((q, j) => (j === i ? novo : q)) } : k)),
                            ),
                        i === c.linha.length - 1,
                    ),
                );
            }
        });
    }, [recorte, pontos, linhas, ativo, somenteLeitura, aoMudar]);

    if (recorte === 'cidade') {
        return <p className="form-ajuda">Área que cobre a cidade inteira não tem desenho no mapa.</p>;
    }

    const desfazer = () => {
        if (recorte === 'bairros') {
            aoMudar(pontos.slice(0, -1), null);

            return;
        }

        aoMudar(
            null,
            linhas.map((c, i) => (i === ativo ? { ...c, linha: c.linha.slice(0, -1) } : c)),
        );
    };

    return (
        <div>
            <div
                ref={caixa}
                style={{ height: 360, borderRadius: 12, border: '1px solid var(--sm-borda, #d7dde5)', overflow: 'hidden' }}
                aria-label={recorte === 'bairros' ? 'Contorno da área no mapa' : 'Corredores da área no mapa'}
            />

            {recorte === 'corredores' && (
                <div style={{ display: 'grid', gap: 8, marginTop: 10 }}>
                    {linhas.map((c, i) => (
                        <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <input
                                type="radio"
                                name="corredor-ativo"
                                checked={ativo === i}
                                onChange={() => setAtivo(i)}
                                aria-label={`Desenhar o corredor ${c.nome || i + 1}`}
                                disabled={somenteLeitura}
                            />
                            <input
                                className="form-control"
                                value={c.nome}
                                disabled={somenteLeitura}
                                placeholder="Nome do corredor (ex.: Avenida Sete de Setembro)"
                                maxLength={80}
                                onChange={(e) => aoMudar(null, linhas.map((k, j) => (j === i ? { ...k, nome: e.target.value } : k)))}
                            />
                            <span className="form-ajuda" style={{ whiteSpace: 'nowrap', margin: 0 }}>
                                {c.linha.length} {c.linha.length === 1 ? 'ponto' : 'pontos'}
                            </span>
                            {!somenteLeitura && (
                                <button
                                    type="button"
                                    className="btn btn-secondary btn-sm"
                                    aria-label={`Remover o corredor ${c.nome || i + 1}`}
                                    onClick={() => {
                                        aoMudar(null, linhas.filter((_, j) => j !== i));
                                        setAtivo(0);
                                    }}
                                >
                                    <Trash2 size={15} aria-hidden />
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {!somenteLeitura && (
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 10 }}>
                    {recorte === 'corredores' && (
                        <button
                            type="button"
                            className="btn btn-secondary btn-sm"
                            onClick={() => {
                                aoMudar(null, [...linhas, { nome: '', linha: [] }]);
                                setAtivo(linhas.length);
                            }}
                        >
                            <Plus size={15} aria-hidden /> Novo corredor
                        </button>
                    )}
                    <button type="button" className="btn btn-secondary btn-sm" onClick={desfazer}>
                        <Undo2 size={15} aria-hidden /> Desfazer último ponto
                    </button>
                    {recorte === 'bairros' && pontos.length > 0 && (
                        <button type="button" className="btn btn-secondary btn-sm" onClick={() => aoMudar([], null)}>
                            <Eraser size={15} aria-hidden /> Apagar contorno
                        </button>
                    )}
                </div>
            )}

            <p className="form-ajuda" style={{ marginTop: 8 }}>
                {somenteLeitura
                    ? 'O desenho que o fiscal vê no aplicativo. Para mudar, clique em Editar.'
                    : recorte === 'bairros'
                      ? 'Clique no mapa para acrescentar cada ponto do contorno; arraste um ponto para ajustar. São precisos ao menos 3 pontos.'
                      : 'Escolha o corredor na lista e clique no mapa para traçar a linha dele; arraste um ponto para ajustar.'}{' '}
                O desenho é aproximado: serve para o fiscal saber onde termina o trabalho da equipe, não é limite oficial.
            </p>
        </div>
    );
}
