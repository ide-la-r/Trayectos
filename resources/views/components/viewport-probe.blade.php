{{--
    Medidor temporal para cazar la franja de debajo de la barra en el iPhone.

    En el navegador la barra sale pegada al borde en las dos pantallas, así que
    el fallo sólo se ve en el móvil de verdad y hace falta leer los números
    desde ahí. Se enciende con ?medir=1 y no se carga de ninguna otra forma.

    QUITAR cuando esté resuelto.
--}}
<div id="medidor"
     style="position: fixed; inset: auto 0 0 0; z-index: 90; background: #EE7203; color: #fff;
            font: 600 11px/1.35 ui-monospace, monospace; padding: 6px 8px; white-space: pre;">
    midiendo…
</div>

<script>
    (() => {
        const caja = document.getElementById('medidor');

        const leer = () => {
            const nav = [...document.querySelectorAll('nav')]
                .find((n) => getComputedStyle(n).position === 'fixed');
            const r = nav ? nav.getBoundingClientRect() : null;
            const raiz = getComputedStyle(document.documentElement);

            // Los env() no se pueden leer directamente: se pasan por una
            // propiedad personalizada para poder consultarlos
            const inset = (lado) => raiz.getPropertyValue('--probe-' + lado).trim() || '?';

            caja.textContent = [
                `ventana ${window.innerHeight}  visual ${Math.round(window.visualViewport?.height ?? 0)}`,
                `pagina  ${document.documentElement.scrollHeight}  scroll ${Math.round(window.scrollY)}`,
                `barra   top ${r ? Math.round(r.top) : '?'}  bottom ${r ? Math.round(r.bottom) : '?'}`,
                `HUECO   ${r ? Math.round(window.innerHeight - r.bottom) : '?'}  ·  screen ${window.screen.height}`,
                `insets  arriba ${inset('top')}  abajo ${inset('bottom')}`,
            ].join('\n');
        };

        leer();
        window.addEventListener('resize', leer);
        window.addEventListener('scroll', leer, { passive: true });
    })();
</script>

<style>
    :root {
        --probe-top: env(safe-area-inset-top, 0px);
        --probe-bottom: env(safe-area-inset-bottom, 0px);
    }
</style>
