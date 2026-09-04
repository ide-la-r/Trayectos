/**
 * Compartir el enlace de invitación al grupo.
 *
 * En el móvil lo interesante es el menú nativo de compartir, que lleva directo a
 * WhatsApp; en escritorio ese menú no existe, así que ahí lo que se ofrece es
 * copiar. La API del portapapeles falla sin contexto seguro y en algún Safari
 * antiguo, por eso queda la selección del campo como último recurso: es mejor
 * dejar el texto seleccionado que no hacer nada y no decir por qué.
 */
export default (url, groupName) => ({
    url,
    groupName,
    copied: false,
    failed: false,
    canShare: false,

    init() {
        this.canShare = typeof navigator.share === 'function';
    },

    async copy() {
        this.failed = false;

        if (await this.writeToClipboard()) {
            this.copied = true;
            setTimeout(() => (this.copied = false), 2200);

            return;
        }

        // Si no hay portapapeles hay que decirlo: un botón que aparenta no
        // hacer nada es peor que no tener botón. El texto queda seleccionado
        // para que Ctrl+C funcione a la primera.
        this.$refs.field?.select();
        this.failed = true;
    },

    async writeToClipboard() {
        try {
            await navigator.clipboard.writeText(this.url);

            return true;
        } catch {
            // Sin contexto seguro o sin permiso: queda el camino antiguo
        }

        try {
            this.$refs.field?.select();

            return document.execCommand('copy');
        } catch {
            return false;
        }
    },

    async share() {
        try {
            await navigator.share({
                title: `Libro de Trayectos — ${this.groupName}`,
                text: `Te invito al grupo «${this.groupName}» para repartir los gastos del coche.`,
                url: this.url,
            });
        } catch {
            // Cancelar el menú de compartir lanza excepción: no es un error
        }
    },
});
