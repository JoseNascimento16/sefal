/**
 * Pede um documento ao servidor e o entrega ao navegador — o CAMINHO ÚNICO dos
 * downloads da Retaguarda (exportação de listagem e emissão de relatório).
 *
 * Três decisões moram aqui, e é por isso que ele existe uma vez só:
 *
 *  1. **POST, com o pedido no CORPO.** Filtros e recortes carregam texto livre; em
 *     query string cairiam na lei do WAF da Prefeitura (`--`, aspas) e a falha
 *     voltaria disfarçada de erro de CORS;
 *  2. **`fetch` + blob, e não o Inertia.** A resposta é um arquivo, não uma
 *     página: uma visita do Inertia não sabe o que fazer com ela;
 *  3. **falha DIZ o motivo.** Download que simplesmente não acontece parece o
 *     sistema travado. O erro volta como texto para a tela mostrar.
 */

/** Lê o CSRF do cookie — este POST sai fora do Inertia. */
function csrf(): string {
    const bruto = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='));

    return bruto ? decodeURIComponent(bruto.slice('XSRF-TOKEN='.length)) : '';
}

/** O nome do arquivo vindo do `Content-Disposition`; cai na reserva se não vier. */
function nomeDoHeader(disposition: string | null, reserva: string): string {
    const m = disposition?.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);

    return m ? decodeURIComponent(m[1]) : reserva;
}

export type ResultadoDownload = { ok: true } | { ok: false; mensagem: string };

export async function baixarDocumento(
    url: string,
    corpo: Record<string, unknown>,
    nomeReserva: string,
): Promise<ResultadoDownload> {
    try {
        const resposta = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                // `Accept: application/json` importa: com ele o servidor devolve a
                // recusa como JSON (`{message}`) em vez de um redirecionamento que
                // o `fetch` seguiria — e o navegador acabaria "baixando" a página
                // de erro como se fosse o documento.
                Accept: 'application/json',
                'X-XSRF-TOKEN': csrf(),
            },
            body: JSON.stringify(corpo),
        });

        if (!resposta.ok) {
            const json = await resposta.json().catch(() => null);

            return {
                ok: false,
                mensagem:
                    json?.message ??
                    'Não foi possível gerar o arquivo. Tente novamente.',
            };
        }

        const objeto = URL.createObjectURL(await resposta.blob());
        const a = document.createElement('a');
        a.href = objeto;
        a.download = nomeDoHeader(
            resposta.headers.get('content-disposition'),
            nomeReserva,
        );
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(objeto);

        return { ok: true };
    } catch {
        return {
            ok: false,
            mensagem: 'Falha de comunicação ao gerar o arquivo.',
        };
    }
}
