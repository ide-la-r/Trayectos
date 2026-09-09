/**
 * Autocompletado de direcciones. Las peticiones van a nuestro backend, que
 * cachea y habla con Photon: llamar al servicio de OSM en cada pulsación es la
 * forma más rápida de que te bloqueen la IP.
 */
export default (config = {}) => ({
    query: config.label || '',
    lat: config.lat || null,
    lon: config.lon || null,
    results: [],
    open: false,
    loading: false,
    timer: null,

    /**
     * Al escribir a mano se invalidan las coordenadas: hay que volver a elegir.
     *
     * Cuelga del evento «input» y NO de un $watch sobre el texto. Con el $watch
     * estaba roto de una forma difícil de ver: elegir un sitio de la lista
     * cambia el texto, así que el vigilante se disparaba —en el microtask
     * siguiente, ya fuera de choose()— y borraba las coordenadas que se
     * acababan de guardar. El formulario contestaba «elige origen y destino del
     * buscador» con el origen y el destino puestos, y no había forma de apuntar
     * un viaje sin escribir los kilómetros a mano.
     *
     * Con «input» sólo cuenta lo que teclea una persona: cambiar el valor desde
     * el código no dispara ese evento, que es justo la diferencia que hacía
     * falta.
     */
    onInput(value) {
        clearTimeout(this.timer);

        this.lat = null;
        this.lon = null;

        if (value.trim().length < 3) {
            this.results = [];
            this.open = false;

            return;
        }

        this.timer = setTimeout(() => this.search(), 400);
    },

    async search() {
        this.loading = true;

        try {
            const response = await fetch(`/api/lugares?q=${encodeURIComponent(this.query)}`, {
                headers: { Accept: 'application/json' },
            });

            const payload = await response.json();
            this.results = payload.results || [];
            this.open = this.results.length > 0;
        } catch {
            this.results = [];
            this.open = false;
        } finally {
            this.loading = false;
        }
    },

    choose(place) {
        this.query = place.context ? `${place.label} (${place.context})` : place.label;
        this.lat = place.lat;
        this.lon = place.lon;
        this.open = false;
        this.results = [];

        clearTimeout(this.timer);
        this.$dispatch('place-chosen', { field: config.name, lat: place.lat, lon: place.lon, label: this.query });
    },
});
