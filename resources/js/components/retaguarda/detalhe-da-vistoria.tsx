import { Camera, FileText, Lightbulb, MapPin, UserRound } from 'lucide-react';
import type { Arquivo } from '@/components/retaguarda/lista-de-arquivos';
import { ListaDeArquivos } from '@/components/retaguarda/lista-de-arquivos';
import { dataBR, dataHoraBR, VAZIO } from '@/lib/datas';
import { contar, plural } from '@/lib/plural';
import type { CatalogoDeRecomendacoes } from '@/lib/recomendacoes';
import { textoDaRecomendacao } from '@/lib/recomendacoes';
import { cn } from '@/lib/utils';

/*
 * A VISTORIA — uma ida da equipe ao ponto, com o que o fiscal registrou.
 *
 * Desde 24/09/2026 a linha da tela Fiscalizações é a FISCALIZAÇÃO (o ciclo), e as
 * vistorias abrem dentro dela; este componente é o detalhe de UMA vistoria, o
 * mesmo que antes se abria na própria linha da fila.
 */

export interface Decisao {
    em: string;
    quem: string;
    o_que: string;
    detalhe: string;
}

/** O prazo de retorno de quem foi notificado — só a Notificação Preliminar tem. */
export interface Prazo {
    /** ISO — quem escreve dd/mm/aaaa é a tela. */
    vence_em: string;
    /** Dias até o vencimento; NEGATIVO quando já venceu. Conta do servidor. */
    dias: number;
    vencido: boolean;
    notificado: string | null;
}

export interface Registro {
    id: number;
    protocolo: string;
    /** 'Denúncia', 'Operação planejada', 'Ronda da equipe', 'Pedido de outro órgão'. */
    origem: string;
    /** O que originou a ida ao ponto: o canal e o protocolo, ou o nome da operação. */
    referencia: string;
    /** O protocolo da denúncia, quando o registro veio de uma. */
    denuncia_protocolo: string | null;
    /** ISO com hora — quem escreve dd/mm/aaaa é a tela. */
    concluida_em: string;
    area: string;
    equipe: string;
    /** Quem assinou a vistoria, com a equipe. */
    fiscal: string;
    endereco: string;
    bairro: string;
    ponto_de_referencia: string | null;
    gps: string | null;
    precisao_m: number | null;
    /**
     * Quem a equipe encontrou no ponto. NULO é caso previsto, e não dado
     * faltando: "nada encontrado no local" é desfecho legítimo — a foto do ponto
     * vazio é a prova da ida.
     */
    alvo: string | null;
    equipamento: string | null;
    /** Nomes das fotos. */
    fotos: string[];
    /** As fotos como arquivos, para ver e baixar. */
    arquivos?: Arquivo[];
    desfecho: string;
    /** `np` = Notificação Preliminar; `aa` = Auto de Apreensão. */
    documento: {
        tipo: 'np' | 'aa';
        numero: string;
        notificado: string | null;
        /** ISO. Vem PRONTO do servidor: a duração do prazo tem um dono só. */
        vence_em: string | null;
        /** "48 horas", "05 dias" — a redação do impresso. */
        prazo_rotulo: string | null;
    } | null;
    consideracoes: string | null;
    /**
     * As **CHAVES** dos atalhos que o fiscal assinalou (`retorno`, `sgci`…),
     * nunca a frase: é a chave que o aplicativo dele grava, e é por ela que o
     * relatório soma. A frase sai do catálogo `recomendacoesDoFiscal`.
     */
    recomendacoes: string[];
    /** A situação em que a denúncia de origem ficou — nulo na fiscalização avulsa. */
    situacao_da_origem: string | null;
    estado: string;
    decisao: Decisao | null;
    /** Dias esperando a leitura da chefia. Nulo depois de decidido. */
    dias_parado: number | null;
    prazo: Prazo | null;
}

/** O tom do selo de cada estado da vistoria. */
export const TOM_DO_ESTADO: Record<string, string> = {
    'Aguardando leitura': 'selo-aviso',
    Ciente: 'selo-neutro',
    'Nova vistoria determinada': 'selo-info',
    'Encaminhada ao Chefe de Setor': 'selo-neutro',
};

/** O documento em uma linha: "Notificação nº 194903". */
export function nomeDoDocumento(d: NonNullable<Registro['documento']>): string {
    return `${d.tipo === 'np' ? 'Notificação' : 'Apreensão'} nº ${d.numero}`;
}

/** "vence em 3 dias" / "venceu há 2 dias" / "vence hoje". */
export function textoDoPrazo(prazo: Prazo): string {
    if (prazo.dias === 0) {
        return 'vence hoje';
    }

    return prazo.dias > 0
        ? `vence em ${contar(prazo.dias, 'dia', 'dias')}`
        : `venceu há ${contar(-prazo.dias, 'dia', 'dias')}`;
}

export function DetalheDaVistoria({
    r,
    recomendacoesDoFiscal,
    liderDa,
}: {
    r: Registro;
    recomendacoesDoFiscal: CatalogoDeRecomendacoes;
    liderDa: (equipe: string | null) => string | null;
}) {
    const fraco = { color: 'var(--sm-texto-fraco)' };

    return (
        <>
                        <dl className="rt-ficha">
                            <div>
                                <dt>Registro</dt>
                                <dd>{r.protocolo}</dd>
                            </div>
                            {/* A HORA da conclusão e o tempo na
                                fila moram aqui: na grade a coluna
                                leva só dd/mm/aaaa, e a espera já é
                                dita pela marca laranja na ponta da
                                linha. */}
                            <div>
                                <dt>Concluída em</dt>
                                <dd>
                                    {dataHoraBR(r.concluida_em)}
                                    {r.dias_parado !== null &&
                                        r.dias_parado > 0 && (
                                            <div style={fraco}>
                                                há{' '}
                                                {contar(
                                                    r.dias_parado,
                                                    'dia',
                                                    'dias',
                                                )}{' '}
                                                na fila
                                            </div>
                                        )}
                                </dd>
                            </div>
                            <div>
                                <dt>Estado</dt>
                                <dd>
                                    <span
                                        className={cn(
                                            'selo',
                                            TOM_DO_ESTADO[r.estado] ??
                                                'selo-neutro',
                                        )}
                                    >
                                        {r.estado}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt>Ponto</dt>
                                <dd>
                                    {r.endereco}
                                    {/* O BAIRRO desceu da grade: na
                                        coluna ele era a sub-linha que
                                        dobrava a altura, e a área
                                        tem linha própria logo abaixo. */}
                                    <div style={fraco}>{r.bairro}</div>
                                </dd>
                            </div>
                            {/* Equipe e fiscal desceram da grade: a
                                decisão da chefia é sobre o PONTO, e
                                a assinatura de quem foi importa ao
                                abrir o registro. O arquivo exportado
                                continua levando as duas. */}
                            <div>
                                <dt>Equipe e fiscal</dt>
                                <dd>
                                    {r.equipe ? `Equipe ${r.equipe}` : VAZIO}
                                    <div style={fraco}>{r.fiscal}</div>
                                </dd>
                            </div>
                            <div>
                                <dt>Desfecho</dt>
                                <dd>{r.desfecho}</dd>
                            </div>
                            <div>
                                <dt>Origem da ida ao ponto</dt>
                                <dd>
                                    {r.origem} · {r.referencia}
                                </dd>
                            </div>
                            <div>
                                <dt>Área e chefia</dt>
                                <dd>
                                    {r.area || VAZIO}
                                    {liderDa(r.equipe) === null
                                        ? ''
                                        : ` · líder ${liderDa(r.equipe)}`}
                                </dd>
                            </div>
                            <div>
                                <dt>Quem foi encontrado</dt>
                                <dd>
                                    {r.alvo ?? 'não identificado'}
                                    {r.equipamento === null
                                        ? ''
                                        : ` · ${r.equipamento}`}
                                </dd>
                            </div>
                            {r.situacao_da_origem !== null && (
                                <div>
                                    <dt>Situação da denúncia</dt>
                                    <dd>{r.situacao_da_origem}</dd>
                                </div>
                            )}
                            {r.documento !== null && (
                                <div>
                                    <dt>Documento lavrado</dt>
                                    <dd>
                                        {nomeDoDocumento(r.documento)}
                                        {r.documento.notificado === null
                                            ? ''
                                            : ` · ${r.documento.notificado}`}
                                    </dd>
                                </div>
                            )}
                            {r.prazo !== null && (
                                <div>
                                    <dt>Prazo de retorno</dt>
                                    <dd>
                                        {dataBR(r.prazo.vence_em)} —{' '}
                                        {textoDoPrazo(r.prazo)}
                                        {/* A redação do IMPRESSO ("48 horas"),
                                            e não os dias que a conta usou: é
                                            o que está escrito na via que o
                                            notificado tem na mão. */}
                                        {r.documento?.prazo_rotulo == null ? (
                                            ''
                                        ) : (
                                            <div style={{ color: 'var(--sm-texto-fraco)' }}>
                                                prazo de {r.documento.prazo_rotulo} na via entregue
                                            </div>
                                        )}
                                    </dd>
                                </div>
                            )}
                            {r.ponto_de_referencia !== null && (
                                <div style={{ gridColumn: '1 / -1' }}>
                                    <dt>Ponto de referência</dt>
                                    <dd>{r.ponto_de_referencia}</dd>
                                </div>
                            )}
                        </dl>

                        {/* A coordenada vem SEMPRE com a
                            precisão: um ponto ruim é pior que
                            um ponto ausente disfarçado de bom. */}
                        {r.gps !== null && (
                            <p className="form-ajuda" style={{ marginTop: 8 }}>
                                <MapPin size={14} aria-hidden /> {r.gps}
                                {r.precisao_m !== null
                                    ? ` · precisão de ±${r.precisao_m} m`
                                    : ''}
                            </p>
                        )}

                        {/* As FOTOS, para ver e baixar (dono, 25/09/2026). O arquivo
                            sai pela rota do servidor, que confere a permissão. */}
                        {(r.arquivos?.length ?? 0) > 0 && (
                            <div style={{ marginTop: 8 }}>
                                <p className="form-ajuda" style={{ marginBottom: 4 }}>
                                    <Camera size={14} aria-hidden />{' '}
                                    {contar(r.arquivos?.length ?? 0, 'foto', 'fotos')}{' '}
                                    {plural(r.arquivos?.length ?? 0, 'registrada', 'registradas')} no ponto:
                                </p>
                                <ListaDeArquivos arquivos={r.arquivos ?? []} />
                            </div>
                        )}

                        <div className="rt-sugestao" style={{ marginTop: 12 }}>
                            <Lightbulb size={16} aria-hidden />
                            <div>
                                <strong>
                                    {r.recomendacoes.length === 0
                                        ? 'Considerações finais do fiscal'
                                        : `${plural(r.recomendacoes.length, 'Recomendação', 'Recomendações')} do fiscal`}
                                </strong>

                                {r.recomendacoes.length > 0 && (
                                    <div style={{ margin: '6px 0 2px' }}>
                                        {r.recomendacoes.map((rec) => (
                                            <span
                                                key={rec}
                                                className="selo selo-info"
                                                style={{
                                                    marginRight: 6,
                                                    marginBottom: 4,
                                                }}
                                            >
                                                {textoDaRecomendacao(
                                                    rec,
                                                    recomendacoesDoFiscal,
                                                )}
                                            </span>
                                        ))}
                                    </div>
                                )}

                                <div>
                                    {r.consideracoes ?? (
                                        <span
                                            style={{
                                                color: 'var(--sm-texto-fraco)',
                                            }}
                                        >
                                            O fiscal não escreveu
                                            considerações neste retorno.
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>

                        {r.denuncia_protocolo !== null && (
                            <p className="form-ajuda" style={{ marginTop: 10 }}>
                                <FileText size={14} aria-hidden /> O percurso inteiro está também no trâmite da demanda{' '}
                    <strong>{r.denuncia_protocolo}</strong>, na Caixa de Entrada.
                            </p>
                        )}

                        {r.decisao !== null && (
                            <p className="form-ajuda" style={{ marginTop: 10 }}>
                                <UserRound size={14} aria-hidden />{' '}
                                <strong>{r.decisao.o_que}</strong> ·{' '}
                                {dataHoraBR(r.decisao.em)} ·{' '}
                                {r.decisao.quem} — {r.decisao.detalhe}
                            </p>
                        )}
        </>
    );
}
