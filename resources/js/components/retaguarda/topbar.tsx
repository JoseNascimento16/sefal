import { Bell, ChevronRight, Moon, Sun } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import { useAppearance } from '@/hooks/use-appearance';
import type { BreadcrumbItem } from '@/types';

/**
 * O CLUSTER de ações que acompanham o sistema inteiro: tema e avisos.
 *
 * Isto era uma BARRA superior (a `rt-topbar`), com trilha, tema, avisos, usuário,
 * sair e o botão do menu. A casca editorial aprovada pelo dono não tem barra: o
 * topo da tela é o cabeçalho editorial da própria página (sobrancelha da seção,
 * título grande, subtítulo), que é onde a pessoa já olha para saber onde está. A
 * barra roubava 68px de altura em toda tela para repetir isso em corpo 13.
 *
 * Então o conteúdo dela se dividiu:
 *  · identidade e SAIR → cartão do usuário no pé do menu lateral;
 *  · onde estou → o cabeçalho da página (`.rt-page-head`, em cada tela);
 *  · abrir o menu → não existe mais: o menu está sempre à vista, painel em tela
 *    larga e doca em tela estreita (ver o `retaguarda-layout`);
 *  · tema e avisos → aqui, num cluster discreto no canto, fora do caminho da
 *    leitura. São controles do sistema, não da tela: ficam à mão e calados.
 *
 * A TRILHA continua chegando por propriedade de layout e só é desenhada quando
 * tem mais de um nível — aí ela diz algo que o cabeçalho da página não diz (o
 * caminho de volta). Com um nível, ela repetiria o título em letra menor.
 */
export function Topbar({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItem[] }) {
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
        <div className="rt-cluster">
            {breadcrumbs.length > 1 && (
                <nav className="rt-trilha" aria-label="Trilha de navegação">
                    {breadcrumbs.map((item, i) => {
                        const ultimo = i === breadcrumbs.length - 1;

                        return (
                            <span key={i} className="rt-trilha-item">
                                {ultimo ? (
                                    <span className="rt-trilha-atual">
                                        {item.title}
                                    </span>
                                ) : (
                                    <>
                                        <Link href={item.href}>
                                            {item.title}
                                        </Link>
                                        <ChevronRight size={13} aria-hidden />
                                    </>
                                )}
                            </span>
                        );
                    })}
                </nav>
            )}

            <button
                type="button"
                className="icon-btn"
                onClick={() => updateAppearance(escuro ? 'light' : 'dark')}
                title={escuro ? 'Usar o tema claro' : 'Usar o tema escuro'}
                aria-label={escuro ? 'Usar o tema claro' : 'Usar o tema escuro'}
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
                    <div className="rt-pop" role="dialog" aria-label="Avisos">
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
        </div>
    );
}
