import Alpine from 'alpinejs';
import placeField from './place-field';
import tripEstimate from './trip-estimate';
import installPrompt from './install-prompt';
import inviteLink from './invite-link';
import fuelArea from './fuel-area';
import nativeMapLinks from './map-links';
import tripMap from './trip-map';
import fuelMap from './fuel-map';
import pushToggle from './push';

Alpine.data('placeField', placeField);
Alpine.data('tripEstimate', tripEstimate);
Alpine.data('installPrompt', installPrompt);
Alpine.data('inviteLink', inviteLink);
Alpine.data('fuelArea', fuelArea);
Alpine.data('tripMap', tripMap);
Alpine.data('fuelMap', fuelMap);
Alpine.data('pushToggle', pushToggle);

window.Alpine = Alpine;
Alpine.start();

// No es un componente de Alpine: sólo reescribe los enlaces «cómo llegar»
// una vez, en cuanto hay HTML.
nativeMapLinks();

/**
 * Obliga a Safari a recolocar la barra inferior.
 *
 * En iOS, al entrar en una pantalla con poco contenido la barra se dibujaba
 * unos centímetros por encima del borde y se colocaba sola en cuanto se
 * arrastraba el dedo: el navegador la tenía bien situada —medido, hueco 0—
 * pero la seguía PINTANDO donde estaba antes de la transición entre páginas.
 *
 * La causa de fondo era el transform que la sacaba a su propia capa, y ya no
 * está. Esto es el cinturón: en «pagereveal», que es justo cuando termina la
 * transición, se le quita y se le devuelve el sitio en el flujo para que el
 * navegador no tenga más remedio que recalcular dónde va.
 *
 * Cuesta una lectura de offsetHeight y sólo corre al cambiar de pantalla.
 */
const settleBottomBar = () => {
    const bar = document.querySelector('.nav-bottom-bar');

    if (!bar) {
        return;
    }

    bar.style.display = 'none';
    void bar.offsetHeight; // leerlo es lo que fuerza el recálculo
    bar.style.display = '';
};

// pagereveal: al terminar una transición entre documentos (Safari, Chrome).
// pageshow: al volver con el botón atrás desde la caché del navegador.
window.addEventListener('pagereveal', settleBottomBar);
window.addEventListener('pageshow', settleBottomBar);

// El service worker sólo aporta en producción; en desarrollo estorba
if ('serviceWorker' in navigator && !import.meta.env.DEV) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Sin service worker la aplicación funciona igual, sólo sin offline
        });
    });
}
