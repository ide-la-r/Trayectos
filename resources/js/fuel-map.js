import { directionsUrl } from './map-links';
import loadMaplibre, { MAP_STYLE } from './maplibre-loader';

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
            /*
             * Un marcador entero por combinación de marca, precio y nivel: la
             * burbuja lleva el precio dentro, así que su ancho depende del
             * texto y no se puede reutilizar una sola imagen para todas. Dos
             * gasolineras de la misma marca al mismo precio comparten imagen.
             */
            for (const station of stations) {
                station.marker = `${station.brand.key}|${station.price}|${station.tier}`;

                if (! map.hasImage(station.marker)) {
                    map.addImage(station.marker, this.bubble(station), { pixelRatio: 2 });
                }
            }

            /*
             * Si hay logotipo puesto, se rehacen las burbujas de esa marca en
             * cuanto llegue. No se espera a que cargue: así el mapa sale ya, y
             * si el fichero falta o está mal se quedan las iniciales y no se
             * rompe nada. Las medidas no cambian —dependen del precio, no del
             * logotipo—, que es lo que exige updateImage.
             */
            for (const brand of this.uniqueBrands(stations)) {
                if (! brand.logo) {
                    continue;
                }

                this.loadLogo(brand)
                    .then((logo) => {
                        for (const station of stations.filter((s) => s.brand.key === brand.key)) {
                            map.updateImage(station.marker, this.bubble(station, logo));
                        }
                    })
                    .catch(() => console.warn('Logotipo no cargado:', brand.logo));
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
                        properties: { index, marker: s.marker },
                    })),
                },
            });

            // La burbuja ya lleva el precio dentro, así que no hay capa de
            // texto: un solo símbolo por gasolinera, y MapLibre esconde los
            // que se pisan igual que antes.
            map.addLayer({
                id: 'gasolineras',
                type: 'symbol',
                source: 'gasolineras',
                layout: {
                    'icon-image': ['get', 'marker'],
                    'icon-anchor': 'bottom',
                    'icon-allow-overlap': false,
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

    loadLogo(brand) {
        return new Promise((resolve, reject) => {
            const image = new Image();

            image.onload = () => resolve(image);
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
    bubble(station, logo = null) {
        const ratio = 2;
        const h = 32 * ratio;
        const tip = 7 * ratio;         // pico que señala el sitio exacto
        const dot = 24 * ratio;        // círculo de la marca
        const padL = 4 * ratio;
        const gap = 6 * ratio;
        const padR = 10 * ratio;
        const pad = 3 * ratio;         // aire para que la sombra no se corte
        const font = `600 ${13 * ratio}px system-ui, -apple-system, sans-serif`;

        // El ancho depende del precio, así que hay que medirlo antes de saber
        // de qué tamaño es el lienzo.
        const ruler = document.createElement('canvas').getContext('2d');
        ruler.font = font;
        const textWidth = Math.ceil(ruler.measureText(station.price).width);

        const w = padL + dot + gap + textWidth + padR;

        const canvas = document.createElement('canvas');
        canvas.width = w + pad * 2;
        canvas.height = h + tip + pad;

        const ctx = canvas.getContext('2d');

        const left = pad;
        const right = pad + w;
        const top = pad;
        const bottom = pad + h;
        const r = h / 2;
        const mid = pad + w / 2;

        // Pastilla con el pico abajo, en el centro para que icon-anchor
        // «bottom» lo clave en la coordenada sin tener que compensar nada.
        ctx.beginPath();
        ctx.moveTo(left + r, top);
        ctx.lineTo(right - r, top);
        ctx.arc(right - r, top + r, r, -Math.PI / 2, Math.PI / 2);
        ctx.lineTo(mid + tip * 0.62, bottom);
        ctx.lineTo(mid, bottom + tip);
        ctx.lineTo(mid - tip * 0.62, bottom);
        ctx.lineTo(left + r, bottom);
        ctx.arc(left + r, top + r, r, Math.PI / 2, -Math.PI / 2);
        ctx.closePath();

        ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
        ctx.shadowBlur = 3 * ratio;
        ctx.shadowOffsetY = 1 * ratio;
        ctx.fillStyle = '#ffffff';
        ctx.fill();

        // Se apaga la sombra antes del borde y del contenido: si no, la heredan
        // y sale todo emborronado.
        ctx.shadowColor = 'transparent';
        ctx.shadowBlur = 0;
        ctx.shadowOffsetY = 0;

        /*
         * El nivel de precio va en el BORDE y no en el número, que es lo que
         * hace legible la burbuja: verde de las baratas, ámbar en la media,
         * rojo de las caras. Y sigue sin ser la información —el precio está
         * escrito dentro— así que quien no distinga los colores lee lo mismo.
         */
        ctx.lineWidth = 2 * ratio;
        ctx.strokeStyle = { 0: '#15803d', 2: '#b91c1c' }[station.tier] ?? '#d97706';
        ctx.stroke();

        // ── Círculo de la marca ─────────────────────────────────────────────
        const cx = left + padL + dot / 2;
        const cy = top + h / 2;

        ctx.save();
        ctx.beginPath();
        ctx.arc(cx, cy, dot / 2, 0, Math.PI * 2);
        ctx.fillStyle = logo ? '#ffffff' : station.brand.bg;
        ctx.fill();
        ctx.clip();

        if (logo) {
            // Encajado sin deformarlo. Un SVG sin tamaño propio declara 0, y de
            // ahí el respaldo.
            const box = dot - 2 * ratio;
            const iw = logo.naturalWidth || logo.width || box;
            const ih = logo.naturalHeight || logo.height || box;
            const scale = Math.min(box / iw, box / ih);

            ctx.drawImage(logo, cx - (iw * scale) / 2, cy - (ih * scale) / 2, iw * scale, ih * scale);
        } else {
            ctx.fillStyle = station.brand.ink;
            ctx.font = `700 ${(station.brand.short.length > 2 ? 9 : 11) * ratio}px system-ui, -apple-system, sans-serif`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(station.brand.short, cx, cy + ratio * 0.5);
        }

        ctx.restore();

        // ── Precio ──────────────────────────────────────────────────────────
        ctx.fillStyle = '#171717';
        ctx.font = font;
        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';
        ctx.fillText(station.price, left + padL + dot + gap, cy + ratio * 0.5);

        return {
            width: canvas.width,
            height: canvas.height,
            data: ctx.getImageData(0, 0, canvas.width, canvas.height).data,
        };
    },

    /** La misma decisión que en el listado: Mapas en Apple, Google en el resto. */
    directionsFor(station) {
        return station ? directionsUrl(station.lat, station.lon) : '#';
    },
});
