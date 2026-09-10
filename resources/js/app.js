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
// No exporta nada: se engancha solo a los eventos que necesita
import './app-shell';

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

// El service worker sólo aporta en producción; en desarrollo estorba
if ('serviceWorker' in navigator && !import.meta.env.DEV) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Sin service worker la aplicación funciona igual, sólo sin offline
        });
    });
}
