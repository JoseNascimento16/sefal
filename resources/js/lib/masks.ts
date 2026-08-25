// Máscaras de campo da Retaguarda e do PWA. Fonte única: nenhuma tela reescreve
// a sua. A máscara é aplicada no `onChange` (valor controlado); para enviar ao
// servidor, normalize com `apenasDigitos` (campos numéricos) ou
// `apenasAlfanumerico` (qualquer campo que possa conter CNPJ).
//
// ⚠️ CNPJ é ALFANUMÉRICO desde 2026: as 12 primeiras posições aceitam letras
// A–Z, e só os 2 dígitos verificadores finais são numéricos. Por isso
// `apenasDigitos` NUNCA pode ser usado num campo de documento — ele apaga as
// letras e corrompe o dado em silêncio. CPF continua com 11 dígitos.

export function apenasDigitos(v: string): string {
    return (v ?? '').replace(/\D/g, '');
}

/**
 * Forma canônica de um documento que pode ser CNPJ: mantém só `[0-9A-Z]`, em
 * maiúsculo, sem máscara. Para um CPF devolve os mesmos 11 dígitos.
 */
export function apenasAlfanumerico(v: string): string {
    return (v ?? '').replace(/[^0-9A-Za-z]/g, '').toUpperCase();
}

/** Separadores do CNPJ (00.000.000/0000-00) por POSIÇÃO — aceita letras. */
function formatarCnpjPosicional(v: string): string {
    let saida = v.slice(0, 2);

    if (v.length > 2) {
        saida += '.' + v.slice(2, 5);
    }

    if (v.length > 5) {
        saida += '.' + v.slice(5, 8);
    }

    if (v.length > 8) {
        saida += '/' + v.slice(8, 12);
    }

    if (v.length > 12) {
        saida += '-' + v.slice(12, 14);
    }

    return saida;
}

/** CPF (000.000.000-00), trava em 11 dígitos. Campo exclusivamente de CPF. */
export function maskCpf(valor: string): string {
    return apenasDigitos(valor)
        .slice(0, 11)
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
}

/** CNPJ (00.000.000/0000-00), trava em 14 posições; aceita o alfanumérico. */
export function maskCnpj(valor: string): string {
    return formatarCnpjPosicional(apenasAlfanumerico(valor).slice(0, 14));
}

/**
 * CPF ou CNPJ no mesmo campo ("documento"). Enquanto for só dígito e couber em
 * 11 posições, formata como CPF; ao aparecer uma letra OU passar de 11, vira
 * CNPJ.
 */
export function maskCpfCnpj(valor: string): string {
    const v = apenasAlfanumerico(valor).slice(0, 14);
    const temLetra = /[A-Z]/.test(v);

    if (!temLetra && v.length <= 11) {
        return v
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    }

    return formatarCnpjPosicional(v);
}

/** Telefone fixo (00) 0000-0000 ou celular (00) 00000-0000. */
export function maskTelefone(valor: string): string {
    const d = apenasDigitos(valor).slice(0, 11);

    if (d.length <= 10) {
        return d
            .replace(/(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{4})(\d{1,4})$/, '$1-$2');
    }

    return d
        .replace(/(\d{2})(\d)/, '($1) $2')
        .replace(/(\d{5})(\d{1,4})$/, '$1-$2');
}

/** CEP 00000-000. */
export function maskCep(valor: string): string {
    return apenasDigitos(valor)
        .slice(0, 8)
        .replace(/(\d{5})(\d{1,3})$/, '$1-$2');
}
