import { Link, router, usePage } from '@inertiajs/react';
import { Bell, ChevronDown, ChevronRight, LogOut, Moon, Sun, UserRound, Users } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ModalConfirm } from '@/components/retaguarda/modal-confirm';
import { useAppearance } from '@/hooks/use-appearance';
import { logout } from '@/routes';
import { edit as editarPerfil } from '@/routes/profile';
import { index as usuarios } from '@/routes/retaguarda/usuarios';
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
 *  · identidade, Meu Perfil, Usuários e SAIR → o menu da CONTA, aqui no canto
 *    superior direito, como no Codecon (dono, 25/09/2026 — antes ficavam no pé
 *    do menu lateral e entre os itens de trabalho);
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
    const { auth } = usePage().props;
    const [avisosAbertos, setAvisosAbertos] = useState(false);
    const avisos = useRef<HTMLDivElement>(null);
    const [contaAberta, setContaAberta] = useState(false);
    const conta = useRef<HTMLDivElement>(null);
    const [confirmandoSaida, setConfirmandoSaida] = useState(false);
    const [saindo, setSaindo] = useState(false);

    const usuario = auth.user;

    /** Iniciais para o avatar — duas letras, ou "?" quando não há nome. */
    const iniciais =
        (usuario?.name ?? '')
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map((p) => p[0] ?? '')
            .join('')
            .toUpperCase() || '?';

    /** O papel de quem entrou: o primeiro setor, ou o desvio do administrador. */
    const papel = usuario?.admin ? 'Administrador' : (usuario?.setores[0] ?? 'Sem setor definido');

    // O menu da conta fecha com clique fora e com Esc, como o de avisos.
    useEffect(() => {
        if (!contaAberta) {
            return;
        }

        const clique = (e: MouseEvent) => {
            if (!conta.current?.contains(e.target as Node)) {
                setContaAberta(false);
            }
        };
        const tecla = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                setContaAberta(false);
            }
        };

        document.addEventListener('mousedown', clique);
        document.addEventListener('keydown', tecla);

        return () => {
            document.removeEventListener('mousedown', clique);
            document.removeEventListener('keydown', tecla);
        };
    }, [contaAberta]);

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

            {usuario !== null && (
                <div style={{ position: 'relative' }} ref={conta}>
                    <button
                        type="button"
                        className="rt-conta-botao"
                        onClick={() => setContaAberta((v) => !v)}
                        aria-label={`Conta de ${usuario.name}`}
                        aria-expanded={contaAberta}
                        aria-haspopup="menu"
                    >
                        <span className="rt-avatar rt-avatar-pequeno" aria-hidden>
                            {iniciais}
                        </span>
                        <span className="rt-conta-nome">{usuario.name}</span>
                        <ChevronDown size={15} aria-hidden />
                    </button>

                    {contaAberta && (
                        <div className="rt-pop rt-conta-menu" role="menu" aria-label="Conta">
                            <div className="rt-conta-cabeca">
                                <strong>{usuario.name}</strong>
                                <span>{papel}</span>
                            </div>
                            <Link
                                href={editarPerfil().url}
                                className="rt-conta-item"
                                role="menuitem"
                                onClick={() => setContaAberta(false)}
                            >
                                <UserRound size={16} aria-hidden /> Meu perfil
                            </Link>
                            {usuario.administra_usuarios && (
                                <Link
                                    href={usuarios().url}
                                    className="rt-conta-item"
                                    role="menuitem"
                                    onClick={() => setContaAberta(false)}
                                >
                                    <Users size={16} aria-hidden /> Usuários
                                </Link>
                            )}
                            <button
                                type="button"
                                className="rt-conta-item rt-conta-sair"
                                role="menuitem"
                                onClick={() => {
                                    setContaAberta(false);
                                    setConfirmandoSaida(true);
                                }}
                            >
                                <LogOut size={16} aria-hidden /> Sair
                            </button>
                        </div>
                    )}
                </div>
            )}

            {/* Sair PERGUNTA antes: a sessão leva embora formulário aberto. */}
            {confirmandoSaida && (
                <ModalConfirm
                    titulo="Sair do sistema?"
                    mensagem="A sessão é encerrada e o que estiver preenchido em formulário aberto se perde. Para entrar de novo você precisa da matrícula e da senha."
                    rotuloConfirmar="Sair do sistema"
                    rotuloCancelar="Continuar no sistema"
                    iconeConfirmar={<LogOut size={16} aria-hidden />}
                    destrutiva
                    processando={saindo}
                    onCancelar={() => setConfirmandoSaida(false)}
                    onConfirmar={() => {
                        setSaindo(true);
                        // A limpeza dos dados em memória vem ANTES do pedido: o
                        // aparelho pode ser compartilhado, e o histórico do Inertia
                        // guarda as telas já visitadas.
                        router.flushAll();
                        router.post(logout().url);
                    }}
                />
            )}
        </div>
    );
}
