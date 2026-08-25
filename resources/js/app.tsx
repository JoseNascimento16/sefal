import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AuthLayout from '@/layouts/auth-layout';
import RetaguardaLayout from '@/layouts/retaguarda-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName =
    import.meta.env.VITE_APP_NAME || 'Fiscalização de Permissionários';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            // "Meu Perfil" mora dentro da Retaguarda: mesma casca, com as abas
            // da própria conta por dentro.
            case name.startsWith('settings/'):
                return [RetaguardaLayout, SettingsLayout];
            default:
                return RetaguardaLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        // Âmbar da sinalização: a barra de progresso é o único aviso de que algo
        // está em curso quando a resposta demora.
        color: '#f4a300',
    },
});

// Aplica o tema (claro/escuro/sistema) antes da primeira pintura.
initializeTheme();
