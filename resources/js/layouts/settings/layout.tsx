import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { edit as editarAparencia } from '@/routes/appearance';
import { edit as editarPerfil } from '@/routes/profile';
import { edit as editarSenha } from '@/routes/security';

/**
 * "Meu Perfil" — a área da própria conta, dentro da Retaguarda.
 *
 * Três assuntos, três abas: os seus dados, a sua senha e a aparência do sistema.
 * Nada de foto de perfil nesta fase, e nada de "apagar minha conta": quem abre e
 * quem encerra o acesso de um servidor é a administração.
 */
const ABAS = [
    { titulo: 'Dados', href: editarPerfil() },
    { titulo: 'Senha', href: editarSenha() },
    { titulo: 'Aparência', href: editarAparencia() },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    // Comparação EXATA: "/retaguarda/perfil" é prefixo de
    // "/retaguarda/perfil/senha", então uma comparação por início marcaria duas
    // abas ao mesmo tempo.
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <>
            <div className="rt-page-head">
                <div>
                    <p className="sobrancelha">Minha conta</p>
                    <h1>Meu Perfil</h1>
                    <p>Seus dados de acesso e as suas preferências.</p>
                </div>
            </div>

            <div
                style={{
                    display: 'flex',
                    gap: 24,
                    flexWrap: 'wrap',
                    alignItems: 'flex-start',
                }}
            >
                <nav
                    aria-label="Assuntos do perfil"
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 4,
                        minWidth: 180,
                    }}
                >
                    {ABAS.map((aba) => (
                        <Link
                            key={aba.titulo}
                            href={aba.href}
                            className={cn(
                                'rt-menu-item',
                                isCurrentUrl(aba.href) && 'ativo',
                            )}
                        >
                            {aba.titulo}
                        </Link>
                    ))}
                </nav>

                <section
                    className="card-premium"
                    style={{ flex: 1, minWidth: 300, maxWidth: 620 }}
                >
                    {children}
                </section>
            </div>
        </>
    );
}
