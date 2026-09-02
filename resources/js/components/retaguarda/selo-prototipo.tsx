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
export function SeloPrototipo({ children }: { children?: ReactNode }) {
    return (
        <div
            role="note"
            style={{
                display: 'flex',
                alignItems: 'flex-start',
                gap: 10,
                padding: '12px 14px',
                marginBottom: 18,
                borderRadius: 'var(--sm-raio-md)',
                // `--sm-aviso` e `--sm-aviso-suave` são os tokens do tema, e o
                // tema escuro os redefine: o selo acompanha sem uma segunda
                // paleta. A borda sai da MESMA cor do texto, com transparência.
                border: '1px solid color-mix(in srgb, var(--sm-aviso) 45%, transparent)',
                background: 'var(--sm-aviso-suave)',
                color: 'var(--sm-aviso)',
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
