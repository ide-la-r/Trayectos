/**
 * Elección de la zona en la pantalla de precios.
 *
 * El punto puede venir del navegador («usar mi ubicación») o del buscador de
 * sitios, que ya existe para los viajes. En los dos casos acaba en los mismos
 * campos ocultos y se envía por POST: las coordenadas no van en la URL.
 */
export default (config = {}) => ({
    // Si todavía no hay zona elegida, el panel se abre solo: es lo único que
    // hay que hacer en esta pantalla para que los precios sirvan de algo.
    open: !config.hasArea,
    locating: false,
    error: '',

    locate() {
        if (!navigator.geolocation) {
            this.error = 'Este navegador no sabe decir dónde estás. Busca el sitio a mano aquí abajo.';
            return;
        }

        this.locating = true;
        this.error = '';

        navigator.geolocation.getCurrentPosition(
            (position) => this.apply(position.coords.latitude, position.coords.longitude, 'tu ubicación'),
            () => {
                this.locating = false;
                this.error = 'No se ha podido saber dónde estás. Puede que hayas denegado el permiso de ubicación: búscalo a mano aquí abajo.';
            },
            // Sin alta precisión: para elegir gasolineras sobra, y así tarda
            // mucho menos y no enciende el GPS.
            { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 },
        );
    },

    fromPlace(event) {
        const { lat, lon, label } = event.detail;

        if (lat && lon) {
            this.apply(lat, lon, label);
        }
    },

    apply(lat, lon, label) {
        this.$refs.lat.value = lat;
        this.$refs.lon.value = lon;
        this.$refs.label.value = label || 'tu ubicación';
        this.$refs.form.submit();
    },
});
