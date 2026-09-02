import { irPara, useApp } from '../app';
import { Selo, Topo, Vazio } from '../componentes';
import {
    ORIGENS,
    acharDemanda,
    prazoEmPalavras,
    tomDoPrazo,
    type FichaSgci,
} from '../dados-demandas';
import { Icone } from '../icones';

/**
 * A demanda por inteiro, antes de sair para a rua.
 *
 * O cartão da fila responde "vou ou não vou agora". Esta tela responde "o que
 * eu vou encontrar lá" — o texto do que o cidadão reclamou, o número do
 * processo que vai para o campo REFERÊNCIA do documento e, quando o alvo é
 * permissionário, a ficha do cadastro.
 */
export function TelaDemanda({ id }: { id: string | null }) {
    const { registros } = useApp();
    const demanda = acharDemanda(id);

    if (!demanda) {
        return (
            <div className="pw-tela">
                <Topo titulo="Demanda" aoVoltar={() => irPara('demandas')} />
                <div className="pw-corpo">
                    <Vazio
                        icone="📭"
                        titulo="Demanda não encontrada"
                        texto="Volte à fila da equipe e escolha por lá."
                    />
                </div>
            </div>
        );
    }

    const origem = ORIGENS[demanda.origem];
    const atendimentos = registros.filter((r) => r.demandaId === demanda.id);

    return (
        <div className="pw-tela">
            <Topo
                titulo={demanda.assunto}
                subtitulo={`${origem.rotulo} · ${demanda.protocolo}`}
                aoVoltar={() => irPara('demandas')}
            />

            <div className="pw-corpo">
                <div className="pw-linha" style={{ gap: 8, flexWrap: 'wrap' }}>
                    <span className="pw-selo pw-selo-origem">
                        <span>{origem.emoji}</span>
                        {origem.rotulo}
                    </span>
                    <Selo tom={tomDoPrazo(demanda.prazoDias)}>
                        <Icone nome="relogio" tamanho={13} />
                        {prazoEmPalavras(demanda.prazoDias)} · {demanda.prazoBr}
                    </Selo>
                </div>

                <p className="pw-fraco" style={{ margin: '10px 0 0', fontSize: 13.5 }}>
                    {origem.explica}
                </p>

                <p className="pw-titulo-secao">O que foi encaminhado</p>

                <div className="pw-card">
                    <p style={{ margin: 0, fontSize: 15, lineHeight: 1.55 }}>{demanda.detalhe}</p>
                    {demanda.solicitante && (
                        <p className="pw-fraco" style={{ margin: '12px 0 0' }}>
                            <Icone nome="pessoa" tamanho={13} /> {demanda.solicitante}
                        </p>
                    )}
                </div>

                <p className="pw-titulo-secao">Onde</p>

                <div className="pw-card">
                    <p className="pw-forte" style={{ margin: 0, fontSize: 15.5 }}>
                        {demanda.endereco}
                    </p>
                    <p className="pw-fraco" style={{ margin: '2px 0 12px' }}>
                        {demanda.bairro}
                    </p>
                    <button
                        type="button"
                        className="pw-btn pw-btn-fantasma pw-btn-pequeno"
                        onClick={() => irPara(`mapa/${demanda.regiao}`)}
                    >
                        <Icone nome="mapa" tamanho={16} />
                        Ver no mapa
                    </button>
                </div>

                {demanda.sgci ? (
                    <FichaDoCadastro ficha={demanda.sgci} />
                ) : (
                    <>
                        <p className="pw-titulo-secao">Cadastro</p>
                        <div className="pw-card">
                            <p style={{ margin: 0, fontSize: 14.5 }}>
                                Sem cadastro localizado para este endereço. O documento sai com a
                                identificação colhida em campo, e o prazo para sanar é{' '}
                                <span className="pw-forte">imediato</span> — é a regra do manual para quem
                                não tem cadastro.
                            </p>
                        </div>
                    </>
                )}

                {atendimentos.length > 0 && (
                    <>
                        <p className="pw-titulo-secao">Já feito nesta demanda</p>
                        {atendimentos.map((r) => (
                            <button
                                key={r.id}
                                type="button"
                                className="pw-card pw-card-toque"
                                onClick={() => irPara(`recibo/${r.id}`)}
                            >
                                <div className="pw-linha-espalha">
                                    <span className="pw-forte">
                                        {r.dataBr} às {r.hora}
                                    </span>
                                    <Selo tom={r.status === 'regular' ? 'ok' : 'alerta'}>
                                        {r.status === 'regular' ? 'Regular' : 'Irregular'}
                                    </Selo>
                                </div>
                                <p className="pw-fraco" style={{ margin: '4px 0 0' }}>
                                    Protocolo {r.protocolo}
                                    {r.documento ? ` · ${r.documento}` : ''}
                                </p>
                            </button>
                        ))}
                    </>
                )}

                <button
                    type="button"
                    className="pw-btn pw-btn-acao"
                    style={{ marginTop: 20, minHeight: 60 }}
                    onClick={() => irPara(`registrar/${demanda.id}`)}
                >
                    <Icone nome="mais" tamanho={20} />
                    Fiscalizar esta demanda
                </button>

                <p className="pw-fraco" style={{ textAlign: 'center', marginTop: 10, fontSize: 13 }}>
                    A fiscalização nasce vinculada ao processo {demanda.protocolo}.
                </p>
            </div>
        </div>
    );
}

/**
 * A ficha do SGCI.
 *
 * ⚠️ Rotulada e emoldurada de propósito: nada aqui foi digitado pelo fiscal, e
 * nada aqui se edita em campo. É o cadastro de comércio informal do município
 * falando — quando a integração existir. Hoje é ficção, e a tela diz isso.
 */
function FichaDoCadastro({ ficha }: { ficha: FichaSgci }) {
    return (
        <>
            <p className="pw-titulo-secao">Dados do cadastro SGCI</p>

            <div className="pw-card pw-card-sgci">
                <div className="pw-linha-espalha" style={{ marginBottom: 10 }}>
                    <span className="pw-selo pw-selo-sgci">
                        <Icone nome="prancheta" tamanho={13} /> SGCI
                    </span>
                    <Selo tom={ficha.situacao === 'Ativo' ? 'ok' : 'alerta'}>{ficha.situacao}</Selo>
                </div>

                <dl className="pw-ficha">
                    <dt>Nome</dt>
                    <dd>{ficha.nome}</dd>
                    <dt>Inscrição</dt>
                    <dd>{ficha.inscricao}</dd>
                    <dt>Atividade</dt>
                    <dd>{ficha.atividade}</dd>
                    <dt>Equipamento</dt>
                    <dd>{ficha.equipamento}</dd>
                    <dt>Permissão desde</dt>
                    <dd>{ficha.desde}</dd>
                    <dt>Preço público</dt>
                    <dd>
                        <Selo tom={ficha.damEmDia ? 'ok' : 'perigo'}>
                            {ficha.damEmDia ? 'DAM quitado no exercício' : 'DAM em aberto'}
                        </Selo>
                    </dd>
                </dl>

                <p className="pw-fraco" style={{ margin: '12px 0 0', fontSize: 12.5 }}>
                    Somente leitura — o cadastro se corrige no SGCI, não em campo. No protótipo, ficha
                    fictícia: não há integração com o SGCI.
                </p>
            </div>
        </>
    );
}
