import { useState } from 'react';
import { Check, Inbox, Layers, Search, X } from 'lucide-react';

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
    agregadas?: { id: number; protocolo: string; assunto: string; requerente: string | null }[];
}

interface PropsDaFila {
    demandas: DemandaEmPreTriagem[];
    base: string;
    podeDecidir: boolean;
}

export function FilaDePreTriagem({ demandas, base, podeDecidir }: PropsDaFila) {
    const { enviando, ocupado, enviar } = useEnvio();

    function liberar(chave: string, ids: number[]) {
        enviar(chave, `${base}/agrupamento/liberar`, { demandas: ids });
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

            <ul className="rt-pretriagem-fila">
                {demandas.map((demanda) => (
                    <li key={demanda.id}>
                        <div className="rt-pretriagem-lado">
                            <div className="rt-pretriagem-cabeca">
                                <strong>{demanda.protocolo}</strong>
                                <span className="selo selo-neutro">{demanda.origem}</span>
                                {demanda.documento_origem && (
                                    <span className="rt-pretriagem-confianca">
                                        nº de origem {demanda.documento_origem}
                                    </span>
                                )}
                            </div>
                            <span>{demanda.assunto}</span>
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
                                <p className="rt-pretriagem-motivo">
                                    Responde também por{' '}
                                    {demanda.agregadas!.map((a) => a.protocolo).join(', ')}.
                                </p>
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
        </section>
    );
}
