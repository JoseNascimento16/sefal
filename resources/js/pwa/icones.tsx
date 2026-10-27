/**
 * Ícones do aplicativo de campo — traço grosso, desenho simples.
 *
 * São SVG escritos aqui, e não uma biblioteca: o aplicativo do fiscal roda em
 * rede de celular no meio da rua, e cada quilobyte que não viaja é meio segundo
 * a menos entre abrir e registrar. São trinta desenhos curtos; não vale uma
 * dependência.
 */

type Props = {
    nome: Nome;
    tamanho?: number;
    className?: string;
};

export type Nome =
    | 'casa'
    | 'mapa'
    | 'lista'
    | 'calor'
    | 'nuvem'
    | 'pessoa'
    | 'mais'
    | 'camera'
    | 'microfone'
    | 'voltar'
    | 'alvo'
    | 'relogio'
    | 'certo'
    | 'alerta'
    | 'documento'
    | 'busca'
    | 'lixeira'
    | 'sair'
    | 'lua'
    | 'sol'
    | 'seta'
    | 'atualizar'
    | 'caixa-entrada'
    | 'assinar'
    | 'imprimir'
    | 'pacote'
    | 'equipe'
    | 'prancheta'
    | 'erro';

const TRACOS: Record<Nome, string> = {
    casa: 'M4 10.5 12 4l8 6.5V19a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 19v-8.5Zm5.5 10V13h5v7.5',
    mapa: 'M9 3 3 6v15l6-3 6 3 6-3V3l-6 3-6-3Zm0 0v15m6-12v15',
    lista: 'M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01',
    calor: 'M12 3c2.5 3 4 5.2 4 7.5a4 4 0 0 1-8 0C8 8.2 9.5 6 12 3Zm0 18c4.4 0 8-2 8-4.5M4 16.5C4 19 7.6 21 12 21',
    nuvem: 'M7 18a4 4 0 0 1 .8-7.9 5.5 5.5 0 0 1 10.6 1.4A3.5 3.5 0 0 1 17.5 18H7Zm5-9v6m0 0 2.5-2.5M12 15l-2.5-2.5',
    pessoa: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-8 8a8 8 0 0 1 16 0',
    mais: 'M12 5v14M5 12h14',
    camera: 'M3 8.5A2.5 2.5 0 0 1 5.5 6h1.8l1.3-2h6.8l1.3 2h1.8A2.5 2.5 0 0 1 21 8.5v9A2.5 2.5 0 0 1 18.5 20h-13A2.5 2.5 0 0 1 3 17.5v-9Zm9 9.5a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Z',
    microfone: 'M12 3a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3ZM5 11a7 7 0 0 0 14 0M12 18v3',
    voltar: 'M15 5 8 12l7 7',
    alvo: 'M12 3v3m0 12v3M3 12h3m12 0h3m-6 0a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    relogio: 'M12 7v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    certo: 'm5 13 4.5 4.5L19 7',
    alerta: 'M12 9v4m0 3h.01M10.3 4.3 2.5 18a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0Z',
    documento: 'M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Zm0 0v5h5M9 13h6m-6 4h4',
    busca: 'm21 21-4.6-4.6M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z',
    lixeira: 'M4 7h16M10 11v6m4-6v6M6 7l1 13h10l1-13M9 7V4h6v3',
    sair: 'M15 17v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v2m2 5H9m12 0-3.5-3.5M21 12l-3.5 3.5',
    lua: 'M21 13.5A9 9 0 1 1 10.5 3a7 7 0 0 0 10.5 10.5Z',
    sol: 'M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0-14v2m0 14v2M3 12h2m14 0h2M5.6 5.6 7 7m10 10 1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4',
    seta: 'm9 5 7 7-7 7',
    atualizar: 'M20 11A8 8 0 0 0 6.3 6.3L4 8.5m0 0V4m0 4.5h4.5M4 13a8 8 0 0 0 13.7 4.7L20 15.5m0 0V20m0-4.5h-4.5',
    'caixa-entrada':
        'M3 13h4l1.5 3h7L17 13h4M3 13l2.6-7.3A2 2 0 0 1 7.5 4.4h9a2 2 0 0 1 1.9 1.3L21 13v4.5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V13Z',
    assinar: 'M3 20.5c3.5.6 5.3-.7 5.3-2.4 0-1.4-1-2-1.9-1.4-1.2.8-.7 3 1.6 3 2.6 0 4.2-2.4 5.6-6.1M12.5 12.6 20 5a2 2 0 0 0-2.8-2.8l-7.6 7.6-1 3.7 3.9-.9Z',
    imprimir:
        'M7 8V4h10v4M7 18H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M7 14h10v6H7v-6Z',
    pacote: 'M21 8.5 12 4 3 8.5m18 0v7L12 20l-9-4.5v-7m18 0L12 13 3 8.5M12 13v7M7.5 6.2 16.5 11',
    equipe: 'M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm-7 9a7 7 0 0 1 14 0m1.5-15.6a3.5 3.5 0 0 1 0 6.7M19 20a6.6 6.6 0 0 0-2.2-4.8',
    prancheta:
        'M9 4.5H7a2 2 0 0 0-2 2V19a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6.5a2 2 0 0 0-2-2h-2M9 4.5a1.5 1.5 0 0 1 1.5-1.5h3A1.5 1.5 0 0 1 15 4.5v1H9v-1ZM9 12h6m-6 4h4',
    erro: 'M12 8v5m0 3h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
};

export function Icone({ nome, tamanho = 22, className }: Props) {
    return (
        <svg
            className={className}
            width={tamanho}
            height={tamanho}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <path d={TRACOS[nome]} />
        </svg>
    );
}
