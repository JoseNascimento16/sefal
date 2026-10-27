import { useMemo } from 'react';

import { irPara, useApp } from '../app';
import { Selo, Topo, Vazio } from '../componentes';
import { DEMANDAS, type Desfecho } from '../dados-demandas';
import { OPERACOES } from '../dados-prototipo';
import { Icone } from '../icones';
import { CartaoDemanda } from './demandas';

/**
 * A OPERAÇÃO, por inteiro — o que a Retaguarda planejou e as denúncias que o
 * Chefe de Setor anexou a ela.
 *
 * O cartão da tela inicial diz que a operação existe; esta tela diz o que fazer
 * nela. Antes, tocar no cartão só abria o mapa no primeiro bairro, e o fiscal
 * não tinha como saber quais denúncias estavam na operação.
 */
export function TelaOperacao({ id }: { id: string | null }) {
    const { registros } = useApp();
    const operacao = OPERACOES.find((o) => o.id === id) ?? null;

    const registrado = useMemo(() => {
        const mapa = new Map<string, Desfecho>();

        for (const r of registros) {
            if (r.demandaId) {
                mapa.set(r.demandaId, r.desfecho);
            }
        }

        return mapa;
    }, [registros]);

    if (!operacao) {
        return (
            <div className="pw-tela">
                <Topo titulo="Operação" aoVoltar={() => irPara('inicio')} />
                <div className="pw-corpo">
                    <Vazio
                        icone="🚩"
                        titulo="Operação não encontrada"
                        texto="Ela pode ter sido encerrada na Retaguarda. Volte ao início para ver as operações abertas."
                    />
                </div>
            </div>
        );
    }

    const denuncias = DEMANDAS.filter((d) => d.operacaoId === operacao.id);
    const bairros = operacao.bairros ?? [];
    const tom = operacao.tom === 'agora' ? 'perigo' : operacao.tom === 'proxima' ? 'alerta' : 'info';

    return (
        <div className="pw-tela">
            <Topo titulo="Operação" subtitulo={operacao.nome} aoVoltar={() => irPara('inicio')} />

            <div className="pw-corpo">
                <div className="pw-card">
                    <div className="pw-linha-espalha" style={{ marginBottom: 6 }}>
                        <span className="pw-forte" style={{ fontSize: 17 }}>
                            {operacao.nome}
                        </span>
                        <Selo tom={tom}>{operacao.quando}</Selo>
                    </div>

                    <dl className="pw-ficha">
                        {operacao.tipo && (
                            <>
                                <dt>Tipo</dt>
                                <dd>{operacao.tipo}</dd>
                            </>
                        )}
                        <dt>Período</dt>
                        <dd>
                            {operacao.quando} · {operacao.horario}
                        </dd>
                        {operacao.local && (
                            <>
                                <dt>Foco</dt>
                                <dd>{operacao.local}</dd>
                            </>
                        )}
                        <dt>Bairros</dt>
                        <dd>{bairros.length > 0 ? bairros.join(', ') : 'Sem bairro definido'}</dd>
                        <dt>Fiscais</dt>
                        <dd>{operacao.fiscais === 1 ? '1 fiscal' : `${operacao.fiscais} fiscais`}</dd>
                        {operacao.abertaPor && (
                            <>
                                <dt>Aberta por</dt>
                                <dd>{operacao.abertaPor}</dd>
                            </>
                        )}
                    </dl>

                    {operacao.observacao && (
                        <p className="pw-fraco" style={{ margin: '10px 0 0' }}>
                            {operacao.observacao}
                        </p>
                    )}

                    {operacao.regiao && (
                        <button
                            type="button"
                            className="pw-btn pw-btn-contorno pw-btn-pequeno"
                            style={{ marginTop: 12 }}
                            onClick={() => irPara(`mapa/${operacao.regiao}`)}
                        >
                            <Icone nome="mapa" tamanho={16} /> Ver no mapa
                        </button>
                    )}
                </div>

                <p className="pw-titulo-secao">
                    Denúncias da operação{denuncias.length > 0 ? ` · ${denuncias.length}` : ''}
                </p>

                {denuncias.length === 0 ? (
                    <Vazio
                        icone="🗂️"
                        titulo="Nenhuma denúncia anexada"
                        texto="O Chefe de Setor não anexou denúncia a esta operação. A ronda segue pelos bairros acima, com registro avulso."
                    />
                ) : (
                    denuncias.map((demanda) => (
                        <CartaoDemanda
                            key={demanda.id}
                            demanda={demanda}
                            registrado={registrado.get(demanda.id) ?? null}
                        />
                    ))
                )}

                <button
                    type="button"
                    className="pw-btn pw-btn-acao"
                    style={{ marginTop: 18, minHeight: 58 }}
                    onClick={() => irPara('registrar')}
                >
                    <Icone nome="mais" tamanho={20} />
                    Registrar fiscalização avulsa
                </button>
            </div>
        </div>
    );
}
