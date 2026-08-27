import { irPara, useApp } from '../app';
import { Interruptor, Selo, Topo } from '../componentes';
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
                                {FISCAL.equipe}
                            </p>
                        </div>
                    </div>
                </div>

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
