import { Moon, Sun } from 'lucide-react';
import { useAppearance } from '@/hooks/use-appearance';
import type { AuthLayoutProps } from '@/types';

/**
 * Casca das telas de acesso (entrar, esqueci minha senha, definir senha).
 *
 * Duas metades: a CIDADE à esquerda e o formulário à direita. A fotografia é a
 * ladeira do Pelourinho — que é onde este sistema trabalha —, e ela entra como
 * pano de fundo, não como ilustração: quem abre a tela precisa ler os campos.
 * Por isso vive só na metade que não tem formulário, sob um véu navy cujo
 * contraste foi MEDIDO (ver o cabeçalho da seção "TELA DE ACESSO" no
 * `retaguarda.css`).
 *
 * Abaixo de 900px a arte vira faixa no topo: mantém a fotografia à vista sem
 * roubar altura de quem só quer digitar a matrícula. É o CSS que decide isso —
 * uma segunda árvore de marcação daria dois donos ao mesmo conteúdo.
 */
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const escuro = resolvedAppearance === 'dark';

    return (
        <div className="rt-entrada">
            <section className="rt-entrada-arte">
                <div className="rt-entrada-marca">
                    {/* A versão BRANCA nos dois temas: o painel é escuro em
                        ambos (é fotografia sob véu navy), então aqui não há
                        troca por tema — ao contrário do menu lateral. */}
                    <img
                        src="/images/marca/brasao-salvador-branco.svg"
                        alt=""
                        aria-hidden
                    />
                    <div>
                        <p className="rt-entrada-nome">SEFAL</p>
                        <p className="rt-entrada-expansao">
                            Fiscalização de Ambulantes
                        </p>
                    </div>
                </div>

                <p className="rt-entrada-dizeres">
                    A fiscalização de rua da Prefeitura de Salvador, do cadastro
                    do permissionário ao que o fiscal registra na calçada.
                </p>

                <div className="rt-entrada-pe">
                    <img
                        className="rt-entrada-lockup"
                        src="/images/marca/salvador-horizontal-branco.svg"
                        alt="Prefeitura de Salvador"
                    />
                    SEMOP · Secretaria de Ordem Pública
                    <br />
                    Acesso restrito a servidores autorizados.
                </div>
            </section>

            <section className="rt-entrada-form">
                {/*
                    O tema se troca ANTES de entrar. Quem opera sob sol direto —
                    ou de noite, que é quando muita fiscalização acontece —
                    precisava logar primeiro para poder enxergar a tela: a única
                    alternância morava na barra superior, que só existe depois do
                    login. É o MESMO controle da Retaguarda (o `useAppearance`
                    grava a escolha), então a preferência atravessa a entrada.
                */}
                <button
                    type="button"
                    className="icon-btn"
                    onClick={() => updateAppearance(escuro ? 'light' : 'dark')}
                    title={escuro ? 'Usar o tema claro' : 'Usar o tema escuro'}
                    aria-label={
                        escuro ? 'Usar o tema claro' : 'Usar o tema escuro'
                    }
                    style={{ position: 'absolute', top: 18, right: 18 }}
                >
                    {escuro ? (
                        <Sun size={18} aria-hidden />
                    ) : (
                        <Moon size={18} aria-hidden />
                    )}
                </button>

                <div className="rt-entrada-caixa">
                    <div className="rt-entrada-cabeca">
                        <img
                            className="rt-marca-clara"
                            src="/images/marca/brasao-salvador-cor.svg"
                            alt=""
                            aria-hidden
                        />
                        <img
                            className="rt-marca-escura"
                            src="/images/marca/brasao-salvador-branco.svg"
                            alt=""
                            aria-hidden
                        />
                        <div>
                            <span className="rt-marca-nome">SEFAL</span>
                            <span
                                className="rt-marca-sub"
                                style={{ display: 'block' }}
                            >
                                SEMOP · Prefeitura de Salvador
                            </span>
                        </div>
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
            </section>
        </div>
    );
}
