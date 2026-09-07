/**
 * Previsualización del coste del viaje. Pide al backend el mismo cálculo que se
 * hará al guardar, para que nadie se lleve sorpresas al pulsar el botón.
 */
export default (config = {}) => ({
    groupId: config.groupId,
    estimate: null,
    loading: false,
    error: null,
    timer: null,

    init() {
        this.$nextTick(() => this.schedule(1200));
    },

    schedule(delay = 600) {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.refresh(), delay);
    },

    payload() {
        const form = this.$refs.form;

        if (!form) {
            return null;
        }

        const data = new FormData(form);
        const occupants = form.querySelectorAll('input[name="passengers[]"]:checked').length;

        if (!data.get('vehicle_id') || occupants === 0) {
            return null;
        }

        const distance = data.get('distance_km');
        const hasCoordinates = data.get('origin_lat') && data.get('destination_lat');

        if (!distance && !hasCoordinates) {
            return null;
        }

        return {
            group_id: this.groupId,
            vehicle_id: data.get('vehicle_id'),
            origin_lat: data.get('origin_lat') || null,
            origin_lon: data.get('origin_lon') || null,
            destination_lat: data.get('destination_lat') || null,
            destination_lon: data.get('destination_lon') || null,
            distance_km: distance || null,
            ascent_m: data.get('ascent_m') || null,
            descent_m: data.get('descent_m') || null,
            round_trip: data.get('round_trip') ? 1 : 0,
            occupants,
            payers: occupants,
            luggage_kg: data.get('luggage_kg') || 0,
            battery_start_pct: data.get('battery_start_pct') || 0,
        };
    },

    async refresh() {
        const payload = this.payload();

        if (!payload) {
            this.estimate = null;
            return;
        }

        this.loading = true;
        this.error = null;

        try {
            const response = await fetch('/api/estimacion', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error('estimación no disponible');
            }

            this.estimate = await response.json();
        } catch {
            this.estimate = null;
            this.error = 'No se ha podido calcular ahora mismo. Puedes guardar el viaje igualmente.';
        } finally {
            this.loading = false;
        }
    },

    euros(cents) {
        if (cents === null || cents === undefined) {
            return '—';
        }

        return (cents / 100).toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });
    },

    /** La comparación llega ya en euros, no en céntimos como el resto. */
    moneda(amount) {
        if (amount === null || amount === undefined) {
            return '—';
        }

        return amount.toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });
    },

    /**
     * Elegir un coche desde la tabla de comparación. Cambiar el value de un
     * select por JavaScript no dispara «change», así que hay que emitirlo a
     * mano o el powertrain que lee el formulario se queda con el del coche
     * anterior y los campos de batería aparecen o desaparecen mal.
     */
    elegirCoche(vehicleId) {
        const select = this.$refs.vehicle;

        if (!select) {
            return;
        }

        select.value = String(vehicleId);
        select.dispatchEvent(new Event('change', { bubbles: true }));
        this.schedule(150);
    },
});
