import { directionsUrl } from './map-links';
import loadMaplibre, { MAP_FONT, MAP_STYLE } from './maplibre-loader';

/**
 * Mapa de gasolineras: la marca de cada una y su precio, escritos encima.
 *
 * La idea es la de Gasall: se lee de un vistazo sin tocar nada. Eso descarta
 * los marcadores de HTML —serían cientos de nodos pisándose— y pide una capa de
 * símbolos, donde MapLibre resuelve él mismo las colisiones, esconde lo que no
 * cabe y va sacando más al acercar el zoom.
 *
 * Las insignias de marca se dibujan aquí, en un canvas, y se registran en el
 * mapa como imágenes. No son los logotipos de las petroleras: son marcas
 * propias con las iniciales y un color aproximado al corporativo. Si algún día
 * hay ficheros de logotipo con licencia acreditada, el único cambio es sustituir
 * badge() por una carga de imagen: el resto ya funciona por clave de marca.
 */
export default (config = {}) => ({
    visible: false,
    loaded: false,
    loading: false,
    failed: false,
    expanded: false,
    chosen: null,
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
            const { Map, NavigationControl, LngLatBounds } = await loadMaplibre();

            this.draw({ Map, NavigationControl, LngLatBounds });
            this.loaded = true;
        } catch {
            this.failed = true;
            this.visible = false;
        } finally {
            this.loading = false;
        }
    },

    /** Pantalla completa por CSS y no con la API del navegador: en iPhone esa API no existe. */
    async toggleExpand() {
        this.expanded = ! this.expanded;
        document.body.classList.toggle('overflow-hidden', this.expanded);

        await this.$nextTick();
        this.map?.resize();
    },

    close() {
        if (this.expanded) {
            this.toggleExpand();
        }
    },

    draw({ Map, NavigationControl, LngLatBounds }) {
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
            fitBoundsOptions: { padding: 48, maxZoom: 14 },
        });

        this.map = map;
        map.addControl(new NavigationControl({ showCompass: false }), 'top-right');

        // Una tesela suelta que falle no invalida el mapa: sólo se deja rastro.
        map.on('error', (event) => console.warn('Mapa de gasolineras:', event.error?.message ?? event));

        const paint = () => {
            // Una insignia por marca, no por gasolinera: en una zona con
            // cuarenta Repsol se dibuja una sola imagen.
            for (const brand of this.uniqueBrands(stations)) {
                if (! map.hasImage(brand.key)) {
                    map.addImage(brand.key, this.badge(brand), { pixelRatio: 2 });
                }

                /*
                 * Si hay logotipo puesto, sustituye a la insignia de iniciales
                 * en cuanto llegue. No se espera a que cargue: así el mapa sale
                 * ya, y si el fichero falta o está mal la insignia se queda y
                 * no se rompe nada.
                 */
                if (brand.logo) {
                    this.loadLogo(brand)
                        .then((image) => map.updateImage(brand.key, image))
                        .catch(() => console.warn('Logotipo no cargado:', brand.logo));
                }
            }

            map.addSource('gasolineras', {
                type: 'geojson',
                data: {
                    type: 'FeatureCollection',
                    features: stations.map((s, index) => ({
                        type: 'Feature',
                        geometry: { type: 'Point', coordinates: [s.lon, s.lat] },
                        // Planas a propósito: MapLibre convierte a texto
                        // cualquier propiedad que no sea un valor simple, así
                        // que un objeto anidado llegaría inservible.
                        properties: {
                            index,
                            brandKey: s.brand.key,
                            price: s.price,
                            tier: s.tier,
                        },
                    })),
                },
            });

            /*
             * El color del precio va por tercios de posición en el ranking, no
             * por rango: una gasolinera disparatada arrastraría a las demás al
             * nivel «barata». El nivel lo calcula el servidor.
             *
             * Y el color no es la información: el número está escrito ahí
             * mismo, así que quien no distinga verde de rojo lee lo mismo.
             */
            const byTier = [
                'match', ['get', 'tier'],
                0, '#15803d',   // credit-700: de las baratas
                2, '#b91c1c',   // debt-700: de las caras
                '#404040',      // neutral-700: en la media
            ];

            map.addLayer({
                id: 'gasolineras',
                type: 'symbol',
                source: 'gasolineras',
                layout: {
                    'icon-image': ['get', 'brandKey'],
                    'icon-anchor': 'bottom',
                    'icon-allow-overlap': false,
                    'text-field': ['get', 'price'],
                    'text-font': MAP_FONT,
                    'text-size': 12,
                    'text-anchor': 'top',
                    'text-offset': [0, 0.25],
                    // El icono y el precio son un solo símbolo: o entran los
                    // dos o no entra ninguno, y nunca se separan.
                    'text-optional': false,
                },
                paint: {
                    'text-color': byTier,
                    'text-halo-color': '#ffffff',
                    'text-halo-width': 1.6,
                },
            });

            map.on('click', 'gasolineras', (event) => {
                this.chosen = stations[event.features[0].properties.index] ?? null;
            });

            // Tocar el mapa fuera de una gasolinera cierra la ficha
            map.on('click', (event) => {
                if (map.queryRenderedFeatures(event.point, { layers: ['gasolineras'] }).length === 0) {
                    this.chosen = null;
                }
            });

            map.on('mouseenter', 'gasolineras', () => { map.getCanvas().style.cursor = 'pointer'; });
            map.on('mouseleave', 'gasolineras', () => { map.getCanvas().style.cursor = ''; });
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

    /** @returns las marcas distintas que hay en la zona */
    uniqueBrands(stations) {
        const brands = new Map();

        for (const s of stations) {
            brands.set(s.brand.key, s.brand);
        }

        return [...brands.values()];
    },

    /** Carga el logotipo de una marca y lo devuelve ya montado en su insignia. */
    loadLogo(brand) {
        return new Promise((resolve, reject) => {
            const image = new Image();

            image.onload = () => resolve(this.badge(brand, image));
            image.onerror = reject;
            image.src = brand.logo;
        });
    },

    /**
     * La insignia de una marca, dibujada en un canvas.
     *
     * Con logotipo: fondo blanco y el logotipo encajado dentro. Sin logotipo:
     * el color de la marca y sus iniciales.
     *
     * A doble resolución y registrada con pixelRatio 2, que es lo que la deja
     * nítida en una pantalla de móvil.
     */
    badge(brand, logo = null) {
        const ratio = 2;
        const side = 30 * ratio;
        const inset = 2 * ratio;

        const canvas = document.createElement('canvas');
        canvas.width = side;
        canvas.height = side;

        const ctx = canvas.getContext('2d');

        ctx.beginPath();

        if (typeof ctx.roundRect === 'function') {
            ctx.roundRect(inset, inset, side - inset * 2, side - inset * 2, 8 * ratio);
        } else {
            // Safari antiguo no tiene roundRect; un cuadrado se lee igual
            ctx.rect(inset, inset, side - inset * 2, side - inset * 2);
        }

        // Con logotipo el fondo va blanco: un logotipo puede ser de cualquier
        // color y sobre el color de la marca podría no verse.
        ctx.fillStyle = logo ? '#ffffff' : brand.bg;
        ctx.fill();

        // El borde es lo que mantiene la insignia legible sobre cualquier parte
        // del mapa, igual que el halo del precio.
        ctx.lineWidth = 2 * ratio;
        ctx.strokeStyle = logo ? brand.bg : '#ffffff';
        ctx.stroke();

        if (logo) {
            // Encajado sin deformarlo. Un SVG sin tamaño propio declara 0, y de
            // ahí el respaldo.
            const pad = 4 * ratio;
            const box = side - inset * 2 - pad * 2;
            const width = logo.naturalWidth || logo.width || box;
            const height = logo.naturalHeight || logo.height || box;
            const scale = Math.min(box / width, box / height);

            ctx.drawImage(
                logo,
                (side - width * scale) / 2,
                (side - height * scale) / 2,
                width * scale,
                height * scale,
            );
        } else {
            ctx.fillStyle = brand.ink;
            ctx.font = `700 ${(brand.short.length > 2 ? 10 : 13) * ratio}px system-ui, -apple-system, sans-serif`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(brand.short, side / 2, side / 2 + ratio);
        }

        return {
            width: side,
            height: side,
            data: ctx.getImageData(0, 0, side, side).data,
        };
    },

    /** La misma decisión que en el listado: Mapas en Apple, Google en el resto. */
    directionsFor(station) {
        return station ? directionsUrl(station.lat, station.lon) : '#';
    },
});
