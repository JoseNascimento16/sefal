import { useFlashToast } from '@/hooks/use-flash-toast';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    // Também nas telas de acesso: um recado do servidor não pode se perder por
    // falta de quem o mostre.
    useFlashToast();

    return (
        <AuthLayoutTemplate title={title} description={description}>
            {children}
        </AuthLayoutTemplate>
    );
}
