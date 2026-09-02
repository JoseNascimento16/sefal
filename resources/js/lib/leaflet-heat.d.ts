/**
 * O `leaflet.heat` é um plugin de navegador antigo: não publica tipos e não
 * exporta nada — ele pendura `heatLayer` no `L` global. A declaração abaixo
 * apenas autoriza o `import`; quem dá forma ao que ele devolve é o `mapa.ts`,
 * que é também quem garante o `L` global antes de carregá-lo.
 *
 * É a mesma declaração que o aplicativo do fiscal tem
 * (`resources/js/pwa/leaflet-heat.d.ts`): o plugin é o mesmo, e o mapa de calor
 * dos dois lados usa a mesma camada.
 */
declare module 'leaflet.heat';
