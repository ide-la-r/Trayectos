/**
 * Los dos remates del armazón de la aplicación.
 *
 * Desde que la aplicación ocupa la ventana y lo que rueda es el contenido de
 * dentro (mira el comentario del body en layouts/app.blade.php), hay dos cosas
 * que el navegador dejó de hacer solo y hay que hacerle:
 *
 *   1. Volver a su sitio después del teclado.
 *   2. Recordar por dónde ibas al volver atrás.
 *
 * Las dos son de móvil; en escritorio el <main> no rueda y no hacen nada.
 */

const scroller = () => document.querySelector('[data-scroller]');

/**
 * Devolver la ventana a su sitio cuando se cierra el teclado.
 *
 * Al enfocar un campo de los de abajo, iOS no encoge la página: desplaza la
 * VENTANA entera para que el campo se vea, y se salta el overflow:hidden del
 * body. Como el armazón mide justo la pantalla, eso saca la cabecera por
 * arriba. Al cerrar el teclado iOS no siempre lo deshace, y la aplicación se
 * queda subida con una franja vacía abajo —el mismo aspecto que el fallo que
 * costó una tarde, pero por otro motivo—.
 *
 * Se mira en focusout y también cuando la ventana visual recupera su altura,
 * porque cerrar el teclado con el botón de bajar no dispara focusout.
 */
const settleAfterKeyboard = () => {
    // En el mismo fotograma iOS todavía no ha terminado; se espera al siguiente
    requestAnimationFrame(() => {
        if (window.scrollY !== 0) {
            window.scrollTo(0, 0);
        }
    });
};

window.addEventListener('focusout', settleAfterKeyboard);

if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', () => {
        // El teclado cerrado es la ventana visual midiendo casi lo mismo que la
        // de maquetación; con margen, que rara vez coinciden al píxel.
        if (window.visualViewport.height >= window.innerHeight - 50) {
            settleAfterKeyboard();
        }
    });
}

/**
 * Recordar por dónde ibas al volver atrás.
 *
 * El navegador guarda y restaura el desplazamiento del DOCUMENTO, y aquí el
 * que rueda es un div. Sin esto, volver de la ficha de un viaje al libro te
 * dejaba arriba del todo, que en una lista larga es un fastidio.
 *
 * Se guarda por ruta en sessionStorage —vive lo que la pestaña— y sólo se
 * restaura si de verdad se ha llegado con el botón atrás; si no, cada visita
 * arrancaría donde la dejaste la última vez, que no es lo que espera nadie.
 */
const scrollKey = () => `scroll:${location.pathname}${location.search}`;

const rememberScroll = () => {
    const main = scroller();

    if (!main || !main.scrollTop) {
        return;
    }

    try {
        sessionStorage.setItem(scrollKey(), String(main.scrollTop));
    } catch {
        // Modo privado o almacenamiento lleno: se pierde el sitio y ya está
    }
};

const restoreScroll = () => {
    const main = scroller();
    const navegacion = performance.getEntriesByType('navigation')[0];

    if (!main || navegacion?.type !== 'back_forward') {
        return;
    }

    try {
        const guardado = Number(sessionStorage.getItem(scrollKey()));

        if (guardado > 0) {
            main.scrollTop = guardado;
        }
    } catch {
        // Igual que arriba: sin sitio guardado se empieza arriba
    }
};

// pagehide y no beforeunload: es el que iOS dispara de verdad al salir
window.addEventListener('pagehide', rememberScroll);
window.addEventListener('pagereveal', restoreScroll);
document.addEventListener('DOMContentLoaded', restoreScroll);
