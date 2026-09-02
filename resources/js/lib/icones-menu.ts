import type { LucideIcon } from 'lucide-react';
import {
    Activity,
    CircleDot,
    ClipboardCheck,
    FileBarChart,
    Flame,
    Inbox,
    KeyRound,
    LayoutGrid,
    ListChecks,
    Map,
    MapPinned,
    Megaphone,
    Siren,
    ScrollText,
    SlidersHorizontal,
    Store,
    TriangleAlert,
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
    ambulantes: Store,
    fiscalizacoes: ClipboardCheck,
    // A caixa de entrada é a bandeja onde o papel de fora cai: e-Salvador, 156,
    // pedido de licença e ofício chegam ali antes de virar trabalho de rua.
    caixa: Inbox,
    // Denúncia é o cidadão FALANDO de fora para dentro: o megafone. A caixa é a
    // bandeja onde o papel cai; a denúncia chega por integração, sem papel.
    denuncias: Megaphone,
    // Operação é ação planejada de rua — a sirene, não o mapa.
    operacoes: Siren,
    // O mapa ao vivo tem o alfinete: ele mostra ONDE cada coisa está agora.
    mapa: MapPinned,
    // O de calor tem a chama: ele mostra onde a coisa se concentra.
    calor: Flame,
    areas: Map,
    usuarios: Users,
    documentos: ScrollText,
    parametrizacao: SlidersHorizontal,
    relatorios: FileBarChart,
    monitoramento: Activity,
    requisitos: ListChecks,
    logs: TriangleAlert,
    permissoes: KeyRound,
    padrao: CircleDot,
};

export function iconeDoMenu(chave: string): LucideIcon {
    return ICONES[chave] ?? ICONES.padrao;
}
