import { Head } from '@inertiajs/react';
import { inicio } from '@/routes/retaguarda';

/**
 * Tela inicial da Retaguarda — destino de quem acabou de entrar.
 *
 * Provisória de propósito: o layout com o menu do sistema chega na próxima
 * entrega. Até lá ela existe para que o login termine em algum lugar que diga
 * ao servidor o que aconteceu, em vez de numa página em branco.
 */
export default function Inicio() {
    return (
        <>
            <Head title="Início" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <h1 className="mb-2 text-xl font-medium">
                        Fiscalização de Permissionários
                    </h1>
                    <p className="text-muted-foreground">
                        Você entrou na Retaguarda. As telas do sistema ainda
                        estão sendo construídas — por enquanto, esta é a tela
                        inicial.
                    </p>
                </div>
            </div>
        </>
    );
}

Inicio.layout = {
    breadcrumbs: [
        {
            title: 'Início',
            href: inicio(),
        },
    ],
};
