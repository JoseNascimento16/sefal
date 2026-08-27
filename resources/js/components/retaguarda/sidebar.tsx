import { Link, usePage } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { iconeDoMenu } from '@/lib/icones-menu';
import { cn } from '@/lib/utils';
import { inicio } from '@/routes/retaguarda';

/**
 * Menu lateral da Retaguarda.
 *
 * As seções e os itens vêm PRONTOS do servidor (`config/retaguarda_menu.php`,
 * montado no `HandleInertiaRequests`): é lá que se decide o que existe e quem vê
 * o quê. A tela não conhece regra de acesso — se conhecesse, a mesma decisão
 * teria dois donos e um dia divergiria.
 *
 * Seção sem tela pronta aparece com o recado do que vem por aí, em vez de sumir:
 * quem usa o sistema enxerga o caminho que está sendo construído.
 */
export function Sidebar({
    aberta,
    onFechar,
    onAbrirPainel,
}: {
    /** No celular a barra desliza por cima do conteúdo. */
    aberta: boolean;
    onFechar: () => void;
    /**
     * Item que abre painel sobre a tela atual em vez de navegar (o Modo Gerente).
     * Quem monta o painel é a casca da Retaguarda, não a barra: ela só diz qual.
     */
    onAbrirPainel: (painel: string) => void;
}) {
    const { menu } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <aside className={cn('rt-sidebar', aberta && 'aberta')}>
            <Link
                href={inicio()}
                className="rt-marca"
                onClick={onFechar}
                aria-label="Ir para a tela inicial"
            >
                {/* O brasão da Prefeitura, nas duas versões: a colorida vale no
                    tema claro, a branca no escuro. Qual aparece é decidido no
                    CSS (ver `.rt-marca-clara` / `.rt-marca-escura`), e não aqui
                    — o tema é aplicado antes da primeira pintura, e um `src`
                    condicional piscaria a versão errada. */}
                <span className="rt-marca-selo">
                    {/* Decorativos de propósito: o nome acessível deste link é o
                        `aria-label` acima, e "SEFAL" está escrito ao lado. */}
                    <img
                        className="rt-marca-clara"
                        src="/images/marca/brasao-salvador-cor.svg"
                        alt=""
                        aria-hidden
                    />
                    <img
                        className="rt-marca-escura"
                        src="/images/marca/brasao-salvador-branco.svg"
                        alt=""
                        aria-hidden
                    />
                </span>
                <span>
                    <span className="rt-marca-nome">SEFAL</span>
                    {/* O selo é estreito (272px de menu, menos o brasão): a
                        linha de apoio tem de caber em uma linha em maiúsculas.
                        Daí o recorte — "SEMOP · Fiscalização" —, com o órgão
                        por extenso no pé do menu e o nome do sistema por
                        extenso na tela de entrar, onde há espaço para ele. */}
                    <span className="rt-marca-sub">SEMOP · Fiscalização</span>
                </span>
            </Link>

            <nav className="rt-menu" aria-label="Menu do sistema">
                {menu.map((secao) => (
                    <div key={secao.rotulo}>
                        <p className="rt-menu-secao-titulo">{secao.rotulo}</p>

                        {secao.itens.map((item) => {
                            const Icone = iconeDoMenu(item.icone);
                            const ativo = isCurrentUrl(item.url);

                            /*
                             * Item de PAINEL não navega: abre sobre a tela atual.
                             * É um <button>, e não um link com o clique
                             * interceptado — assim o teclado, o leitor de tela e
                             * o "abrir em nova aba" do navegador contam a mesma
                             * história que a tela: aqui não se vai a lugar
                             * nenhum.
                             */
                            if (item.modal !== null) {
                                return (
                                    <button
                                        key={item.url}
                                        type="button"
                                        className="rt-menu-item"
                                        onClick={() => {
                                            onFechar();
                                            onAbrirPainel(item.modal as string);
                                        }}
                                    >
                                        <Icone size={18} aria-hidden />
                                        {item.rotulo}
                                    </button>
                                );
                            }

                            return (
                                <Link
                                    key={item.url}
                                    href={item.url}
                                    onClick={onFechar}
                                    className={cn(
                                        'rt-menu-item',
                                        ativo && 'ativo',
                                    )}
                                    aria-current={ativo ? 'page' : undefined}
                                >
                                    <Icone size={18} aria-hidden />
                                    {item.rotulo}
                                </Link>
                            );
                        })}

                        {secao.itens.length === 0 && secao.vazio && (
                            <p className="rt-menu-vazio">{secao.vazio}</p>
                        )}
                    </div>
                ))}
            </nav>

            {/* No pé, o lockup completo da Prefeitura — é aqui que a autoria do
                sistema é declarada, longe do trabalho. O texto abaixo dele diz o
                ÓRGÃO por extenso, que o lockup não diz; repetir "Prefeitura de
                Salvador" em palavras ao lado do desenho que já a nomeia seria a
                mesma informação duas vezes. */}
            <div className="rt-sidebar-pe">
                <img
                    className="rt-marca-pe rt-marca-clara"
                    src="/images/marca/salvador-horizontal-cor.svg"
                    alt="Prefeitura de Salvador"
                />
                <img
                    className="rt-marca-pe rt-marca-escura"
                    src="/images/marca/salvador-horizontal-branco.svg"
                    alt="Prefeitura de Salvador"
                />
                SEMOP · Secretaria de Ordem Pública
            </div>
        </aside>
    );
}
