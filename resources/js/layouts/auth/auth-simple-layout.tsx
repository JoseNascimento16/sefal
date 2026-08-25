import { ShieldCheck } from 'lucide-react';
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
    return (
        <div
            style={{
                minHeight: '100svh',
                display: 'grid',
                placeItems: 'center',
                padding: 24,
                background:
                    'radial-gradient(circle at 20% 0%, var(--sm-primaria-suave), transparent 55%), var(--sm-app)',
            }}
        >
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
