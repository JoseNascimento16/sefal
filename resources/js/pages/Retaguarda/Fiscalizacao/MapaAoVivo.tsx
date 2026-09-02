import { Head, router } from '@inertiajs/react';
import * as L from 'leaflet';
import { ArrowRight, Radio, Send, Sparkles, TriangleAlert, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { SeloPrototipo } from '@/components/retaguarda/selo-prototipo';
import type {
    Equipe,
    FiscalEmCampo,
    Ponto,
    Registro,
    Situacao,
} from '@/dados-prototipo/mapas';
import {
    COR_DO_PINO,
    focoDoDia,
    haQuantosDias,
    haQuantoTempo,
    noRecorte,
    recorteEmPalavras,
} from '@/dados-prototipo/mapas';
import { criarMapa, pinoDaCidade } from '@/lib/mapa';
import { contar } from '@/lib/plural';
import { cn } from '@/lib/utils';
import { index as rotaMapa } from '@/routes/retaguarda/mapa';
import { index as rotaOperacoes } from '@/routes/retaguarda/operacoes';
import { index as rotaAmbulantes } from '@/routes/retaguarda/ambulantes';

/**
 * Mapa ao Vivo — a cidade agora, para o GESTOR.
 *
 * Não é a tela do fiscal. O aplicativo mostra a calçada em que ele está; esta
 * mostra a CIDADE, em escala de operação. A pergunta que ela responde é uma só:
 * **para onde eu mando gente hoje?**
 *
 * ── O desenho: imersivo (RN-07) ─────────────────────────────────────────────
 *
 * O mapa é o FUNDO, sangrando de borda a borda, e a leitura flutua sobre a
 * cidade em painéis de vidro. O menu permanece — o imersivo é sobre o conteúdo.
 * Por baixo das imagens fica o navy com a malha de ruas do resto do sistema:
 * é o que a tela mostra enquanto os tiles chegam, e o que ela mostra se eles não
 * chegarem (a tela degrada para o desenho do mockup, não para um retângulo
 * cinza).
 *
 * ── Uma coisa só grita ──────────────────────────────────────────────────────
 *
 * Só dois tipos de ponto PULSAM: o retorno vencido (alguém prometeu voltar e não
 * voltou) e o que acabou de entrar. Pulso em tudo não destaca nada — e o retorno
 * vencido carrega o "há N dias" colado no pino, porque é a informação que faz o
 * gestor agir e ela não pode depender de um clique.
 *
 * ── Os números saem da lista desenhada ──────────────────────────────────────
 *
 * "A cidade agora", o foco do dia e os últimos registros são agregações dos
 * MESMOS pontos que o mapa está desenhando (RN-06), e por isso mudam junto com o
 * filtro do gestor: filtrar a Equipe C1 e ver o número da cidade inteira seria
 * responder outra pergunta. O recorte vai dito em palavras embaixo dos números,
 * para ninguém ler "7 retornos vencidos" achando que é a cidade toda.
 *
 * ⚠️ PROTÓTIPO: pessoas, horários e situações são inventados; as coordenadas e a
 * estrutura de equipes, não (ver `App\Support\Prototipo\MapasFicticios`). Não há
 * tempo real — a tela diz o instante que está mostrando.
 */

/** Os filtros de situação que o gestor tem — a busca do mapa é o próprio mapa. */
const SITUACOES = [
    { chave: 'todas', rotulo: 'Tudo', cor: COR_DO_PINO.fiscal },
    { chave: 'retorno', rotulo: 'Retorno vencido', cor: COR_DO_PINO.retorno },
    { chave: 'irregular', rotulo: 'Irregular', cor: COR_DO_PINO.irregular },
    { chave: 'regular', rotulo: 'Regular', cor: COR_DO_PINO.regular },
    { chave: 'hoje', rotulo: 'Entrou hoje', cor: COR_DO_PINO.hoje },
] as const;

type Filtro = (typeof SITUACOES)[number]['chave'];

/** O período de leitura dos registros — "hoje" é o que o mapa ao vivo é. */
const PERIODOS = [
    { chave: 'hoje', rotulo: 'Hoje', horas: 24 },
    { chave: 'turno', rotulo: 'Este turno', horas: 6 },
    { chave: 'hora', rotulo: 'Última hora', horas: 1 },
] as const;

type Periodo = (typeof PERIODOS)[number]['chave'];

/** O que o cartão de detalhe mostra: um ponto conhecido ou um registro do dia. */
type Selecionado =
    | { tipo: 'ponto'; ponto: Ponto }
    | { tipo: 'registro'; registro: Registro }
    | { tipo: 'fiscal'; fiscal: FiscalEmCampo };

export default function MapaAoVivo({
    pontos,
    registros,
    fiscais,
    equipes,
    centro,
    momento,
}: {
    pontos: Ponto[];
    registros: Registro[];
    fiscais: FiscalEmCampo[];
    equipes: Equipe[];
    centro: { lat: number; lng: number };
    momento: string;
}) {
    const caixa = useRef<HTMLDivElement>(null);
    const mapaRef = useRef<L.Map | null>(null);
    const camadaRef = useRef<L.LayerGroup | null>(null);

    const [filtro, setFiltro] = useState<Filtro>('todas');
    const [periodo, setPeriodo] = useState<Periodo>('hoje');
    const [equipe, setEquipe] = useState<string | null>(null);
    const [selecionado, setSelecionado] = useState<Selecionado | null>(null);

    const horas = PERIODOS.find((p) => p.chave === periodo)?.horas ?? 24;

    /* ── O recorte do gestor ────────────────────────────────────────────────
       Três filtros, e os três valem ao mesmo tempo: a equipe (ou o turno, quando
       a equipe é a Noturna), a situação e o período. O resultado é o que o mapa
       desenha E o que os painéis contam — uma lista só, para os dois não poderem
       discordar. */
    const pontosNoRecorte = useMemo(
        () => pontos.filter((p) => noRecorte(p, equipe, equipes)),
        [pontos, equipe, equipes],
    );

    const registrosNoRecorte = useMemo(
        () =>
            registros.filter(
                (r) => noRecorte(r, equipe, equipes) && r.ha_minutos <= horas * 60,
            ),
        [registros, equipe, equipes, horas],
    );

    const fiscaisNoRecorte = useMemo(
        () => fiscais.filter((f) => noRecorte(f, equipe, equipes)),
        [fiscais, equipe, equipes],
    );

    const vencidos = useMemo(
        () =>
            pontosNoRecorte
                .filter((p) => p.retorno_ha_dias !== null)
                .sort((a, b) => (b.retorno_ha_dias ?? 0) - (a.retorno_ha_dias ?? 0)),
        [pontosNoRecorte],
    );

    const foco = useMemo(
        () => focoDoDia(registrosNoRecorte, pontos),
        [registrosNoRecorte, pontos],
    );

    const ultimos = useMemo(
        () => [...registrosNoRecorte].sort((a, b) => a.ha_minutos - b.ha_minutos).slice(0, 4),
        [registrosNoRecorte],
    );

    // ── O mapa: criado uma vez, e só ────────────────────────────────────────
    useEffect(() => {
        if (!caixa.current || mapaRef.current) {
            return;
        }

        const mapa = criarMapa(caixa.current, [centro.lat, centro.lng], 12);
        mapaRef.current = mapa;

        /* O mapa nasce antes de o navegador terminar de distribuir a altura da
           tela. Sem esta remedida ele desenha metade das imagens e deixa uma
           faixa vazia no rodapé — o mesmo defeito que apareceu no aplicativo. */
        const ajuste = window.setTimeout(() => mapa.invalidateSize(), 60);

        return () => {
            window.clearTimeout(ajuste);
            mapa.remove();
            mapaRef.current = null;
            camadaRef.current = null;
        };
        // O centro é o da cidade e não muda; recriar o mapa a cada render
        // perderia o zoom que o gestor acabou de dar.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    /* ── Os pinos: refeitos a cada mudança de recorte ───────────────────────
       Uma camada só, trocada inteira. Guardar marcador por marcador para
       esconder e mostrar individualmente daria dois donos ao "o que está no
       mapa" — e o dia em que os dois discordassem, o mapa mostraria um ponto que
       o painel não conta. */
    useEffect(() => {
        const mapa = mapaRef.current;

        if (mapa === null) {
            return;
        }

        camadaRef.current?.remove();

        const camada = L.layerGroup();
        const querSituacao = (s: Situacao | 'retorno') => filtro === 'todas' || filtro === s;

        for (const ponto of pontosNoRecorte) {
            const vencido = ponto.retorno_ha_dias !== null;
            const tipo = vencido ? 'retorno' : ponto.situacao;

            if (!querSituacao(tipo)) {
                continue;
            }

            L.marker([ponto.lat, ponto.lng], {
                icon: pinoDaCidade(
                    tipo,
                    vencido ? haQuantosDias(ponto.retorno_ha_dias ?? 0) : undefined,
                ),
                title: `${ponto.apelido} · ${ponto.bairro}`,
            })
                .on('click', () => setSelecionado({ tipo: 'ponto', ponto }))
                .addTo(camada);
        }

        if (filtro === 'todas' || filtro === 'hoje') {
            for (const registro of registrosNoRecorte) {
                L.marker([registro.lat, registro.lng], {
                    icon: pinoDaCidade('hoje'),
                    title: `${registro.protocolo} · ${registro.bairro}`,
                })
                    .on('click', () => setSelecionado({ tipo: 'registro', registro }))
                    .addTo(camada);
            }
        }

        // Os fiscais aparecem SEMPRE: "quem está na rua agora" não é uma situação
        // de ponto, e esconder a equipe atrás de um filtro de situação faria o
        // gestor mandar reforço para onde já tem gente.
        for (const fiscal of fiscaisNoRecorte) {
            L.marker([fiscal.lat, fiscal.lng], {
                icon: pinoDaCidade('fiscal'),
                title: `${fiscal.nome} · ${fiscal.bairro}`,
            })
                .on('click', () => setSelecionado({ tipo: 'fiscal', fiscal }))
                .addTo(camada);
        }

        camada.addTo(mapa);
        camadaRef.current = camada;
    }, [pontosNoRecorte, registrosNoRecorte, fiscaisNoRecorte, filtro]);

    /**
     * Aproxima a cidade num ponto — é o que o clique no painel faz.
     *
     * O padrão é 15, e não 17: em 17 a rua enche a tela e o gestor perde a
     * vizinhança, que é justamente o que ele foi ver. Aproximar é enquadrar o
     * bairro, não o poste.
     */
    function irAte(lat: number, lng: number, zoom = 15) {
        mapaRef.current?.flyTo([lat, lng], zoom, { duration: 0.9 });
    }

    return (
        <>
            <Head title="Mapa ao Vivo" />

            {/* O FUNDO: a cidade. A malha de ruas fica por baixo das imagens. */}
            <div className="rt-mapa-tela">
                <MalhaDeRuas />
                <div ref={caixa} style={{ position: 'absolute', inset: 0 }} />
            </div>

            {/* A CAMADA de leitura: flutua sobre a cidade, sem bloquear o mapa. */}
            <div className="rt-mapa-camada">
                <div className="rt-mapa-topo">
                    <div>
                        <p className="sobrancelha">Fiscalização</p>
                        <h1>Mapa ao Vivo</h1>
                        <p className="rt-mapa-sub">
                            A cidade em {momento} — o que está registrado, quem está na
                            rua e o que já passou do prazo de retorno.
                        </p>
                    </div>
                </div>

                <div className="rt-mapa-filtros">
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

                    <div className="rt-mapa-grupo">
                        <span className="rt-mapa-rotulo">Situação</span>
                        {SITUACOES.map((s) => (
                            <button
                                key={s.chave}
                                type="button"
                                className={cn(
                                    'rt-chip-vidro',
                                    filtro === s.chave && 'ativo',
                                )}
                                onClick={() => setFiltro(s.chave)}
                            >
                                <i
                                    className="rt-chip-dot"
                                    style={{ color: s.cor }}
                                    aria-hidden
                                />
                                {s.rotulo}
                            </button>
                        ))}
                    </div>

                    <div className="rt-mapa-grupo">
                        <span className="rt-mapa-rotulo">Período</span>
                        {PERIODOS.map((p) => (
                            <button
                                key={p.chave}
                                type="button"
                                className={cn(
                                    'rt-chip-vidro',
                                    periodo === p.chave && 'ativo',
                                )}
                                onClick={() => setPeriodo(p.chave)}
                            >
                                {p.rotulo}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="rt-mapa-corpo">
                    <div className="rt-mapa-coluna">
                        <SeloPrototipo escuro>
                            Pessoas, horários e situações são inventados. As coordenadas
                            são reais, e a área/equipe de cada bairro vem do cadastro de
                            Áreas e Equipes. Não há tempo real: a tela mostra um instante.
                        </SeloPrototipo>

                        <section className="rt-vidro">
                            <h2 className="rt-vidro-titulo">A cidade agora</h2>

                            <div className="rt-mapa-numeros">
                                <span className="rt-mapa-numero">
                                    <b>{registrosNoRecorte.length}</b>
                                    <span>registros no período</span>
                                </span>
                                <span className="rt-mapa-numero">
                                    <b className="alerta">{vencidos.length}</b>
                                    <span>retornos vencidos</span>
                                </span>
                                <span className="rt-mapa-numero">
                                    <b className="acao">{fiscaisNoRecorte.length}</b>
                                    <span>fiscais em campo</span>
                                </span>
                            </div>

                            {/* O recorte DITO: sem isto alguém lê o número achando que
                                é a cidade toda quando um filtro está ligado. */}
                            <p className="rt-vidro-fraco" style={{ marginTop: 13 }}>
                                {recorteEmPalavras(equipe, equipes)} ·{' '}
                                {contar(pontosNoRecorte.length, 'ponto conhecido', 'pontos conhecidos')}{' '}
                                no recorte
                            </p>
                        </section>

                        {foco !== null && (
                            <section className="rt-vidro alerta">
                                <h2 className="rt-vidro-titulo">
                                    <span>Foco do dia · {foco.bairro}</span>
                                    <span className="rt-vidro-selo">{foco.fatia}%</span>
                                </h2>

                                <p>
                                    Maior concentração de ocorrência irregular no período
                                    — {contar(foco.ocorrencias, 'registro', 'registros')} de{' '}
                                    {registrosNoRecorte.length}. Responde a{' '}
                                    <strong>Equipe {foco.equipe}</strong> ({foco.area}), de{' '}
                                    {foco.encarregado}.
                                </p>

                                <button
                                    type="button"
                                    className="rt-vidro-acao"
                                    onClick={() => router.visit(rotaOperacoes())}
                                >
                                    <Sparkles size={15} aria-hidden />
                                    Planejar operação
                                    <ArrowRight size={15} aria-hidden />
                                </button>
                            </section>
                        )}

                        <section className="rt-vidro">
                            <h2 className="rt-vidro-titulo">Últimos registros</h2>

                            {ultimos.length === 0 ? (
                                <p className="rt-vidro-fraco">
                                    Nada entrou neste recorte. Amplie o período ou tire o
                                    filtro de equipe.
                                </p>
                            ) : (
                                ultimos.map((r) => (
                                    <button
                                        key={r.id}
                                        type="button"
                                        className="rt-mapa-item"
                                        onClick={() => {
                                            setSelecionado({ tipo: 'registro', registro: r });
                                            irAte(r.lat, r.lng);
                                        }}
                                    >
                                        <span
                                            className="rt-mapa-item-marca"
                                            style={{
                                                background:
                                                    r.situacao === 'irregular'
                                                        ? 'rgba(224, 112, 32, 0.22)'
                                                        : 'rgba(44, 153, 88, 0.22)',
                                            }}
                                            aria-hidden
                                        >
                                            {r.emoji}
                                        </span>
                                        <span style={{ minWidth: 0, flex: 1 }}>
                                            <span className="rt-mapa-item-nome">
                                                {r.apelido} · {r.ocorrencia}
                                            </span>
                                            <span className="rt-mapa-item-apoio">
                                                {r.bairro} · {haQuantoTempo(r.ha_minutos)} ·{' '}
                                                {r.fiscal}
                                            </span>
                                        </span>
                                    </button>
                                ))
                            )}
                        </section>
                    </div>

                    <div className="rt-mapa-coluna direita">
                        {/* O cartão do ponto escolhido mora no TOPO desta coluna, e
                            não flutuando junto ao pino como no mockup: solto, ele
                            cobria o painel de quem está na rua justamente quando o
                            gestor acabara de clicar num ponto para comparar as duas
                            coisas. Lugar fixo também dá previsibilidade — a resposta
                            do clique aparece sempre no mesmo canto. */}
                        {selecionado !== null && (
                            <CartaoDeDetalhe
                                selecionado={selecionado}
                                onFechar={() => setSelecionado(null)}
                            />
                        )}

                        <section className="rt-vidro">
                            <h2 className="rt-vidro-titulo">
                                <span>Quem está na rua</span>
                                <Radio size={14} aria-hidden />
                            </h2>

                            {fiscaisNoRecorte.length === 0 ? (
                                <p className="rt-vidro-fraco">
                                    Nenhum fiscal em campo neste recorte.
                                </p>
                            ) : (
                                fiscaisNoRecorte.map((f) => (
                                    <button
                                        key={f.id}
                                        type="button"
                                        className="rt-mapa-item"
                                        onClick={() => {
                                            setSelecionado({ tipo: 'fiscal', fiscal: f });
                                            irAte(f.lat, f.lng, 15);
                                        }}
                                    >
                                        <span
                                            className="rt-mapa-item-marca"
                                            style={{
                                                background: 'rgba(124, 196, 255, 0.2)',
                                                color: '#9fd2ff',
                                                fontSize: 12,
                                                fontWeight: 700,
                                            }}
                                            aria-hidden
                                        >
                                            {f.iniciais}
                                        </span>
                                        <span style={{ minWidth: 0, flex: 1 }}>
                                            <span className="rt-mapa-item-nome">
                                                {f.nome}
                                            </span>
                                            <span className="rt-mapa-item-apoio">
                                                {f.bairro} · Equipe {f.equipe} ·{' '}
                                                {haQuantoTempo(f.em_campo_ha)} em campo
                                            </span>
                                        </span>
                                    </button>
                                ))
                            )}
                        </section>

                        {vencidos.length > 0 && (
                            <section className="rt-vidro alerta">
                                <h2 className="rt-vidro-titulo">
                                    <span>Retorno vencido</span>
                                    <TriangleAlert size={14} aria-hidden />
                                </h2>

                                <p className="rt-vidro-fraco">
                                    {contar(vencidos.length, 'ponto', 'pontos')} com prazo
                                    de retorno passado. O mais antigo:{' '}
                                    <strong style={{ color: '#ffb877' }}>
                                        {haQuantosDias(vencidos[0].retorno_ha_dias ?? 0)}
                                    </strong>
                                    .
                                </p>

                                <button
                                    type="button"
                                    className="rt-vidro-acao fantasma"
                                    onClick={() => {
                                        setFiltro('retorno');
                                        setSelecionado({
                                            tipo: 'ponto',
                                            ponto: vencidos[0],
                                        });
                                        irAte(vencidos[0].lat, vencidos[0].lng);
                                    }}
                                >
                                    Ver o mais antigo no mapa
                                    <ArrowRight size={15} aria-hidden />
                                </button>
                            </section>
                        )}
                    </div>
                </div>

                <div className="rt-mapa-legenda" aria-hidden>
                    {SITUACOES.filter((s) => s.chave !== 'todas').map((s) => (
                        <span key={s.chave}>
                            <i className="rt-chip-dot" style={{ color: s.cor }} />
                            {s.rotulo}
                        </span>
                    ))}
                    <span>
                        <i
                            className="rt-chip-dot"
                            style={{ color: COR_DO_PINO.fiscal }}
                        />
                        Fiscal em campo
                    </span>
                </div>
            </div>
        </>
    );
}

/**
 * O cartão de vidro do ponto escolhido — resumo e as ações que dali saem.
 *
 * As ações LEVAM a outras telas (o prontuário do ambulante, o encaminhamento
 * de fiscalização); nenhuma delas grava aqui. Mapa é leitura: quem grava
 * fiscalização é o aplicativo, em rua.
 */
function CartaoDeDetalhe({
    selecionado,
    onFechar,
}: {
    selecionado: Selecionado;
    onFechar: () => void;
}) {
    if (selecionado.tipo === 'fiscal') {
        const f = selecionado.fiscal;

        return (
            <section className="rt-vidro detalhe">
                <h2 className="rt-vidro-titulo">
                    <span>Fiscal em campo</span>
                    <button
                        type="button"
                        className="rt-mapa-fechar"
                        onClick={onFechar}
                        aria-label="Fechar o cartão"
                    >
                        <X size={14} aria-hidden />
                    </button>
                </h2>

                <p style={{ fontWeight: 650, fontSize: 15 }}>{f.nome}</p>

                <dl className="rt-mapa-ficha">
                    <dt>Matrícula</dt>
                    <dd>{f.matricula}</dd>
                    <dt>Equipe</dt>
                    <dd>
                        {f.equipe} · {f.area}
                    </dd>
                    <dt>Último ponto</dt>
                    <dd>{f.bairro}</dd>
                    <dt>Em campo</dt>
                    <dd>{haQuantoTempo(f.em_campo_ha)}</dd>
                    <dt>Registrou hoje</dt>
                    <dd>{contar(f.registros_hoje, 'fiscalização', 'fiscalizações')}</dd>
                </dl>
            </section>
        );
    }

    if (selecionado.tipo === 'registro') {
        const r = selecionado.registro;

        return (
            <section className="rt-vidro detalhe">
                <h2 className="rt-vidro-titulo">
                    <span>Entrou {haQuantoTempo(r.ha_minutos)}</span>
                    <button
                        type="button"
                        className="rt-mapa-fechar"
                        onClick={onFechar}
                        aria-label="Fechar o cartão"
                    >
                        <X size={14} aria-hidden />
                    </button>
                </h2>

                <p style={{ fontWeight: 650, fontSize: 15 }}>
                    {r.emoji} {r.apelido}
                </p>

                <dl className="rt-mapa-ficha">
                    <dt>Protocolo</dt>
                    <dd>{r.protocolo}</dd>
                    <dt>Ocorrência</dt>
                    <dd>{r.ocorrencia}</dd>
                    <dt>Situação</dt>
                    <dd
                        style={{
                            color:
                                r.situacao === 'irregular'
                                    ? COR_DO_PINO.irregular
                                    : COR_DO_PINO.regular,
                            fontWeight: 700,
                        }}
                    >
                        {r.situacao === 'irregular' ? 'Irregular' : 'Regular'}
                    </dd>
                    <dt>Local</dt>
                    <dd>
                        {r.bairro} · {r.area}
                    </dd>
                    <dt>Fiscal</dt>
                    <dd>{r.fiscal}</dd>
                </dl>
            </section>
        );
    }

    const p = selecionado.ponto;
    const vencido = p.retorno_ha_dias !== null;

    return (
        <section className={cn('rt-vidro', 'detalhe', vencido && 'alerta')}>
            <h2 className="rt-vidro-titulo">
                <span>
                    {vencido
                        ? `Retorno vencido · ${haQuantosDias(p.retorno_ha_dias ?? 0)}`
                        : 'Ponto conhecido'}
                </span>
                <button
                    type="button"
                    className="rt-mapa-fechar"
                    onClick={onFechar}
                    aria-label="Fechar o cartão"
                >
                    <X size={14} aria-hidden />
                </button>
            </h2>

            <p style={{ fontWeight: 650, fontSize: 15 }}>
                {p.emoji} {p.apelido}
            </p>
            <p className="rt-vidro-fraco">{p.nome}</p>

            <dl className="rt-mapa-ficha">
                <dt>Atividade</dt>
                <dd>{p.atividade}</dd>
                <dt>Situação</dt>
                <dd
                    style={{
                        color: vencido
                            ? COR_DO_PINO.retorno
                            : p.situacao === 'irregular'
                              ? COR_DO_PINO.irregular
                              : COR_DO_PINO.regular,
                        fontWeight: 700,
                    }}
                >
                    {p.situacao === 'irregular' ? 'Irregular' : 'Regular'}
                </dd>
                <dt>Permissão</dt>
                <dd>{p.permissao ?? 'Sem permissão registrada'}</dd>
                <dt>Local</dt>
                <dd>
                    {p.bairro} · {p.area} ({p.regiao})
                </dd>
                <dt>Equipe</dt>
                <dd>
                    {p.equipe} · {p.encarregado}
                </dd>
                <dt>Última visita</dt>
                <dd>{p.ultima_em}</dd>
            </dl>

            {/* Bairro coberto por mais de uma equipe é caso NORMAL, e o cartão diz
                isso em palavras: o vínculo bairro↔equipe não é 1:1 — quem confirma
                o destino é gente (a mesma conversa da Caixa de Entrada). */}
            {p.tambem_de.length > 0 && (
                <p className="rt-vidro-fraco" style={{ marginTop: 11 }}>
                    Este bairro também é coberto por {p.tambem_de.join(', ')}. Não é
                    duplicidade a corrigir: confirme com quem conhece o ponto.
                </p>
            )}

            <button
                type="button"
                className="rt-vidro-acao"
                onClick={() => router.visit(rotaAmbulantes())}
            >
                Ver prontuário
                <ArrowRight size={15} aria-hidden />
            </button>

            <button
                type="button"
                className="rt-vidro-acao fantasma"
                style={{ marginTop: 8 }}
                onClick={() => router.visit(rotaOperacoes())}
            >
                <Send size={14} aria-hidden />
                Encaminhar fiscalização
            </button>
        </section>
    );
}

/**
 * A malha de ruas por baixo das imagens do mapa.
 *
 * É a mesma cidade decorativa do pé do menu e do splash de boas-vindas — nenhum
 * traço aqui é dado do sistema. Ela existe por um motivo prático: enquanto os
 * tiles não chegam (e se eles não chegarem), a tela mostra o desenho aprovado em
 * vez de um retângulo vazio.
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
                <radialGradient id="mapa-brilho-a">
                    <stop offset="0%" stopColor="#e07020" stopOpacity="0.3" />
                    <stop offset="100%" stopColor="#e07020" stopOpacity="0" />
                </radialGradient>
                <radialGradient id="mapa-brilho-b">
                    <stop offset="0%" stopColor="#0066b2" stopOpacity="0.36" />
                    <stop offset="100%" stopColor="#0066b2" stopOpacity="0" />
                </radialGradient>
            </defs>

            <ellipse cx="1010" cy="330" rx="300" ry="230" fill="url(#mapa-brilho-a)" />
            <ellipse cx="720" cy="640" rx="280" ry="200" fill="url(#mapa-brilho-b)" />

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

MapaAoVivo.layout = {
    imersivo: true,
    breadcrumbs: [{ title: 'Mapa ao Vivo', href: rotaMapa() }],
};
