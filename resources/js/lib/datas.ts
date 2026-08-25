// Datas na tela são SEMPRE em português do Brasil (dd/mm/aaaa) — lei do projeto.
// O formato ISO existe só por dentro: valor de `<input type="date">`, coluna do
// banco, corpo da requisição. Nada de `aaaa-mm-dd` à vista do usuário, nem em
// documento gerado.
//
// As funções trabalham em cima do TEXTO, sem `new Date()`, de propósito: criar
// um Date a partir de "2026-08-25" o interpreta como meia-noite em UTC e, num
// fuso negativo como o nosso, a data exibida volta um dia.

/** Vazio na tela: um travessão, nunca "null" nem string em branco. */
export const VAZIO = '—';

/** `2026-08-25` → `25/08/2026`. Aceita data e hora ISO (corta no `T`). */
export function dataBR(iso: string | null | undefined): string {
    if (!iso) {
        return VAZIO;
    }

    const [data] = String(iso).split('T');
    const [ano, mes, dia] = data.split('-');

    if (!ano || !mes || !dia) {
        return String(iso);
    }

    return `${dia}/${mes}/${ano}`;
}

/** `2026-08-25T14:30:00` → `25/08/2026 14:30`. */
export function dataHoraBR(iso: string | null | undefined): string {
    if (!iso) {
        return VAZIO;
    }

    const [data, hora] = String(iso).replace(' ', 'T').split('T');

    return `${dataBR(data)} ${hora?.substring(0, 5) ?? ''}`.trim();
}

/** Dias corridos entre duas datas ISO (fim − início). */
export function diasEntre(isoInicio: string, isoFim: string): number {
    const [a1, m1, d1] = isoInicio.split('T')[0].split('-').map(Number);
    const [a2, m2, d2] = isoFim.split('T')[0].split('-').map(Number);

    return Math.floor(
        (Date.UTC(a2, m2 - 1, d2) - Date.UTC(a1, m1 - 1, d1)) / 86400000,
    );
}

/** Hoje em ISO (`aaaa-mm-dd`) — para preencher `<input type="date">`. */
export function hojeISO(): string {
    const agora = new Date();
    const mes = String(agora.getMonth() + 1).padStart(2, '0');
    const dia = String(agora.getDate()).padStart(2, '0');

    return `${agora.getFullYear()}-${mes}-${dia}`;
}
