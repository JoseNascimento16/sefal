import { FlaskConical } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Selo de PROTÓTIPO — a tela diz, em cima, que não é sistema pronto.
 *
 * Protótipo que se disfarça de entrega vira decisão tomada por engano: alguém vê
 * a grade cheia, conclui que o módulo está no ar e planeja em cima disso. O selo
 * não é enfeite — é a única coisa que separa "aprovamos a forma" de "está
 * funcionando".
 *
 * Ele carrega DUAS informações, e as duas importam: que os dados são fictícios, e
 * o que muda quando aquilo virar produção (`children`).
 *
 * Cores vêm dos tokens do Design System (`--sm-aviso*`), como o resto da casca:
 * nada de paleta paralela para uma tela de protótipo — a que se aprova é a tela
 * real, não uma parecida.
 */
export function SeloPrototipo({
    escuro = false,
    children,
}: {
    /**
     * O selo está sobre uma superfície ESCURA NOS DOIS TEMAS — hoje, a camada de
     * vidro das telas de mapa (RN-07).
     *
     * Sem isto ele usaria os tokens do tema, e no tema claro o texto sairia em
     * marrom (`--sm-aviso` = #8a5300) sobre o vidro escuro: ilegível. É a mesma
     * razão pela qual o menu tem paleta própria (RN-01) — superfície que não
     * acompanha o tema precisa de cor que também não acompanhe.
     */
    escuro?: boolean;
    children?: ReactNode;
}) {
    return (
        <div
            role="note"
            style={{
                display: 'flex',
                alignItems: 'flex-start',
                gap: 10,
                padding: '12px 14px',
                marginBottom: escuro ? 0 : 18,
                borderRadius: escuro ? 18 : 'var(--sm-raio-md)',
                // `--sm-aviso` e `--sm-aviso-suave` são os tokens do tema, e o
                // tema escuro os redefine: o selo acompanha sem uma segunda
                // paleta. A borda sai da MESMA cor do texto, com transparência.
                border: escuro
                    ? '1px solid rgba(255, 154, 77, 0.34)'
                    : '1px solid color-mix(in srgb, var(--sm-aviso) 45%, transparent)',
                background: escuro ? 'rgba(13, 26, 46, 0.82)' : 'var(--sm-aviso-suave)',
                backdropFilter: escuro ? 'blur(14px)' : undefined,
                color: escuro ? '#ffb877' : 'var(--sm-aviso)',
                fontSize: 13,
                lineHeight: 1.55,
            }}
        >
            <FlaskConical
                size={17}
                aria-hidden
                style={{ flexShrink: 0, marginTop: 2 }}
            />

            <div>
                <strong style={{ letterSpacing: '.04em' }}>
                    PROTÓTIPO · DADOS FICTÍCIOS
                </strong>
                {children ? <div>{children}</div> : null}
            </div>
        </div>
    );
}
