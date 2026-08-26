import { usePage } from '@inertiajs/react';
import type { AcoesDaTela } from '@/types/auth';

/**
 * O que esta pessoa pode fazer na tela aberta — CAMINHO ÚNICO da pergunta no
 * front.
 *
 * O servidor responde em `HandleInertiaRequests::acoes()`, consultando o mesmo
 * `PermissaoService` que as guardas consultam; aqui a tela só lê. Nenhuma tela
 * decide isso por conta própria: se houvesse uma segunda conta, um dia ela
 * ofereceria o botão que o servidor recusa — ou esconderia o que ele aceita.
 *
 * ⚠️ Isto NÃO é autorização. Quem barra são as guardas, no servidor, e elas
 * continuam barrando mesmo que a tela ofereça o botão. O que se ganha aqui é a
 * pessoa descobrir a recusa ANTES de preencher o formulário inteiro.
 *
 * Quando o servidor manda `null` — visitante, ou tela fora do Modo Gerente —
 * devolve tudo liberado: ausência de restrição declarada não é restrição.
 */
export function useAcoes(): AcoesDaTela {
    const { acoes } = usePage().props;

    return (
        acoes ?? {
            visivel: true,
            habilitado: true,
            apenas_leitura: false,
            incluir: true,
            excluir: true,
        }
    );
}
