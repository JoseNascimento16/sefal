import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, PanelLeftClose, PanelLeftOpen } from 'lucide-react';
import { useState } from 'react';
import { ModalConfirm } from '@/components/retaguarda/modal-confirm';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { iconeDoMenu } from '@/lib/icones-menu';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { inicio } from '@/routes/retaguarda';
import type { MenuContador, MenuItem } from '@/types/navigation';

/**
 * Menu lateral da Retaguarda — a casca "editorial curva" aprovada pelo dono.
 *
 * O painel é navy, com o canto direito curvado, e serve de moldura escura para o
 * miolo claro do trabalho: a cidade de noite de um lado, a mesa iluminada do
 * outro. Ele é escuro nos DOIS temas de propósito (ver o `retaguarda.css`).
 *
 * Duas formas, a mesma informação:
 *  · **estendido** — nome do item, contador à direita, cartão do usuário no pé;
 *  · **doca** (retraído) — cartão flutuante estreito, ícone + rótulo curto,
 *    contador virando selo no canto. Quem escolhe é a pessoa, e a escolha fica
 *    guardada (ver o `retaguarda-layout`).
 *
 * As seções e os itens vêm PRONTOS do servidor (`config/retaguarda_menu.php`,
 * montado no `HandleInertiaRequests`): é lá que se decide o que existe, quem vê o
 * quê e qual número cada item mostra. A tela não conhece regra de acesso — se
 * conhecesse, a mesma decisão teria dois donos e um dia divergiria.
 *
 * Seção sem tela pronta aparece com o recado do que vem por aí, em vez de sumir:
 * quem usa o sistema enxerga o caminho que está sendo construído.
 */

/** O número do item, na forma do menu estendido. */
function Contador({ contador }: { contador: MenuContador }) {
    return (
        <span className={cn('rt-menu-contador', contador.tom === 'alerta' && 'alerta')}>
            {contador.valor}
        </span>
    );
}

/** O mesmo número na doca: selo no canto do ícone, onde não há largura para mais. */
function Selo({ contador }: { contador: MenuContador }) {
    return (
        <span className={cn('rt-doca-selo', contador.tom === 'alerta' && 'alerta')}>
            {contador.valor > 99 ? '99+' : contador.valor}
        </span>
    );
}

export function Sidebar({
    emDoca,
    onAlternarRetracao,
    onAbrirPainel,
}: {
    /**
     * Forma retraída: a doca flutuante. Vem decidida pela casca — é ela que junta
     * a preferência da pessoa com a largura da janela.
     */
    emDoca: boolean;
    onAlternarRetracao: () => void;
    /**
     * Item que abre painel sobre a tela atual em vez de navegar (o Modo Gerente).
     * Quem monta o painel é a casca da Retaguarda, não a barra: ela só diz qual.
     */
    onAbrirPainel: (painel: string) => void;
}) {
    const { menu, auth } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();
    const [confirmandoSaida, setConfirmandoSaida] = useState(false);
    const [saindo, setSaindo] = useState(false);

    /** Iniciais para o avatar — duas letras, ou "?" quando não há nome. */
    const iniciais =
        (auth.user?.name ?? '')
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map((p) => p[0] ?? '')
            .join('')
            .toUpperCase() || '?';

    /** O papel de quem entrou: o primeiro setor, ou o desvio do administrador. */
    const papel = auth.user?.admin
        ? 'Administrador'
        : (auth.user?.setores[0] ?? 'Sem setor definido');

    /**
     * O clique de um item — navegar ou abrir painel. Está aqui, e não duplicado
     * nas duas formas do menu, porque a decisão é a mesma nas duas: mudar só uma
     * delas amanhã é o defeito que a duplicação sempre produz.
     */
    function acionar(item: MenuItem) {
        if (item.modal !== null) {
            onAbrirPainel(item.modal);
        }
    }

    /*
     * Item de PAINEL não navega: abre sobre a tela atual. É um <button>, e não um
     * link com o clique interceptado — assim o teclado, o leitor de tela e o
     * "abrir em nova aba" do navegador contam a mesma história que a tela: aqui
     * não se vai a lugar nenhum.
     */
    function ItemEstendido({ item }: { item: MenuItem }) {
        const Icone = iconeDoMenu(item.icone);
        const ativo = item.modal === null && isCurrentUrl(item.url);

        const dentro = (
            <>
                <Icone size={18} aria-hidden />
                <span className="rt-menu-rotulo">{item.rotulo}</span>
                {item.contador && <Contador contador={item.contador} />}
            </>
        );

        if (item.modal !== null) {
            return (
                <button
                    type="button"
                    className="rt-menu-item"
                    onClick={() => acionar(item)}
                >
                    {dentro}
                </button>
            );
        }

        return (
            <Link
                href={item.url}
                onClick={() => acionar(item)}
                className={cn('rt-menu-item', ativo && 'ativo')}
                aria-current={ativo ? 'page' : undefined}
            >
                {dentro}
            </Link>
        );
    }

    /** O mesmo item na doca: ícone grande, rótulo curto embaixo, selo no canto. */
    function ItemDaDoca({ item }: { item: MenuItem }) {
        const Icone = iconeDoMenu(item.icone);
        const ativo = item.modal === null && isCurrentUrl(item.url);

        const dentro = (
            <>
                <Icone size={21} aria-hidden />
                <span className="rt-doca-rotulo">{item.curto}</span>
                {item.contador && <Selo contador={item.contador} />}
            </>
        );

        // O nome inteiro vai no `title` e no rótulo acessível: o rótulo curto é
        // recorte visual, e ninguém deve depender dele para saber onde clica.
        if (item.modal !== null) {
            return (
                <button
                    type="button"
                    className="rt-doca-item"
                    title={item.rotulo}
                    aria-label={item.rotulo}
                    onClick={() => acionar(item)}
                >
                    {dentro}
                </button>
            );
        }

        return (
            <Link
                href={item.url}
                onClick={() => acionar(item)}
                className={cn('rt-doca-item', ativo && 'ativo')}
                title={item.rotulo}
                aria-label={item.rotulo}
                aria-current={ativo ? 'page' : undefined}
            >
                {dentro}
            </Link>
        );
    }

    /** Sair PERGUNTA antes: a sessão leva embora formulário aberto. */
    const perguntaDeSaida = confirmandoSaida && (
        <ModalConfirm
            titulo="Sair do sistema?"
            mensagem="A sessão é encerrada e o que estiver preenchido em formulário aberto se perde. Para entrar de novo você precisa da matrícula e da senha."
            rotuloConfirmar="Sair do sistema"
            rotuloCancelar="Continuar no sistema"
            iconeConfirmar={<LogOut size={16} aria-hidden />}
            destrutiva
            processando={saindo}
            onCancelar={() => setConfirmandoSaida(false)}
            onConfirmar={() => {
                setSaindo(true);
                // A limpeza dos dados em memória vem ANTES do pedido: o aparelho
                // pode ser compartilhado, e o histórico de navegação do Inertia
                // guarda as telas já visitadas.
                router.flushAll();
                router.post(logout().url);
            }}
        />
    );

    if (emDoca) {
        return (
            <>
                <aside className="rt-doca" aria-label="Menu do sistema">
                    <Link
                        href={inicio()}
                        className="rt-doca-marca"
                        aria-label="Ir para a tela inicial"
                    >
                        <img
                            src="/images/marca/brasao-salvador-branco.svg"
                            alt=""
                            aria-hidden
                        />
                        <span>SEFAL</span>
                    </Link>

                    <nav className="rt-doca-itens">
                        {menu.map((secao) => (
                            <div key={secao.rotulo} className="rt-doca-grupo">
                                {secao.itens.map((item) => (
                                    <ItemDaDoca key={item.url} item={item} />
                                ))}
                            </div>
                        ))}
                    </nav>

                    <div className="rt-doca-pe">
                        <button
                            type="button"
                            className="rt-doca-acao"
                            title="Expandir o menu"
                            aria-label="Expandir o menu"
                            onClick={onAlternarRetracao}
                        >
                            <PanelLeftOpen size={19} aria-hidden />
                        </button>
                        <button
                            type="button"
                            className="rt-doca-avatar"
                            title={`${auth.user?.name ?? ''} — sair do sistema`}
                            aria-label="Sair do sistema"
                            onClick={() => setConfirmandoSaida(true)}
                        >
                            {iniciais}
                        </button>
                    </div>
                </aside>

                {perguntaDeSaida}
            </>
        );
    }

    return (
        <>
            <aside className="rt-sidebar">
                {/* A malha de ruas no pé do painel: a cidade por baixo do menu,
                    decoração e nada mais — nenhum traço ali é dado do sistema. */}
                <svg
                    className="rt-sidebar-malha"
                    viewBox="0 0 292 220"
                    preserveAspectRatio="none"
                    aria-hidden="true"
                >
                    <g stroke="#26537e" strokeWidth="1.5" fill="none">
                        <path d="M-10 60 C 70 40, 150 80, 230 55 L 300 45" />
                        <path d="M-10 130 C 90 115, 180 150, 300 125" />
                        <path d="M-10 195 C 80 180, 190 210, 300 190" />
                        <path d="M70 30 C 80 100, 60 160, 75 230" />
                        <path d="M190 20 C 180 90, 205 160, 190 230" />
                    </g>
                    <circle cx="190" cy="128" r="4" fill="#4d9fdc" />
                    <circle cx="75" cy="132" r="4" fill="#ff9a4d" />
                </svg>

                <div className="rt-marca-linha">
                    <Link
                        href={inicio()}
                        className="rt-marca"
                        aria-label="Ir para a tela inicial"
                    >
                        {/* Aqui o brasão é sempre o BRANCO: o painel é navy nos
                            dois temas, e a versão colorida desapareceria nele. */}
                        <img
                            src="/images/marca/brasao-salvador-branco.svg"
                            alt=""
                            aria-hidden
                        />
                        <span>
                            <span className="rt-marca-nome">SEFAL</span>
                            <span className="rt-marca-sub">
                                Fiscalização de Ambulantes
                            </span>
                        </span>
                    </Link>

                    <button
                        type="button"
                        className="rt-marca-recolher"
                        title="Retrair o menu"
                        aria-label="Retrair o menu"
                        onClick={onAlternarRetracao}
                    >
                        <PanelLeftClose size={18} aria-hidden />
                    </button>
                </div>

                <nav className="rt-menu" aria-label="Menu do sistema">
                    {menu.map((secao, i) => (
                        <div key={secao.rotulo} className="rt-menu-secao">
                            {/* A régua separa as seções em vez de um título para
                                cada uma: com sete títulos o painel virava índice.
                                A primeira seção não precisa de régua acima. */}
                            {i > 0 && <div className="rt-menu-regua" />}

                            {secao.itens.map((item) => (
                                <ItemEstendido key={item.url} item={item} />
                            ))}

                            {secao.itens.length === 0 && secao.vazio && (
                                <p className="rt-menu-vazio">{secao.vazio}</p>
                            )}
                        </div>
                    ))}
                </nav>

                {/* O cartão de quem entrou, no pé: identidade e saída no mesmo
                    lugar, longe das ações de trabalho. */}
                <div className="rt-usuario-cartao">
                    <span className="rt-avatar" aria-hidden>
                        {iniciais}
                    </span>
                    <span className="rt-usuario-texto">
                        <span className="rt-usuario-nome">
                            {auth.user?.name ?? ''}
                        </span>
                        <span className="rt-usuario-papel">{papel}</span>
                    </span>
                    <button
                        type="button"
                        className="rt-usuario-sair"
                        title="Sair do sistema"
                        aria-label="Sair do sistema"
                        onClick={() => setConfirmandoSaida(true)}
                    >
                        <LogOut size={16} aria-hidden />
                    </button>
                </div>
            </aside>

            {perguntaDeSaida}
        </>
    );
}
