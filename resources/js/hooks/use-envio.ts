import { router } from '@inertiajs/react';
import { useState } from 'react';

type PostArgs = Parameters<typeof router.post>;
type Opts = PostArgs[2];

/**
 * PADRÃO de ação de escrita — guarda contra duplo clique + sinal de progresso.
 *
 * Toda ação que grava (e que pode demorar) passa por aqui em vez de chamar
 * `router.post(...)` direto. Sem a guarda, quem não vê resposta reclica achando
 * que não pegou — e o segundo envio erra ou duplica o registro.
 *
 *  - `enviando` é a chave da ação em voo (ou `null`);
 *  - `ocupado` é verdadeiro enquanto QUALQUER ação está em voo — serve para
 *    desabilitar os outros botões da tela;
 *  - `enviar()` ignora cliques repetidos até a resposta voltar.
 *
 * Use junto com `<BotaoAcao>`, que já mostra o spinner e se desabilita sozinho.
 *
 * @example
 *   const { enviando, ocupado, enviar } = useEnvio();
 *   enviar('validar', url, { ambulante_id }, { onSuccess: fechar });
 *   <BotaoAcao carregando={enviando === 'validar'} ocupado={ocupado} onClick={validar}>Validar</BotaoAcao>
 */
export function useEnvio() {
    const [enviando, setEnviando] = useState<string | null>(null);
    const ocupado = enviando !== null;

    /** Dispara um POST guardado contra duplo clique. `acao` identifica o botão. */
    function enviar(
        acao: string,
        url: PostArgs[0],
        dados?: PostArgs[1],
        opts: Opts = {},
    ) {
        if (ocupado) {
            return;
        }

        router.post(url, dados, {
            preserveScroll: true,
            ...opts,
            onStart: (visit) => {
                setEnviando(acao);
                opts.onStart?.(visit);
            },
            onFinish: (visit) => {
                setEnviando(null);
                opts.onFinish?.(visit);
            },
        });
    }

    /**
     * Saída de emergência para os outros verbos (put/patch/delete/visit) ou para
     * chamadas que você mesmo dispara: embrulha as opções do Inertia com a guarda.
     *
     * @example router.delete(url, guardar('excluir', { onSuccess: fechar }))
     */
    function guardar(acao: string, opts: Opts = {}): Opts {
        return {
            preserveScroll: true,
            ...opts,
            onStart: (visit) => {
                if (!ocupado) {
                    setEnviando(acao);
                }

                opts.onStart?.(visit);
            },
            onFinish: (visit) => {
                setEnviando(null);
                opts.onFinish?.(visit);
            },
        };
    }

    return { enviando, ocupado, enviar, guardar };
}
