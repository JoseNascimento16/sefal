import { useState } from 'react';

import { irPara, useApp } from '../app';
import { Selo, Topo, Vazio } from '../componentes';
import { FISCAL, nomeRegiao, type Registro, type ViaImpressa } from '../dados-prototipo';
import { Icone } from '../icones';
import { htmlDaVia, imprimirHtml } from '../impressao';

/* ============================================================================
   A VIA DO NOTIFICADO — o que sai da impressora de bolso.
   ----------------------------------------------------------------------------
   O último passo do manual do cliente é "entregar a via ao notificado", e é o
   passo que decide se o documento vale: sem papel na mão de quem foi
   notificado, o prazo não corre e a defesa não existe.

   A bobina é de 80mm. Isso não é detalhe estético — é o que obriga o documento
   a sair em UMA COLUNA, com rótulo em cima e valor embaixo, e é por isso que a
   prévia aqui tem largura fixa em vez de acompanhar a tela: o fiscal precisa
   ver, antes de imprimir, o que vai caber e o que vai quebrar de linha.

   A impressão é a do CODECON (`impressao.ts`): o cupom vai ao diálogo de
   impressão do aparelho, que alcança a térmica Bluetooth pelo serviço de
   impressão pareado — ou salva em PDF. O conteúdo sai congelado do momento da
   lavratura (`registro.via`), e não remontado a partir de tela nenhuma.
   ============================================================================ */

export function TelaVia({ id }: { id: string | null }) {
    const { registros } = useApp();
    const registro = registros.find((r) => r.id === id);
    const [saindo, setSaindo] = useState(false);
    const [saiu, setSaiu] = useState(0);
    const [falha, setFalha] = useState<string | null>(null);

    if (!registro || !registro.documento) {
        return (
            <div className="pw-tela">
                <Topo titulo="Via do documento" aoVoltar={() => irPara('registros')} />
                <div className="pw-corpo">
                    <Vazio
                        icone="🧾"
                        titulo="Nenhum documento lavrado"
                        texto="A via existe depois que a Notificação ou o Auto de Apreensão é lavrado."
                    />
                </div>
            </div>
        );
    }

    const via = registro.via ?? viaReconstituida(registro);
    const naSessao = registro.via !== null;

    const imprimir = async () => {
        if (saindo) {
            return;
        }

        setSaindo(true);
        setFalha(null);

        try {
            await imprimirHtml(htmlDaVia(via, FISCAL));
            setSaiu((n) => n + 1);
        } catch (erro) {
            setFalha(erro instanceof Error ? erro.message : 'Não foi possível imprimir.');
        } finally {
            setSaindo(false);
        }
    };

    return (
        <div className="pw-tela">
            <Topo
                titulo={via.titulo === 'AUTO DE APREENSÃO' ? 'Auto de Apreensão' : 'Notificação Preliminar'}
                subtitulo={`Nº ${via.numero} · via do notificado`}
                aoVoltar={() => irPara(`recibo/${registro.id}`)}
            />

            <div className="pw-corpo">
                <div className="pw-linha" style={{ gap: 8, flexWrap: 'wrap' }}>
                    <Selo tom="neutro">
                        <Icone nome="imprimir" tamanho={13} /> Bobina 80 mm
                    </Selo>
                    <Selo tom={saiu > 0 ? 'ok' : 'neutro'}>
                        {saiu === 0
                            ? 'Nenhuma via impressa'
                            : saiu === 1
                              ? '1 via impressa'
                              : `${saiu} vias impressas`}
                    </Selo>
                </div>

                {!naSessao && (
                    <p className="pw-fraco" style={{ margin: '12px 0 0', fontSize: 13 }}>
                        A via original não veio com este registro — ela foi remontada com o que o registro
                        guarda.
                    </p>
                )}

                <div className="pw-bobina-palco">
                    <div className="pw-bobina">
                        <p className="pw-bobina-orgao">
                            PREFEITURA MUNICIPAL DE SALVADOR
                            <br />
                            SECRETARIA MUNICIPAL DE ORDEM PÚBLICA
                            <br />
                            SEMOP · SEFAL
                        </p>

                        <p className="pw-bobina-titulo">{via.titulo}</p>
                        <p className="pw-bobina-numero">Nº {via.numero}</p>

                        <hr className="pw-bobina-corte" />

                        {via.campos.map((campo) => (
                            <div key={campo.rotulo} className="pw-bobina-campo">
                                <span>{campo.rotulo}</span>
                                <strong>{campo.valor}</strong>
                            </div>
                        ))}

                        {via.listas
                            .filter((lista) => lista.itens.length > 0)
                            .map((lista) => (
                                <div key={lista.titulo}>
                                    <hr className="pw-bobina-corte" />
                                    <p className="pw-bobina-secao">{lista.titulo.toUpperCase()}</p>
                                    <ul className="pw-bobina-lista">
                                        {lista.itens.map((item) => (
                                            <li key={item}>{item}</li>
                                        ))}
                                    </ul>
                                </div>
                            ))}

                        <hr className="pw-bobina-corte" />

                        {via.assinaturas.map((assinatura) => (
                            <div key={assinatura.rotulo} className="pw-bobina-assinatura">
                                <span className="pw-bobina-linha-assinatura" />
                                <span>
                                    {assinatura.rotulo}
                                    {assinatura.nome ? ` · ${assinatura.nome}` : ''}
                                </span>
                                <strong>
                                    {assinatura.estado === 'assinada'
                                        ? 'Assinou'
                                        : assinatura.estado === 'recusada'
                                          ? 'RECUSOU ASSINAR'
                                          : 'Não colhida'}
                                </strong>
                            </div>
                        ))}

                        <div className="pw-bobina-assinatura">
                            <span className="pw-bobina-linha-assinatura" />
                            <span>Agente fiscal</span>
                            <strong>
                                {FISCAL.nome} · {FISCAL.matricula}
                            </strong>
                        </div>

                        <hr className="pw-bobina-corte" />

                        <p className="pw-bobina-rodape">{via.rodape}</p>
                    </div>
                </div>

                <button
                    type="button"
                    className="pw-btn pw-btn-acao"
                    style={{ marginTop: 20, minHeight: 58 }}
                    onClick={() => void imprimir()}
                    disabled={saindo}
                >
                    <Icone
                        nome={saindo ? 'atualizar' : 'imprimir'}
                        tamanho={20}
                        className={saindo ? 'pw-girando' : undefined}
                    />
                    {saindo ? 'Imprimindo…' : saiu > 0 ? 'Imprimir outra via' : 'Imprimir e entregar'}
                </button>

                {falha && (
                    <p role="alert" className="pw-fraco" style={{ margin: '10px 0 0', color: 'var(--pw-perigo)' }}>
                        {falha}
                    </p>
                )}

                <p className="pw-fraco" style={{ margin: '10px 0 0', fontSize: 12.5 }}>
                    Abre a impressão do aparelho: escolha a impressora térmica (pareada por Bluetooth, com o serviço de
                    impressão dela instalado) ou "Salvar como PDF".
                </p>

                <p className="pw-fraco" style={{ margin: '12px 0 0', fontSize: 13.5, lineHeight: 1.6 }}>
                    {via.tipo === 'aa'
                        ? 'Uma via fica com o notificado; a outra acompanha os bens até o SEGUB.'
                        : 'A via fica com o notificado — é ela que faz o prazo começar a correr.'}{' '}
                    O documento também sobe no próximo envio, junto com o registro.
                </p>

                <div className="pw-linha" style={{ gap: 10, marginTop: 18 }}>
                    <button
                        type="button"
                        className="pw-btn pw-btn-contorno"
                        onClick={() => irPara(`recibo/${registro.id}`)}
                    >
                        Voltar ao recibo
                    </button>
                    <button
                        type="button"
                        className="pw-btn pw-btn-fantasma"
                        onClick={() => irPara('registrar')}
                    >
                        <Icone nome="mais" tamanho={18} />
                        Próximo ponto
                    </button>
                </div>
            </div>
        </div>
    );
}

/**
 * A via de um documento lavrado FORA desta sessão.
 *
 * Um registro que veio do servidor pode ter número de documento sem a via
 * guardada — lavrado antes de a via passar a ser gravada, ou em outro
 * aparelho. Em vez de mostrar uma tela vazia (e parecer que a impressão está
 * quebrada), remontamos o mínimo verificável a partir do próprio registro, e
 * a tela avisa que foi remontado.
 */
const viaReconstituida = (registro: Registro): ViaImpressa => {
    const apreensao = registro.documentoTipo === 'aa';

    return {
        tipo: apreensao ? 'aa' : 'np',
        numero: (registro.documento ?? '').replace(/^\w+\s/, ''),
        titulo: apreensao ? 'AUTO DE APREENSÃO' : 'NOTIFICAÇÃO PRELIMINAR',
        campos: [
            { rotulo: 'Referência', valor: registro.referencia ?? '—' },
            { rotulo: 'Nome', valor: registro.ambulante ?? 'Não identificado' },
            { rotulo: 'Local da atividade', valor: registro.endereco },
            { rotulo: 'Bairro / região', valor: nomeRegiao(registro.regiao) },
            { rotulo: 'Data / hora', valor: `${registro.dataBr} às ${registro.hora}` },
            { rotulo: 'Vencimento', valor: registro.retornoBr ?? '—' },
            { rotulo: 'Agente fiscal', valor: `${FISCAL.nome} — matrícula ${FISCAL.matricula}` },
        ],
        listas: [
            {
                titulo: apreensao ? 'Discriminação do material' : 'Constatação',
                itens: registro.relato ? [registro.relato] : [],
            },
        ],
        assinaturas: [{ rotulo: 'Notificado', estado: 'assinada' }],
        rodape: 'Rua 28 de Setembro, nº 26 — Baixa dos Sapateiros — CEP 40020-240 — Salvador/BA',
    };
};
