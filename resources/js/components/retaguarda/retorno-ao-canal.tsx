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

    if (tipo === null || (registrado === null && (!decide || demanda.situacao !== 'Concluída'))) {
        return null;
    }

    const abertura = tipo === 'processo';
    const titulo = abertura ? 'Processo no e-Salvador' : 'Resposta ao e-Salvador';

    return (
        <>
            <hr className="rt-regua" />
            <h3 className="card-titulo">{titulo}</h3>

            {registrado !== null ? (
                <div className="rt-sugestao" style={{ marginTop: 8 }}>
                    <CheckCircle2 size={16} aria-hidden />
                    <div>
                        <strong>
                            {abertura ? 'Processo aberto' : 'Respondida'}
                            {registrado.processo ? ` · ${registrado.processo}` : ''} · {dataHoraBR(registrado.em)}
                            {registrado.por ? ` · ${registrado.por}` : ''}
                        </strong>
                        <div style={{ whiteSpace: 'pre-wrap', marginTop: 4 }}>{registrado.texto}</div>
                        <div style={{ marginTop: 6, color: 'var(--sm-texto-fraco)' }}>
                            {registrado.enviado
                                ? 'Enviado pela integração.'
                                : 'Registrado aqui e feito à mão no e-Salvador — a integração ainda não escreve lá.'}
                        </div>
                    </div>
                </div>
            ) : (
                <FormularioDeRetorno demanda={demanda} abertura={abertura} rota={rota} />
            )}
        </>
    );
}

function FormularioDeRetorno({
    demanda,
    abertura,
    rota,
}: {
    demanda: RetornoDaDemanda;
    abertura: boolean;
    rota: string;
}) {
    const { enviando, ocupado, enviar } = useEnvio();
    const [texto, setTexto] = useState('');
    const [processo, setProcesso] = useState('');
    const [erros, setErros] = useState<Record<string, string>>({});

    const origem = demanda.protocolo_origem || demanda.documento_origem || '';
    const chave = `retorno-${demanda.id}`;

    function registrar() {
        enviar(
            chave,
            rota,
            { texto, processo: processo.trim() === '' ? null : processo.trim() },
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
                        A avulsa não tem processo: concluído o trabalho, você <strong>abre o processo no
                        e-Salvador</strong> com o resultado e registra aqui o número. A integração ainda
                        não abre processo — o ato é seu, à mão, lá.
                    </>
                ) : (
                    <>
                        Concluído o trabalho, você <strong>responde no processo de origem</strong>
                        {origem ? ` (${origem})` : ''} o que a fiscalização apurou — é o que o requerente
                        vai ler. A integração ainda não escreve no e-Salvador: registre aqui e faça o
                        trâmite à mão lá.
                    </>
                )}
            </p>

            <div className="form-group">
                <label className="form-label" htmlFor={`${chave}-texto`}>
                    {abertura ? 'Resultado a constar no processo' : 'Resposta ao requerente'}
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
                        {abertura ? 'Nº do processo aberto no e-Salvador' : 'Nº do processo (se diferente do de origem)'}
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
                    onClick={registrar}
                >
                    {abertura ? 'Registrar abertura do processo' : 'Registrar resposta'}
                </BotaoAcao>
                {texto.trim().length < 15 && (
                    <span className="form-ajuda" style={{ margin: 0 }}>
                        Escreva o resultado para registrar.
                    </span>
                )}
            </div>
        </>
    );
}
