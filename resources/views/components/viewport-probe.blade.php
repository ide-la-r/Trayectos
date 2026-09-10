{{--
    Medidor temporal, segunda vuelta.

    La pregunta ahora es una sola: ¿cambia el tamaño de la ventana al arrastrar
    el dedo? Si al entrar mide 873 y después 932, la barra no está mal pintada:
    es que la ventana crece y la barra la sigue.

    Por eso guarda el valor DE ENTRADA y lo enseña junto al de ahora. Con una
    captura después de arrastrar se ve la comparación entera.

    QUITAR cuando esté resuelto.
--}}
<div id="medidor"
     style="position: fixed; inset: auto 0 auto 0; top: 45%; z-index: 90; background: #EE7203;
            color: #fff; font: 600 12px/1.4 ui-monospace, monospace; padding: 8px;
            white-space: pre;">
    midiendo…
</div>

<script>
    (() => {
        const caja = document.getElementById('medidor');
        const barra = () => document.querySelector('.nav-bottom-bar');

        const alto = () => window.innerHeight;
        const fondoBarra = () => {
            const b = barra();
            return b ? Math.round(b.getBoundingClientRect().bottom) : 0;
        };

        // Lo de la entrada se guarda una vez y no se vuelve a tocar
        const entrada = { ventana: alto(), barra: fondoBarra() };
        let gestos = 0;

        const pintar = () => {
            caja.textContent = [
                `             al entrar    ahora`,
                `ventana        ${String(entrada.ventana).padStart(4)}       ${String(alto()).padStart(4)}`,
                `fondo barra    ${String(entrada.barra).padStart(4)}       ${String(fondoBarra()).padStart(4)}`,
                `pantalla ${window.screen.height}  ·  gestos ${gestos}`,
            ].join('\n');
        };

        pintar();

        const alMover = () => {
            gestos++;
            pintar();
        };

        window.addEventListener('resize', alMover);
        window.addEventListener('scroll', alMover, { passive: true });
        window.addEventListener('touchmove', alMover, { passive: true });
        window.visualViewport?.addEventListener('resize', alMover);
    })();
</script>
