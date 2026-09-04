/**
 * A RECOMENDAÇÃO do fiscal, da chave gravada até a frase que a Retaguarda lê.
 *
 * O aplicativo do fiscal grava CHAVE (`retorno`, `sgci`, `operacao`…) e não a
 * frase, porque é a chave que o relatório soma ("quantos registros pediram
 * operação na área?") e porque a redação de um lado não pode mudar o dado do
 * outro. Quem traduz é a leitura — aqui.
 *
 * O catálogo vem do SERVIDOR (`config/prototipo_denuncias.recomendacoes_do_fiscal`,
 * na redação `explicito`), pelo mesmo motivo dos outros catálogos das telas:
 * escrito também aqui, um dia discordaria — e a tela mostraria uma frase que o
 * catálogo já não tem.
 *
 * ⚠️ A redação da Retaguarda é a EXPLÍCITA, e a curta é a do celular (decisão do
 * dono). "Sugerir retorno da equipe" cabe numa pílula de aparelho e não diz
 * QUANDO voltar; "Voltar ao ponto no vencimento do prazo" diz, e é isso que
 * quem decide precisa ler.
 *
 * ⚠️ CHAVE DESCONHECIDA NÃO DESAPARECE DA TELA — ela aparece CRUA. Recomendação
 * que evapora em silêncio é pior que recomendação feia: a chefia decidiria sem
 * saber que o fiscal pediu alguma coisa, e ninguém teria como perceber que os
 * dois lados divergiram. Chave crua na tela é o sintoma visível de que o
 * catálogo do aplicativo andou sem o do servidor.
 */

/** Chave da recomendação → a frase que a Retaguarda mostra. */
export type CatalogoDeRecomendacoes = Record<string, string>;

/** A frase de uma chave — ou a própria chave, quando o catálogo não a conhece. */
export function textoDaRecomendacao(chave: string, catalogo: CatalogoDeRecomendacoes): string {
    const texto = catalogo[chave];

    return texto === undefined || texto.trim() === '' ? chave : texto;
}

/**
 * As frases de uma lista de chaves, na ordem em que o fiscal as assinalou.
 *
 * Serve a busca e a exportação, que precisam do TEXTO: quem procura por
 * "operação" tem de achar o registro em que o fiscal pediu operação, e o
 * arquivo é lido por quem decide — a chave `operacao` numa célula não é resposta.
 */
export function textosDasRecomendacoes(
    chaves: readonly string[],
    catalogo: CatalogoDeRecomendacoes,
): string[] {
    return chaves.map((chave) => textoDaRecomendacao(chave, catalogo));
}
