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
    const { tema, alternarTema, registros, pendentes, sair } = useApp();
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
                                Matrícula {FISCAL.matricula} · desde {FISCAL.desde}
                            </p>
                            <p className="pw-fraco" style={{ margin: 0 }}>
                                {EQUIPE.nome} · {EQUIPE.area} — {EQUIPE.areaNome} · {FISCAL.turno}
                            </p>
                        </div>
                    </div>
                </div>

                {/* A ÁREA da equipe, por extenso.
                    Não é enfeite de perfil: é a régua do que chega para este
                    fiscal. A demanda cai na equipe da área onde fica o endereço,
                    então quem duvida de uma demanda na fila confere aqui se o
                    bairro é dele. */}
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
                        <Selo tom="info">{EQUIPE.bairros.length} bairros</Selo>
                    </div>

                    <div className="pw-linha" style={{ gap: 6, flexWrap: 'wrap' }}>
                        {EQUIPE.bairros.map((bairro) => (
                            <Selo key={bairro} tom="neutro">
                                {bairro}
                            </Selo>
                        ))}
                    </div>

                    <p className="pw-fraco" style={{ margin: '12px 0 0', fontSize: 12.5 }}>
                        As demandas do e-Salvador, do Fala Salvador 156 e de licença nova caem na equipe da
                        área onde fica o endereço. É esta lista que decide.
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

                <button
                    type="button"
                    className="pw-btn pw-btn-contorno"
                    style={{ marginTop: 22 }}
                    onClick={sair}
                >
                    <Icone nome="sair" tamanho={19} />
                    Encerrar plantão e sair
                </button>

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
                    SEFAL · Aplicativo de campo — protótipo de demonstração
                    <br />
                    SEMOP · Secretaria Municipal de Ordem Pública
                </p>
            </div>
        </div>
    );
}
