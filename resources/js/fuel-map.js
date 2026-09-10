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
            /*
             * Los logotipos se cargan A LA VEZ que MapLibre y antes de dibujar,
             * no después. Son unos kilobytes contra los 274 de la librería, así
             * que en tiempo salen gratis, y así cada burbuja se genera ya con
             * su logotipo: sustituirla después no vale, porque el ancho de la
             * burbuja depende del logotipo y updateImage exige el mismo tamaño.
             */
            const [{ Map, NavigationControl, LngLatBounds }, logos] = await Promise.all([
                loadMaplibre(),
                this.loadLogos(),
            ]);

            this.draw({ Map, NavigationControl, LngLatBounds }, logos);
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

        // En <html> y NO en el body: el body ya lleva overflow-hidden como
        // pieza del armazón móvil, y quitárselo al cerrar el mapa dejaba el
        // documento desplazable otra vez. En escritorio, además, la clase en el
        // body la pisaba lg:overflow-visible y la página rodaba tras el mapa.
        document.documentElement.classList.toggle('overflow-hidden', this.expanded);

        await this.$nextTick();
        this.map?.resize();
    },

    close() {
        if (this.expanded) {
            this.toggleExpand();
        }
    },

    /**
     * Los logotipos de las marcas que tengan fichero puesto.
     *
     * Una marca sin logotipo, o cuyo fichero falte o esté roto, simplemente no
     * entra en el mapa que se devuelve: su burbuja saldrá con las iniciales.
     *
     * @returns Map de clave de marca a imagen cargada
     */
    async loadLogos() {
        const brands = this.uniqueBrands(config.stations ?? []).filter((b) => b.logo);

        const cargados = await Promise.all(brands.map((brand) => new Promise((resolve) => {
            const image = new Image();

            image.onload = () => resolve([brand.key, image]);
            image.onerror = () => {
                console.warn('Logotipo no cargado:', brand.logo);
                resolve(null);
            };
            image.src = brand.logo;
        })));

        return new window.Map(cargados.filter(Boolean));
    },

    draw({ Map, NavigationControl, LngLatBounds }, logos) {
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
                    map.addImage(
                        station.marker,
                        this.bubble(station, logos.get(station.brand.key)),
                        { pixelRatio: 2 },
                    );
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
        const slotH = 24 * ratio;      // alto del hueco de la marca
        const padL = 4 * ratio;
        const gap = 6 * ratio;
        const padR = 10 * ratio;
        const pad = 3 * ratio;         // aire para que la sombra no se corte
        const font = `600 ${13 * ratio}px system-ui, -apple-system, sans-serif`;

        /*
         * El hueco de la marca se adapta a la forma de su logotipo, porque los
         * de las petroleras casi nunca son un símbolo cuadrado: son el nombre
         * escrito y muy alargados. En un círculo se quedarían en una raya.
         *
         * Y hay un suelo de legibilidad: si al encajarlo su alto no llega a
         * 11 px, no se usa y se vuelve a las iniciales.
         *
         * El ancho máximo son 52 px por un caso concreto: el logotipo oficial
         * de Repsol mide 4,27 a 1 y con 44 se quedaba en 10,3 px de alto, justo
         * por debajo del suelo. Con 52 llega a 12,2 y se lee.
         */
        const maxSlotW = 52 * ratio;
        let slotW = slotH;
        let useLogo = false;

        if (logo) {
            const iw = logo.naturalWidth || logo.width || 1;
            const ih = logo.naturalHeight || logo.height || 1;

            slotW = Math.min(maxSlotW, Math.max(slotH, slotH * (iw / ih)));
            useLogo = Math.min(slotW / iw, slotH / ih) * ih >= 11 * ratio;

            if (! useLogo) {
                slotW = slotH;
            }
        }

        // El ancho depende del precio, así que hay que medirlo antes de saber
        // de qué tamaño es el lienzo.
        const ruler = document.createElement('canvas').getContext('2d');
        ruler.font = font;
        const textWidth = Math.ceil(ruler.measureText(station.price).width);

        const w = padL + slotW + gap + textWidth + padR;

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

        // ── Hueco de la marca ───────────────────────────────────────────────
        const cx = left + padL + slotW / 2;
        const cy = top + h / 2;

        ctx.save();
        ctx.beginPath();

        if (slotW === slotH) {
            // Cuadrado: círculo, que es lo que se lee como insignia de marca
            ctx.arc(cx, cy, slotH / 2, 0, Math.PI * 2);
        } else {
            // Alargado: pastilla, para que el logotipo respire a los lados
            const rr = slotH / 2;
            ctx.moveTo(cx - slotW / 2 + rr, cy - slotH / 2);
            ctx.lineTo(cx + slotW / 2 - rr, cy - slotH / 2);
            ctx.arc(cx + slotW / 2 - rr, cy, rr, -Math.PI / 2, Math.PI / 2);
            ctx.lineTo(cx - slotW / 2 + rr, cy + slotH / 2);
            ctx.arc(cx - slotW / 2 + rr, cy, rr, Math.PI / 2, -Math.PI / 2);
        }

        ctx.closePath();
        // Con logotipo el fondo va blanco: el logotipo puede ser de cualquier
        // color y sobre el de la marca podría no verse.
        ctx.fillStyle = useLogo ? '#ffffff' : station.brand.bg;
        ctx.fill();
        ctx.clip();

        if (useLogo) {
            const iw = logo.naturalWidth || logo.width || slotW;
            const ih = logo.naturalHeight || logo.height || slotH;
            const scale = Math.min(slotW / iw, slotH / ih);

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
        ctx.fillText(station.price, left + padL + slotW + gap, cy + ratio * 0.5);

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
