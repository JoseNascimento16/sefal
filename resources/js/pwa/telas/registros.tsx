import { useMemo, useState } from 'react';

import { irPara, useApp } from '../app';
import { Selo, SeloSituacao, Topo, Vazio, atalhoDoPerfil } from '../componentes';
import { FISCAL, HOJE_BR, OCORRENCIAS, nomeRegiao, type Registro } from '../dados-prototipo';
import { Icone } from '../icones';

/**
 * O que o fiscal fez — hoje e na semana.
 *
 * A lista é do TURNO, não do arquivo: quem abre aqui quer conferir o que
 * acabou de registrar, achar o ponto onde marcou retorno e ver o que ainda não
 * subiu. Consulta histórica é assunto da Retaguarda, na mesa.
 */
export function TelaRegistros() {
    const { registros } = useApp();
    const [periodo, setPeriodo] = useState<'hoje' | 'semana'>('hoje');

    const lista = useMemo(
        () => (periodo === 'hoje' ? registros.filter((r) => r.dataBr === HOJE_BR) : registros),
        [registros, periodo],
    );

    const irregulares = lista.filter((r) => r.status === 'irregular').length;
    const retornos = lista.filter((r) => r.retornoBr).length;

    return (
        <div className="pw-tela">
            <Topo
                titulo="Meus registros"
                subtitulo={
                    lista.length === 1 ? '1 fiscalização no recorte' : `${lista.length} fiscalizações no recorte`
                }
                perfil={atalhoDoPerfil(() => irPara('perfil'), FISCAL.iniciais)}
            />

            <div className="pw-corpo">
                <div className="pw-periodo" style={{ gridTemplateColumns: '1fr 1fr' }}>
                    <button
                        type="button"
                        className={periodo === 'hoje' ? 'pw-periodo-ativo' : undefined}
                        onClick={() => setPeriodo('hoje')}
                    >
                        Hoje
                    </button>
                    <button
                        type="button"
                        className={periodo === 'semana' ? 'pw-periodo-ativo' : undefined}
                        onClick={() => setPeriodo('semana')}
                    >
                        Últimos 7 dias
                    </button>
                </div>

                <div className="pw-linha" style={{ gap: 8, flexWrap: 'wrap', margin: '14px 0 4px' }}>
                    <Selo tom="alerta">
                        <Icone nome="alerta" tamanho={13} />
                        {irregulares === 1 ? '1 irregular' : `${irregulares} irregulares`}
                    </Selo>
                    <Selo tom="ok">
                        <Icone nome="certo" tamanho={13} />
                        {lista.length - irregulares === 1
                            ? '1 regular'
                            : `${lista.length - irregulares} regulares`}
                    </Selo>
                    <Selo tom="info">
                        <Icone nome="relogio" tamanho={13} />
                        {retornos === 1 ? '1 retorno marcado' : `${retornos} retornos marcados`}
                    </Selo>
                </div>

                {lista.length === 0 ? (
                    <Vazio
                        icone="📋"
                        titulo="Nenhum registro no recorte"
                        texto="Toque em Fiscalizar no mapa para abrir o primeiro do turno."
                    />
                ) : (
                    lista.map((registro) => <Cartao key={registro.id} registro={registro} />)
                )}
            </div>
        </div>
    );
}

function Cartao({ registro }: { registro: Registro }) {
    const rotulos = registro.ocorrencias
        .map((o) => OCORRENCIAS.find((x) => x.id === o))
        .filter((o): o is (typeof OCORRENCIAS)[number] => Boolean(o));

    return (
        <button
            type="button"
            className="pw-card pw-card-toque"
            onClick={() => irPara(`recibo/${registro.id}`)}
        >
            <div className="pw-linha-espalha" style={{ marginBottom: 8 }}>
                <span className="pw-forte" style={{ fontSize: 17 }}>
                    {registro.hora}
                    <span className="pw-fraco" style={{ marginLeft: 8, fontWeight: 500 }}>
                        {registro.dataBr}
                    </span>
                </span>
                <SeloSituacao situacao={registro.status} />
            </div>

            <p style={{ margin: 0, fontSize: 15 }}>{registro.endereco}</p>
            <p className="pw-fraco" style={{ margin: '2px 0 10px' }}>
                {nomeRegiao(registro.regiao)} · {registro.ambulante ?? 'Não identificado'}
            </p>

            {registro.origem === 'dirigida' && registro.referencia && (
                <p style={{ margin: '0 0 10px' }}>
                    <span className="pw-selo pw-selo-origem">
                        <Icone nome="caixa-entrada" tamanho={13} />
                        {registro.referencia}
                    </span>
                </p>
            )}

            {rotulos.length > 0 && (
                <div className="pw-linha" style={{ flexWrap: 'wrap', gap: 6, marginBottom: 10 }}>
                    {rotulos.map((o) => (
                        <Selo key={o.id} tom="neutro">
                            {o.emoji} {o.rotulo}
                        </Selo>
                    ))}
                </div>
            )}

            <div className="pw-linha-espalha">
                <span className="pw-miniaturas">
                    {Array.from({ length: Math.min(registro.fotos, 4) }).map((_, i) => (
                        <span key={i} className="pw-miniatura">
                            📷
                        </span>
                    ))}
                    {registro.fotos > 4 && (
                        <span className="pw-miniatura pw-fraco">+{registro.fotos - 4}</span>
                    )}
                    {registro.fotos === 0 && <span className="pw-fraco">Sem foto</span>}
                </span>

                <span className="pw-linha" style={{ gap: 6 }}>
                    {registro.documento && (
                        <Selo tom="info">
                            <Icone nome="documento" tamanho={13} /> {registro.documento}
                        </Selo>
                    )}
                    {registro.retornoBr && (
                        <Selo tom="perigo">
                            <Icone nome="relogio" tamanho={13} /> {registro.retornoBr}
                        </Selo>
                    )}
                    {registro.envio !== 'enviado' && (
                        <Selo tom={registro.envio === 'erro' ? 'perigo' : 'neutro'}>
                            {registro.envio === 'erro' ? 'Falhou' : 'Na fila'}
                        </Selo>
                    )}
                </span>
            </div>
        </button>
    );
}
