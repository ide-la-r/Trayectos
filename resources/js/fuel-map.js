import { directionsUrl } from './map-links';
import loadMaplibre, { MAP_FONT, MAP_STYLE } from './maplibre-loader';

/**
 * Mapa de gasolineras con el precio escrito encima.
 *
 * La idea es la de Gasall: el precio se lee de un vistazo, sin tocar ningún
 * punto. Eso descarta los marcadores de HTML —serían cientos de nodos que se
 * pisan unos a otros— y pide una capa de símbolos: MapLibre resuelve él mismo
 * las colisiones, esconde las etiquetas que no caben y va sacando más a medida
 * que se acerca el zoom. Es exactamente el comportamiento que se quiere y sale
 * gratis.
 *
 * Igual que el mapa del viaje, no se descarga nada hasta que alguien lo pide.
 */
export default (config = {}) => ({
    visible: false,
    loaded: false,
    loading: false,
    failed: false,
    map: null,

    async show() {
        if (this.loaded || this.loading) {
            return;
        }

        this.loading = true;
        this.failed = false;

        // El contenedor tiene que ocupar sitio ANTES de crear el mapa: si
        // MapLibre nace dentro de un display:none se dibuja a 0x0 y se queda
        // así. De ahí el nextTick.
        this.visible = true;
        await this.$nextTick();

        try {
            const { Map, NavigationControl, Popup, LngLatBounds } = await loadMaplibre();

            this.draw({ Map, NavigationControl, Popup, LngLatBounds });
            this.loaded = true;
        } catch {
            this.failed = true;
            this.visible = false;
        } finally {
            this.loading = false;
        }
    },

    draw({ Map, NavigationControl, Popup, LngLatBounds }) {
        const stations = config.stations ?? [];

        if (stations.length === 0) {
            this.failed = true;
            this.visible = false;

            return;
        }

        const bounds = stations.reduce(
            (box, s) => box.extend([s.lon, s.lat]),
            new LngLatBounds([stations[0].lon, stations[0].lat], [stations[0].lon, stations[0].lat]),
        );

        const map = new Map({
            container: this.$refs.canvas,
            style: MAP_STYLE,
            bounds,
            fitBoundsOptions: { padding: 40, maxZoom: 14 },
        });

        this.map = map;
        map.addControl(new NavigationControl({ showCompass: false }), 'top-right');

        // Una tesela suelta que falle no invalida el mapa: sólo se deja rastro.
        map.on('error', (event) => console.warn('Mapa de gasolineras:', event.error?.message ?? event));

        const paint = () => {
            map.addSource('gasolineras', {
                type: 'geojson',
                data: {
                    type: 'FeatureCollection',
                    features: stations.map((s) => ({
                        type: 'Feature',
                        geometry: { type: 'Point', coordinates: [s.lon, s.lat] },
                        properties: s,
                    })),
                },
            });

            /*
             * El color va por tercios de posición en el ranking, no por rango de
             * precio: una gasolinera disparatada arrastraría a todas las demás
             * al nivel «barata». El nivel lo calcula el servidor.
             *
             * Y el color nunca es la información: el precio está escrito al
             * lado, así que quien no distinga verde de rojo lee lo mismo.
             */
            const porNivel = [
                'match', ['get', 'tier'],
                0, '#15803d',   // credit-700: de las baratas
                2, '#b91c1c',   // debt-700: de las caras
                '#404040',      // neutral-700: en la media
            ];

            map.addLayer({
                id: 'gasolineras-punto',
                type: 'circle',
                source: 'gasolineras',
                paint: {
                    'circle-radius': 5,
                    'circle-color': porNivel,
                    'circle-stroke-width': 2,
                    'circle-stroke-color': '#ffffff',
                },
            });

            map.addLayer({
                id: 'gasolineras-precio',
                type: 'symbol',
                source: 'gasolineras',
                layout: {
                    'text-field': ['get', 'price'],
                    'text-font': MAP_FONT,
                    'text-size': 12,
                    'text-offset': [0, -1.1],
                    'text-anchor': 'bottom',
                    // Sin allow-overlap: es lo que hace que MapLibre esconda las
                    // que se pisan y saque mas al acercar el zoom.
                    'text-ignore-placement': false,
                },
                paint: {
                    'text-color': porNivel,
                    'text-halo-color': '#ffffff',
                    'text-halo-width': 1.6,
                },
            });

            map.on('click', 'gasolineras-punto', (event) => {
                const s = event.features[0].properties;

                new Popup({ offset: 12, closeButton: false })
                    .setLngLat([s.lon, s.lat])
                    .setDOMContent(this.card(s))
                    .addTo(map);
            });

            for (const capa of ['gasolineras-punto', 'gasolineras-precio']) {
                map.on('mouseenter', capa, () => { map.getCanvas().style.cursor = 'pointer'; });
                map.on('mouseleave', capa, () => { map.getCanvas().style.cursor = ''; });
            }
        };

        /*
         * «style.load» y no «load»: load espera al primer pintado completo, y
         * eso no ocurre mientras la pestaña está en segundo plano, porque el
         * navegador no ejecuta requestAnimationFrame. Las gasolineras se
         * quedaban sin pintar hasta volver a la pestaña.
         */
        if (map.isStyleLoaded()) {
            paint();
        } else {
            map.once('style.load', paint);
        }
    },

    /**
     * El contenido de la burbuja, construido con nodos y textContent en vez de
     * con una cadena de HTML: los rótulos y las direcciones vienen de la API
     * del Ministerio y no hay ninguna razón para dejarlos entrar como HTML.
     */
    card(station) {
        const card = document.createElement('div');
        card.className = 'space-y-0.5';

        const line = (text, className) => {
            if (! text) {
                return;
            }

            const el = document.createElement('p');
            el.className = className;
            el.textContent = text;
            card.appendChild(el);
        };

        line(`${station.price} €/${station.unit}`, 'text-sm font-semibold text-neutral-900');
        line(station.label, 'text-xs font-medium text-neutral-800');
        line([station.municipality, station.address].filter(Boolean).join(' · '), 'text-xs text-neutral-500');
        line(station.distance ? `a ${station.distance} km` : null, 'text-xs text-neutral-500');

        const link = document.createElement('a');
        // La misma decisión que en el listado: Mapas en los aparatos de Apple,
        // Google Maps en el resto.
        link.href = directionsUrl(station.lat, station.lon);
        link.target = '_blank';
        link.rel = 'noopener';
        link.className = 'mt-1 inline-block text-xs font-medium text-neutral-900 underline underline-offset-2';
        link.textContent = 'Cómo llegar';
        card.appendChild(link);

        return card;
    },
});
