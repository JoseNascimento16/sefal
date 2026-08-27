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
): L.DivIcon {
    const etiqueta = selo ? `<span class="pw-selo-retorno">${selo}</span>` : '';

    return L.divIcon({
        className: '',
        html: `<div class="pw-pino pw-pino-${tipo}"><span>${emoji}</span></div>${etiqueta}`,
        iconSize: [34, 34],
        iconAnchor: [17, 34],
    });
}

export const pinoDoFiscal = (): L.DivIcon =>
    L.divIcon({ className: '', html: '<div class="pw-pino-eu"></div>', iconSize: [22, 22], iconAnchor: [11, 11] });

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
