/*
 * MapLibre busca su worker en tiempo de ejecución, componiendo la ruta con
 * import.meta.url del propio paquete. Empaquetado con Vite ese fichero no
 * existe al lado del chunk, así que daba 404 y el mapa se quedaba en blanco sin
 * decir nada: el canvas se creaba, pero las teselas no se procesaban porque no
 * había worker. El único rastro era un «An unknown error occurred when fetching
 * the script» en la consola.
 *
 * Con «?worker&url» lo empaqueta Vite —resolviendo sus propias importaciones— y
 * aquí sólo hay que pasarle la dirección buena.
 */
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';

let pending = null;

/**
 * Carga MapLibre una sola vez, cuando alguien lo pide de verdad.
 *
 * Son unos 274 kB comprimidos, así que va en su propio trozo y no se descarga
 * al abrir ninguna pantalla. La promesa se guarda para que dos mapas en la
 * misma página —la ruta de un viaje y las gasolineras— no lo pidan dos veces.
 *
 * OJO: importaciones por nombre. MapLibre 6 no tiene export por defecto;
 * pedirlo dejaba la variable en undefined y, peor, hacía que Rollup se llevara
 * la librería entera por tree-shaking, dejando un trozo de 0,5 kB donde debía
 * haber un mega.
 */
export default function loadMaplibre() {
    pending ??= (async () => {
        const [maplibre] = await Promise.all([
            import('maplibre-gl'),
            import('maplibre-gl/dist/maplibre-gl.css'),
        ]);

        maplibre.setWorkerUrl(workerUrl);

        return maplibre;
    })();

    return pending;
}

/**
 * OpenFreeMap sirve tres estilos y ninguno pide clave ni tiene cuota.
 *
 * «liberty» es el que se parece a un mapa normal —parques en verde, agua en
 * azul, carreteras con su color— y es el que se eligió después de comparar los
 * tres: «positron» está lavado a propósito para servir de fondo a unos datos y
 * hacía que la pantalla pareciera a medio hacer.
 */
export const MAP_STYLE = 'https://tiles.openfreemap.org/styles/liberty';

/**
 * El estilo trae sus fuentes tipográficas como glifos; ésta es la única que se
 * puede dar por segura en positron y hace falta nombrarla para escribir texto
 * sobre el mapa.
 */
export const MAP_FONT = ['Noto Sans Regular'];
