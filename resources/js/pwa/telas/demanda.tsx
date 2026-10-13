import { irPara, useApp } from '../app';
import { Selo, Topo, Vazio } from '../componentes';
import {
    ORIGENS,
    SITUACOES_EXPLICADAS,
    TOM_DA_SITUACAO,
    acharDemanda,
    podeRegistrarRetorno,
    podeVistoriar,
    prazoDoRetornoEmPalavras,
    prazoEmPalavras,
    rotuloDoRequerente,
    tomDoPrazo,
    type Demanda,
    type DocumentoDeCampo,
    type FichaSgci,
    type RegistroDeCampo,
} from '../dados-demandas';
import { MOTIVOS_NP, nomeDoDocumento } from '../dados-documentos';
import { Icone } from '../icones';

/**
 * A denúncia por inteiro — o que aconteceu com ela até chegar aqui, e o que
 * falta fazer.
 *
 * O cartão da fila responde "vou ou não vou agora". Esta tela responde três
 * outras: o que o cidadão relatou, COMO a denúncia chegou até esta equipe (a
 * triagem escolheu a área, o Chefe de Setor escolheu equipe ou operação) e, quando ela
 * já andou, o que a equipe registrou no ponto e o documento que lavrou.
 *
 * O ato oferecido no fim muda com a situação: vistoriar, continuar a vistoria
 * aberta, registrar o retorno de um prazo — ou nenhum, quando a denúncia já se
 * encerrou. Oferecer sempre "Fiscalizar" faria o fiscal abrir uma vistoria nova
 * sobre uma denúncia concluída.
 */
export function TelaDemanda({ id }: { id: string | null }) {
    const { registros } = useApp();
    const demanda = acharDemanda(id);

    if (!demanda) {
        return (
            <div className="pw-tela">
                <Topo titulo="Denúncia" aoVoltar={() => irPara('demandas')} />
                <div className="pw-corpo">
                    <Vazio
                        icone="📭"
                        titulo="Denúncia não encontrada"
                        texto="Volte à fila da equipe e escolha por lá."
                    />
                </div>
            </div>
        );
    }

    const origem = ORIGENS[demanda.origem];
    const atendimentos = registros.filter((r) => r.demandaId === demanda.id);
    const emRetorno = podeRegistrarRetorno(demanda);

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
                    <Selo tom={TOM_DA_SITUACAO[demanda.situacao]}>{demanda.situacao}</Selo>
                    <Selo tom={tomDoPrazo(demanda.prazoDias)}>
                        <Icone nome="relogio" tamanho={13} />
                        {prazoEmPalavras(demanda.prazoDias)} · {demanda.prazoBr}
                    </Selo>
                </div>

                {/* A situação sozinha é uma palavra colorida. O que o fiscal
                    precisa saber é DE QUEM É A BOLA — e "Aguardando
                    regularização" e "Retorno vencido" respondem coisas opostas. */}
                <p className="pw-fraco" style={{ margin: '10px 0 0', fontSize: 13.5 }}>
                    {SITUACOES_EXPLICADAS[demanda.situacao]}
                </p>

                <p className="pw-titulo-secao">O que a ouvidoria recebeu</p>

                <div className="pw-card">
                    <p style={{ margin: 0, fontSize: 15, lineHeight: 1.55 }}>{demanda.detalhe}</p>
                    <p className="pw-fraco" style={{ margin: '12px 0 0' }}>
                        <Icone nome="pessoa" tamanho={13} /> {rotuloDoRequerente(demanda)}
                    </p>
                    <p className="pw-fraco" style={{ margin: '2px 0 0', fontSize: 12.5 }}>
                        {origem.rotulo} nº {demanda.documentoOrigem} · recebida em{' '}
                        {demanda.recebidaBr}
                    </p>
                    <p className="pw-fraco" style={{ margin: '10px 0 0', fontSize: 13 }}>
                        {origem.explica}
                    </p>
                </div>

                <p className="pw-titulo-secao">Onde</p>

                <div className="pw-card">
                    <p className="pw-forte" style={{ margin: 0, fontSize: 15.5 }}>
                        {demanda.endereco}
                    </p>
                    <p className="pw-fraco" style={{ margin: '2px 0 12px' }}>
                        {demanda.bairro}
                        {demanda.referencia ? ` · ${demanda.referencia}` : ''}
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

                <ComoChegou demanda={demanda} />

                {demanda.sgci ? (
                    <FichaDoCadastro ficha={demanda.sgci} />
                ) : (
                    <>
                        {/* Não é "cadastro faltando": é ambulante SEM PERMISSÃO,
                            que é a maior parte de quem a fiscalização encontra —
                            e é essa resposta que define o prazo do documento. */}
                        <p className="pw-titulo-secao">Permissão da SEMOP</p>
                        <div className="pw-card">
                            <p style={{ margin: '0 0 8px', fontSize: 14.5 }}>
                                <span className="pw-forte">Ambulante sem permissão registrada.</span> Não
                                há permissão da SEMOP localizada para este endereço no SGCI.
                            </p>
                            <p style={{ margin: 0, fontSize: 14.5 }}>
                                O documento sai com a identificação colhida em campo, e o prazo para sanar
                                é <span className="pw-forte">imediato</span> — é a regra do manual para
                                quem não tem permissão.
                            </p>
                        </div>
                    </>
                )}

                {demanda.campo && <RegistroDaEquipe campo={demanda.campo} />}

                {demanda.documento && <DocumentoLavrado documento={demanda.documento} />}

                {demanda.desfecho && (
                    <>
                        <p className="pw-titulo-secao">Desfecho registrado</p>
                        <div className="pw-card">
                            <p className="pw-forte" style={{ margin: 0, fontSize: 15.5 }}>
                                {demanda.desfecho}
                            </p>
                            <p className="pw-fraco" style={{ margin: '4px 0 0', fontSize: 13 }}>
                                É este desfecho que fecha o passo do trâmite na Retaguarda.
                            </p>
                        </div>
                    </>
                )}

                {atendimentos.length > 0 && (
                    <>
                        <p className="pw-titulo-secao">Registrado neste aparelho</p>
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
                                        {r.desfecho}
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

                <AtoDevido demanda={demanda} emRetorno={emRetorno} />
            </div>
        </div>
    );
}

/**
 * Como a denúncia chegou até esta equipe — as duas etapas, com nome.
 *
 * O fiscal não participa de nenhuma das duas, e é justamente por isso que elas
 * ficam escritas: sem elas, a denúncia apareceria na fila como se tivesse
 * brotado ali, e a pergunta "por que isto é meu?" não teria resposta na tela.
 */
function ComoChegou({ demanda }: { demanda: Demanda }) {
    return (
        <>
            <p className="pw-titulo-secao">Como chegou até a sua equipe</p>

            <div className="pw-card">
                <dl className="pw-ficha">
                    <dt>Triagem</dt>
                    <dd>
                        Encaminhada à {demanda.area ?? 'área não definida'}
                        <br />
                        <span className="pw-fraco">
                            O Coordenador tria o que a ouvidoria entrega e escolhe a área pelo bairro.
                        </span>
                    </dd>
                    <dt>Chefe de Setor</dt>
                    <dd>
                        {demanda.operacao
                            ? `Anexada à ${demanda.operacao}`
                            : `Direcionada à Equipe ${demanda.equipe ?? '—'}`}
                        <br />
                        <span className="pw-fraco">
                            {demanda.operacao
                                ? 'A denúncia entrou numa operação já planejada para a região, em vez de gerar uma ida isolada ao local.'
                                : 'Vistoria dirigida à equipe da área, com prazo para atender.'}
                        </span>
                    </dd>
                </dl>
            </div>
        </>
    );
}

/** O que a equipe encontrou no ponto — o passo de campo, em leitura. */
function RegistroDaEquipe({ campo }: { campo: RegistroDeCampo }) {
    return (
        <>
            <p className="pw-titulo-secao">O que a equipe registrou no ponto</p>

            <div className="pw-card">
                <div className="pw-linha-espalha" style={{ marginBottom: 10 }}>
                    <span className="pw-forte" style={{ fontSize: 15 }}>
                        {campo.encontrado}
                    </span>
                    <Selo tom="neutro">{campo.quandoBr}</Selo>
                </div>

                <p style={{ margin: 0, fontSize: 14.5, lineHeight: 1.55 }}>{campo.relato}</p>

                {campo.ambulante && (
                    <p className="pw-fraco" style={{ margin: '10px 0 0', fontSize: 13 }}>
                        <Icone nome="pessoa" tamanho={13} /> {campo.ambulante}
                    </p>
                )}
                {campo.equipamento && (
                    <p className="pw-fraco" style={{ margin: '2px 0 0', fontSize: 13 }}>
                        <Icone nome="prancheta" tamanho={13} /> {campo.equipamento}
                    </p>
                )}

                <div className="pw-linha" style={{ gap: 8, flexWrap: 'wrap', marginTop: 12 }}>
                    <Selo tom="neutro">
                        <Icone nome="camera" tamanho={13} />
                        {campo.fotos === 1 ? '1 foto' : `${campo.fotos} fotos`}
                    </Selo>
                    {campo.gps && (
                        /* A precisão anda junto com a coordenada: ponto ruim é pior
                           que ponto ausente disfarçado de bom. */
                        <Selo tom="neutro">
                            <Icone nome="alvo" tamanho={13} />
                            {campo.gps} · ±{campo.precisaoM} m
                        </Selo>
                    )}
                </div>
            </div>
        </>
    );
}

/**
 * O documento lavrado em rua, em LEITURA.
 *
 * A redação dos motivos não é copiada para cá: a denúncia guarda a CHAVE
 * (`puxada`, `mesas`) e o texto vem do impresso, em `dados-documentos.ts`.
 * Guardar o texto junto daria dois donos à redação de um formulário legal.
 */
function DocumentoLavrado({ documento }: { documento: DocumentoDeCampo }) {
    const motivos = documento.motivos.map(
        (chave) => MOTIVOS_NP.find((m) => m.id === chave)?.texto ?? chave,
    );

    return (
        <>
            <p className="pw-titulo-secao">Documento lavrado</p>

            <div className="pw-card">
                <div className="pw-linha-espalha" style={{ marginBottom: 8 }}>
                    <span className="pw-forte" style={{ fontSize: 15.5 }}>
                        {nomeDoDocumento(documento.tipo)}
                    </span>
                    <Selo tom="info">Nº {documento.numero}</Selo>
                </div>

                <dl className="pw-ficha">
                    <dt>Notificado</dt>
                    <dd>{documento.notificado}</dd>
                    <dt>Lavrado em</dt>
                    <dd>{documento.lavradoBr}</dd>
                    {documento.prazoRotulo && (
                        <>
                            <dt>Prazo</dt>
                            <dd>
                                {documento.prazoRotulo}
                                {documento.venceBr && documento.venceEmDias !== null && (
                                    <>
                                        {' '}
                                        <Selo tom={tomDoPrazo(documento.venceEmDias)}>
                                            <Icone nome="relogio" tamanho={13} />
                                            {prazoDoRetornoEmPalavras(documento.venceEmDias)} ·{' '}
                                            {documento.venceBr}
                                        </Selo>
                                    </>
                                )}
                            </dd>
                        </>
                    )}
                </dl>

                {motivos.length > 0 && (
                    <>
                        <p className="pw-forte" style={{ margin: '12px 0 6px', fontSize: 14 }}>
                            Assinalado no impresso
                        </p>
                        <ul style={{ margin: 0, paddingLeft: 18, fontSize: 14, lineHeight: 1.5 }}>
                            {motivos.map((texto) => (
                                <li key={texto}>{texto}</li>
                            ))}
                        </ul>
                    </>
                )}

                <div className="pw-linha" style={{ gap: 8, flexWrap: 'wrap', marginTop: 12 }}>
                    {documento.assinaturas.map((a) => (
                        <Selo
                            key={a.rotulo}
                            tom={
                                a.estado === 'assinada'
                                    ? 'ok'
                                    : a.estado === 'recusada'
                                      ? 'perigo'
                                      : 'neutro'
                            }
                        >
                            <Icone nome="assinar" tamanho={13} />
                            {a.rotulo}
                            {a.estado === 'assinada'
                                ? ' · assinou'
                                : a.estado === 'recusada'
                                  ? ' · recusou assinar'
                                  : ' · não colhida'}
                        </Selo>
                    ))}
                </div>

                {/* Recusar assinar é fato jurídico corriqueiro — o documento
                    registra a recusa em vez de esconder, e o fiscal precisa ver
                    isso antes de voltar ao ponto. */}
            </div>
        </>
    );
}

/** O ato que a situação pede — e nada, quando a denúncia já se encerrou. */
function AtoDevido({ demanda, emRetorno }: { demanda: Demanda; emRetorno: boolean }) {
    if (!podeVistoriar(demanda) && !emRetorno) {
        return (
            <div className="pw-card" style={{ marginTop: 20 }}>
                <p style={{ margin: 0, fontSize: 14.5 }}>
                    {demanda.situacao === 'Retorno vencido'
                        ? 'O prazo venceu com a situação mantida, e a denúncia voltou ao Chefe de Setor da área para a próxima medida. A equipe não tem ato pendente aqui.'
                        : 'Denúncia encerrada. Não há ato pendente para a equipe.'}
                </p>
            </div>
        );
    }

    return (
        <>
            <button
                type="button"
                className="pw-btn pw-btn-acao"
                style={{ marginTop: 20, minHeight: 60 }}
                onClick={() => irPara(`registrar/${demanda.id}`)}
            >
                <Icone nome={emRetorno ? 'relogio' : 'mais'} tamanho={20} />
                {emRetorno
                    ? 'Registrar o retorno'
                    : demanda.situacao === 'Em campo'
                      ? 'Continuar a vistoria'
                      : 'Fiscalizar esta denúncia'}
            </button>

            <p className="pw-fraco" style={{ textAlign: 'center', marginTop: 10, fontSize: 13 }}>
                {emRetorno
                    ? `O retorno confere o prazo da ${nomeDoDocumento(demanda.documento?.tipo ?? 'np')} nº ${demanda.documento?.numero ?? '—'}.`
                    : `A fiscalização nasce vinculada à denúncia ${demanda.protocolo}.`}
            </p>
        </>
    );
}

/**
 * A ficha da permissão no SGCI.
 *
 * Ela é a prova do ATRIBUTO: quem o sistema fiscaliza é o ambulante, e ter
 * permissão da SEMOP é uma característica dele — não a categoria de todos. Por
 * isso a ficha se apresenta dizendo "ambulante · permissionário SEMOP", e não
 * apenas "cadastro".
 *
 * ⚠️ Rotulada e emoldurada de propósito: nada aqui foi digitado pelo fiscal, e
 * nada aqui se edita em campo. É o cadastro de comércio informal do município
 * falando — quando a integração existir. Hoje é ficção, e a tela diz isso.
 */
function FichaDoCadastro({ ficha }: { ficha: FichaSgci }) {
    return (
        <>
            <p className="pw-titulo-secao">Permissão da SEMOP</p>

            <div className="pw-card pw-card-sgci">
                <div className="pw-linha-espalha" style={{ marginBottom: 10 }}>
                    <span className="pw-selo pw-selo-sgci">
                        <Icone nome="prancheta" tamanho={13} /> SGCI
                    </span>
                    <Selo tom={ficha.situacao === 'Ativo' ? 'ok' : 'alerta'}>{ficha.situacao}</Selo>
                </div>

                <p className="pw-forte" style={{ margin: '0 0 10px', fontSize: 14.5 }}>
                    Ambulante · permissionário SEMOP
                </p>

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
