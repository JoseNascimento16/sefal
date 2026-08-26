import type { ReactNode } from 'react';
import { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useScrollLock } from '@/hooks/use-scroll-lock';

/**
 * A camada escura que fica por cima da tela — base de TODA janela sobreposta da
 * Retaguarda (confirmação, escolha de formato de exportação).
 *
 * Ela existe para resolver, num lugar só, as três coisas que uma camada
 * sobreposta tem de fazer e que é fácil esquecer em cada janela:
 *
 *  1. **travar a rolagem** de trás, para a decisão ser o único assunto;
 *  2. **neutralizar o fundo** (`inert`). Sem isto a janela era modal apenas para
 *     quem olha: `Tab` e leitor de tela continuavam alcançando os botões da tela
 *     de trás — com dois "Excluir" e dois "Voltar" no mesmo caminho de teclado,
 *     e o de trás capaz de disparar de verdade;
 *  3. **sair do lugar onde a tela a declarou.** A janela é desenhada dentro da
 *     página, mas mora no `<body>`: é isso que permite marcar a aplicação inteira
 *     como inerte sem apagar a própria janela junto — se ela ficasse dentro,
 *     ficaria inerte também.
 *
 * `clicandoFora` só é chamado no clique na área escura; passe `undefined` para
 * impedir o fechamento (é o que se faz no meio de uma ação em voo, para a pessoa
 * não fechar a camada sem saber se aquilo foi gravado).
 */
/**
 * Camadas EMPILHADAS (uma confirmação por cima da escolha de formato): a de cima
 * fecha primeiro e não pode devolver o fundo enquanto a de baixo continua no ar.
 * Só a última a soltar devolve — a mesma conta que a trava de rolagem faz.
 */
let camadas = 0;

export function Sobreposicao({
    clicandoFora,
    children,
}: {
    clicandoFora?: () => void;
    children: ReactNode;
}) {
    useScrollLock(true);

    useEffect(() => {
        // A raiz da aplicação Inertia. É o irmão da camada no `<body>`, e é ele
        // que fica inerte enquanto a janela está no ar.
        const raiz = document.getElementById('app');

        camadas += 1;
        raiz?.setAttribute('inert', '');

        return () => {
            camadas -= 1;

            if (camadas === 0) {
                raiz?.removeAttribute('inert');
            }
        };
    }, []);

    return createPortal(
        <div
            className="sobreposicao"
            role="presentation"
            onClick={clicandoFora}
        >
            {children}
        </div>,
        document.body,
    );
}
