/**
 * Mapa del recorrido de un viaje.
 *
 * MapLibre pesa unos 200 kB comprimidos y las teselas las sirve un tercero, así
 * que nada de esto se descarga al abrir la pantalla: el perfil de altitud, que
 * es lo que explica el coste, se dibuja en el servidor y está siempre. El mapa
 * sólo se carga cuando alguien lo pide, con un import dinámico que Vite deja en
 * su propio trozo.
 *
 * Si falla —sin cobertura, el servicio caído— se dice y ya está: la pantalla
 * sigue entera sin él.
 */
/*
 * MapLibre busca su worker en tiempo de ejecución, componiendo la ruta con
 * import.meta.url del propio paquete. Empaquetado con Vite ese fichero no
 * existe al lado del chunk, así que daba 404 y el mapa se quedaba en blanco
 * sin decir nada: el canvas se creaba, pero el evento «load» no llegaba nunca
 * porque sin worker no hay quien procese las teselas. El único rastro era un
 * «An unknown error occurred when fetching the script» en la consola.
 *
 * Con «?worker&url» lo empaqueta Vite —resolviendo sus propias importaciones—
 * y aquí sólo hay que pasarle la dirección buena.
 */
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';

export default (config = {}) => ({
    visible: false,
    loaded: false,
    loading: false,
    failed: false,

    async show() {
        if (this.loaded || this.loading) {
            return;
        }

        this.loading = true;
        this.failed = false;

        // El contenedor tiene que ocupar sitio ANTES de crear el mapa: si
        // MapLibre nace dentro de un display:none se dibuja a 0x0 y se queda
        // asi. De ahi el nextTick.
        this.visible = true;
        await this.$nextTick();

        try {
            /*
             * Importaciones por nombre. MapLibre 6 NO tiene export por defecto:
             * pedirlo dejaba maplibregl en undefined y, peor, hacia que Rollup
             * se llevara la libreria entera por tree-shaking. El unico sintoma
             * era un trozo de 0,5 kB en el build y un boton que no hacia nada.
             */
            const [{ Map, Marker, NavigationControl, LngLatBounds, setWorkerUrl }] = await Promise.all([
                import('maplibre-gl'),
                import('maplibre-gl/dist/maplibre-gl.css'),
            ]);

            setWorkerUrl(workerUrl);

            this.draw({ Map, Marker, NavigationControl, LngLatBounds });
            this.loaded = true;
        } catch {
            this.failed = true;
            this.visible = false;
        } finally {
            this.loading = false;
        }
    },

    draw({ Map, Marker, NavigationControl, LngLatBounds }) {
        // [lon, lat, altitud] -> [lon, lat]: al mapa la altitud le sobra
        const line = config.geometry.map(([lon, lat]) => [lon, lat]);

        const bounds = line.reduce(
            (box, point) => box.extend(point),
            new LngLatBounds(line[0], line[0]),
        );

        const map = new Map({
            container: this.$refs.canvas,
            // Estilo apagado a proposito: el protagonista es la linea del
            // recorrido, no los rotulos de las calles.
            style: 'https://tiles.openfreemap.org/styles/positron',
            bounds,
            fitBoundsOptions: { padding: 32 },
        });

        map.addControl(new NavigationControl({ showCompass: false }), 'top-right');

        // Una tesela suelta que falle no invalida el mapa, así que esto no
        // marca fallo: sólo deja rastro para poder mirarlo.
        map.on('error', (event) => console.warn('Mapa del recorrido:', event.error?.message ?? event));

        /*
         * «style.load» y no «load»: load espera al primer pintado completo, y
         * eso no ocurre mientras la pestaña está en segundo plano, porque el
         * navegador no ejecuta requestAnimationFrame. El recorrido se quedaba
         * sin dibujar hasta volver a la pestaña. Para añadir fuentes y capas
         * basta con que el estilo esté leído.
         */
        const dibujarRecorrido = () => {
            map.addSource('recorrido', {
                type: 'geojson',
                data: { type: 'Feature', geometry: { type: 'LineString', coordinates: line } },
            });

            // Dos capas: una gruesa clara debajo hace de borde y mantiene la
            // linea legible sobre cualquier color del mapa.
            map.addLayer({
                id: 'recorrido-borde',
                type: 'line',
                source: 'recorrido',
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: { 'line-color': '#ffffff', 'line-width': 7 },
            });

            map.addLayer({
                id: 'recorrido-linea',
                type: 'line',
                source: 'recorrido',
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: { 'line-color': '#171717', 'line-width': 3 },
            });

            this.marker(Marker, map, line[0], '#171717');
            this.marker(Marker, map, line[line.length - 1], '#ffffff');
        };

        if (map.isStyleLoaded()) {
            dibujarRecorrido();
        } else {
            map.once('style.load', dibujarRecorrido);
        }
    },

    marker(Marker, map, at, color) {
        const dot = document.createElement('div');
        dot.className = 'trip-marker';
        dot.style.background = color;

        new Marker({ element: dot }).setLngLat(at).addTo(map);
    },
});
