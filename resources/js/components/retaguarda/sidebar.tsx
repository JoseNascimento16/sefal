import { Link, router, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    LogOut,
    PanelLeftClose,
    PanelLeftOpen,
} from 'lucide-react';
import type { CSSProperties } from 'react';
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
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
 *    guardada (ver o `retaguarda-layout`). Em tela estreita a doca deita no pé.
 *
 * ── Item que é PASTA ────────────────────────────────────────────────────────
 *
 * Um item pode ter `filhos`: aí ele não leva a lugar nenhum, ele ABRE. As três
 * formas da casca resolvem isso de jeitos diferentes, porque a largura manda:
 *
 *  · estendido — os filhos descem por baixo do pai, indentados;
 *  · doca (96px) — não cabe lista nenhuma, então o clique abre um painel FLUTUANTE
 *    ao lado do ícone;
 *  · doca deitada no pé — o mesmo painel, abrindo para CIMA.
 *
 * O painel flutuante é desenhado no `<body>` e posicionado a partir do retângulo
 * do botão. Não é capricho: a fila de itens da doca deitada rola na horizontal
 * (`overflow-x`), e um painel posicionado dentro dela seria CORTADO justamente na
 * forma em que ele é mais necessário.
 *
 * O pai abre sozinho quando um filho está ativo, e mostra o próprio estado ativo:
 * sem isso, quem entrasse por um filho veria a pasta fechada e o menu não diria
 * onde a pessoa está.
 *
 * As seções e os itens vêm PRONTOS do servidor (`config/retaguarda_menu.php`,
 * montado no `HandleInertiaRequests`): é lá que se decide o que existe, quem vê o
 * quê e qual número cada item mostra. A tela não conhece regra de acesso — se
 * conhecesse, a mesma decisão teria dois donos e um dia divergiria. Pasta sem
 * filho visível não chega aqui.
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

/** Identidade de um item para o estado da tela — a pasta não tem endereço. */
function chaveDoItem(item: MenuItem): string {
    return item.url ?? `pasta:${item.rotulo}`;
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

    /**
     * As pastas que a PESSOA abriu ou fechou à mão.
     *
     * Só o que ela mexeu entra aqui: o estado natural é derivado (pasta com filho
     * ativo nasce aberta). Guardar o estado de todas faria a pasta do filho ativo
     * aparecer fechada até alguém clicar nela — e o menu deixaria de dizer onde a
     * pessoa está.
     */
    const [pastas, setPastas] = useState<Record<string, boolean>>({});

    /** A pasta cujo painel flutuante está aberto na doca, e onde desenhá-lo. */
    const [flutuante, setFlutuante] = useState<{
        chave: string;
        item: MenuItem;
        estilo: CSSProperties;
    } | null>(null);

    const painelFlutuante = useRef<HTMLDivElement | null>(null);

    /*
     * O painel flutuante fecha com Escape, com um clique fora dele e quando a
     * janela muda de tamanho — este último porque a posição foi CALCULADA a partir
     * do retângulo do botão, e ela deixa de valer quando a doca se move (é
     * exatamente o que acontece quando a barra deita no pé).
     */
    useEffect(() => {
        if (flutuante === null) {
            return;
        }

        function noEscape(e: KeyboardEvent) {
            if (e.key === 'Escape') {
                setFlutuante(null);
            }
        }

        function foraDoPainel(e: PointerEvent) {
            const alvo = e.target;

            if (alvo instanceof Node && painelFlutuante.current?.contains(alvo)) {
                return;
            }

            // Clique no próprio botão da pasta: quem trata é o `onClick` dele, que
            // alterna. Fechar aqui também faria o painel abrir e fechar no mesmo
            // clique.
            if (alvo instanceof Element && alvo.closest('[data-pasta-da-doca]')) {
                return;
            }

            setFlutuante(null);
        }

        // Nomeada, e não uma função inline no `addEventListener`: inline, a
        // remoção na limpeza não casaria com nada e o ouvinte ficaria pendurado a
        // cada abertura do painel.
        function fechar() {
            setFlutuante(null);
        }

        window.addEventListener('keydown', noEscape);
        window.addEventListener('pointerdown', foraDoPainel);
        window.addEventListener('resize', fechar);

        return () => {
            window.removeEventListener('keydown', noEscape);
            window.removeEventListener('pointerdown', foraDoPainel);
            window.removeEventListener('resize', fechar);
        };
    }, [flutuante]);

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

        // Navegar por um filho fecha o painel flutuante: ele cobria a tela nova.
        setFlutuante(null);
    }

    /** Este item é o que está aberto agora? Pasta não é destino, então nunca é. */
    function estaAtivo(item: MenuItem): boolean {
        return item.modal === null && item.url !== null && isCurrentUrl(item.url);
    }

    /** A pasta está mostrando a tela aberta? É o que faz o pai se marcar. */
    function comFilhoAtivo(item: MenuItem): boolean {
        return item.filhos.some((filho) => estaAtivo(filho));
    }

    /*
     * Item de PAINEL não navega: abre sobre a tela atual. É um <button>, e não um
     * link com o clique interceptado — assim o teclado, o leitor de tela e o
     * "abrir em nova aba" do navegador contam a mesma história que a tela: aqui
     * não se vai a lugar nenhum.
     */
    function ItemEstendido({ item, filho = false }: { item: MenuItem; filho?: boolean }) {
        const Icone = iconeDoMenu(item.icone);
        const ativo = estaAtivo(item);
        const classe = cn(filho ? 'rt-menu-filho' : 'rt-menu-item', ativo && 'ativo');

        const dentro = (
            <>
                <Icone size={filho ? 16 : 18} aria-hidden />
                <span className="rt-menu-rotulo">{item.rotulo}</span>
                {item.contador && <Contador contador={item.contador} />}
            </>
        );

        if (item.modal !== null || item.url === null) {
            return (
                <button
                    type="button"
                    className={classe}
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
                className={classe}
                aria-current={ativo ? 'page' : undefined}
            >
                {dentro}
            </Link>
        );
    }

    /** A pasta no menu estendido: cabeça que abre e os filhos descendo por baixo. */
    function PastaEstendida({ item }: { item: MenuItem }) {
        const Icone = iconeDoMenu(item.icone);
        const chave = chaveDoItem(item);
        const dentroAtivo = comFilhoAtivo(item);
        // Sem mexida da pessoa, a pasta do filho ativo nasce ABERTA.
        const aberta = pastas[chave] ?? dentroAtivo;

        return (
            <>
                <button
                    type="button"
                    className={cn('rt-menu-item', 'rt-menu-pasta', dentroAtivo && 'com-ativo')}
                    aria-expanded={aberta}
                    onClick={() =>
                        setPastas((atual) => ({ ...atual, [chave]: !aberta }))
                    }
                >
                    <Icone size={18} aria-hidden />
                    <span className="rt-menu-rotulo">{item.rotulo}</span>
                    {item.contador && <Contador contador={item.contador} />}
                    <ChevronDown
                        size={16}
                        aria-hidden
                        className={cn('rt-menu-seta', aberta && 'aberta')}
                    />
                </button>

                {aberta && (
                    <div className="rt-menu-filhos">
                        {item.filhos.map((f) => (
                            <ItemEstendido key={chaveDoItem(f)} item={f} filho />
                        ))}
                    </div>
                )}
            </>
        );
    }

    /** O mesmo item na doca: ícone grande, rótulo curto embaixo, selo no canto. */
    function ItemDaDoca({ item }: { item: MenuItem }) {
        const Icone = iconeDoMenu(item.icone);
        const ativo = estaAtivo(item);

        const dentro = (
            <>
                <Icone size={21} aria-hidden />
                <span className="rt-doca-rotulo">{item.curto}</span>
                {item.contador && <Selo contador={item.contador} />}
            </>
        );

        // O nome inteiro vai no `title` e no rótulo acessível: o rótulo curto é
        // recorte visual, e ninguém deve depender dele para saber onde clica.
        if (item.modal !== null || item.url === null) {
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

    /**
     * A pasta na doca: o clique abre um painel flutuante com os filhos.
     *
     * A posição é calculada do retângulo do botão, e a DIREÇÃO também: abre ao lado
     * quando cabe (doca em pé, à esquerda) e para cima quando não cabe (doca
     * deitada no pé da tela). Direção fixa deixaria metade do painel fora da tela
     * numa das duas formas.
     */
    function PastaDaDoca({ item }: { item: MenuItem }) {
        const Icone = iconeDoMenu(item.icone);
        const chave = chaveDoItem(item);
        const dentroAtivo = comFilhoAtivo(item);
        const aberta = flutuante?.chave === chave;

        const LARGURA = 224;
        const FOLGA = 10;

        function abrir(e: React.MouseEvent<HTMLButtonElement>) {
            if (aberta) {
                setFlutuante(null);

                return;
            }

            const r = e.currentTarget.getBoundingClientRect();

            /*
             * A altura que o painel vai ocupar, estimada dos filhos (título + uma
             * linha por filho + respiro). Estimativa porque a decisão de direção
             * acontece ANTES de ele existir na tela.
             *
             * Ela é o que faltava: decidir a direção só pela largura punha o painel
             * fora da tela justamente na barra deitada no pé — largura sobrando de
             * lado, e nenhuma altura abaixo do botão. Verificado em 560×820, com o
             * painel vazando por baixo.
             */
            const altura = 44 + item.filhos.length * 40 + 20;
            const cabeAoLado =
                r.right + FOLGA + LARGURA < window.innerWidth &&
                r.top + altura + 12 <= window.innerHeight;

            // Preso dentro da tela nas duas direções: o item pode estar na borda
            // (a fila da barra deitada rola), e sem o limite o painel sairia por
            // ali.
            const presoNaHorizontal = Math.min(
                Math.max(r.left + r.width / 2 - LARGURA / 2, 12),
                Math.max(window.innerWidth - LARGURA - 12, 12),
            );

            const estilo: CSSProperties = cabeAoLado
                ? { left: r.right + FOLGA, top: r.top, width: LARGURA }
                : {
                      left: presoNaHorizontal,
                      bottom: window.innerHeight - r.top + FOLGA,
                      width: LARGURA,
                      // Se nem acima couber (tela muito baixa), o painel rola em
                      // vez de vazar — some é pior que apertado.
                      maxHeight: Math.max(r.top - FOLGA - 12, 120),
                      overflowY: 'auto',
                  };

            setFlutuante({ chave, item, estilo });
        }

        return (
            <button
                type="button"
                data-pasta-da-doca
                className={cn('rt-doca-item', (dentroAtivo || aberta) && 'ativo')}
                title={item.rotulo}
                aria-label={item.rotulo}
                aria-expanded={aberta}
                onClick={abrir}
            >
                <Icone size={21} aria-hidden />
                <span className="rt-doca-rotulo">{item.curto}</span>
                {item.contador && <Selo contador={item.contador} />}
                {/* A marca de "tem coisa dentro": sem ela o ícone da pasta é
                    indistinguível de um item que navega. */}
                <span className="rt-doca-pasta-marca" aria-hidden />
            </button>
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

    /*
     * O painel flutuante mora no `<body>`, e não dentro da doca: a fila de itens da
     * doca deitada rola na horizontal, e ali dentro o painel seria cortado.
     */
    const painelDaPasta =
        flutuante !== null &&
        createPortal(
            <div
                ref={painelFlutuante}
                className="rt-doca-flutuante"
                style={flutuante.estilo}
                role="group"
                aria-label={flutuante.item.rotulo}
            >
                <p className="rt-doca-flutuante-titulo">{flutuante.item.rotulo}</p>

                {flutuante.item.filhos.map((f) => {
                    const ativo = estaAtivo(f);

                    return f.url === null ? null : (
                        <Link
                            key={chaveDoItem(f)}
                            href={f.url}
                            onClick={() => acionar(f)}
                            className={cn('rt-doca-flutuante-item', ativo && 'ativo')}
                            aria-current={ativo ? 'page' : undefined}
                        >
                            {f.rotulo}
                            {f.contador && <Contador contador={f.contador} />}
                        </Link>
                    );
                })}
            </div>,
            document.body,
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
                                {secao.itens.map((item) =>
                                    item.filhos.length > 0 ? (
                                        <PastaDaDoca key={chaveDoItem(item)} item={item} />
                                    ) : (
                                        <ItemDaDoca key={chaveDoItem(item)} item={item} />
                                    ),
                                )}
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

                {painelDaPasta}
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

                            {secao.itens.map((item) =>
                                item.filhos.length > 0 ? (
                                    <PastaEstendida key={chaveDoItem(item)} item={item} />
                                ) : (
                                    <ItemEstendido key={chaveDoItem(item)} item={item} />
                                ),
                            )}

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
