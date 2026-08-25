import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { Sidebar } from '@/components/retaguarda/sidebar';
import { Topbar } from '@/components/retaguarda/topbar';
import { useAppearance } from '@/hooks/use-appearance';
import { useFlashToast } from '@/hooks/use-flash-toast';
import type { BreadcrumbItem } from '@/types';

/**
 * Casca de TODA tela autenticada da Retaguarda: menu lateral + barra superior.
 *
 * A tela recebe a trilha de navegação por propriedade de layout:
 *
 *   MinhaTela.layout = { breadcrumbs: [{ title: 'Permissionários', href: url }] };
 */
export default function RetaguardaLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: ReactNode;
}) {
    const [menuAberto, setMenuAberto] = useState(false);
    const { resolvedAppearance } = useAppearance();

    // Os recados do servidor (`flash.sucesso` / `flash.erro`) aparecem aqui, uma
    // vez só, para toda a Retaguarda — nenhuma tela precisa se lembrar disso.
    useFlashToast();

    /*
     * O tema tem DOIS marcadores no <html>, escritos pela MESMA fonte (o
     * `useAppearance`): a classe `.dark`, que o Tailwind usa nas suas utilidades,
     * e o atributo `data-theme`, que os tokens do Design System também aceitam e
     * que permite inverter o tema de um trecho isolado (uma prévia de documento,
     * por exemplo). São dois marcadores, uma decisão — nunca duas decisões.
     */
    useEffect(() => {
        document.documentElement.dataset.theme = resolvedAppearance;
    }, [resolvedAppearance]);

    return (
        <div className="rt-shell">
            <Sidebar
                aberta={menuAberto}
                onFechar={() => setMenuAberto(false)}
            />

            {/* No celular, a barra abre por cima: o véu deixa claro que o resto
                da tela está esperando e dá um lugar óbvio para fechar. */}
            {menuAberto && (
                <div
                    className="rt-veu"
                    role="presentation"
                    onClick={() => setMenuAberto(false)}
                />
            )}

            <div className="rt-principal">
                <Topbar
                    breadcrumbs={breadcrumbs}
                    onAbrirMenu={() => setMenuAberto(true)}
                />

                <main className="rt-conteudo">{children}</main>
            </div>
        </div>
    );
}
