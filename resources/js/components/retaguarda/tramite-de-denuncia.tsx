import {
    Ban,
    Camera,
    Check,
    FileText,
    Info,
    Lightbulb,
    ListChecks,
    MapPin,
    Minus,
    PenLine,
    UserRound,
} from 'lucide-react';
import type { KeyboardEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import type {
    CampoLido,
    DocumentoDeCampo,
    RegistroDeCampo,
    TramiteDenuncia,
} from '@/dados-prototipo/denuncias';
import { TOM_DA_SITUACAO } from '@/dados-prototipo/denuncias';
import { dataBR, dataHoraBR, VAZIO } from '@/lib/datas';
import { contar, plural } from '@/lib/plural';
import type { CatalogoDeRecomendacoes } from '@/lib/recomendacoes';
import { textoDaRecomendacao } from '@/lib/recomendacoes';
import { cn } from '@/lib/utils';

/**
 * O TRÂMITE NAVEGÁVEL de uma denúncia — a linha do tempo, e o que cada passo
 * produziu.
 *
 * ── Por que o trâmite deixou de ser uma lista de frases ──────────────────────
 *
 * Enquanto a denúncia só era triada e direcionada, cada passo cabia numa linha:
 * ele produzia uma DECISÃO, e a decisão é uma frase. Quando a denúncia passa a
 * andar até a vistoria, isso deixa de ser verdade — o passo do fiscal produziu
 * relato, situação encontrada, fotos, coordenada, e às vezes um DOCUMENTO com
 * número, motivos assinalados, penalidades e prazo. Nada disso cabe numa linha,
 * e empilhar tudo em texto corrido faria o trâmite de sete passos virar uma
 * parede que ninguém lê.
 *
 * Então o trâmite virou **abas verticais**: a linha do tempo à esquerda (ou em
 * cima, no retrato), e o conteúdo do passo escolhido ao lado. A linha do tempo
 * continua sendo o que ela era — a leitura de relance de quem fez o quê e
 * quando —, e o detalhe só existe para o passo que a pessoa escolheu olhar.
 *
 * ── Por que abas de verdade, e não `div` clicável ────────────────────────────
 *
 * Cada passo é um `<button role="tab">` dentro de um `role="tablist"` vertical,
 * com **tabindex móvel** e as setas navegando entre eles (↑/↓/←/→, Home, End) —
 * o padrão de abas com ativação automática. `div` com `onClick` não recebe foco,
 * não responde a tecla e chega ao leitor de tela como texto parado: a única
 * porta para o conteúdo existiria apenas para quem usa mouse.
 *
 * A ativação segue o foco (mover a seta já troca o painel) porque o conteúdo já
 * está na mão — ele veio no mesmo prop da denúncia, sem requisição nenhuma. É o
 * caso em que o padrão recomenda ativação automática.
 *
 * ── A Retaguarda LÊ o documento; ela não o emite ─────────────────────────────
 *
 * O documento aparece aqui na forma do papel — número, campos na ordem do
 * impresso, caixas assinaladas, assinaturas com o estado de cada uma —, e em
 * modo de leitura, sem um único campo de formulário. Quem lavra Notificação
 * Preliminar e Auto de Apreensão é o fiscal, em rua, no aplicativo. Oferecer
 * aqui um botão de emitir criaria um segundo dono para o ato mais delicado do
 * sistema.
 *
 * ── As considerações do fiscal ficam em DESTAQUE, não na ficha ───────────────
 *
 * O passo que fecha a vistoria carrega, além do desfecho, o que o fiscal
 * escreveu (`consideracoes`) e o que ele assinalou (`recomendacoes`) — e é por
 * essas duas coisas que o Chefe de Setor e o Coordenador entendem o que ele está
 * pedindo, e sabem para onde dirigir o caso. Por isso elas vêm num bloco
 * destacado, logo abaixo do desfecho, e ganham selo próprio na linha do tempo:
 * enterradas no meio de "o que ficou decidido neste passo", seriam lidas depois
 * da decisão que deveriam orientar.
 */

/** Quantas fotos o passo registrou — o número que vira selo na linha do tempo. */
function totalDeFotos(t: TramiteDenuncia): number {
    return t.campo?.fotos.length ?? 0;
}

/**
 * O QUE O FISCAL RECOMENDOU ao fechar a vistoria, e o que ele escreveu.
 *
 * Fica em destaque, e não como duas linhas da ficha, porque é a informação pela
 * qual o Chefe de Setor e o Coordenador decidem o próximo ato: o desfecho conta
 * como a vistoria terminou, e a recomendação conta o que o fiscal está PEDINDO.
 * Enterrada no meio de "o que ficou decidido neste passo", ela seria lida depois
 * da decisão que ela deveria orientar.
 *
 * As duas partes vêm juntas de propósito: o atalho diz O QUE fazer (e é somável
 * pelo relatório), a consideração conta o caso. Uma sem a outra perde metade.
 *
 * O passo traz a CHAVE do atalho, e aqui ela vira a redação EXPLÍCITA do
 * catálogo do servidor — que é a que serve a quem decide. Chave que o catálogo
 * não conhece aparece CRUA, e não desaparece: ver `@/lib/recomendacoes`.
 */
function ConsideracoesDoFiscal({
    consideracoes,
    recomendacoes,
    catalogo,
}: {
    consideracoes: string | null;
    recomendacoes: string[];
    catalogo: CatalogoDeRecomendacoes;
}) {
    if (consideracoes === null && recomendacoes.length === 0) {
        return null;
    }

    return (
        <div className="rt-sugestao" style={{ margin: '0 0 14px' }}>
            <Lightbulb size={16} aria-hidden />
            <div>
                <strong>
                    {recomendacoes.length === 0
                        ? 'Considerações finais do fiscal'
                        : `${plural(recomendacoes.length, 'Recomendação', 'Recomendações')} do fiscal`}
                </strong>

                {recomendacoes.length > 0 && (
                    <div style={{ margin: '6px 0 2px' }}>
                        {recomendacoes.map((r) => (
                            <span
                                key={r}
                                className="selo selo-info"
                                style={{ marginRight: 6, marginBottom: 4 }}
                            >
                                {textoDaRecomendacao(r, catalogo)}
                            </span>
                        ))}
                    </div>
                )}

                {/* O texto livre vem DEPOIS dos atalhos: eles se leem de relance,
                    ele se lê com atenção. E quando não há texto a tela diz isso,
                    em vez de deixar o bloco pela metade — espaço em branco depois
                    de "recomendações" parece recomendação que não carregou. */}
                <div>
                    {consideracoes ?? (
                        <span style={{ color: 'var(--sm-texto-fraco)' }}>
                            O fiscal não escreveu considerações — só assinalou{' '}
                            {plural(recomendacoes.length, 'a recomendação', 'as recomendações')}{' '}
                            acima.
                        </span>
                    )}
                </div>
            </div>
        </div>
    );
}

/** Uma ficha de rótulo/valor — a mesma forma em toda a leitura do módulo. */
function Ficha({ campos }: { campos: CampoLido[] }) {
    if (campos.length === 0) {
        return null;
    }

    return (
        <dl className="rt-ficha">
            {campos.map((c) => (
                <div key={c.rotulo} style={{ gridColumn: '1 / -1' }}>
                    <dt>{c.rotulo}</dt>
                    <dd>{c.valor || VAZIO}</dd>
                </div>
            ))}
        </dl>
    );
}

function Secao({
    titulo,
    icone,
    children,
}: {
    titulo: string;
    icone: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="rt-tramite-secao">
            <h4 className="rt-tramite-secao-titulo">
                {icone} {titulo}
            </h4>
            {children}
        </div>
    );
}

/** O que a equipe registrou no local. */
function RegistroEmCampo({ campo }: { campo: RegistroDeCampo }) {
    return (
        <Secao titulo="O que a equipe registrou em campo" icone={<Camera size={15} aria-hidden />}>
            {campo.encontrado !== null && (
                <p style={{ marginBottom: 10 }}>
                    <span className="selo selo-info">{campo.encontrado}</span>
                </p>
            )}

            <Ficha
                campos={[
                    ...(campo.ambulante !== null
                        ? [{ rotulo: 'Quem estava no ponto', valor: campo.ambulante }]
                        : []),
                    ...(campo.equipamento !== null
                        ? [{ rotulo: 'Equipamento', valor: campo.equipamento }]
                        : []),
                    { rotulo: 'Relato da vistoria', valor: campo.relato },
                ]}
            />

            {/* A coordenada vem SEMPRE com a precisão: um ponto ruim é pior que
                um ponto ausente disfarçado de bom, e é a precisão que diz se dá
                para confiar no lugar registrado. */}
            {campo.gps !== null && (
                <p className="form-ajuda" style={{ marginTop: 8 }}>
                    <MapPin size={14} aria-hidden /> {campo.gps}
                    {campo.precisao_m !== null ? ` · precisão de ±${campo.precisao_m} m` : ''}
                </p>
            )}

            {campo.fotos.length > 0 && (
                <div style={{ marginTop: 10 }}>
                    <p className="form-ajuda" style={{ marginBottom: 6 }}>
                        {contar(campo.fotos.length, 'foto', 'fotos')}{' '}
                        {plural(campo.fotos.length, 'registrada', 'registradas')} pelo
                        aplicativo do fiscal:
                    </p>
                    {campo.fotos.map((foto) => (
                        <span
                            key={foto}
                            className="selo selo-neutro"
                            style={{ marginRight: 6, marginBottom: 4 }}
                        >
                            <Camera size={12} aria-hidden /> {foto}
                        </span>
                    ))}
                </div>
            )}
        </Secao>
    );
}

/** O documento lavrado em campo, em leitura — na forma do papel. */
function DocumentoLido({ documento }: { documento: DocumentoDeCampo }) {
    const estados = {
        assinada: { rotulo: 'Assinou', icone: <Check size={12} aria-hidden />, tom: 'selo-ok' },
        recusada: {
            rotulo: 'Recusou assinar',
            icone: <Ban size={12} aria-hidden />,
            tom: 'selo-perigo',
        },
        pendente: {
            rotulo: 'Não colhida',
            icone: <Minus size={12} aria-hidden />,
            tom: 'selo-neutro',
        },
    };

    return (
        <Secao
            titulo={
                documento.tipo === 'np' ? 'Notificação Preliminar lavrada' : 'Auto de Apreensão lavrado'
            }
            icone={<FileText size={15} aria-hidden />}
        >
            <div className="rt-documento">
                <div className="rt-documento-cabeca">
                    <div>
                        <p className="rt-documento-orgao">
                            PREFEITURA MUNICIPAL DE SALVADOR · SEMOP
                        </p>
                        <p className="rt-documento-setor">
                            Setor de Fiscalização em Logradouro Público — SEFAL
                        </p>
                    </div>
                    <span className="rt-documento-numero">Nº {documento.numero}</span>
                </div>

                <p className="rt-documento-titulo">{documento.titulo}</p>

                {/* A ordem dos campos é a do IMPRESSO, e a data entra aqui — não
                    no meio dos campos que o servidor manda — porque quem escreve
                    dd/mm/aaaa é a tela. */}
                <Ficha
                    campos={[
                        { rotulo: 'Data / hora da lavratura', valor: dataHoraBR(documento.emitido_em) },
                        ...documento.campos,
                        ...(documento.vence_em !== null
                            ? [
                                  {
                                      rotulo: 'Vencimento do prazo',
                                      valor: `${dataBR(documento.vence_em)}${
                                          documento.prazo_rotulo === null
                                              ? ''
                                              : ` (${documento.prazo_rotulo})`
                                      }`,
                                  },
                              ]
                            : []),
                        { rotulo: 'Agente fiscal', valor: documento.agente },
                    ]}
                />

                {documento.listas
                    .filter((lista) => lista.itens.length > 0)
                    .map((lista) => (
                        <div key={lista.titulo} className="rt-documento-lista">
                            <p className="rt-documento-secao">{lista.titulo}</p>
                            <ul>
                                {lista.itens.map((item) => (
                                    <li key={item}>{item}</li>
                                ))}
                            </ul>
                        </div>
                    ))}

                {documento.assinaturas.length > 0 && (
                    <div className="rt-documento-lista">
                        <p className="rt-documento-secao">Assinaturas</p>
                        <ul className="rt-documento-assinaturas">
                            {documento.assinaturas.map((a) => {
                                const estado = estados[a.estado];

                                return (
                                    <li key={a.rotulo}>
                                        <span>
                                            {a.rotulo}
                                            {a.nome === null ? '' : ` · ${a.nome}`}
                                        </span>
                                        <span className={cn('selo', estado.tom)}>
                                            {estado.icone} {estado.rotulo}
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                )}

                <p className="rt-documento-rodape">{documento.rodape}</p>
            </div>

            {/* Dito na tela, e não só no código: quem lê aqui é a Retaguarda, e a
                Retaguarda não lavra documento de campo. */}
            <p className="form-ajuda" style={{ marginTop: 10 }}>
                <Info size={14} aria-hidden /> Leitura do documento lavrado em rua pelo
                aplicativo do fiscal. A Retaguarda <strong>não emite</strong> Notificação
                nem Auto de Apreensão — ela acompanha o que foi emitido.
            </p>
        </Secao>
    );
}

export function TramiteDeDenuncia({
    tramites,
    /** O próximo passo, quando há um — dito como próximo passo, nunca como fato. */
    proximoPasso,
    /**
     * Chave da recomendação → a frase que a Retaguarda mostra (catálogo do
     * servidor, na redação explícita). O passo grava chave; a leitura traduz.
     */
    recomendacoesDoFiscal,
}: {
    tramites: TramiteDenuncia[];
    proximoPasso?: { o_que: string; quem: string; detalhe: string } | null;
    recomendacoesDoFiscal: CatalogoDeRecomendacoes;
}) {
    /*
     * Abre no ÚLTIMO passo, e não no primeiro: quem abre uma denúncia quer saber
     * em que pé ela está. Abrir no recebimento por integração — o passo que é
     * igual em toda denúncia — obrigaria a clicar até o fim toda vez.
     */
    const [passo, setPasso] = useState(() => Math.max(0, tramites.length - 1));
    const botoes = useRef<(HTMLButtonElement | null)[]>([]);

    /*
     * Trocar de denúncia sem sair da aba de detalhe (o que acontece ao decidir
     * algo e a lista voltar do servidor) deixaria o índice apontando para um
     * passo que a nova denúncia não tem — painel em branco, sem erro nenhum para
     * investigar.
     */
    useEffect(() => {
        setPasso(Math.max(0, tramites.length - 1));
    }, [tramites]);

    const atual = tramites[Math.min(passo, tramites.length - 1)] ?? null;

    /** Move o foco E a seleção — abas de ativação automática. */
    function mover(destino: number) {
        const alvo = Math.max(0, Math.min(tramites.length - 1, destino));

        setPasso(alvo);
        botoes.current[alvo]?.focus();
    }

    function aoTeclar(evento: KeyboardEvent<HTMLButtonElement>, indice: number) {
        const teclas: Record<string, number> = {
            ArrowDown: indice + 1,
            ArrowRight: indice + 1,
            ArrowUp: indice - 1,
            ArrowLeft: indice - 1,
            Home: 0,
            End: tramites.length - 1,
        };

        if (!(evento.key in teclas)) {
            return;
        }

        evento.preventDefault();
        mover(teclas[evento.key]);
    }

    if (atual === null) {
        return null;
    }

    return (
        <div className="rt-tramite-nav">
            {/* O fio vertical da linha do tempo mora NESTE contêiner, e não no
                `tablist`, porque o próximo passo também pendura nele — e ele não
                é aba (ver abaixo). */}
            <div className="rt-tramite-passos">
                <div
                    className="rt-tramite-abas"
                    role="tablist"
                    aria-orientation="vertical"
                    aria-label="Passos do trâmite da denúncia"
                >
                    {tramites.map((t, i) => (
                        <button
                            key={`${t.em}-${t.o_que}-${i}`}
                            type="button"
                            role="tab"
                            id={`tramite-passo-${i}`}
                            aria-controls="tramite-detalhe"
                            aria-selected={i === passo}
                            // Tabindex móvel: a lista inteira é UMA parada de tabulação,
                            // e as setas andam dentro dela. Com todos os passos
                            // tabuláveis, um trâmite de sete linhas viraria sete
                            // paradas antes do conteúdo.
                            tabIndex={i === passo ? 0 : -1}
                            ref={(elemento) => {
                                botoes.current[i] = elemento;
                            }}
                            className={cn('rt-tramite-passo', i === passo && 'ativo')}
                            onClick={() => setPasso(i)}
                            onKeyDown={(evento) => aoTeclar(evento, i)}
                        >
                            <strong>{t.o_que}</strong>
                            <span className="rt-tramite-quando">{dataHoraBR(t.em)}</span>
                            <span className="rt-tramite-passo-quem">{t.quem}</span>

                            {/* O que o passo PRODUZIU, de relance: é o que faz a pessoa
                                saber onde clicar sem abrir os sete passos. */}
                            {(t.documento !== null ||
                                totalDeFotos(t) > 0 ||
                                t.recomendacoes.length > 0) && (
                                <span className="rt-tramite-passo-selos">
                                    {/* A recomendação vem PRIMEIRO entre os selos:
                                        é o que faz alguém abrir aquele passo em vez
                                        dos outros seis. */}
                                    {t.recomendacoes.length > 0 && (
                                        <span className="selo selo-info">
                                            <Lightbulb size={11} aria-hidden />{' '}
                                            {contar(
                                                t.recomendacoes.length,
                                                'recomendação',
                                                'recomendações',
                                            )}
                                        </span>
                                    )}
                                    {t.documento !== null && (
                                        <span className="selo selo-aviso">
                                            <FileText size={11} aria-hidden />{' '}
                                            {t.documento.tipo === 'np' ? 'Notificação' : 'Apreensão'} nº{' '}
                                            {t.documento.numero}
                                        </span>
                                    )}
                                    {totalDeFotos(t) > 0 && (
                                        <span className="selo selo-neutro">
                                            <Camera size={11} aria-hidden /> {totalDeFotos(t)}
                                        </span>
                                    )}
                                </span>
                            )}
                        </button>
                    ))}
                </div>

                {/* Fora do `tablist` de propósito: não é aba, porque não tem
                    conteúdo para mostrar — é o que vem DEPOIS, e clicar nele não
                    levaria a lugar nenhum. Fora, e não escondido: é informação
                    legítima ("alguém tem de voltar ao ponto"), e um elemento que
                    não é aba DENTRO da lista de abas confunde o leitor de tela. */}
                {proximoPasso && (
                    <div className="rt-tramite-passo futuro">
                        <strong>{proximoPasso.o_que}</strong>
                        <span className="rt-tramite-quando">próximo passo</span>
                        <span className="rt-tramite-passo-quem">{proximoPasso.quem}</span>
                        <span className="rt-tramite-passo-detalhe">{proximoPasso.detalhe}</span>
                    </div>
                )}
            </div>

            <div
                className="rt-tramite-detalhe"
                role="tabpanel"
                id="tramite-detalhe"
                aria-labelledby={`tramite-passo-${passo}`}
                // O painel recebe foco para quem chega nele por tabulação depois
                // de escolher o passo — sem isso, o conteúdo longo (um documento
                // inteiro) não é alcançável em ordem pelo teclado.
                tabIndex={0}
            >
                <div className="rt-tramite-detalhe-cabeca">
                    <div>
                        <h4 className="card-titulo">{atual.o_que}</h4>
                        <p className="card-sub">
                            {dataHoraBR(atual.em)} · {atual.quem}
                        </p>
                    </div>

                    {atual.situacao !== '' && (
                        <span
                            className={cn('selo', TOM_DA_SITUACAO[atual.situacao] ?? 'selo-neutro')}
                            title="A situação em que a denúncia entrou com este passo"
                        >
                            {atual.situacao}
                        </span>
                    )}
                </div>

                {atual.detalhe.trim() !== '' && (
                    <p className="rt-tramite-detalhe-texto">{atual.detalhe}</p>
                )}

                {atual.desfecho !== null && (
                    <p style={{ marginBottom: 12 }}>
                        <span className="selo selo-ok">
                            <ListChecks size={12} aria-hidden /> Desfecho: {atual.desfecho}
                        </span>
                    </p>
                )}

                <ConsideracoesDoFiscal
                    consideracoes={atual.consideracoes}
                    recomendacoes={atual.recomendacoes}
                    catalogo={recomendacoesDoFiscal}
                />

                {/* A DECISÃO tomada no passo: para onde foi, por quê, o que
                    ficou registrado. É o conteúdo dos passos administrativos, que
                    não produzem foto nem papel. */}
                {atual.campos.length > 0 && (
                    <Secao titulo="O que ficou decidido neste passo" icone={<PenLine size={15} aria-hidden />}>
                        <Ficha campos={atual.campos} />
                    </Secao>
                )}

                {atual.campo !== null && <RegistroEmCampo campo={atual.campo} />}

                {atual.documento !== null && <DocumentoLido documento={atual.documento} />}

                {/* Passo sem conteúdo próprio não fica com o painel em branco: ele
                    diz que não produziu mais nada. Branco sem explicação parece
                    tela quebrada. */}
                {atual.campos.length === 0 &&
                    atual.campo === null &&
                    atual.documento === null &&
                    atual.desfecho === null &&
                    atual.consideracoes === null &&
                    atual.recomendacoes.length === 0 && (
                        <p className="form-ajuda">
                            <UserRound size={14} aria-hidden /> Este passo registrou o ato e
                            mais nada — nenhum documento foi lavrado e nenhuma vistoria foi
                            registrada nele.
                        </p>
                    )}
            </div>
        </div>
    );
}
