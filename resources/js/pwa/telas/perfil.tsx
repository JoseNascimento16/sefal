import { useState } from 'react';

import { irPara, useApp } from '../app';
import { Interruptor, Selo, Topo } from '../componentes';
import { EQUIPE } from '../dados-demandas';
import { FISCAL, HOJE_BR } from '../dados-prototipo';
import { Icone } from '../icones';

/**
 * O fiscal, o aparelho e a saída.
 *
 * Tela curta de propósito: em campo, "perfil" é onde se troca o tema por causa
 * do sol ou da noite e onde se encerra o plantão. O resto é conferência.
 */
export function TelaPerfil() {
    const { tema, alternarTema, registros, pendentes, sair, sincronizar, sincronizando } = useApp();
    const [confirmandoSaida, setConfirmandoSaida] = useState(false);
    /* O que ainda está só no aparelho: o despachado sem sinal e o aberto. */
    const abertos = registros.filter((r) => r.despachadoBr === null).length;
    const naoEnviados = pendentes + abertos;

    /* O aparelho é compartilhado: sair com registro só no aparelho é deixá-lo
       parado até ESTE fiscal entrar de novo. A saída avisa antes — nunca em
       silêncio. */
    const pedirSaida = () => (naoEnviados > 0 ? setConfirmandoSaida(true) : sair());
    const doDia = registros.filter((r) => r.dataBr === HOJE_BR);
    const irregulares = doDia.filter((r) => r.status === 'irregular').length;

    return (
        <div className="pw-tela">
            <Topo titulo="Perfil" subtitulo={FISCAL.setor} aoVoltar={() => irPara('inicio')} />

            <div className="pw-corpo">
                <div className="pw-card">
                    <div className="pw-linha">
                        <span
                            style={{
                                display: 'grid',
                                placeItems: 'center',
                                width: 58,
                                height: 58,
                                flex: '0 0 58px',
                                borderRadius: 999,
                                background: 'var(--pw-primaria)',
                                color: '#fff',
                                fontSize: 21,
                                fontWeight: 800,
                            }}
                        >
                            {FISCAL.iniciais}
                        </span>
                        <div style={{ minWidth: 0 }}>
                            <p className="pw-forte" style={{ margin: 0, fontSize: 17 }}>
                                {FISCAL.nome}
                            </p>
                            <p className="pw-fraco" style={{ margin: 0 }}>
                                {FISCAL.papel} · matrícula {FISCAL.matricula} · desde {FISCAL.desde}
                            </p>
                            <p className="pw-fraco" style={{ margin: 0 }}>
                                {EQUIPE.nome} · {EQUIPE.area} — {EQUIPE.areaNome} · {FISCAL.turno}
                            </p>
                        </div>
                    </div>
                </div>

                {/* A ÁREA da equipe, por extenso.
                    Não é enfeite de perfil: é a régua do que chega para este
                    fiscal. A denúncia é encaminhada à área do bairro do endereço
                    e direcionada por ela à equipe, então quem duvida de uma
                    denúncia na fila confere aqui se o bairro é dele. */}
                <p className="pw-titulo-secao">Minha equipe e minha área</p>

                <div className="pw-card">
                    <div className="pw-linha-espalha" style={{ marginBottom: 10 }}>
                        <span className="pw-linha" style={{ gap: 10 }}>
                            <span className="pw-equipe-selo">
                                <Icone nome="equipe" tamanho={18} />
                            </span>
                            <span style={{ minWidth: 0 }}>
                                <span className="pw-forte" style={{ display: 'block', fontSize: 15.5 }}>
                                    {EQUIPE.nome} · {EQUIPE.area} — {EQUIPE.areaNome}
                                </span>
                                <span className="pw-fraco" style={{ fontSize: 13 }}>
                                    Encarregado {EQUIPE.encarregado}
                                </span>
                            </span>
                        </span>
                        <Selo tom="info">{EQUIPE.recorteRotulo}</Selo>
                    </div>

                    {/* Nem toda equipe se descreve por bloco de bairros: a
                        Itinerante percorre corredores e a Noturna cobre a cidade
                        inteira. Listar "0 bairros" nelas seria a leitura
                        invertida — por isso o recorte manda no que aparece. */}
                    {EQUIPE.bairros.length > 0 ? (
                        <div className="pw-linha" style={{ gap: 6, flexWrap: 'wrap' }}>
                            {EQUIPE.bairros.map((bairro) => (
                                <Selo key={bairro} tom="neutro">
                                    {bairro}
                                </Selo>
                            ))}
                        </div>
                    ) : (
                        <p className="pw-fraco" style={{ margin: 0, fontSize: 13.5 }}>
                            Cobertura: toda a cidade. O recorte desta equipe é o turno.
                        </p>
                    )}

                    <p className="pw-fraco" style={{ margin: '12px 0 0', fontSize: 12.5 }}>
                        {EQUIPE.recorteExplicacao} As demandas chegam à equipe da área onde fica o
                        endereço: o Chefe de Setor encaminha ao líder, e o líder envia aos fiscais.
                    </p>
                </div>

                <button
                    type="button"
                    className="pw-btn pw-btn-contorno"
                    style={{ marginTop: 12 }}
                    onClick={() => irPara('demandas')}
                >
                    <Icone nome="caixa-entrada" tamanho={18} />
                    Abrir a fila da equipe
                </button>

                <p className="pw-titulo-secao">Meu turno de hoje</p>

                <div className="pw-duas-colunas">
                    <div className="pw-card">
                        <p className="pw-forte" style={{ margin: 0, fontSize: 26 }}>
                            {doDia.length}
                        </p>
                        <p className="pw-fraco" style={{ margin: 0 }}>
                            {doDia.length === 1 ? 'fiscalização feita' : 'fiscalizações feitas'}
                        </p>
                    </div>
                    <div className="pw-card">
                        <p className="pw-forte" style={{ margin: 0, fontSize: 26, color: 'var(--pw-alerta)' }}>
                            {irregulares}
                        </p>
                        <p className="pw-fraco" style={{ margin: 0 }}>
                            {irregulares === 1 ? 'local irregular' : 'locais irregulares'}
                        </p>
                    </div>
                </div>

                <p className="pw-titulo-secao">Aparelho</p>

                <Interruptor
                    ligado={tema === 'escuro'}
                    aoAlternar={alternarTema}
                    titulo="Tema escuro"
                    descricao="Para o turno da noite e para a fiscalização de festa"
                />

                <div className="pw-card" style={{ marginTop: 12 }}>
                    <div className="pw-linha-espalha">
                        <span className="pw-linha" style={{ gap: 10 }}>
                            <Icone nome={tema === 'escuro' ? 'lua' : 'sol'} tamanho={20} />
                            <span className="pw-fraco">Tela em uso agora</span>
                        </span>
                        <Selo tom="neutro">{tema === 'escuro' ? 'Escuro' : 'Claro'}</Selo>
                    </div>
                    <div className="pw-linha-espalha" style={{ marginTop: 12 }}>
                        <span className="pw-linha" style={{ gap: 10 }}>
                            <Icone nome="nuvem" tamanho={20} />
                            <span className="pw-fraco">Registros aguardando envio</span>
                        </span>
                        <Selo tom={pendentes === 0 ? 'ok' : 'alerta'}>{pendentes}</Selo>
                    </div>
                </div>

                {confirmandoSaida ? (
                    <div className="pw-card" role="alertdialog" aria-label="Há registros não enviados" style={{ marginTop: 22 }}>
                        <p className="pw-forte" style={{ margin: 0, fontSize: 16 }}>
                            <Icone nome="alerta" tamanho={18} />{' '}
                            {naoEnviados === 1 ? '1 registro ainda está só neste aparelho' : `${naoEnviados} registros ainda estão só neste aparelho`}
                        </p>
                        <p className="pw-fraco" style={{ margin: '8px 0 0' }}>
                            {pendentes > 0 && 'Há envio esperando sinal. '}
                            {abertos > 0 && 'Há registro aberto, sem conclusão. '}
                            Se você sair, eles ficam guardados aqui e só sobem quando você entrar de novo neste
                            aparelho — o líder não os recebe até lá.
                        </p>
                        {pendentes > 0 && (
                            <button
                                type="button"
                                className="pw-btn pw-btn-acao"
                                style={{ marginTop: 14 }}
                                disabled={sincronizando}
                                onClick={() => void sincronizar().then(() => setConfirmandoSaida(false))}
                            >
                                <Icone nome="atualizar" tamanho={19} className={sincronizando ? 'pw-girando' : undefined} />
                                {sincronizando ? 'Enviando…' : 'Enviar agora'}
                            </button>
                        )}
                        <button type="button" className="pw-btn pw-btn-contorno" style={{ marginTop: 10 }} onClick={sair}>
                            <Icone nome="sair" tamanho={19} />
                            Sair assim mesmo
                        </button>
                        <button
                            type="button"
                            className="pw-btn pw-btn-fantasma"
                            style={{ marginTop: 6 }}
                            onClick={() => setConfirmandoSaida(false)}
                        >
                            Continuar no plantão
                        </button>
                    </div>
                ) : (
                    <button type="button" className="pw-btn pw-btn-contorno" style={{ marginTop: 22 }} onClick={pedirSaida}>
                        <Icone nome="sair" tamanho={19} />
                        Encerrar plantão e sair
                    </button>
                )}

                {/* Brasão oficial no rodapé: aqui o fundo muda com o tema, então
                    entra a versão colorida no claro e a branca no escuro. */}
                <img
                    className="pw-marca-rodape pw-marca-rodape-cor"
                    src="/images/marca/salvador-horizontal-cor.svg"
                    alt="Prefeitura Municipal do Salvador"
                    width={168}
                    height={68}
                />
                <img
                    className="pw-marca-rodape pw-marca-rodape-branco"
                    src="/images/marca/salvador-horizontal-branco.svg"
                    alt="Prefeitura Municipal do Salvador"
                    width={168}
                    height={68}
                />

                <p className="pw-fraco" style={{ textAlign: 'center', marginTop: 4 }}>
                    SEFAL · Aplicativo de campo — em implantação
                    <br />
                    SEMOP · Secretaria Municipal de Ordem Pública
                </p>
            </div>
        </div>
    );
}
