import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { ModoGerentePermissoes } from '@/components/retaguarda/modo-gerente-permissoes';
import { Sidebar } from '@/components/retaguarda/sidebar';
import { Topbar } from '@/components/retaguarda/topbar';
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

    /*
     * O painel sobreposto aberto agora (hoje só o Modo Gerente), ou `null`.
     *
     * Ele mora AQUI, e não na barra lateral, porque a barra fecha sozinha no
     * celular ao clicar num item: com o estado lá dentro, o painel morreria junto
     * com a barra. E mora aqui, e não em cada tela, porque abre sobre qualquer
     * uma delas.
     */
    const [painelAberto, setPainelAberto] = useState<string | null>(null);

    // Os recados do servidor (`flash.sucesso` / `flash.erro`) aparecem aqui, uma
    // vez só, para toda a Retaguarda — nenhuma tela precisa se lembrar disso.
    useFlashToast();

    /*
     * O servidor também pode pedir que um painel abra: é o que acontece com quem
     * digita (ou tem nos favoritos) o endereço de algo que hoje é painel, e não
     * página — ver `HandleInertiaRequests::painel`.
     */
    const { painel } = usePage().props;

    useEffect(() => {
        if (painel !== null) {
            setPainelAberto(painel);
        }
    }, [painel]);

    /*
     * O tema NÃO é escrito aqui. Quem marca o <html> (classe `.dark` e atributo
     * `data-theme`, juntos) é o `applyTheme` do `use-appearance` — um só dono,
     * para os dois marcadores nunca discordarem e para valerem também nas telas
     * que ficam fora desta casca, como a de entrar.
     */

    return (
        <div className="rt-shell">
            <Sidebar
                aberta={menuAberto}
                onFechar={() => setMenuAberto(false)}
                onAbrirPainel={setPainelAberto}
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

            {painelAberto === 'modo-gerente' && (
                <ModoGerentePermissoes
                    onFechar={() => setPainelAberto(null)}
                />
            )}
        </div>
    );
}
