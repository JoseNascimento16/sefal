import { Moon, ShieldCheck, Sun } from 'lucide-react';
import { useAppearance } from '@/hooks/use-appearance';
import type { AuthLayoutProps } from '@/types';

/**
 * Casca das telas de acesso (entrar, esqueci minha senha, definir senha).
 *
 * Mesma identidade da Retaguarda — petróleo com o fio âmbar —, para quem entra
 * reconhecer o sistema antes de digitar a matrícula. Sem link para o site: aqui
 * só se entra.
 */
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const escuro = resolvedAppearance === 'dark';

    return (
        <div
            style={{
                position: 'relative',
                minHeight: '100svh',
                display: 'grid',
                placeItems: 'center',
                padding: 24,
                background:
                    'radial-gradient(circle at 20% 0%, var(--sm-primaria-suave), transparent 55%), var(--sm-app)',
            }}
        >
            {/*
                O tema se troca ANTES de entrar. Quem opera sob sol direto — ou de
                noite, que é quando muita fiscalização acontece — precisava logar
                primeiro para poder enxergar a tela: a única alternância morava na
                barra superior, que só existe depois do login.
                É o MESMO controle da Retaguarda (o `useAppearance` grava a
                escolha), então a preferência atravessa a entrada.
            */}
            <button
                type="button"
                className="icon-btn"
                onClick={() => updateAppearance(escuro ? 'light' : 'dark')}
                title={escuro ? 'Usar o tema claro' : 'Usar o tema escuro'}
                aria-label={escuro ? 'Usar o tema claro' : 'Usar o tema escuro'}
                style={{ position: 'absolute', top: 18, right: 18 }}
            >
                {escuro ? (
                    <Sun size={18} aria-hidden />
                ) : (
                    <Moon size={18} aria-hidden />
                )}
            </button>

            <div style={{ width: '100%', maxWidth: 420 }}>
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        alignItems: 'center',
                        gap: 10,
                        marginBottom: 22,
                        textAlign: 'center',
                    }}
                >
                    <span className="rt-marca-selo">
                        <ShieldCheck size={22} aria-hidden />
                    </span>
                    <span
                        className="rt-marca-nome"
                        style={{ fontSize: 24, letterSpacing: '0.02em' }}
                    >
                        SEFAL
                    </span>
                    <span
                        style={{
                            marginTop: -6,
                            fontSize: 12.5,
                            fontWeight: 600,
                            color: 'var(--sm-texto-fraco)',
                        }}
                    >
                        Sistema de Fiscalização de Ambulantes
                    </span>
                    <span className="rt-marca-sub">
                        SEMOP · Prefeitura de Salvador
                    </span>
                </div>

                <div className="card-premium">
                    <h1
                        style={{
                            fontSize: 20,
                            fontWeight: 800,
                            color: 'var(--sm-texto)',
                        }}
                    >
                        {title}
                    </h1>
                    {description && (
                        <p className="card-sub" style={{ marginBottom: 22 }}>
                            {description}
                        </p>
                    )}

                    {children}
                </div>
            </div>
        </div>
    );
}
