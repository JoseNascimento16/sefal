import type { LucideIcon } from 'lucide-react';
import { FileSpreadsheet, FileText, FileType2 } from 'lucide-react';

/**
 * Os formatos em que o sistema entrega documento — CATÁLOGO ÚNICO.
 *
 * Dois lugares oferecem essa escolha (a exportação de listagem e a emissão de
 * relatório), e é a mesma escolha: mesmos três formatos, mesma ordem, mesmos
 * ícones e mesmas cores. Com uma lista em cada tela, um dia elas divergiriam —
 * um Excel verde num canto e azul no outro, ou um formato novo que aparece só
 * numa delas.
 *
 * As cores são as de sinalização do DS (perigo, ok, info): não é decoração, é o
 * mesmo par cor↔formato em toda a Retaguarda, para a pessoa reconhecer o botão
 * antes de ler o rótulo.
 */

export type FormatoExportacao = 'pdf' | 'xlsx' | 'docx';

export interface FormatoDeDocumento {
    chave: FormatoExportacao;
    rotulo: string;
    Icone: LucideIcon;
    cor: string;
}

export const FORMATOS_DE_DOCUMENTO: FormatoDeDocumento[] = [
    { chave: 'pdf', rotulo: 'PDF', Icone: FileText, cor: '#b3261e' },
    { chave: 'xlsx', rotulo: 'EXCEL', Icone: FileSpreadsheet, cor: '#0f7a52' },
    { chave: 'docx', rotulo: 'WORD', Icone: FileType2, cor: '#0b6f8c' },
];
