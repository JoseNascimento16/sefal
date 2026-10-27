/* ============================================================================
   A conversa com o servidor
   ----------------------------------------------------------------------------
   O aplicativo mora na mesma origem da Retaguarda, então fala com ela pela
   MESMA sessão: o cookie vai sozinho (`credentials: 'same-origin'`), e o token
   anti-falsificação sai do cookie `XSRF-TOKEN` que o Laravel deixa no
   navegador. Tudo é JSON — inclusive as recusas, que o servidor manda sempre
   com a frase pronta para a tela.
   ============================================================================ */

/** Recusa do servidor (ou da rede), com a frase que a tela mostra. */
export class ErroDoServidor extends Error {
    constructor(
        message: string,
        /** 0 = sem resposta (sem sinal, servidor fora). */
        public readonly status: number,
        /** Os campos recusados, quando a recusa é de validação (422). */
        public readonly campos: Record<string, string[]> = {},
    ) {
        super(message);
    }

    /** Vale tentar de novo depois (sem sinal, servidor ocupado ou fora)? */
    get passageiro(): boolean {
        return this.status === 0 || this.status === 429 || this.status >= 500;
    }
}

const tokenAntiFalsificacao = (): string | null => {
    const par = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));

    return par ? decodeURIComponent(par.slice('XSRF-TOKEN='.length)) : null;
};

const SEM_SINAL = 'Sem conexão com o servidor. O que você registrar fica no aparelho e é enviado quando o sinal voltar.';

async function pedir<T>(metodo: 'GET' | 'POST', caminho: string, corpo?: FormData | object): Promise<T> {
    const cabecalhos: Record<string, string> = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    const token = tokenAntiFalsificacao();

    if (token) {
        cabecalhos['X-XSRF-TOKEN'] = token;
    }

    let body: BodyInit | undefined;

    if (corpo instanceof FormData) {
        body = corpo;
    } else if (corpo !== undefined) {
        cabecalhos['Content-Type'] = 'application/json';
        body = JSON.stringify(corpo);
    }

    let resposta: Response;

    try {
        resposta = await fetch(`/app/api/${caminho}`, { method: metodo, headers: cabecalhos, body, credentials: 'same-origin' });
    } catch {
        throw new ErroDoServidor(SEM_SINAL, 0);
    }

    if (resposta.status === 204) {
        return undefined as T;
    }

    const dados = await resposta.json().catch(() => null);

    if (!resposta.ok) {
        const campos = (dados?.errors ?? {}) as Record<string, string[]>;
        const primeira = Object.values(campos)[0]?.[0];
        const frase =
            primeira ??
            dados?.message ??
            (resposta.status === 419
                ? 'A sessão expirou. Entre de novo.'
                : `O servidor recusou o pedido (erro ${resposta.status}). Tente de novo em instantes.`);

        throw new ErroDoServidor(frase, resposta.status, campos);
    }

    return dados as T;
}

export const api = {
    get: <T>(caminho: string) => pedir<T>('GET', caminho),
    post: <T>(caminho: string, corpo?: FormData | object) => pedir<T>('POST', caminho, corpo),
};
