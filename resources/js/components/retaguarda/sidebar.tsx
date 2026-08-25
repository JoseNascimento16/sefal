import { Link, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
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
}: {
    /** No celular a barra desliza por cima do conteúdo. */
    aberta: boolean;
    onFechar: () => void;
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
                <span className="rt-marca-selo">
                    <ShieldCheck size={22} aria-hidden />
                </span>
                <span>
                    <span className="rt-marca-nome">Fiscalização</span>
                    <span className="rt-marca-sub">
                        SEMOP · Permissionários
                    </span>
                </span>
            </Link>

            <nav className="rt-menu" aria-label="Menu do sistema">
                {menu.map((secao) => (
                    <div key={secao.rotulo}>
                        <p className="rt-menu-secao-titulo">{secao.rotulo}</p>

                        {secao.itens.map((item) => {
                            const Icone = iconeDoMenu(item.icone);
                            const ativo = isCurrentUrl(item.url);

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

            <div className="rt-sidebar-pe">SEMOP · Prefeitura de Salvador</div>
        </aside>
    );
}
