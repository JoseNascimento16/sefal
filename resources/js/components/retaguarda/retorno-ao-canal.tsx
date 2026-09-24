import { CheckCircle2, ExternalLink, Send } from 'lucide-react';
import { useState } from 'react';
import { BotaoAcao } from '@/components/retaguarda/acao';
import { useEnvio } from '@/hooks/use-envio';
import { dataHoraBR } from '@/lib/datas';

/**
 * O que o servidor sabe sobre o retorno de uma demanda ao canal de origem.
 *
 * `retorno_ao_canal` diz o TIPO que o canal pede (`tramite` = responder no
 * processo do e-Salvador; `processo` = abrir um processo, caso da avulsa) ou
 * `null` quando o canal não recebe retorno por sistema. `resposta_ao_canal` é o
 * que já foi registrado, ou `null`.
 */
export interface RetornoDaDemanda {
    id: number;
    protocolo: string;
    situacao: string;
    protocolo_origem?: string;
    documento_origem?: string;
    retorno_ao_canal: 'tramite' | 'processo' | null;
    /** Onde o retorno é feito — "e-Salvador", "e-Protocolo". */
    retorno_em?: string | null;
    /** Já foi a campo? A que VOLTOU ao chefe depois da vistoria também pode ser respondida. */
    passou_por_fiscalizacao?: boolean;
    resposta_ao_canal: {
        texto: string;
        em: string;
        por: string | null;
        processo: string | null;
        enviado: boolean;
    } | null;
}

/**
 * O RETORNO AO CANAL, no detalhe da demanda — o ato do Chefe de Setor que fecha
 * o ciclo.
 *
 * Aparece só quando há o que fazer ou o que mostrar: canal com retorno por
 * sistema E demanda concluída. Mostra o retorno já registrado (quem, quando, o
 * texto, o processo) ou, para quem decide, o formulário.
 *
 * ⚠️ A escrita na API do e-Salvador está PROIBIDA por enquanto (é produção): o
 * chefe faz o ato à mão no e-Salvador e registra aqui — por isso o número do
 * processo é pedido na abertura, e o texto diz "registrado aqui" e não
 * "enviado". Quando a integração for liberada, o mesmo formulário passa a enviar.
 */
export function RetornoAoCanal({
    demanda,
    decide,
    rota,
}: {
    demanda: RetornoDaDemanda;
    /** Esta pessoa é quem responde ao canal (chefe ou administrador)? Vem do servidor. */
    decide: boolean;
    /** A URL do ato para ESTA demanda — cada tela a monta pela própria rota. */
    rota: string;
}) {
    const tipo = demanda.retorno_ao_canal;
    const registrado = demanda.resposta_ao_canal;

    /*
     * Cabe responder quando a fiscalização já produziu resultado: a demanda está
     * concluída, ou VOLTOU ao chefe depois da vistoria (o líder encaminhou para
     * ele deliberar). A mesma regra, no servidor, é `RetornoAoCanal::impedimento`.
     */
    const podeResponder =
        demanda.situacao === 'Concluída' ||
        (['Recebida', 'Em pré-triagem'].includes(demanda.situacao) && demanda.passou_por_fiscalizacao === true);

    if (tipo === null || (registrado === null && (!decide || !podeResponder))) {
        return null;
    }

    const abertura = tipo === 'processo';
    const onde = demanda.retorno_em ?? 'e-Salvador';
    const titulo = abertura ? 'Deliberação do Chefe de Setor' : `Resposta ao ${onde}`;

    return (
        <>
            <hr className="rt-regua" />
            <h3 className="card-titulo">{titulo}</h3>

            {registrado !== null ? (
                <div className="rt-sugestao" style={{ marginTop: 8 }}>
                    <CheckCircle2 size={16} aria-hidden />
                    <div>
                        <strong>
                            {abertura
                                ? registrado.processo
                                    ? 'Processo aberto'
                                    : 'Encerrada com a fiscalização, sem processo'
                                : 'Respondida'}
                            {registrado.processo ? ` · ${registrado.processo}` : ''} · {dataHoraBR(registrado.em)}
                            {registrado.por ? ` · ${registrado.por}` : ''}
                        </strong>
                        <div style={{ whiteSpace: 'pre-wrap', marginTop: 4 }}>{registrado.texto}</div>
                        <div style={{ marginTop: 6, color: 'var(--sm-texto-fraco)' }}>
                            {registrado.enviado
                                ? 'Enviado pela integração.'
                                : abertura && !registrado.processo
                                  ? 'Deliberação registrada aqui: a avulsa terminou na fiscalização.'
                                  : `Registrado aqui e feito à mão no ${onde} — a integração ainda não escreve lá.`}
                        </div>
                    </div>
                </div>
            ) : (
                <FormularioDeRetorno demanda={demanda} abertura={abertura} onde={onde} rota={rota} />
            )}
        </>
    );
}

function FormularioDeRetorno({
    demanda,
    abertura,
    onde,
    rota,
}: {
    demanda: RetornoDaDemanda;
    abertura: boolean;
    onde: string;
    rota: string;
}) {
    const { enviando, ocupado, enviar } = useEnvio();
    const [texto, setTexto] = useState('');
    const [processo, setProcesso] = useState('');
    const [erros, setErros] = useState<Record<string, string>>({});

    const origem = demanda.protocolo_origem || demanda.documento_origem || '';
    const chave = `retorno-${demanda.id}`;

    function registrar(semProcesso = false) {
        enviar(
            semProcesso ? `${chave}-sem` : chave,
            rota,
            { texto, processo: processo.trim() === '' ? null : processo.trim(), sem_processo: semProcesso },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setTexto('');
                    setProcesso('');
                    setErros({});
                },
                onError: (e) => setErros(e as Record<string, string>),
            },
        );
    }

    const erro = (campo: string) =>
        erros[campo] === undefined ? null : (
            <p className="form-ajuda" style={{ color: 'var(--sm-perigo)' }}>
                {erros[campo]}
            </p>
        );

    return (
        <>
            <p className="card-sub">
                {abertura ? (
                    <>
                        A avulsa não tem processo. Concluída a fiscalização, você <strong>delibera</strong>:
                        abre um processo no e-Salvador com o resultado (feito à mão lá — a integração
                        ainda não abre processo — e o número registrado aqui), ou{' '}
                        <strong>encerra só com a fiscalização</strong>, sem processo.
                    </>
                ) : (
                    <>
                        Com o resultado da fiscalização, você <strong>responde no processo de origem</strong>
                        {origem ? ` (${origem})` : ''} o que foi apurado — é o que o requerente vai ler. A
                        integração ainda não escreve no {onde}: registre aqui e faça o trâmite à mão lá.
                    </>
                )}
            </p>

            <div className="form-group">
                <label className="form-label" htmlFor={`${chave}-texto`}>
                    {abertura ? 'Resultado da fiscalização' : 'Resposta ao requerente'}
                </label>
                <textarea
                    id={`${chave}-texto`}
                    className="form-control"
                    rows={4}
                    value={texto}
                    maxLength={4000}
                    placeholder="O que a fiscalização encontrou e o que foi feito, na linguagem de quem vai ler."
                    onChange={(e) => setTexto(e.target.value)}
                />
                {erro('texto')}
            </div>

            <div className="rt-form-linha">
                <div className="form-group">
                    <label className="form-label" htmlFor={`${chave}-processo`}>
                        {abertura ? `Nº do processo aberto no ${onde} (se abrir)` : 'Nº do processo (se diferente do de origem)'}
                    </label>
                    <input
                        id={`${chave}-processo`}
                        type="text"
                        className="form-control"
                        value={processo}
                        maxLength={40}
                        placeholder={abertura ? 'Ex.: 215.5382.001234/2026' : origem || 'Ex.: 215.5382.001234/2026'}
                        onChange={(e) => setProcesso(e.target.value)}
                    />
                    {erro('processo')}
                </div>
            </div>

            <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
                <BotaoAcao
                    icone={abertura ? <ExternalLink size={16} aria-hidden /> : <Send size={16} aria-hidden />}
                    carregando={enviando === chave}
                    ocupado={ocupado}
                    disabled={texto.trim().length < 15 || (abertura && processo.trim() === '')}
                    rotuloCarregando="Registrando…"
                    onClick={() => registrar(false)}
                >
                    {abertura ? 'Registrar abertura do processo' : 'Registrar resposta'}
                </BotaoAcao>
                {/* A deliberação que só a avulsa tem: terminar na fiscalização. */}
                {abertura && (
                    <BotaoAcao
                        className="btn btn-secondary btn-sm"
                        icone={<CheckCircle2 size={16} aria-hidden />}
                        carregando={enviando === `${chave}-sem`}
                        ocupado={ocupado}
                        disabled={texto.trim().length < 15}
                        rotuloCarregando="Encerrando…"
                        onClick={() => registrar(true)}
                    >
                        Encerrar sem processo
                    </BotaoAcao>
                )}
                {texto.trim().length < 15 && (
                    <span className="form-ajuda" style={{ margin: 0 }}>
                        Escreva o resultado para registrar.
                    </span>
                )}
            </div>
        </>
    );
}
