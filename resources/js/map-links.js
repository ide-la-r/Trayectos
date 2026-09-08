/**
 * Enlaces «cómo llegar» que abren la aplicación de mapas del aparato.
 *
 * El HTML se sirve con Google Maps, que funciona en todas partes y sigue
 * funcionando si esto no llega a ejecutarse. Aquí sólo se reescribe donde hay
 * algo mejor: en iPhone, iPad y Mac, Mapas de Apple, que es lo que esa gente
 * tiene por defecto y lo que abre sin pasar por el navegador.
 */
export default function nativeMapLinks() {
    const isApple = /iPad|iPhone|iPod|Macintosh/.test(navigator.userAgent)
        // Un iPad moderno se presenta como Macintosh; los puntos táctiles lo delatan
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    if (! isApple) {
        return;
    }

    document.querySelectorAll('a[data-lat][data-lon]').forEach((link) => {
        link.href = `https://maps.apple.com/?daddr=${link.dataset.lat},${link.dataset.lon}&dirflg=d`;
    });
}
