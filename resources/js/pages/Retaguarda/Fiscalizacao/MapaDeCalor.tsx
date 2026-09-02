import { Head, router } from '@inertiajs/react';
import type * as L from 'leaflet';
import { ArrowRight, Flame, Sparkles, Target } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import BotaoExportar from '@/components/retaguarda/exportar';
import { SeloPrototipo } from '@/components/retaguarda/selo-prototipo';
import type {
    BairroDoCalor,
    Equipe,
    Janela,
    LinhaDoRanking,
    PontoDeCalor,
} from '@/dados-prototipo/mapas';
import {
    GRADIENTE_CSS,
    JANELAS,
    LIMIAR_VARIACAO,
    leituraEmUmaFrase,
    noRecorte,
    ranking,
    recomendacao,
    recorteEmPalavras,
} from '@/dados-prototipo/mapas';
import { camadaDeCalor, criarMapa, GRADIENTE_CALOR, type CamadaCalor } from '@/lib/mapa';
import { contar } from '@/lib/plural';
import { cn } from '@/lib/utils';
import { index as rotaCalor } from '@/routes/retaguarda/mapa-de-calor';
import { index as rotaOperacoes } from '@/routes/retaguarda/operacoes';

/**
 * Mapa de Calor — o registro virando decisão de operação.
 *
 * Cada abordagem registrada em rua é uma linha; mil linhas são um DESENHO. Esta é
 * a tela que devolve o registro ao trabalho: ela diz onde a cidade está pedindo
 * presença, quanto isso mudou desde a última vez que alguém olhou, e de quem é a
 * área.
 *
 * ── A leitura vem ANTES do mapa ─────────────────────────────────────────────
 *
 * A primeira coisa da tela é UMA FRASE ("o Centro Histórico concentra 42% das
 * ocorrências dos últimos 30 dias — 3,1× a média da cidade"). Quem tem trinta
 * segundos entre duas reuniões não interpreta gradiente: lê a frase. A mancha
 * serve para conferir e para achar o recorte; a frase é o que sai da tela na
 * cabeça de quem olhou.
 *
 * ── A variação é contra o PERÍODO ANTERIOR ──────────────────────────────────
 *
 * Não contra a média do ano: a pergunta da operação é "isto está piorando desde
 * a última vez?". Por isso o servidor manda 180 dias mesmo quando a janela é de
 * 90 — sem os 180 a coluna de variação seria invenção. Trocar de janela não é
 * uma nova consulta: é o mesmo dado com outro corte, feito no navegador, e a
 * comparação fica garantidamente coerente com a mancha desenhada.
 *
 * ── A recomendação diz o MOTIVO ─────────────────────────────────────────────
 *
 * Ela não aponta sempre o primeiro do ranking: um bairro em subida forte na
 * segunda posição costuma ser a melhor aposta, porque o líder já é conhecido e
 * provavelmente já tem operação de rotina. E ela escreve qual dos dois motivos
 * está mandando — recomendação sem motivo é adivinhação com aparência de dado.
 *
 * ⚠️ PROTÓTIPO: a incidência é inventada; as coordenadas e a estrutura de
 * equipes, não (ver `App\Support\Prototipo\MapasFicticios`). O botão de operação
 * LEVA ao Cadastro de Operação — quem cria a operação é aquela tela.
 */
export default function MapaDeCalor({
    bairros,
    pontos,
    equipes,
    centro,
    momento,
}: {
    bairros: BairroDoCalor[];
    pontos: PontoDeCalor[];
    equipes: Equipe[];
    centro: { lat: number; lng: number };
    momento: string;
}) {
    const caixa = useRef<HTMLDivElement>(null);
    const mapaRef = useRef<L.Map | null>(null);
    const camadaRef = useRef<CamadaCalor | null>(null);

    const [janela, setJanela] = useState<Janela>(30);
    const [equipe, setEquipe] = useState<string | null>(null);

    /* ── O recorte ──────────────────────────────────────────────────────────
       Filtrar por equipe é filtrar por BAIRRO (o bloco daquela área) — exceto na
       Noturna, cujo recorte é o TURNO: nela o filtro seleciona o que foi
       registrado à noite em qualquer bairro. Ver `noRecorte`. */
    const noRecorteDaEquipe = useMemo(
        () =>
            pontos.filter(([indice, , , , noturno]) =>
                noRecorte(
                    {
                        equipe: bairros[indice].equipe,
                        turno: noturno === 1 ? 'Noturno' : 'Diurno',
                    },
                    equipe,
                    equipes,
                ),
            ),
        [pontos, bairros, equipe, equipes],
    );

    /* A mancha desenha só a JANELA — o que ficou fora dela é o passado com que a
       janela se compara, e pintá-lo na mesma cor faria "90 dias" e "7 dias"
       parecerem a mesma cidade. */
    const pontosDaJanela = useMemo(
        () =>
            noRecorteDaEquipe
                .filter(([, , , dias]) => dias <= janela)
                .map(([, lat, lng]): [number, number, number] => [lat, lng, 0.7]),
        [noRecorteDaEquipe, janela],
    );

    const linhas = useMemo(
        () => ranking(noRecorteDaEquipe, bairros, janela),
        [noRecorteDaEquipe, bairros, janela],
    );

    const frase = useMemo(() => leituraEmUmaFrase(linhas, janela), [linhas, janela]);
    const sugestao = useMemo(() => recomendacao(linhas, janela), [linhas, janela]);
    const topo = useMemo(() => linhas.slice(0, 8), [linhas]);

    // ── O mapa: criado uma vez ──────────────────────────────────────────────
    useEffect(() => {
        if (!caixa.current || mapaRef.current) {
            return;
        }

        const mapa = criarMapa(caixa.current, [centro.lat, centro.lng], 12);
        mapaRef.current = mapa;

        const ajuste = window.setTimeout(() => mapa.invalidateSize(), 60);

        return () => {
            window.clearTimeout(ajuste);
            mapa.remove();
            mapaRef.current = null;
            camadaRef.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    /* A camada de calor é carregada SOB DEMANDA (o plugin só vem para quem abre
       esta tela) e depois apenas trocada de pontos a cada mudança de recorte —
       recriá-la faria a mancha piscar em cada clique de filtro. */
    useEffect(() => {
        let vivo = true;

        void (async () => {
            const mapa = mapaRef.current;

            if (mapa === null) {
                return;
            }

            if (camadaRef.current !== null) {
                camadaRef.current.setLatLngs(pontosDaJanela);

                return;
            }

            const camada = await camadaDeCalor(pontosDaJanela, {
                radius: 34,
                blur: 26,
                maxZoom: 15,
                minOpacity: 0.32,
                gradient: GRADIENTE_CALOR,
            });

            if (!vivo || mapaRef.current === null) {
                return;
            }

            camada.addTo(mapaRef.current);
            camadaRef.current = camada;
        })();

        return () => {
            vivo = false;
        };
    }, [pontosDaJanela]);

    /** Aproxima a cidade num bairro do ranking. */
    function irAte(linha: LinhaDoRanking) {
        const bairro = bairros.find((b) => b.bairro === linha.bairro);

        if (bairro !== undefined) {
            mapaRef.current?.flyTo([bairro.lat, bairro.lng], 14, { duration: 0.9 });
        }
    }

    return (
        <>
            <Head title="Mapa de Calor" />

            <div className="rt-mapa-tela">
                <MalhaDeRuas />
                <div ref={caixa} style={{ position: 'absolute', inset: 0 }} />
            </div>

            <div className="rt-mapa-camada">
                <div className="rt-mapa-topo">
                    <div>
                        <p className="sobrancelha">Fiscalização</p>
                        <h1>Mapa de Calor</h1>
                        <p className="rt-mapa-sub">
                            Onde a ocorrência se concentra, e o quanto isso mudou desde o
                            período anterior — a leitura que decide a próxima operação.
                        </p>
                    </div>
                </div>

                <div className="rt-mapa-filtros">
                    <div className="rt-mapa-grupo">
                        <span className="rt-mapa-rotulo">Período</span>
                        {JANELAS.map((j) => (
                            <button
                                key={j}
                                type="button"
                                className={cn('rt-chip-vidro', janela === j && 'ativo')}
                                onClick={() => setJanela(j)}
                            >
                                {j} dias
                            </button>
                        ))}
                    </div>

                    <div className="rt-mapa-grupo">
                        <span className="rt-mapa-rotulo">Equipe</span>
                        <button
                            type="button"
                            className={cn('rt-chip-vidro', equipe === null && 'ativo')}
                            onClick={() => setEquipe(null)}
                        >
                            Toda Salvador
                        </button>
                        {equipes.map((e) => (
                            <button
                                key={e.equipe}
                                type="button"
                                className={cn(
                                    'rt-chip-vidro',
                                    equipe === e.equipe && 'ativo',
                                )}
                                onClick={() => setEquipe(e.equipe)}
                                title={`${e.area} · ${e.regiao} · ${e.encarregado}`}
                            >
                                {e.equipe}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="rt-mapa-corpo">
                    <div className="rt-mapa-coluna">
                        <SeloPrototipo escuro>
                            A incidência é inventada. As coordenadas são reais, e a
                            área/equipe de cada bairro vem do cadastro de Áreas e Equipes.
                        </SeloPrototipo>

                        {/* A LEITURA — primeira coisa da tela, e de propósito. */}
                        <section className="rt-vidro">
                            <h2 className="rt-vidro-titulo">
                                <span>A leitura do período</span>
                                <Flame size={14} aria-hidden />
                            </h2>

                            {frase === null ? (
                                <p className="rt-vidro-fraco">
                                    Nada registrado neste recorte. Amplie o período ou
                                    tire o filtro de equipe.
                                </p>
                            ) : (
                                <p className="rt-mapa-leitura">
                                    <strong>{frase.destaque}</strong>
                                    {frase.resto}
                                </p>
                            )}

                            <p className="rt-vidro-fraco" style={{ marginTop: 12 }}>
                                {recorteEmPalavras(equipe, equipes)} ·{' '}
                                {contar(pontosDaJanela.length, 'ocorrência', 'ocorrências')}{' '}
                                nos últimos {janela} dias · apurado em {momento}
                            </p>
                        </section>

                        {sugestao !== null && (
                            <section className="rt-vidro alerta">
                                <h2 className="rt-vidro-titulo">
                                    <span>Operação sugerida</span>
                                    <Target size={14} aria-hidden />
                                </h2>

                                <p>
                                    <strong>{sugestao.alvo.bairro}</strong> — {sugestao.alvo.area}{' '}
                                    ({sugestao.alvo.regiao}), Equipe{' '}
                                    {sugestao.alvo.equipe} de {sugestao.alvo.encarregado}.
                                </p>
                                <p className="rt-vidro-fraco" style={{ marginTop: 8 }}>
                                    {sugestao.motivo}
                                </p>

                                <button
                                    type="button"
                                    className="rt-vidro-acao"
                                    onClick={() => router.visit(rotaOperacoes())}
                                >
                                    <Sparkles size={15} aria-hidden />
                                    Abrir Cadastro de Operação
                                    <ArrowRight size={15} aria-hidden />
                                </button>
                            </section>
                        )}
                    </div>

                    <div className="rt-mapa-coluna direita">
                        <section className="rt-vidro">
                            <h2 className="rt-vidro-titulo">
                                <span>Regiões mais quentes</span>
                                {/* A exportação sai do RECORTE VISÍVEL: as linhas que a
                                    tela está mostrando, com o período e a equipe ditos
                                    no contexto do documento. */}
                                <BotaoExportar
                                    titulo="Regiões mais quentes"
                                    subtitulo="Fiscalização › Mapa de Calor"
                                    contexto={`Últimos ${janela} dias · ${recorteEmPalavras(equipe, equipes)} · apurado em ${momento} · PROTÓTIPO (dados fictícios)`}
                                    colunas={[
                                        { chave: 'posicao', titulo: '#', alinhar: 'right' },
                                        { chave: 'bairro', titulo: 'Bairro' },
                                        { chave: 'area', titulo: 'Área' },
                                        { chave: 'regiao', titulo: 'Região' },
                                        { chave: 'equipe', titulo: 'Equipe' },
                                        { chave: 'encarregado', titulo: 'Encarregado' },
                                        {
                                            chave: 'ocorrencias',
                                            titulo: 'Ocorrências',
                                            alinhar: 'right',
                                        },
                                        {
                                            chave: 'fatia_texto',
                                            titulo: '% do período',
                                            alinhar: 'right',
                                        },
                                        {
                                            chave: 'variacao_texto',
                                            titulo: 'Variação',
                                            alinhar: 'right',
                                        },
                                    ]}
                                    linhas={linhas.map((l) => ({
                                        posicao: l.posicao,
                                        bairro: l.bairro,
                                        area: l.area,
                                        regiao: l.regiao,
                                        equipe: l.equipe,
                                        encarregado: l.encarregado,
                                        ocorrencias: l.ocorrencias,
                                        fatia_texto: `${l.fatia}%`,
                                        variacao_texto: textoDaVariacao(l),
                                    }))}
                                    orientacao="landscape"
                                />
                            </h2>

                            {topo.length === 0 ? (
                                <p className="rt-vidro-fraco">
                                    Nenhuma região no recorte.
                                </p>
                            ) : (
                                <div className="rt-mapa-rank">
                                    {topo.map((l) => (
                                        <button
                                            key={l.bairro}
                                            type="button"
                                            className="rt-mapa-rank-linha"
                                            onClick={() => irAte(l)}
                                        >
                                            <span className="rt-mapa-rank-topo">
                                                <span className="rt-mapa-rank-nome">
                                                    {l.posicao}. {l.bairro}
                                                </span>
                                                <span
                                                    className={cn(
                                                        'rt-mapa-var',
                                                        tomDaVariacao(l),
                                                    )}
                                                >
                                                    {textoDaVariacao(l)}
                                                </span>
                                            </span>
                                            <span className="rt-mapa-rank-apoio">
                                                {contar(
                                                    l.ocorrencias,
                                                    'ocorrência',
                                                    'ocorrências',
                                                )}{' '}
                                                · {l.fatia}% do período · Equipe {l.equipe}
                                            </span>
                                            <span className="rt-mapa-rank-barra">
                                                <i
                                                    style={{
                                                        width: `${Math.max(l.fatia, 3)}%`,
                                                    }}
                                                />
                                            </span>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </section>
                    </div>
                </div>

                <div className="rt-mapa-legenda">
                    <span>Menos ocorrência</span>
                    <i
                        className="rt-mapa-regua"
                        style={{ background: GRADIENTE_CSS }}
                        aria-hidden
                    />
                    <span>Mais ocorrência</span>
                    <span style={{ opacity: 0.75 }}>
                        A mancha não leva número: o número está no ranking, a mancha diz
                        onde olhar.
                    </span>
                </div>
            </div>
        </>
    );
}

/** "+38%" / "−12%" / "estável" / "novo no período" — sem "+∞%". */
function textoDaVariacao(linha: LinhaDoRanking): string {
    if (!linha.variacao_conhecida) {
        return 'novo no período';
    }

    if (Math.abs(linha.variacao) < LIMIAR_VARIACAO) {
        return 'estável';
    }

    // O sinal de menos é o TIPOGRÁFICO (−), e não o hífen: em corpo pequeno o
    // hífen ao lado de um número lê como separador.
    return linha.variacao > 0 ? `+${linha.variacao}%` : `−${Math.abs(linha.variacao)}%`;
}

/**
 * O tom da variação. Subir é LARANJA e cair é VERDE — e não o contrário: aqui
 * "mais" é mais ocorrência de irregularidade, então a variação positiva é a má
 * notícia.
 */
function tomDaVariacao(linha: LinhaDoRanking): string {
    if (!linha.variacao_conhecida || Math.abs(linha.variacao) < LIMIAR_VARIACAO) {
        return 'estavel';
    }

    return linha.variacao > 0 ? 'subiu' : 'caiu';
}

/**
 * A malha de ruas por baixo das imagens do mapa — a mesma cidade decorativa do pé
 * do menu. Nenhum traço aqui é dado do sistema: ela existe para a tela mostrar o
 * desenho aprovado enquanto os tiles não chegam, e se eles não chegarem.
 */
function MalhaDeRuas() {
    return (
        <svg
            className="rt-mapa-malha"
            viewBox="0 0 1440 900"
            preserveAspectRatio="xMidYMid slice"
            aria-hidden="true"
        >
            <defs>
                <radialGradient id="calor-brilho-a">
                    <stop offset="0%" stopColor="#e07020" stopOpacity="0.34" />
                    <stop offset="100%" stopColor="#e07020" stopOpacity="0" />
                </radialGradient>
                <radialGradient id="calor-brilho-b">
                    <stop offset="0%" stopColor="#0066b2" stopOpacity="0.32" />
                    <stop offset="100%" stopColor="#0066b2" stopOpacity="0" />
                </radialGradient>
            </defs>

            <ellipse cx="640" cy="380" rx="320" ry="240" fill="url(#calor-brilho-a)" />
            <ellipse cx="1040" cy="650" rx="280" ry="200" fill="url(#calor-brilho-b)" />

            <g stroke="#26537e" strokeOpacity="0.45" strokeWidth="2" fill="none">
                <path d="M-40 200 C 340 160, 700 260, 1080 200 S 1420 150, 1500 190" />
                <path d="M-40 450 C 300 430, 620 500, 960 460 S 1400 410, 1500 450" />
                <path d="M-40 690 C 360 660, 740 740, 1120 690 L 1500 660" />
            </g>
            <g stroke="#1b3a5e" strokeOpacity="0.55" strokeWidth="2" fill="none">
                <path d="M330 -20 C 350 260, 290 560, 350 920" />
                <path d="M700 -20 C 680 240, 750 520, 710 920" />
                <path d="M1060 -20 C 1085 300, 1025 620, 1085 920" />
            </g>
        </svg>
    );
}

MapaDeCalor.layout = {
    imersivo: true,
    breadcrumbs: [{ title: 'Mapa de Calor', href: rotaCalor() }],
};
