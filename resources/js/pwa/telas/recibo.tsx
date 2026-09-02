import { irPara, useApp } from '../app';
import { Selo, Topo, Vazio } from '../componentes';
import { ORIGENS, acharDemanda } from '../dados-demandas';
import { nomeDoDocumento } from '../dados-documentos';
import { OCORRENCIAS, nomeRegiao } from '../dados-prototipo';
import { Icone } from '../icones';

/**
 * O fim do registro rápido — e a porta do documento, para quem precisar dele.
 *
 * O recibo existe porque o fiscal precisa ver, em dois segundos, que o registro
 * PEGOU: protocolo, hora, lugar e decisão. Só depois disso a tela oferece o
 * caminho excepcional (lavrar Notificação Preliminar ou Auto de Apreensão), e
 * oferece de forma discreta: o normal é encerrar aqui e ir ao próximo ponto.
 *
 * A ORIGEM abre a lista de campos. Num registro dirigido, é o que prova que a
 * demanda foi atendida — e é o número que o administrativo vai procurar.
 */
export function TelaRecibo({ id }: { id: string | null }) {
    const { registros } = useApp();
    const registro = registros.find((r) => r.id === id);

    if (!registro) {
        return (
            <div className="pw-tela">
                <Topo titulo="Registro" aoVoltar={() => irPara('mapa')} />
                <div className="pw-corpo">
                    <Vazio
                        icone="🧾"
                        titulo="Registro não encontrado"
                        texto="Ele pode ter sido aberto noutra sessão do protótipo."
                    />
                </div>
            </div>
        );
    }

    const regular = registro.status === 'regular';
    const nomesDasOcorrencias = registro.ocorrencias
        .map((o) => OCORRENCIAS.find((x) => x.id === o)?.rotulo ?? o)
        .join(' · ');
    const demanda = acharDemanda(registro.demandaId);

    return (
        <div className="pw-tela">
            <Topo titulo="Registro concluído" aoVoltar={() => irPara('mapa')} />

            <div className="pw-corpo">
                <div className="pw-recibo">
                    <div
                        className="pw-recibo-selo"
                        style={{
                            background: regular ? 'var(--pw-ok-suave)' : 'var(--pw-alerta-suave)',
                            color: regular ? 'var(--pw-ok)' : 'var(--pw-alerta)',
                        }}
                    >
                        <Icone nome={regular ? 'certo' : 'alerta'} tamanho={38} />
                    </div>

                    <p
                        className="pw-forte"
                        style={{ margin: 0, textAlign: 'center', fontSize: 20, letterSpacing: '-0.02em' }}
                    >
                        {regular ? 'Local regular' : 'Ocorrência registrada'}
                    </p>
                    <p className="pw-fraco" style={{ margin: '2px 0 0', textAlign: 'center' }}>
                        Protocolo {registro.protocolo}
                    </p>

                    <hr className="pw-tracejado" />

                    <dl>
                        <dt>Origem</dt>
                        <dd>
                            {demanda ? (
                                <>
                                    <span className="pw-selo pw-selo-origem">
                                        <span>{ORIGENS[demanda.origem].emoji}</span>
                                        {ORIGENS[demanda.origem].curto}
                                    </span>
                                    <br />
                                    <span className="pw-fraco">Referência {demanda.protocolo}</span>
                                </>
                            ) : (
                                'Avulsa — encontrada em ronda'
                            )}
                        </dd>
                        <dt>Quando</dt>
                        <dd>
                            {registro.dataBr} às {registro.hora}
                        </dd>
                        <dt>Onde</dt>
                        <dd>
                            {registro.endereco}
                            <br />
                            <span className="pw-fraco">
                                {nomeRegiao(registro.regiao)} · {registro.lat.toFixed(5)},{' '}
                                {registro.lng.toFixed(5)}
                            </span>
                        </dd>
                        <dt>Ambulante</dt>
                        <dd>{registro.ambulante ?? 'Não identificado'}</dd>
                        <dt>Ocorrências</dt>
                        <dd>{nomesDasOcorrencias || 'Sem marcação'}</dd>
                        <dt>Fotos</dt>
                        <dd>
                            {registro.fotos === 0
                                ? 'Nenhuma'
                                : registro.fotos === 1
                                  ? '1 foto'
                                  : `${registro.fotos} fotos`}
                        </dd>
                        {registro.relato && (
                            <>
                                <dt>Relato</dt>
                                <dd style={{ fontWeight: 400 }}>{registro.relato}</dd>
                            </>
                        )}
                        {registro.retornoBr && (
                            <>
                                <dt>Retorno</dt>
                                <dd>
                                    <Selo tom="info">
                                        <Icone nome="relogio" tamanho={13} /> {registro.retornoBr}
                                    </Selo>
                                </dd>
                            </>
                        )}
                        <dt>Envio</dt>
                        <dd>
                            <Selo tom={registro.envio === 'enviado' ? 'ok' : 'neutro'}>
                                {registro.envio === 'enviado' ? 'Enviado' : 'Na fila — sai no sinal'}
                            </Selo>
                        </dd>
                    </dl>
                </div>

                <div style={{ marginTop: 22, display: 'grid', gap: 10 }}>
                    <button
                        type="button"
                        className="pw-btn pw-btn-acao"
                        style={{ minHeight: 58 }}
                        onClick={() => irPara('registrar')}
                    >
                        <Icone nome="mais" tamanho={20} />
                        Novo registro
                    </button>
                    <button
                        type="button"
                        className="pw-btn pw-btn-contorno"
                        onClick={() => irPara(demanda ? 'demandas' : 'mapa')}
                    >
                        {demanda ? 'Voltar às demandas' : 'Voltar ao mapa'}
                    </button>
                </div>

                <p className="pw-titulo-secao">
                    {registro.documento ? 'Documento lavrado' : 'Caminho excepcional'}
                </p>

                {registro.documentoTipo && registro.documento ? (
                    <div className="pw-card" style={{ borderColor: 'var(--pw-ok)' }}>
                        <div className="pw-linha-espalha" style={{ marginBottom: 6 }}>
                            <span className="pw-forte" style={{ fontSize: 16 }}>
                                {nomeDoDocumento(registro.documentoTipo)}
                            </span>
                            <Selo tom="ok">Nº {registro.documento.replace(/^\w+\s/, '')}</Selo>
                        </div>
                        <p className="pw-fraco" style={{ margin: '0 0 12px' }}>
                            {registro.documentoTipo === 'aa'
                                ? 'Bens encaminhados ao SEGUB. Uma via fica com o notificado.'
                                : 'A via fica com o notificado — é ela que faz o prazo correr.'}
                        </p>
                        <button
                            type="button"
                            className="pw-btn pw-btn-contorno"
                            onClick={() => irPara(`via/${registro.id}`)}
                        >
                            <Icone nome="imprimir" tamanho={18} />
                            Imprimir a via
                        </button>
                    </div>
                ) : (
                    <div className="pw-card">
                        <p style={{ margin: '0 0 10px', fontSize: 14.5, color: 'var(--pw-texto-corpo)' }}>
                            A maior parte das abordagens termina aqui. Lavrar Notificação Preliminar ou
                            Auto de Apreensão só quando houver irregularidade a sanar em prazo, ou material
                            a recolher.
                        </p>
                        <button
                            type="button"
                            className="pw-btn pw-btn-fantasma"
                            onClick={() => irPara(`documentos/${registro.id}`)}
                        >
                            <Icone nome="documento" tamanho={18} />
                            Lavrar documento de campo
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}
