/**
 * Avisos en el móvil.
 *
 * En iPhone esto SÓLO existe si la aplicación está en la pantalla de inicio:
 * desde Safari, `Notification` ni siquiera está definido. Por eso lo primero
 * que se hace es mirar si se puede, y si no, decir por qué en vez de enseñar
 * un interruptor que no va a funcionar.
 *
 * El permiso hay que pedirlo desde un toque de la persona: si se pide al
 * cargar la página, el navegador lo deniega sin preguntar y ya no hay vuelta
 * atrás salvo yendo a los ajustes.
 */
export default (publicKey) => ({
    supported: false,
    // Bloqueado de verdad: dijo que no en su día y el navegador ya no pregunta
    blocked: false,
    // Se puede, pero hay que instalar la aplicación antes (iPhone)
    needsInstall: false,
    enabled: false,
    busy: false,
    error: '',

    async init() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            // En iOS fuera de la pantalla de inicio no hay nada de esto
            this.needsInstall = this.isIos() && !this.isStandalone();
            return;
        }

        this.supported = true;
        this.blocked = Notification.permission === 'denied';

        const subscription = await this.current();
        this.enabled = subscription !== null;
    },

    isIos() {
        return /iphone|ipad|ipod/i.test(navigator.userAgent);
    },

    isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    },

    /** La suscripción de este navegador, si ya la hay. */
    async current() {
        const registration = await navigator.serviceWorker.getRegistration();

        return registration ? registration.pushManager.getSubscription() : null;
    },

    async toggle() {
        if (this.busy) {
            return;
        }

        this.busy = true;
        this.error = '';

        try {
            await (this.enabled ? this.disable() : this.enable());
        } catch (problem) {
            this.error = problem.message || 'No se han podido activar los avisos.';
        } finally {
            this.busy = false;
        }
    },

    async enable() {
        const permission = await Notification.requestPermission();

        if (permission !== 'granted') {
            this.blocked = permission === 'denied';
            throw new Error(
                this.blocked
                    ? 'Has bloqueado los avisos para esta aplicación. Se vuelven a permitir desde los ajustes del móvil.'
                    : 'Sin permiso no se pueden mandar avisos.',
            );
        }

        const registration = await navigator.serviceWorker.ready;

        const subscription = await registration.pushManager.subscribe({
            // Obligatorio: el navegador exige que cada aviso se vea. Un push
            // silencioso sirve para rastrear a la gente y por eso no se deja.
            userVisibleOnly: true,
            applicationServerKey: this.decodeKey(publicKey),
        });

        await this.send('POST', subscription.toJSON());

        this.enabled = true;
    },

    async disable() {
        const subscription = await this.current();

        if (subscription) {
            // Primero el servidor: si se da de baja aquí y el envío falla,
            // quedaría una suscripción muerta a la que se seguiría escribiendo.
            await this.send('DELETE', { endpoint: subscription.endpoint });
            await subscription.unsubscribe();
        }

        this.enabled = false;
    },

    async send(method, body) {
        const response = await fetch('/avisos', {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify(body),
        });

        if (!response.ok) {
            throw new Error('El servidor no ha aceptado la suscripción.');
        }
    },

    /**
     * La clave pública viaja en base64 «de URL» y el navegador la quiere en
     * bytes.
     */
    decodeKey(key) {
        const padded = (key + '='.repeat((4 - (key.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(padded);

        return Uint8Array.from([...raw].map((character) => character.charCodeAt(0)));
    },
});
