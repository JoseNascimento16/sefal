import type { ReactNode } from 'react';

import { Icone, type Nome } from './icones';

/* ============================================================================
   Peças de tela reaproveitadas pelo aplicativo de campo.
   ============================================================================ */

/**
 * Junta classes com espaço FORA do texto.
 *
 * Escrever `` `pw-chip${ligado ? ' ligado' : ''}` `` parece inofensivo e não é:
 * o formatador do projeto normaliza o conteúdo do texto de classe e come o
 * espaço da frente — a classe sai grudada, o destaque some e nada acusa.
 */
export const classes = (...partes: (string | false | null | undefined)[]): string =>
    partes.filter(Boolean).join(' ');

export function Topo({
    titulo,
    subtitulo,
    aoVoltar,
    acao,
    perfil,
}: {
    titulo: string;
    subtitulo?: string;
    aoVoltar?: () => void;
    acao?: ReactNode;
    /** Iniciais do fiscal: quando vêm, a barra de título ganha o atalho do perfil. */
    perfil?: { iniciais: string; aoAbrir: () => void };
}) {
    return (
        <header className="pw-topo">
            {aoVoltar && (
                <button type="button" className="pw-topo-botao" onClick={aoVoltar} aria-label="Voltar">
                    <Icone nome="voltar" />
                </button>
            )}
            <div style={{ flex: 1, minWidth: 0 }}>
                <h1>{titulo}</h1>
                {subtitulo && <p>{subtitulo}</p>}
            </div>
            {acao}
            {perfil && (
                <button
                    type="button"
                    className="pw-topo-botao pw-topo-avatar"
                    onClick={perfil.aoAbrir}
                    aria-label="Abrir o perfil"
                >
                    {perfil.iniciais}
                </button>
            )}
        </header>
    );
}

/**
 * O atalho do perfil na barra de título.
 *
 * A barra inferior é de OPERAÇÃO — cinco destinos de trabalho, e nenhum a mais.
 * O perfil não é operação: é conta, tema e saída, coisa de antes e de depois do
 * plantão. Por isso ele mora no canto de cima, onde não disputa o polegar.
 */
export const atalhoDoPerfil = (aoAbrir: () => void, iniciais: string) => ({ iniciais, aoAbrir });

export function Selo({
    tom,
    children,
}: {
    tom: 'ok' | 'alerta' | 'perigo' | 'info' | 'neutro';
    children: ReactNode;
}) {
    return <span className={`pw-selo pw-selo-${tom}`}>{children}</span>;
}

export function SeloSituacao({ situacao }: { situacao: 'regular' | 'irregular' }) {
    return situacao === 'regular' ? (
        <Selo tom="ok">
            <Icone nome="certo" tamanho={13} /> Regular
        </Selo>
    ) : (
        <Selo tom="alerta">
            <Icone nome="alerta" tamanho={13} /> Irregular
        </Selo>
    );
}

export function Interruptor({
    ligado,
    aoAlternar,
    titulo,
    descricao,
}: {
    ligado: boolean;
    aoAlternar: () => void;
    titulo: string;
    descricao?: string;
}) {
    return (
        <button
            type="button"
            className={classes('pw-interruptor', ligado && 'pw-ligado')}
            onClick={aoAlternar}
            aria-pressed={ligado}
        >
            <span style={{ textAlign: 'left' }}>
                <span className="pw-forte" style={{ display: 'block' }}>
                    {titulo}
                </span>
                {descricao && <span className="pw-fraco">{descricao}</span>}
            </span>
            <span className="pw-interruptor-trilho" />
        </button>
    );
}

export function Vazio({ icone, titulo, texto }: { icone: string; titulo: string; texto: string }) {
    return (
        <div className="pw-vazio">
            <div className="pw-vazio-icone">{icone}</div>
            <p className="pw-forte" style={{ margin: '0 0 4px', color: 'var(--pw-texto)' }}>
                {titulo}
            </p>
            <p style={{ margin: 0, fontSize: 14 }}>{texto}</p>
        </div>
    );
}

/**
 * Recado de uma linha ou duas, acima do que ele explica.
 *
 * O tom `alerta` é para IMPEDIMENTO — o motivo de algo não funcionar. Ele existe
 * porque o azul de informação fazia o texto parecer opcional, e motivo de bloqueio
 * não é leitura opcional: é a resposta à pergunta "por que não deixa?".
 */
export function Aviso({ children, tom = 'info' }: { children: ReactNode; tom?: 'info' | 'alerta' }) {
    return (
        <p className={classes('pw-aviso', tom === 'alerta' && 'pw-aviso-alerta')}>
            <Icone nome="erro" tamanho={18} />
            <span>{children}</span>
        </p>
    );
}

/**
 * Caixa de múltipla escolha ao tamanho da rua.
 *
 * Os documentos oficiais são blocos de caixinhas para assinalar com "X" — a
 * Notificação Preliminar tem vinte delas. Numa tela de celular, isso só
 * funciona se a linha INTEIRA for o alvo do toque (não só o quadradinho) e se
 * o marcado se distinguir de longe: fundo, borda e peso do texto mudam juntos.
 */
export function Marcavel({
    marcado,
    aoAlternar,
    children,
    fim,
}: {
    marcado: boolean;
    aoAlternar: () => void;
    children: ReactNode;
    /** Canto direito — contagem, valor, o que a linha precisar mostrar. */
    fim?: ReactNode;
}) {
    return (
        <button
            type="button"
            className={classes('pw-marcavel', marcado && 'pw-marcavel-ligado')}
            onClick={aoAlternar}
            aria-pressed={marcado}
        >
            <span className={classes('pw-quadro', marcado && 'pw-quadro-marcado')} aria-hidden="true">
                {marcado && <Icone nome="certo" tamanho={15} />}
            </span>
            <span className="pw-marcavel-texto">{children}</span>
            {fim}
        </button>
    );
}

/** Assinatura recolhida em campo — ou a recusa, que também é informação. */
export function Assinatura({
    rotulo,
    estado,
    aoAssinar,
    aoRecusar,
    nome,
    aoNome,
    lugarNome,
}: {
    rotulo: string;
    estado: 'pendente' | 'assinada' | 'recusada';
    aoAssinar: () => void;
    aoRecusar: () => void;
    nome?: string;
    aoNome?: (valor: string) => void;
    lugarNome?: string;
}) {
    return (
        <div className={classes('pw-assinatura', estado !== 'pendente' && 'pw-assinatura-feita')}>
            <div className="pw-linha-espalha" style={{ marginBottom: 8 }}>
                <span className="pw-forte" style={{ fontSize: 14.5 }}>
                    {rotulo}
                </span>
                {estado === 'assinada' && (
                    <Selo tom="ok">
                        <Icone nome="certo" tamanho={13} /> Assinou
                    </Selo>
                )}
                {estado === 'recusada' && (
                    <Selo tom="perigo">
                        <Icone nome="alerta" tamanho={13} /> Recusou assinar
                    </Selo>
                )}
            </div>

            {aoNome && (
                <input
                    className="pw-entrada"
                    style={{ marginBottom: 8 }}
                    value={nome ?? ''}
                    onChange={(e) => aoNome(e.target.value)}
                    placeholder={lugarNome}
                />
            )}

            {estado === 'assinada' ? (
                <div className="pw-rabisco" aria-hidden="true" />
            ) : (
                <div className="pw-linha" style={{ gap: 8 }}>
                    <button
                        type="button"
                        className="pw-btn pw-btn-contorno pw-btn-pequeno"
                        onClick={aoAssinar}
                    >
                        <Icone nome="assinar" tamanho={16} />
                        Colher assinatura
                    </button>
                    <button
                        type="button"
                        className="pw-btn pw-btn-fantasma pw-btn-pequeno"
                        onClick={aoRecusar}
                    >
                        Recusou
                    </button>
                </div>
            )}
        </div>
    );
}

export type Aba = 'inicio' | 'demandas' | 'mapa' | 'registros' | 'calor' | 'sincronizacao';

const ABAS: { id: Aba; rotulo: string; icone: Nome }[] = [
    { id: 'inicio', rotulo: 'Início', icone: 'casa' },
    { id: 'demandas', rotulo: 'Demandas', icone: 'caixa-entrada' },
    { id: 'mapa', rotulo: 'Mapa', icone: 'mapa' },
    { id: 'registros', rotulo: 'Registros', icone: 'lista' },
    { id: 'calor', rotulo: 'Calor', icone: 'calor' },
    { id: 'sincronizacao', rotulo: 'Enviar', icone: 'nuvem' },
];

export function BarraInferior({
    ativa,
    aoTrocar,
    pendentes,
    demandas,
}: {
    ativa: Aba;
    aoTrocar: (aba: Aba) => void;
    pendentes: number;
    /** Demandas vencidas — o número que faz o fiscal olhar a fila. */
    demandas?: number;
}) {
    const conta = (aba: Aba): number =>
        aba === 'sincronizacao' ? pendentes : aba === 'demandas' ? (demandas ?? 0) : 0;

    return (
        <nav className="pw-nav" aria-label="Navegação principal">
            {ABAS.map((aba) => (
                <button
                    key={aba.id}
                    type="button"
                    className={aba.id === ativa ? 'pw-nav-ativo' : undefined}
                    onClick={() => aoTrocar(aba.id)}
                    aria-current={aba.id === ativa ? 'page' : undefined}
                >
                    <Icone nome={aba.icone} tamanho={23} />
                    {aba.rotulo}
                    {conta(aba.id) > 0 && <span className="pw-nav-conta">{conta(aba.id)}</span>}
                </button>
            ))}
        </nav>
    );
}
