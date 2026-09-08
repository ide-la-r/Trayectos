import Alpine from 'alpinejs';
import placeField from './place-field';
import tripEstimate from './trip-estimate';
import installPrompt from './install-prompt';
import inviteLink from './invite-link';
import fuelArea from './fuel-area';

Alpine.data('placeField', placeField);
Alpine.data('tripEstimate', tripEstimate);
Alpine.data('installPrompt', installPrompt);
Alpine.data('inviteLink', inviteLink);
Alpine.data('fuelArea', fuelArea);

window.Alpine = Alpine;
Alpine.start();

// El service worker sólo aporta en producción; en desarrollo estorba
if ('serviceWorker' in navigator && !import.meta.env.DEV) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Sin service worker la aplicación funciona igual, sólo sin offline
        });
    });
}
