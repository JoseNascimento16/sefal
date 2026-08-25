import { useEffect } from 'react';

// Contador global: com camadas EMPILHADAS (uma confirmação por cima de um
// formulário sobreposto), a de cima fecha primeiro e não pode devolver a
// rolagem enquanto a de baixo continua aberta. Só a última a soltar restaura.
let travas = 0;
let overflowOriginal = '';
let paddingRightOriginal = '';

/**
 * Trava a rolagem da página enquanto uma camada sobreposta está aberta.
 *
 * Sem isto a rolagem "vaza": a pessoa rola dentro da camada, chega ao fim do
 * conteúdo e a página de trás começa a andar junto — a interação deixa de estar
 * isolada. Já vem embutido no `<ModalConfirm>`; use direto só ao construir uma
 * camada que não passe por ele.
 */
export function useScrollLock(ativo: boolean): void {
    useEffect(() => {
        if (!ativo) {
            return;
        }

        const body = document.body;
        travas += 1;

        if (travas === 1) {
            overflowOriginal = body.style.overflow;
            paddingRightOriginal = body.style.paddingRight;

            // Esconder o overflow tira a barra de rolagem e o conteúdo "pula"
            // para a direita. Compensa com a largura exata dela (0 em quem usa
            // barra sobreposta).
            const larguraBarra =
                window.innerWidth - document.documentElement.clientWidth;

            if (larguraBarra > 0) {
                const atual =
                    parseFloat(window.getComputedStyle(body).paddingRight) || 0;
                body.style.paddingRight = `${atual + larguraBarra}px`;
            }

            body.style.overflow = 'hidden';
        }

        return () => {
            travas -= 1;

            if (travas === 0) {
                body.style.overflow = overflowOriginal;
                body.style.paddingRight = paddingRightOriginal;
            }
        };
    }, [ativo]);
}
