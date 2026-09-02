import { irPara, useApp } from '../app';
import { Selo, Topo, Vazio } from '../componentes';
import { OCORRENCIAS, nomeRegiao } from '../dados-prototipo';
import { Icone } from '../icones';

/**
 * O fim do registro rápido — e o começo da escalada, para quem precisar dela.
 *
 * O recibo existe porque o fiscal precisa ver, em dois segundos, que o registro
 * PEGOU: protocolo, hora, lugar e decisão. Só depois disso a tela oferece o
 * caminho excepcional (identificar a pessoa, emitir documento), e oferece de
 * forma discreta: o normal é encerrar aqui e ir ao próximo ponto.
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
                    <button type="button" className="pw-btn pw-btn-contorno" onClick={() => irPara('mapa')}>
                        Voltar ao mapa
                    </button>
                </div>

                <p className="pw-titulo-secao">Caminho excepcional</p>

                <div className="pw-card">
                    <p style={{ margin: '0 0 10px', fontSize: 14.5, color: 'var(--pw-texto-corpo)' }}>
                        A maior parte das abordagens termina aqui. Identificar a pessoa e emitir documento
                        só quando o ambulante insistir em permanecer ou a situação exigir.
                    </p>
                    <button
                        type="button"
                        className="pw-btn pw-btn-fantasma"
                        onClick={() => irPara(`escalada/${registro.id}`)}
                    >
                        <Icone nome="documento" tamanho={18} />
                        Identificar ambulante e gerar documento
                    </button>
                    {registro.documento && (
                        <p className="pw-fraco" style={{ margin: '10px 0 0' }}>
                            Documento já emitido: {registro.documento}
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}
