import { irPara, useApp } from '../app';
import { Aviso, Selo, Topo, Vazio } from '../componentes';
import { numeroReservado, restamNaFaixa, type Faixa } from '../dados-documentos';
import { DOCUMENTOS_DE_CAMPO, nomeRegiao } from '../dados-prototipo';
import { Icone } from '../icones';

/* ============================================================================
   DOCUMENTOS DE CAMPO — a escalada, agora com os papéis certos.
   ----------------------------------------------------------------------------
   O protótipo anterior oferecia aqui um par inventado ("Notificação de Termo" e
   "Auto de Infração"). Os blocos que o cliente entregou dizem qual é o par de
   verdade: **Notificação Preliminar** — que aponta as irregularidades e dá
   prazo para sanar — e **Auto de Apreensão** — que recolhe material e o manda
   para o SEGUB.

   Continua sendo o caminho EXCEPCIONAL, e a tela continua dizendo isso: a
   fiscalização é educativa e o registro leve resolve a maioria. Mas quando é
   preciso lavrar, o documento sai numerado no meio da rua, sem sinal — porque
   o aparelho carrega uma FAIXA de números reservada, como o bloco de papel
   carrega as folhas já impressas.
   ============================================================================ */

export function TelaDocumentos({ id }: { id: string | null }) {
    const { registros } = useApp();
    const registro = registros.find((r) => r.id === id);

    if (!registro) {
        return (
            <div className="pw-tela">
                <Topo titulo="Documentos de campo" aoVoltar={() => irPara('mapa')} />
                <div className="pw-corpo">
                    <Vazio
                        icone="📄"
                        titulo="Registro não encontrado"
                        texto="Abra um registro pela lista e escolha o documento por lá."
                    />
                </div>
            </div>
        );
    }

    return (
        <div className="pw-tela">
            <Topo
                titulo="Documentos de campo"
                subtitulo="Caminho excepcional"
                aoVoltar={() => irPara(`recibo/${registro.id}`)}
            />

            <div className="pw-corpo">
                <Aviso>
                    Lavrar documento não é o padrão: use quando o notificado tiver irregularidade a sanar
                    em prazo, ou quando houver material a recolher.
                </Aviso>

                <p className="pw-titulo-secao">Sobre qual fiscalização</p>

                <div className="pw-card">
                    <p className="pw-forte" style={{ margin: 0 }}>
                        {registro.endereco}
                    </p>
                    <p className="pw-fraco" style={{ margin: '2px 0 0' }}>
                        {nomeRegiao(registro.regiao)} · {registro.dataBr} às {registro.hora} · protocolo{' '}
                        {registro.protocolo}
                    </p>
                    {registro.referencia && (
                        <p className="pw-fraco" style={{ margin: '8px 0 0', fontSize: 13 }}>
                            <Icone nome="caixa-entrada" tamanho={13} /> Referência {registro.referencia} —
                            entra no documento.
                        </p>
                    )}
                </div>

                {registro.documento && (
                    <div className="pw-card" style={{ marginTop: 12, borderColor: 'var(--pw-ok)' }}>
                        <p className="pw-forte" style={{ margin: 0 }}>
                            <Icone nome="certo" tamanho={16} /> {registro.documento} já lavrado
                        </p>
                        <p className="pw-fraco" style={{ margin: '4px 0 12px' }}>
                            A via do notificado pode ser impressa de novo a qualquer momento.
                        </p>
                        <button
                            type="button"
                            className="pw-btn pw-btn-contorno pw-btn-pequeno"
                            onClick={() => irPara(`via/${registro.id}`)}
                        >
                            <Icone nome="imprimir" tamanho={16} />
                            Ver a via
                        </button>
                    </div>
                )}

                <p className="pw-titulo-secao">O que lavrar</p>

                {DOCUMENTOS_DE_CAMPO.map((doc) => (
                    <button
                        key={doc.id}
                        type="button"
                        className="pw-card pw-card-toque pw-card-documento"
                        onClick={() =>
                            irPara(`${doc.id === 'np' ? 'notificacao' : 'apreensao'}/${registro.id}`)
                        }
                    >
                        <span className="pw-doc-icone" aria-hidden="true">
                            {doc.emoji}
                        </span>
                        <span style={{ minWidth: 0, flex: 1, textAlign: 'left' }}>
                            <span className="pw-linha-espalha" style={{ marginBottom: 4 }}>
                                <span className="pw-forte" style={{ fontSize: 16.5 }}>
                                    {doc.titulo}
                                </span>
                                <Selo tom="neutro">Nº {numeroReservado(doc.id as Faixa)}</Selo>
                            </span>
                            <span className="pw-fraco" style={{ display: 'block', fontSize: 14 }}>
                                {doc.descricao}
                            </span>
                            <span className="pw-fraco" style={{ display: 'block', marginTop: 6, fontSize: 12.5 }}>
                                {restamNaFaixa(doc.id as Faixa)} números reservados neste aparelho
                            </span>
                        </span>
                        <Icone nome="seta" tamanho={20} />
                    </button>
                ))}

                <p className="pw-fraco" style={{ marginTop: 16, fontSize: 13.5, lineHeight: 1.6 }}>
                    A numeração vem de uma faixa reservada ao aparelho, como as folhas do bloco de papel:
                    o documento nasce numerado mesmo sem sinal, e o número não se repete quando outro
                    fiscal lavrar o dele.
                </p>
            </div>
        </div>
    );
}
