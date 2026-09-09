/**
 * Enlaces «cómo llegar» que abren la aplicación de mapas del aparato.
 *
 * En iPhone, iPad y Mac se manda a Mapas de Apple, que es lo que esa gente
 * tiene por defecto y lo que abre sin pasar por el navegador; en el resto, a
 * Google Maps. Los dos van directos a la ruta en coche, no a un punto en el
 * mapa: quien mira esto va a ir.
 */
function isApple() {
    return /iPad|iPhone|iPod|Macintosh/.test(navigator.userAgent)
        // Un iPad moderno se presenta como Macintosh; los puntos táctiles lo delatan
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

export function directionsUrl(lat, lon) {
    return isApple()
        ? `https://maps.apple.com/?daddr=${lat},${lon}&dirflg=d`
        : `https://www.google.com/maps/dir/?api=1&destination=${lat},${lon}&travelmode=driving`;
}

/**
 * Los enlaces del listado se sirven con Google Maps, que funciona en todas
 * partes y sigue funcionando si esto no llega a ejecutarse. Aquí sólo se
 * reescriben los que tengan algo mejor.
 */
export default function nativeMapLinks() {
    if (! isApple()) {
        return;
    }

    document.querySelectorAll('a[data-lat][data-lon]').forEach((link) => {
        link.href = directionsUrl(link.dataset.lat, link.dataset.lon);
    });
}
