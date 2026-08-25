import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

/**
 * Mostra o recado que o servidor mandou — CAMINHO ÚNICO das mensagens.
 *
 * O servidor grava na sessão (`->with('flash.sucesso', '…')` ou
 * `->with('flash.erro', '…')`), o `HandleInertiaRequests` compartilha, e aqui
 * vira um aviso flutuante. Nenhuma tela mostra recado por conta própria: se
 * houvesse um segundo jeito, um dia só um dos dois apareceria.
 *
 * A reação é presa ao `chave`, que o servidor troca a cada resposta com recado.
 * Preso ao TEXTO, salvar duas vezes seguidas avisaria só na primeira vez — mesma
 * mensagem, mesma página, nada que o React entenda como mudança.
 */
export function useFlashToast(): void {
    const { flash } = usePage().props;

    useEffect(() => {
        if (!flash?.chave) {
            return;
        }

        if (flash.sucesso) {
            toast.success(flash.sucesso);
        }

        if (flash.erro) {
            toast.error(flash.erro);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [flash?.chave]);
}
