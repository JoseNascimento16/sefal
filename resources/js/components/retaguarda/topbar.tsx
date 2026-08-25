import { Link, router, usePage } from '@inertiajs/react';
import { Bell, ChevronRight, LogOut, Menu, Moon, Sun } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useAppearance } from '@/hooks/use-appearance';
import { logout } from '@/routes';
import type { BreadcrumbItem } from '@/types';

/** Iniciais para o avatar — duas letras, ou "?" quando não há nome. */
function iniciais(nome: string): string {
    const partes = nome.trim().split(/\s+/);

    return (
        ((partes[0]?.[0] ?? '') + (partes[1]?.[0] ?? '')).toUpperCase() || '?'
    );
}

/**
 * Barra superior da Retaguarda: onde a pessoa está, quem ela é e as ações que
 * acompanham o sistema inteiro (tema, avisos, sair).
 */
export function Topbar({
    breadcrumbs = [],
    onAbrirMenu,
}: {
    breadcrumbs?: BreadcrumbItem[];
    onAbrirMenu: () => void;
}) {
    const { auth } = usePage().props;
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const [avisosAbertos, setAvisosAbertos] = useState(false);
    const avisos = useRef<HTMLDivElement>(null);

    const escuro = resolvedAppearance === 'dark';

    // Fecha os avisos ao clicar fora ou no Esc — camada aberta que só fecha no
    // mesmo botão vira armadilha, sobretudo no celular.
    useEffect(() => {
        if (!avisosAbertos) {
            return;
        }

        const clique = (e: MouseEvent) => {
            if (!avisos.current?.contains(e.target as Node)) {
                setAvisosAbertos(false);
            }
        };
        const tecla = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                setAvisosAbertos(false);
            }
        };

        document.addEventListener('mousedown', clique);
        document.addEventListener('keydown', tecla);

        return () => {
            document.removeEventListener('mousedown', clique);
            document.removeEventListener('keydown', tecla);
        };
    }, [avisosAbertos]);

    return (
        <header className="rt-topbar">
            <button
                type="button"
                className="icon-btn rt-menu-botao"
                onClick={onAbrirMenu}
                title="Abrir o menu"
                aria-label="Abrir o menu"
            >
                <Menu size={18} aria-hidden />
            </button>

            <nav className="rt-trilha" aria-label="Trilha de navegação">
                {breadcrumbs.length === 0 ? (
                    <span className="rt-trilha-atual">Retaguarda</span>
                ) : (
                    breadcrumbs.map((item, i) => {
                        const ultimo = i === breadcrumbs.length - 1;

                        return (
                            <span
                                key={i}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 8,
                                }}
                            >
                                {ultimo ? (
                                    <span className="rt-trilha-atual">
                                        {item.title}
                                    </span>
                                ) : (
                                    <>
                                        <Link href={item.href}>
                                            {item.title}
                                        </Link>
                                        <ChevronRight size={14} aria-hidden />
                                    </>
                                )}
                            </span>
                        );
                    })
                )}
            </nav>

            <div className="rt-topbar-acoes">
                <button
                    type="button"
                    className="icon-btn"
                    onClick={() => updateAppearance(escuro ? 'light' : 'dark')}
                    title={escuro ? 'Usar o tema claro' : 'Usar o tema escuro'}
                    aria-label={
                        escuro ? 'Usar o tema claro' : 'Usar o tema escuro'
                    }
                >
                    {escuro ? (
                        <Sun size={18} aria-hidden />
                    ) : (
                        <Moon size={18} aria-hidden />
                    )}
                </button>

                <div style={{ position: 'relative' }} ref={avisos}>
                    <button
                        type="button"
                        className="icon-btn"
                        onClick={() => setAvisosAbertos((v) => !v)}
                        title="Avisos"
                        aria-label="Avisos"
                        aria-expanded={avisosAbertos}
                    >
                        <Bell size={18} aria-hidden />
                    </button>

                    {avisosAbertos && (
                        <div
                            className="rt-pop"
                            role="dialog"
                            aria-label="Avisos"
                        >
                            <p
                                style={{
                                    fontWeight: 700,
                                    color: 'var(--sm-texto)',
                                    marginBottom: 4,
                                }}
                            >
                                Avisos
                            </p>
                            <p
                                style={{
                                    fontSize: 13,
                                    color: 'var(--sm-texto-fraco)',
                                }}
                            >
                                Sem notificações.
                            </p>
                        </div>
                    )}
                </div>

                {auth.user && (
                    <div className="rt-usuario">
                        <span className="rt-avatar" aria-hidden>
                            {iniciais(auth.user.name)}
                        </span>
                        <span>
                            <span
                                className="rt-usuario-nome"
                                style={{ display: 'block' }}
                            >
                                {auth.user.name}
                            </span>
                            {/* A matrícula é guardada em minúsculo (forma
                                canônica) e MOSTRADA em maiúsculo, que é como ela
                                vem no crachá e nos documentos do servidor. */}
                            <span className="rt-usuario-matricula">
                                {auth.user.login.toUpperCase()}
                            </span>
                        </span>
                    </div>
                )}

                <Link
                    href={logout()}
                    as="button"
                    className="icon-btn"
                    title="Sair do sistema"
                    aria-label="Sair do sistema"
                    onClick={() => router.flushAll()}
                >
                    <LogOut size={18} aria-hidden />
                </Link>
            </div>
        </header>
    );
}
