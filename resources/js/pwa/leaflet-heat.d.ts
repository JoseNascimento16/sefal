/**
 * O `leaflet.heat` é um plugin de navegador antigo: não publica tipos e não
 * exporta nada — ele pendura `heatLayer` no `L` global. A declaração abaixo
 * apenas autoriza o `import`; quem dá forma ao que ele devolve é o
 * `mapa.ts`, que é também quem garante o `L` global antes de carregá-lo.
 */
declare module 'leaflet.heat';
