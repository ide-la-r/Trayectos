/**
 * Instalación en la pantalla de inicio.
 *
 * En Android el navegador da un evento y se puede pedir la instalación con un
 * botón. En iOS no existe ese evento: hay que explicarle a la persona que use
 * Compartir → Añadir a pantalla de inicio, o no lo instalará nadie.
 */
export default () => ({
    deferredPrompt: null,
    canPrompt: false,
    showIosHelp: false,

    init() {
        const standalone =
            window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

        if (standalone || localStorage.getItem('install-dismissed') === '1') {
            return;
        }

        const isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent);

        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            this.deferredPrompt = event;
            this.canPrompt = true;
        });

        if (isIos) {
            this.showIosHelp = true;
        }
    },

    async install() {
        if (!this.deferredPrompt) {
            return;
        }

        this.deferredPrompt.prompt();
        await this.deferredPrompt.userChoice;

        this.deferredPrompt = null;
        this.canPrompt = false;
    },

    dismiss() {
        localStorage.setItem('install-dismissed', '1');
        this.canPrompt = false;
        this.showIosHelp = false;
    },
});
