import { useState } from 'react';
import { Check, Inbox, Layers, Link2, Search, Store, Unlink, X } from 'lucide-react';

import { BotaoAcao } from '@/components/retaguarda/acao';
import { Sobreposicao } from '@/components/retaguarda/sobreposicao';
import { useEnvio } from '@/hooks/use-envio';
import { dataBR } from '@/lib/datas';

/*
 * A PRÉ-TRIAGEM: dez denúncias que são um fato.
 *
 * O e-Salvador não entrega casos organizados — entrega o que cada cidadão
 * escreveu. Dez pessoas relatam "mesas e cadeiras atrapalhando a via" em dez
 * protocolos e, quando a equipe chega, é o mesmo ambulante. Este painel é onde
 * o coordenador junta os dez num registro só, ANTES de mandar alguém à rua.
 *
 * ── Por que a proposta abre com os DOIS RELATOS lado a lado ─────────────────
 *
 * Aceitar junta casos de cidadãos diferentes: a resposta da fiscalização passa
 * a valer para os dois, e o protocolo de um deles deixa de andar sozinho.
 * Ninguém deve decidir isso lendo "DEN-0031 parece DEN-0029, 87%" — o que
 * convence é ver o que cada um escreveu.
 *
 * Por isso a confiança aparece, mas discreta, e o MOTIVO aparece por extenso: o
 * coordenador precisa poder discordar do raciocínio, e não só do resultado.
 *
 * ── Recusar exige o porquê, e é de propósito ────────────────────────────────
 *
 * A recusa é a informação mais cara desta tela: é o que impede a varredura de
 * propor o mesmo par amanhã. Um assistente que insiste no que já foi negado faz
 * o coordenador parar de ler a lista — e aí as propostas boas se perdem junto.
 */

/** Um dos lados da proposta, como o servidor o entrega. */
export interface LadoDaSugestao {
    id: number;
    protocolo: string;
    numero_origem: string;
    canal: string;
    assunto: string;
    /** Nome de FACHADA do que foi denunciado — o que decide "é o mesmo bar?". */
    estabelecimento: string;
    /** Pessoa ou razão social por trás do estabelecimento. */
    denunciado: string;
    relato: string;
    endereco: string;
    referencia: string;
    bairro: string;
    requerente: string | null;
    anonima: boolean;
    recebida_em: string;
    situacao: string;
}

export interface SugestaoDeAgrupamento {
    id: number;
    /** `regra` (determinista) ou `ia` — muda o peso que o coordenador lhe dá. */
    origem: string;
    confianca: number | null;
    confianca_pct: number | null;
    /** POR QUE. É o que o coordenador lê antes de aceitar. */
    motivo: string;
    agregada: LadoDaSugestao | null;
    principal: LadoDaSugestao | null;
}

interface Props {
    sugestoes: SugestaoDeAgrupamento[];
    /** Prefixo das rotas: muda entre Denúncias e Caixa de Entrada. */
    base: string;
    /** Quem apenas acompanha não decide — a mesma resposta governa o servidor. */
    podeDecidir: boolean;
}

/*
 * QUEM foi denunciado — a linha que decide a pré-triagem.
 *
 * Dois relatos de "mesas na calçada" na mesma rua podem ser o mesmo bar ou dois
 * estabelecimentos a cinquenta metros um do outro. O assunto não separa (o
 * comércio de rua repete o mesmo assunto a cidade inteira) e o endereço o
 * cidadão escreve de memória. O nome da fachada separa — por isso ele aparece
 * em destaque, e não perdido no meio do relato.
 *
 * Quando não veio nenhum dos dois, a linha DIZ isso em vez de sumir: "a
 * ouvidoria não informou" é informação, e o coordenador precisa saber que está
 * decidindo sem ela.
 */
function QuemFoiDenunciado({ lado }: { lado: { estabelecimento: string; denunciado: string } }) {
    const temEstabelecimento = lado.estabelecimento.trim() !== '';
    const temDenunciado = lado.denunciado.trim() !== '';

    if (!temEstabelecimento && !temDenunciado) {
        return <small className="rt-pretriagem-sem-alvo">Denunciado não informado pelo canal</small>;
    }

    return (
        <small className="rt-pretriagem-alvo">
            <Store size={13} aria-hidden />
            {temEstabelecimento && <strong>{lado.estabelecimento}</strong>}
            {temEstabelecimento && temDenunciado && ' · '}
            {temDenunciado && <span>{lado.denunciado}</span>}
        </small>
    );
}

export function PreTriagem({ sugestoes, base, podeDecidir }: Props) {
    const { enviando, ocupado, enviar } = useEnvio();
    const [recusando, setRecusando] = useState<SugestaoDeAgrupamento | null>(null);
    const [motivo, setMotivo] = useState('');

    /*
     * As propostas são mostradas AGRUPADAS pela principal. O servidor já as
     * fecha em grupo (uma principal por conjunto), e a tela respeita isso: o
     * coordenador olha um caso de cada vez, e não uma lista de pares soltos que
     * ele teria de reconstruir de cabeça.
     */
    const grupos = new Map<number, { principal: LadoDaSugestao; itens: SugestaoDeAgrupamento[] }>();

    for (const sugestao of sugestoes) {
        if (!sugestao.principal || !sugestao.agregada) continue;

        const grupo = grupos.get(sugestao.principal.id);

        if (grupo) {
            grupo.itens.push(sugestao);
        } else {
            grupos.set(sugestao.principal.id, { principal: sugestao.principal, itens: [sugestao] });
        }
    }

    function recusar() {
        if (!recusando) return;
        enviar(`recusar-${recusando.id}`, `${base}/agrupamento/sugestoes/${recusando.id}/recusar`, { observacao: motivo }, {
            onSuccess: () => {
                setRecusando(null);
                setMotivo('');
            },
        });
    }

    return (
        <section className="rt-pretriagem">
            <header className="rt-pretriagem-topo">
                <div>
                    <h2>
                        <Layers size={17} aria-hidden /> Possíveis denúncias repetidas
                    </h2>
                    <p>
                        {sugestoes.length === 0
                            ? 'Nenhuma repetição aparente entre as denúncias abertas. Rode a varredura depois de uma leva nova.'
                            : `O sistema encontrou ${sugestoes.length === 1 ? '1 denúncia que parece' : `${sugestoes.length} denúncias que parecem`} relatar um fato já registrado. Confira antes de agrupar — agrupar muda a resposta que o cidadão recebe.`}
                    </p>
                </div>

                {podeDecidir && (
                    <BotaoAcao
                        icone={<Search size={16} aria-hidden />}
                        carregando={enviando === 'varrer'}
                        ocupado={ocupado}
                        rotuloCarregando="Procurando…"
                        onClick={() => enviar('varrer', `${base}/agrupamento/varrer`)}
                    >
                        Procurar repetições
                    </BotaoAcao>
                )}
            </header>

            {[...grupos.values()].map(({ principal, itens }) => (
                <article key={principal.id} className="rt-pretriagem-grupo">
                    <div className="rt-pretriagem-principal">
                        <span className="selo selo-info">Registro que vai a campo</span>
                        <strong>{principal.protocolo}</strong>
                        <span>{principal.assunto}</span>
                        <QuemFoiDenunciado lado={principal} />
                        <small>
                            {principal.endereco || principal.bairro} · recebida em {dataBR(principal.recebida_em)} ·{' '}
                            {principal.anonima ? 'Anônima' : principal.requerente}
                        </small>
                        <p className="rt-pretriagem-relato">{principal.relato}</p>
                    </div>

                    <ul className="rt-pretriagem-lista">
                        {itens.map((sugestao) => (
                            <li key={sugestao.id}>
                                <div className="rt-pretriagem-lado">
                                    <div className="rt-pretriagem-cabeca">
                                        <strong>{sugestao.agregada!.protocolo}</strong>
                                        <span className="selo selo-neutro">{sugestao.agregada!.canal}</span>
                                        {sugestao.confianca_pct !== null && (
                                            /* Discreto de propósito: o que convence é o relato, não o número. */
                                            <span className="rt-pretriagem-confianca">{sugestao.confianca_pct}% de semelhança</span>
                                        )}
                                    </div>
                                    <span>{sugestao.agregada!.assunto}</span>
                                    <QuemFoiDenunciado lado={sugestao.agregada!} />
                                    <small>
                                        {sugestao.agregada!.endereco || sugestao.agregada!.bairro} · recebida em{' '}
                                        {dataBR(sugestao.agregada!.recebida_em)} ·{' '}
                                        {sugestao.agregada!.anonima ? 'Anônima' : sugestao.agregada!.requerente}
                                    </small>
                                    <p className="rt-pretriagem-relato">{sugestao.agregada!.relato}</p>
                                    {/* O RACIOCÍNIO, por extenso: dá para discordar dele, e não só do resultado. */}
                                    <p className="rt-pretriagem-motivo">Por que o sistema acha: {sugestao.motivo}</p>
                                </div>

                                {podeDecidir && (
                                    <div className="rt-pretriagem-acoes">
                                        <BotaoAcao
                                            carregando={enviando === `aceitar-${sugestao.id}`}
                                            ocupado={ocupado}
                                            rotuloCarregando="Agrupando…"
                                            onClick={() =>
                                                enviar(`aceitar-${sugestao.id}`, `${base}/agrupamento/sugestoes/${sugestao.id}/aceitar`)
                                            }
                                        >
                                            É o mesmo caso
                                        </BotaoAcao>
                                        <button
                                            type="button"
                                            className="btn btn-secondary btn-sm"
                                            disabled={ocupado}
                                            onClick={() => {
                                                setRecusando(sugestao);
                                                setMotivo('');
                                            }}
                                        >
                                            Não é
                                        </button>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                </article>
            ))}

            {recusando && (
                <Sobreposicao clicandoFora={ocupado ? undefined : () => setRecusando(null)}>
                    {/*
                      * As mesmas classes da folha que o resto do módulo usa
                      * (`card-premium` + `sobreposicao-*`). Um estilo próprio aqui
                      * abriria uma segunda gramática de janela na mesma tela.
                      */}
                    <div
                        className="card-premium"
                        style={{ width: '100%', maxWidth: 560, maxHeight: 'min(92vh, 100% - 8px)', overflowY: 'auto' }}
                        role="dialog"
                        aria-modal="true"
                        aria-label="Por que não são o mesmo caso?"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <h2 className="sobreposicao-titulo">
                            <X size={17} aria-hidden /> Por que não são o mesmo caso?
                        </h2>

                        <p className="rt-pretriagem-motivo" style={{ marginBottom: 14 }}>
                            {recusando.agregada!.protocolo} e {recusando.principal!.protocolo}. O que você escrever aqui
                            é o que impede o sistema de propor esse par de novo.
                        </p>

                        <label className="form-label" htmlFor="motivo-recusa">
                            Motivo
                        </label>
                        <textarea
                            id="motivo-recusa"
                            className="form-control"
                            rows={3}
                            value={motivo}
                            onChange={(e) => setMotivo(e.target.value)}
                            placeholder="São dois estabelecimentos diferentes, a cinquenta metros um do outro."
                        />

                        <div className="sobreposicao-acoes" style={{ marginTop: 18 }}>
                            <button
                                type="button"
                                className="btn btn-secondary btn-sm"
                                onClick={() => setRecusando(null)}
                                disabled={ocupado}
                            >
                                Voltar
                            </button>
                            <BotaoAcao
                                carregando={enviando === `recusar-${recusando.id}`}
                                ocupado={ocupado}
                                disabled={motivo.trim().length < 10}
                                rotuloCarregando="Registrando…"
                                onClick={recusar}
                            >
                                Recusar sugestão
                            </BotaoAcao>
                        </div>
                    </div>
                </Sobreposicao>
            )}

        </section>
    );
}

/* ────────────────────────────────────────────────────────────────────────────
 * A FILA da pré-triagem — o que chegou e ainda não foi entendido.
 *
 * O painel acima responde "estes dois são o mesmo fato?". Esta lista responde
 * a pergunta anterior: "o que chegou?". Ela existe porque o coordenador precisa
 * ver a leva crua inteira — inclusive o que a varredura não ligou a ninguém —
 * antes de dizer que olhou.
 *
 * ── Por que LIBERAR é um ato, e não consequência ────────────────────────────
 *
 * Seria fácil mandar para a Caixa tudo que a máquina não agrupou. Mas "a regra
 * não achou repetição" não é o mesmo que "alguém olhou": a varredura só enxerga
 * o que a regra alcança, e quem conhece a rua é o coordenador. O botão é ele
 * dizendo que leu.
 * ──────────────────────────────────────────────────────────────────────────── */

/** Uma denúncia como esta fila precisa dela. */
export interface DemandaEmPreTriagem {
    id: number;
    protocolo: string;
    origem: string;
    documento_origem: string;
    assunto: string;
    descricao: string;
    endereco: string;
    bairro: string;
    recebida_em: string;
    anonima: boolean;
    requerente: string | null;
    estabelecimento: string;
    denunciado: string;
    agregadas?: { id: number; protocolo: string; assunto: string; requerente: string | null }[];
}

interface PropsDaFila {
    demandas: DemandaEmPreTriagem[];
    base: string;
    podeDecidir: boolean;
}

export function FilaDePreTriagem({ demandas, base, podeDecidir }: PropsDaFila) {
    const { enviando, ocupado, enviar } = useEnvio();
    const [desassociando, setDesassociando] = useState<{ id: number; protocolo: string; principal: string } | null>(null);
    const [motivo, setMotivo] = useState('');

    /*
     * A JUNÇÃO À MÃO — o caminho para o que a varredura não achou.
     *
     * A regra enxerga bairro, rua, palavras e o nome da fachada. Ela não enxerga
     * "é aquele camelô da banca azul", que cada cidadão descreve de um jeito, nem
     * o relato em que alguém errou o nome da rua. Sem esta porta, o coordenador vê
     * que são o mesmo fato e não tem o que clicar — e a equipe vai duas vezes ao
     * mesmo ponto, que é o que a pré-triagem existe para evitar.
     *
     * A seleção fica na TELA, e não numa segunda janela de busca: o que ele
     * precisa comparar (o relato, o endereço, quem foi denunciado) já está diante
     * dele. Pedir que ele reconheça os casos de novo, por protocolo, numa lista
     * solta, é pedir que decore.
     */
    const [selecionadas, setSelecionadas] = useState<number[]>([]);
    const [juntando, setJuntando] = useState(false);
    const [principalEscolhida, setPrincipalEscolhida] = useState<number | null>(null);
    const [motivoDaJuncao, setMotivoDaJuncao] = useState('');

    function alternar(id: number) {
        setSelecionadas((atual) =>
            atual.includes(id) ? atual.filter((x) => x !== id) : [...atual, id],
        );
    }

    function abrirJuncao() {
        // A mais ANTIGA vem pré-escolhida: é a que espera há mais tempo e a que a
        // ouvidoria já cobrou. A fila vem em ordem crescente, então é a primeira
        // selecionada que aparece nela.
        const maisAntiga = demandas.find((d) => selecionadas.includes(d.id));

        setPrincipalEscolhida(maisAntiga?.id ?? selecionadas[0] ?? null);
        setMotivoDaJuncao('');
        setJuntando(true);
    }

    function juntar() {
        if (principalEscolhida === null) return;

        enviar('juntar', `${base}/agrupamento/juntar`, {
            demandas: selecionadas,
            principal_id: principalEscolhida,
            motivo: motivoDaJuncao,
        }, {
            onSuccess: () => {
                setJuntando(false);
                setSelecionadas([]);
            },
        });
    }

    function liberar(chave: string, ids: number[]) {
        enviar(chave, `${base}/agrupamento/liberar`, { demandas: ids });
    }

    /*
     * DESASSOCIAR e' barato de proposito.
     *
     * A associacao pode estar errada -- "mesas na calcada" pode ser dois
     * estabelecimentos a cinquenta metros um do outro --, e quem tria descobre
     * isso relendo, nao no instante do clique. Como nada foi fundido, a denuncia
     * volta a fila no estado em que chegou. Se desagrupar fosse caro ou
     * irreversivel, o coordenador deixaria o erro de pe.
     *
     * O MOTIVO e' exigido mesmo assim: a denuncia desagrupada volta para a mesa
     * de alguem, que vai precisar entender por que -- e e' esse texto que o
     * cidadao encontra no tramite do protocolo dele.
     */
    function desassociar() {
        if (!desassociando) return;
        enviar(`desagrupar-${desassociando.id}`, `${base}/agrupamento/${desassociando.id}/desagrupar`, { motivo }, {
            onSuccess: () => {
                setDesassociando(null);
                setMotivo('');
            },
        });
    }

    if (demandas.length === 0) {
        return (
            <section className="rt-pretriagem">
                <div className="rt-pretriagem-vazio">
                    <Inbox size={22} aria-hidden />
                    <strong>Nada esperando pré-triagem</strong>
                    <p>
                        Quando o e-Salvador entregar uma leva nova, ela aparece aqui — crua, do jeito
                        que os cidadãos escreveram — para você dizer quantos fatos ela contém.
                    </p>
                </div>
            </section>
        );
    }

    return (
        <section className="rt-pretriagem">
            <header className="rt-pretriagem-topo">
                <div>
                    <h2>
                        <Inbox size={17} aria-hidden /> Chegaram por integração
                    </h2>
                    <p>
                        {demandas.length === 1
                            ? '1 denúncia aguarda pré-triagem.'
                            : `${demandas.length} denúncias aguardam pré-triagem.`}{' '}
                        Junte o que for o mesmo fato e depois libere para a Caixa — é lá que elas
                        passam pelo seu crivo de encaminhar ou devolver.
                    </p>
                </div>

                {podeDecidir && (
                    <BotaoAcao
                        icone={<Check size={16} aria-hidden />}
                        carregando={enviando === 'liberar-todas'}
                        ocupado={ocupado}
                        rotuloCarregando="Liberando…"
                        onClick={() => liberar('liberar-todas', demandas.map((d) => d.id))}
                    >
                        Liberar todas para a Caixa
                    </BotaoAcao>
                )}
            </header>

            {/*
              * A barra aparece assim que HÁ seleção, e não só a partir de duas.
              * Com uma só marcada ela diz o que falta, em vez de o botão surgir do
              * nada na segunda — quem marcou uma precisa saber que o caminho existe.
              */}
            {podeDecidir && selecionadas.length > 0 && (
                <div className="rt-pretriagem-selecao">
                    <span>
                        {selecionadas.length === 1
                            ? '1 denúncia marcada — marque outra para juntar as duas num caso só.'
                            : `${selecionadas.length} denúncias marcadas.`}
                    </span>
                    <div className="rt-pretriagem-acoes">
                        <button
                            type="button"
                            className="btn btn-secondary btn-sm"
                            disabled={ocupado}
                            onClick={() => setSelecionadas([])}
                        >
                            Limpar
                        </button>
                        <BotaoAcao
                            icone={<Link2 size={16} aria-hidden />}
                            carregando={enviando === 'juntar'}
                            ocupado={ocupado}
                            disabled={selecionadas.length < 2}
                            rotuloCarregando="Juntando…"
                            onClick={abrirJuncao}
                        >
                            Juntar num caso só
                        </BotaoAcao>
                    </div>
                </div>
            )}

            <ul className="rt-pretriagem-fila">
                {demandas.map((demanda) => (
                    <li key={demanda.id}>
                        <div className="rt-pretriagem-lado">
                            <div className="rt-pretriagem-cabeca">
                                {podeDecidir && (
                                    <label className="rt-pretriagem-marca">
                                        <input
                                            type="checkbox"
                                            checked={selecionadas.includes(demanda.id)}
                                            onChange={() => alternar(demanda.id)}
                                            disabled={ocupado}
                                            aria-label={`Marcar ${demanda.protocolo} para juntar`}
                                        />
                                    </label>
                                )}
                                <strong>{demanda.protocolo}</strong>
                                <span className="selo selo-neutro">{demanda.origem}</span>
                                {demanda.documento_origem && (
                                    <span className="rt-pretriagem-confianca">
                                        nº de origem {demanda.documento_origem}
                                    </span>
                                )}
                            </div>
                            <span>{demanda.assunto}</span>
                            <QuemFoiDenunciado lado={demanda} />
                            <small>
                                {demanda.endereco || demanda.bairro} · recebida em{' '}
                                {dataBR(demanda.recebida_em)} ·{' '}
                                {demanda.anonima ? 'Anônima' : demanda.requerente}
                            </small>
                            <p className="rt-pretriagem-relato">{demanda.descricao}</p>

                            {/*
                              * O resultado da consolidação aparece NA PRÓPRIA linha: é o
                              * que muda o peso da decisão de liberar — este registro não
                              * vai mais responder por um cidadão, e sim por vários.
                              */}
                            {(demanda.agregadas?.length ?? 0) > 0 && (
                                <div className="rt-pretriagem-agregadas">
                                    <p className="rt-pretriagem-motivo">
                                        Responde também por{' '}
                                        {demanda.agregadas!.length === 1
                                            ? '1 denúncia'
                                            : `${demanda.agregadas!.length} denúncias`}
                                        :
                                    </p>
                                    <ul>
                                        {demanda.agregadas!.map((agregada) => (
                                            <li key={agregada.id}>
                                                <span>
                                                    <strong>{agregada.protocolo}</strong> · {agregada.assunto}
                                                    {agregada.requerente ? ` · ${agregada.requerente}` : ' · Anônima'}
                                                </span>
                                                {podeDecidir && (
                                                    <button
                                                        type="button"
                                                        className="btn btn-secondary btn-sm"
                                                        disabled={ocupado}
                                                        onClick={() =>
                                                            setDesassociando({
                                                                id: agregada.id,
                                                                protocolo: agregada.protocolo,
                                                                principal: demanda.protocolo,
                                                            })
                                                        }
                                                    >
                                                        <Unlink size={14} aria-hidden /> Desassociar
                                                    </button>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>

                        {podeDecidir && (
                            <div className="rt-pretriagem-acoes">
                                <BotaoAcao
                                    carregando={enviando === `liberar-${demanda.id}`}
                                    ocupado={ocupado}
                                    rotuloCarregando="Liberando…"
                                    onClick={() => liberar(`liberar-${demanda.id}`, [demanda.id])}
                                >
                                    Liberar para a Caixa
                                </BotaoAcao>
                            </div>
                        )}
                    </li>
                ))}
            </ul>

            {juntando && (
                <Sobreposicao clicandoFora={ocupado ? undefined : () => setJuntando(false)}>
                    <div
                        className="card-premium"
                        style={{ width: '100%', maxWidth: 620, maxHeight: 'min(92vh, 100% - 8px)', overflowY: 'auto' }}
                        role="dialog"
                        aria-modal="true"
                        aria-label="Juntar denúncias num caso só"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <h2 className="sobreposicao-titulo">
                            <Link2 size={17} aria-hidden /> Juntar {selecionadas.length} denúncias num caso só
                        </h2>

                        <p className="rt-pretriagem-motivo" style={{ marginBottom: 14 }}>
                            Uma delas vai a campo; as outras passam a ser respondidas por ela. Quando a
                            fiscalização voltar, a resposta vale para todas — cada cidadão recebe o
                            resultado no protocolo dele.
                        </p>

                        {/*
                          * QUAL vai a campo é escolha, não automatismo. A mais antiga vem
                          * marcada porque é a que espera há mais tempo, mas o coordenador
                          * pode preferir a que descreve melhor o ponto — e é ela que o
                          * fiscal vai ler na rua.
                          */}
                        <span className="form-label">Qual delas vai a campo?</span>
                        <ul className="rt-pretriagem-escolha">
                            {demandas
                                .filter((d) => selecionadas.includes(d.id))
                                .map((d) => (
                                    <li key={d.id}>
                                        <label>
                                            <input
                                                type="radio"
                                                name="principal-da-juncao"
                                                checked={principalEscolhida === d.id}
                                                onChange={() => setPrincipalEscolhida(d.id)}
                                                disabled={ocupado}
                                            />
                                            <span>
                                                <strong>{d.protocolo}</strong> · {d.assunto}
                                                <small>
                                                    {d.endereco || d.bairro}
                                                    {d.estabelecimento.trim() !== '' && ` · ${d.estabelecimento}`}
                                                </small>
                                            </span>
                                        </label>
                                    </li>
                                ))}
                        </ul>

                        <label className="form-label" htmlFor="motivo-juncao" style={{ marginTop: 14 }}>
                            Por que são o mesmo caso?
                        </label>
                        <textarea
                            id="motivo-juncao"
                            className="form-control"
                            rows={3}
                            value={motivoDaJuncao}
                            onChange={(e) => setMotivoDaJuncao(e.target.value)}
                            placeholder="É o mesmo camelô da banca azul em frente ao número 210 — cada um descreveu de um jeito."
                        />

                        <div className="sobreposicao-acoes" style={{ marginTop: 18 }}>
                            <button
                                type="button"
                                className="btn btn-secondary btn-sm"
                                onClick={() => setJuntando(false)}
                                disabled={ocupado}
                            >
                                Voltar
                            </button>
                            <BotaoAcao
                                carregando={enviando === 'juntar'}
                                ocupado={ocupado}
                                disabled={motivoDaJuncao.trim().length < 10 || principalEscolhida === null}
                                rotuloCarregando="Juntando…"
                                onClick={juntar}
                            >
                                Juntar
                            </BotaoAcao>
                        </div>
                    </div>
                </Sobreposicao>
            )}

            {desassociando && (
                <Sobreposicao clicandoFora={ocupado ? undefined : () => setDesassociando(null)}>
                    <div
                        className="card-premium"
                        style={{ width: '100%', maxWidth: 560, maxHeight: 'min(92vh, 100% - 8px)', overflowY: 'auto' }}
                        role="dialog"
                        aria-modal="true"
                        aria-label="Desassociar denúncia"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <h2 className="sobreposicao-titulo">
                            <Unlink size={17} aria-hidden /> Desassociar {desassociando.protocolo}
                        </h2>

                        <p className="rt-pretriagem-motivo" style={{ marginBottom: 14 }}>
                            Ela deixa de ser respondida por {desassociando.principal} e volta para a
                            pré-triagem, com decisão própria. O que você escrever fica no trâmite das
                            duas — é o que explica a quem ler depois por que elas se separaram.
                        </p>

                        <label className="form-label" htmlFor="motivo-desassociar">
                            Motivo
                        </label>
                        <textarea
                            id="motivo-desassociar"
                            className="form-control"
                            rows={3}
                            value={motivo}
                            onChange={(e) => setMotivo(e.target.value)}
                            placeholder="Reli os dois relatos: são dois bares diferentes, um em cada esquina do quarteirão."
                        />

                        <div className="sobreposicao-acoes" style={{ marginTop: 18 }}>
                            <button
                                type="button"
                                className="btn btn-secondary btn-sm"
                                onClick={() => setDesassociando(null)}
                                disabled={ocupado}
                            >
                                Voltar
                            </button>
                            <BotaoAcao
                                carregando={enviando === `desagrupar-${desassociando.id}`}
                                ocupado={ocupado}
                                disabled={motivo.trim().length < 10}
                                rotuloCarregando="Desassociando…"
                                onClick={desassociar}
                            >
                                Desassociar
                            </BotaoAcao>
                        </div>
                    </div>
                </Sobreposicao>
            )}
        </section>
    );
}
