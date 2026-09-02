/**
 * FONTE ÚNICA da saudação pelo horário e do primeiro nome.
 *
 * Duas telas cumprimentam quem entra — o splash de boas-vindas e a tela Início, e
 * as duas ao mesmo tempo, na mesma entrada. Com uma cópia da regra em cada uma,
 * elas discordariam: era exatamente o caso às 3h da manhã, quando o Início dizia
 * "Bom dia" (o corte dele era só `hora < 12`) e a faixa correta é NOITE.
 *
 * Quem mexer nas faixas mexe aqui, e as duas telas acompanham.
 */

/**
 * Saudação pelo horário de quem está olhando: 06:00–11:59 bom dia · 12:00–17:59
 * boa tarde · 18:00–05:59 boa noite.
 *
 * ⚠️ A MADRUGADA é noite, e neste sistema isso não é hipótese remota: fiscalização
 * de ambulante acontece de madrugada, em Carnaval e em festa de largo. Com o corte
 * apenas em `hora < 12`, quem abre o sistema às 3h recebe "Bom dia".
 */
export function saudacaoDaHora(hora: number): string {
    if (hora >= 6 && hora < 12) return 'Bom dia';
    if (hora >= 12 && hora < 18) return 'Boa tarde';

    return 'Boa noite';
}

/** A saudação de AGORA, pelo relógio de quem está olhando. */
export function saudacaoAgora(): string {
    return saudacaoDaHora(new Date().getHours());
}

/**
 * Primeiro nome, capitalizado — "Boa tarde, José" soa com gente; o nome completo em
 * caixa alta, como vem do cadastro, soa com ficha.
 */
export function primeiroNome(nome: string): string {
    const limpo = nome.trim();
    if (limpo === '') return '';

    const parte = limpo.split(/\s+/)[0];

    return parte.charAt(0).toUpperCase() + parte.slice(1).toLowerCase();
}
