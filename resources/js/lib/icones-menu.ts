import type { LucideIcon } from 'lucide-react';
import {
    CircleDot,
    ClipboardCheck,
    FileBarChart,
    KeyRound,
    LayoutGrid,
    Map,
    ScrollText,
    Store,
    UserRound,
    Users,
} from 'lucide-react';

/**
 * Ponte entre a CHAVE de ícone que vem do servidor (`config/retaguarda_menu.php`)
 * e o componente que a desenha.
 *
 * O servidor manda uma palavra, não um componente: a configuração do menu é PHP e
 * não pode importar React. Chave desconhecida cai no ícone neutro — um item novo
 * no menu nunca deixa a barra sem desenhar por causa de um nome errado.
 */
const ICONES: Record<string, LucideIcon> = {
    inicio: LayoutGrid,
    perfil: UserRound,
    permissionarios: Store,
    fiscalizacoes: ClipboardCheck,
    areas: Map,
    usuarios: Users,
    documentos: ScrollText,
    relatorios: FileBarChart,
    permissoes: KeyRound,
    padrao: CircleDot,
};

export function iconeDoMenu(chave: string): LucideIcon {
    return ICONES[chave] ?? ICONES.padrao;
}
