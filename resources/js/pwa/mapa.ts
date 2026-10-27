import * as L from 'leaflet';

/* ============================================================================
   Mapa — a base que as três telas com mapa compartilham.
   ----------------------------------------------------------------------------
   Um lugar só cria camada de imagens, pino e camada de calor. Espalhar isso
   pelas telas faria cada uma escolher um zoom, um tom de pino e um provedor de
   imagens diferente — e o fiscal veria três mapas em vez de um.
   ============================================================================ */

/* O `leaflet.heat` é plugin de navegador da época do `<script>`: ele espera o
   `L` no objeto global e não exporta nada. Publicar o `L` aqui, ANTES de o
   plugin ser carregado (o que só acontece sob demanda, lá embaixo), é o que faz
   ele funcionar dentro de um pacote moderno. */
(window as unknown as { L: typeof L }).L = L;

export const TILES_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
export const TILES_CREDITO = '© OpenStreetMap';

export function criarMapa(
    elemento: HTMLElement,
    centro: [number, number],
    zoom: number,
    /** A prévia da tela inicial não tem controles: ela é um convite, não um mapa. */
    opcoes: { controles?: boolean } = {},
): L.Map {
    const mapa = L.map(elemento, {
        center: centro,
        zoom,
        zoomControl: false,
        attributionControl: true,
    });

    L.tileLayer(TILES_URL, { maxZoom: 19, attribution: TILES_CREDITO }).addTo(mapa);

    if (opcoes.controles !== false) {
        L.control.zoom({ position: 'bottomleft' }).addTo(mapa);
    }

    /* O mapa não descobre sozinho que o contêiner mudou de tamanho — e ele muda
       o tempo todo em campo: o aparelho gira, o teclado sobe, o tablet abre o
       painel lateral. Sem isto, sobra faixa cinza onde deveria haver rua. */
    const observador = new ResizeObserver(() => mapa.invalidateSize());
    observador.observe(elemento);
    mapa.on('unload', () => observador.disconnect());

    return mapa;
}

/** Pino em forma de gota, com o emoji da atividade dentro. */
export function pinoAmbulante(
    emoji: string,
    tipo: 'regular' | 'irregular' | 'retorno',
    selo?: string,
    /** Fora da área da equipe: o pino fica apagado, para o recorte ser óbvio. */
    fora?: boolean,
): L.DivIcon {
    const etiqueta = selo ? `<span class="pw-selo-retorno">${selo}</span>` : '';
    const apagado = fora ? ' pw-pino-fora' : '';

    return L.divIcon({
        className: '',
        html: `<div class="pw-pino pw-pino-${tipo}${apagado}"><span>${emoji}</span></div>${etiqueta}`,
        iconSize: [34, 34],
        iconAnchor: [17, 34],
    });
}

export const pinoDoFiscal = (): L.DivIcon =>
    L.divIcon({ className: '', html: '<div class="pw-pino-eu"></div>', iconSize: [22, 22], iconAnchor: [11, 11] });

/* ============================ Área da equipe ============================ */

/**
 * O ponto está dentro do anel?
 *
 * Lançamento de raio, o algoritmo clássico: conta quantas arestas do anel um
 * raio horizontal cruza à esquerda do ponto — ímpar é dentro, par é fora.
 * Serve para APAGAR o pino que cai fora da área da equipe, e para nada mais: é
 * uma decisão de desenho, não de competência. O contorno é aproximado, então
 * quem estiver na fronteira pode cair para qualquer lado — e é por isso que o
 * pino de fora fica apagado, e não escondido.
 */
export function dentroDoAnel(lat: number, lng: number, anel: [number, number][]): boolean {
    let dentro = false;

    for (let i = 0, j = anel.length - 1; i < anel.length; j = i++) {
        const [latI, lngI] = anel[i];
        const [latJ, lngJ] = anel[j];

        const cruza =
            latI > lat !== latJ > lat &&
            lng < ((lngJ - lngI) * (lat - latI)) / (latJ - latI) + lngI;

        if (cruza) {
            dentro = !dentro;
        }
    }

    return dentro;
}

/** A cor da marca, lida do tema em uso — no escuro ela clareia. */
const azulDaMarca = (): string =>
    getComputedStyle(document.documentElement).getPropertyValue('--pw-acao').trim() || '#0066b2';

/**
 * A delimitação da área da equipe, como uma camada só.
 *
 * Uma camada e não vários desenhos soltos: assim o interruptor da tela a
 * acende e apaga inteira, sem precisar saber quantos polígonos, linhas e
 * rótulos ela tem por dentro.
 *
 * ⚠️ O contorno é APROXIMADO — desenhado à mão a partir do bloco de bairros da
 * parametrização, não de limite oficial de bairro. A legenda da tela diz isso
 * ao fiscal, porque um contorno no mapa parece exato mesmo quando não é.
 */
export function camadaDaArea(opcoes: {
    contorno: [number, number][] | null;
    corredores: { nome: string; linha: [number, number][] }[] | null;
    rotulo: string;
}): L.LayerGroup {
    const azul = azulDaMarca();
    const camada = L.layerGroup();

    if (opcoes.contorno) {
        L.polygon(opcoes.contorno, {
            color: azul,
            weight: 3,
            opacity: 0.9,
            /* Tracejado: contorno cheio se confunde com rua ou com limite
               oficial, e este é aproximado. */
            dashArray: '9 6',
            fillColor: azul,
            fillOpacity: 0.08,
            interactive: false,
        }).addTo(camada);

        L.marker(centroDoAnel(opcoes.contorno), {
            icon: L.divIcon({
                className: '',
                html: `<span class="pw-rotulo-area">${opcoes.rotulo}</span>`,
                iconSize: [0, 0],
            }),
            interactive: false,
            zIndexOffset: -100,
        }).addTo(camada);
    }

    for (const corredor of opcoes.corredores ?? []) {
        L.polyline(corredor.linha, {
            color: azul,
            weight: 8,
            opacity: 0.45,
            lineCap: 'round',
            interactive: false,
        }).addTo(camada);

        L.marker(corredor.linha[Math.floor(corredor.linha.length / 2)], {
            icon: L.divIcon({
                className: '',
                html: `<span class="pw-rotulo-area">${corredor.nome}</span>`,
                iconSize: [0, 0],
            }),
            interactive: false,
            zIndexOffset: -100,
        }).addTo(camada);
    }

    return camada;
}

/** O meio do anel, para pendurar o rótulo — média simples, basta. */
const centroDoAnel = (anel: [number, number][]): [number, number] => [
    anel.reduce((soma, p) => soma + p[0], 0) / anel.length,
    anel.reduce((soma, p) => soma + p[1], 0) / anel.length,
];

export type CamadaCalor = L.Layer & {
    setLatLngs(pontos: [number, number, number][]): void;
};

type FabricaDeCalor = (
    pontos: [number, number, number][],
    opcoes: Record<string, unknown>,
) => CamadaCalor;

/**
 * Carrega o plugin de calor sob demanda: ele só é baixado por quem abre a tela
 * do mapa de calor, e não por todo fiscal que abre o aplicativo para registrar.
 */
export async function camadaDeCalor(
    pontos: [number, number, number][],
    opcoes: Record<string, unknown>,
): Promise<CamadaCalor> {
    await import('leaflet.heat');

    const fabrica = (L as unknown as { heatLayer: FabricaDeCalor }).heatLayer;

    return fabrica(pontos, opcoes);
}
