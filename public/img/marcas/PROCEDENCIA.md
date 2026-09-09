# De dónde sale cada logotipo

Todos los ficheros de esta carpeta se han comprobado uno a uno: se miró de dónde
venían y se abrió cada uno para ver que es el logotipo que dice ser y no otra
cosa. Buscando el de Shell, por ejemplo, aparecía también el de la película
*Ghost in the Shell*.

## Los que tienen licencia libre

| Fichero | Marca | Origen | Licencia |
|---|---|---|---|
| `q8.svg` | Q8 | [Wikimedia Commons](https://commons.wikimedia.org/wiki/File:Q8_logo.svg) | Dominio público |
| `carrefour.svg` | Carrefour | [Simple Icons](https://simpleicons.org/?q=carrefour) | CC0 1.0 |

Wikimedia marca ese logotipo como no protegido por derechos de autor porque su
diseño son formas y texto simples, y eso no alcanza el umbral de originalidad
que exige la ley. No es una interpretación nuestra: es la etiqueta que lleva el
fichero en su origen.

## Los que vienen de la web de la propia marca

Todos son el **icono cuadrado** que cada marca publica para que su web se vea
bien guardada en la pantalla de inicio de un móvil. Es su símbolo, no el nombre
escrito, que es lo que se lee en una insignia de 24 píxeles.

| Fichero | Marca | Origen |
|---|---|---|
| `repsol.svg` | Repsol | `repsol.es/content/dam/global/logotipos/repsol/` (recortado al símbolo) |
| `bp.svg` | BP | `bp.com/icon.svg` |
| `cepsa.png` | Cepsa | `moeve.es` (icono para móviles) |
| `moeve.png` | Moeve | `moeve.es` (icono para móviles) |
| `galp.png` | Galp | `galp.com` (PNG de 256 px extraído de su favicon) |
| `plenoil.svg` | Plenoil | `plenergy.es` (icono del sitio) |
| `shell.png` | Shell | `shell.es` (icono para móviles) |

Cepsa y Moeve comparten fichero porque **son la misma empresa**: Cepsa pasó a
llamarse Moeve en 2026 y su web redirige. El Ministerio sigue publicando muchas
estaciones con el rótulo antiguo, así que las dos claves apuntan al mismo
símbolo.

El de Repsol es el único que venía con el nombre escrito al lado, y en una
insignia de 24 píxeles eso no se lee. Está **recortado al símbolo** cambiando
sólo el `viewBox` del SVG, que es la ventana por la que se mira el dibujo: no
se ha tocado ni un trazo, el fichero sigue entero y sólo se enseña la llama.

Éstos **no llevan licencia libre**: son la marca de su dueño, descargada del
sitio del dueño. Se usan aquí para una sola cosa, decir de qué marca es cada
gasolinera, que es el uso identificativo que hacen todas las aplicaciones de
mapas y de precios de carburante.

No hay ninguna licencia concedida ni relación con esas empresas. Si alguna
pidiera que se retirase su logotipo, basta con borrar su fichero de esta
carpeta: el mapa vuelve a poner las iniciales de esa marca y no se rompe nada.

## Las que salen con iniciales

- **Ballenoil**: su web está detrás de Cloudflare y no deja pasar.
- **Petroprix**: el icono que declara su web no se puede descargar.
- **Alcampo**: sólo publica un `.ico` de 48 px sin PNG dentro, que habría que
  decodificar a mano.
- **Avia, Petronor, Campsa**: sus webs no responden a la descarga.

Salen con sus iniciales sobre el color de la marca, que en la insignia se leen
bien. Para añadir cualquiera basta con abrir su web, guardar el icono y dejarlo
aquí con el nombre de la marca: mira el `LEEME.md`.
