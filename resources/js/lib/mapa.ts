import 'leaflet/dist/leaflet.css';
import * as L from 'leaflet';

/* ============================================================================
   O MAPA da Retaguarda — a base que as duas telas de mapa compartilham.
   ----------------------------------------------------------------------------
   Um lugar só cria a camada de imagens, o pino e a camada de calor. Espalhar
   isso pelas telas faria cada uma escolher um zoom, um tom de pino e um
   provedor de imagens diferente — e a chefia veria dois mapas em vez de um.

   É deliberadamente o MESMO caminho do aplicativo do fiscal
   (`resources/js/pwa/mapa.ts`): mesmo provedor de imagens, mesmo plugin de
   calor, mesmo gradiente. Quem olha a cidade no telefone e depois na Retaguarda
   está olhando a mesma cidade — o que muda é a escala e a pergunta, não a
   linguagem do mapa.
   ============================================================================ */

/* O `leaflet.heat` é plugin de navegador da época do `<script>`: ele espera o
   `L` no objeto global e não exporta nada. Publicar o `L` aqui, ANTES de o
   plugin ser carregado (o que só acontece sob demanda, lá embaixo), é o que faz
   ele funcionar dentro de um pacote moderno. */
(window as unknown as { L: typeof L }).L = L;

export const TILES_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
export const TILES_CREDITO = '© OpenStreetMap';

/** A Praça Municipal, no Centro Histórico — o centro do mapa da cidade. */
export const CENTRO_SALVADOR: [number, number] = [-12.973, -38.514];

/**
 * O gradiente da camada de calor. O MESMO do aplicativo do fiscal: azul para
 * pouco, vermelho para muito. Duas escalas diferentes para a mesma informação
 * fariam a chefia e o fiscal discordarem sobre o que é "quente".
 */
export const GRADIENTE_CALOR = {
    0.2: '#2b83ba',
    0.4: '#7fcdbb',
    0.6: '#ffffbf',
    0.8: '#fdae61',
    1.0: '#d7191c',
};

export function criarMapa(
    elemento: HTMLElement,
    centro: [number, number],
    zoom: number,
    opcoes: { controles?: boolean } = {},
): L.Map {
    const mapa = L.map(elemento, {
        center: centro,
        zoom,
        zoomControl: false,
        attributionControl: true,
        /* O mapa é o FUNDO da tela imersiva, e o painel de vidro fica em cima
           dele. Rolar com a roda sobre o painel não pode aproximar a cidade por
           trás — mas rolar sobre o mapa deve. O Leaflet resolve isso sozinho
           (o evento não atravessa o painel), então a roda continua ligada. */
        scrollWheelZoom: true,
    });

    L.tileLayer(TILES_URL, { maxZoom: 19, minZoom: 10, attribution: TILES_CREDITO }).addTo(mapa);

    if (opcoes.controles !== false) {
        L.control.zoom({ position: 'bottomright' }).addTo(mapa);
    }

    /* O mapa não descobre sozinho que o contêiner mudou de tamanho — e ele muda:
       a doca do menu retrai, a janela é redimensionada, o painel lateral abre.
       Sem isto, sobra faixa vazia onde deveria haver rua. */
    const observador = new ResizeObserver(() => mapa.invalidateSize());
    observador.observe(elemento);
    mapa.on('unload', () => observador.disconnect());

    return mapa;
}

/** O que um pino do Mapa ao Vivo pode ser — cada um com a sua cor e o seu pulso. */
export type TipoDePino = 'regular' | 'irregular' | 'retorno' | 'hoje' | 'fiscal';

/**
 * Pino do mapa: um ponto luminoso com anel, como no mockup imersivo aprovado.
 *
 * Não é a gota do aplicativo do fiscal, e a diferença é de propósito: lá cada
 * pino é UM ponto que se vai visitar, aqui são dezenas vistos de cima, e a gota
 * com emoji dentro viraria confete em escala de cidade. O que a Retaguarda
 * precisa ler de longe é a COR (o que é) e o PULSO (o que está fora do
 * esperado) — a identidade do ponto aparece no cartão, ao clicar.
 */
export function pinoDaCidade(tipo: TipoDePino, selo?: string): L.DivIcon {
    const etiqueta = selo ? `<span class="rt-pino-selo">${selo}</span>` : '';

    /* A CAIXA do pino (26px) é bem maior que o ponto luminoso (10px), e isso é
       deliberado: o ponto é pequeno para a cidade não virar confete, mas alvo de
       10px é alvo que se erra — sobretudo em telefone. A caixa é o que se
       clica; o ponto é o que se vê. */
    return L.divIcon({
        className: '',
        html: `<div class="rt-pino rt-pino-${tipo}"><i></i></div>${etiqueta}`,
        iconSize: [26, 26],
        iconAnchor: [13, 13],
    });
}

export type CamadaCalor = L.Layer & {
    setLatLngs(pontos: [number, number, number][]): void;
};

type FabricaDeCalor = (
    pontos: [number, number, number][],
    opcoes: Record<string, unknown>,
) => CamadaCalor;

/**
 * Carrega o plugin de calor SOB DEMANDA: ele só é baixado por quem abre o mapa
 * de calor, e não por toda pessoa que abre a Retaguarda.
 */
export async function camadaDeCalor(
    pontos: [number, number, number][],
    opcoes: Record<string, unknown>,
): Promise<CamadaCalor> {
    await import('leaflet.heat');

    const fabrica = (L as unknown as { heatLayer: FabricaDeCalor }).heatLayer;

    return fabrica(pontos, opcoes);
}
