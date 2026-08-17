# APIs externas

Las cuatro son gratuitas y **ninguna pide tarjeta de crédito**. Los contratos de abajo están
verificados contra los servicios reales el **17 de agosto de 2026**.

| Servicio | Para qué | Registro | Cuota |
|---|---|---|---|
| [OpenRouteService](https://openrouteservice.org/dev/) | Distancia, duración y desnivel | Email | ~2.000 peticiones/día, 40/min |
| [Open Topo Data](https://www.opentopodata.org/) | Altitud (respaldo) | Ninguno | 100 puntos/petición · 1/s · 1.000/día |
| [Photon](https://photon.komoot.io/) | Autocompletado de direcciones | Ninguno | Uso razonable |
| [MITECO](https://sedeaplicaciones.minetur.gob.es/ServiciosRESTCarburantes/PreciosCarburantes/) | Precios de carburante | Ninguno | Sin límite documentado |
| [Red Eléctrica](https://apidatos.ree.es/) | Precio de la electricidad | Ninguno | Uso razonable |

---

## OpenRouteService

```
POST /v2/directions/driving-car/geojson
Authorization: <clave>

{ "coordinates": [[lon, lat], [lon, lat]], "elevation": true, "instructions": false }
```

**La clave del diseño**: con `elevation: true`, una sola llamada devuelve
`properties.summary.distance`, `properties.ascent` y `properties.descent`. No hace falta gastar cuota
en un servicio de altitud aparte para el caso normal.

- Las coordenadas van en **`[lon, lat]`**, al revés de lo habitual.
- Las rutas entre dos puntos no cambian: se cachean una semana, lo que ahorra la mayor parte de la
  cuota en un grupo con trayectos habituales.
- La geometría se simplifica a 120 vértices antes de guardarla; nadie necesita 4.000 puntos por viaje.
- Un contador diario propio ([`ApiQuota`](../app/Services/Support/ApiQuota.php)) evita llegar al 429:
  al quedarse sin margen, el planificador degrada solo.

## Open Topo Data

```
GET /v1/eudem25m?locations=40.416775,-3.703790|40.788913,-4.003585
```

Se usa como respaldo cuando ORS no está disponible. El dataset **`eudem25m`** cubre España a 25 m,
bastante mejor que el `srtm90m` global.

Al acumular el perfil se aplica un **umbral de ruido de 3 m**: sin él, un tramo llano acumularía
cientos de metros de desnivel inexistente por el error del modelo digital.

Si algún día la cuota se queda corta: `docker run opentopodata` con los MDT del IGN, coste cero.

## Photon (autocompletado)

```
GET https://photon.komoot.io/api/?q=Navacerrada&limit=6&lang=default&lat=40.4&lon=-3.7
```

- **`lang=es` devuelve HTTP 400.** Con `lang=default` los topónimos llegan en el idioma local, que
  para España es justo lo que se quiere. (Verificado: `es` → 400, `default` → «Comunidad de Madrid»,
  `en` → «Community of Madrid».)
- `lat`/`lon` sesgan los resultados hacia el centro de España.
- **Nunca se llama desde el navegador en cada tecla.** La petición pasa por el backend, que cachea 30
  días y manda un `User-Agent` identificable. Llamar a un servicio OSM gratuito en cada pulsación es
  la forma más rápida de que bloqueen la IP.
- Los resultados vacíos **no se cachean**: convertirían un fallo puntual en un «ese sitio no existe»
  durante un mes.

## MITECO — Geoportal de Gasolineras

```
BASE=https://sedeaplicaciones.minetur.gob.es/ServiciosRESTCarburantes/PreciosCarburantes

GET $BASE/Listados/ProductosPetroliferos/
GET $BASE/Listados/Provincias/
GET $BASE/EstacionesTerrestres/                             # ~11.500 estaciones, ~20 MB
GET $BASE/EstacionesTerrestres/FiltroProvincia/{id}          # el que se usa en producción
```

Respuesta real (recortada) del endpoint por provincia:

```json
{
  "Fecha": "17/08/2026 17:44:12",
  "ListaEESSPrecio": [{
    "IDEESS": "4982",
    "Rótulo": "CEPSA",
    "Dirección": "AUTOVIA NOROESTE KM. 111",
    "Municipio": "Adanero",
    "Provincia": "ÁVILA",
    "C.P.": "05296",
    "Latitud": "40,955111",
    "Longitud (WGS84)": "-4,614556",
    "Precio Gasoleo A": "1,974",
    "Precio Gasolina 95 E5": "1,759",
    "Precio Gases licuados del petróleo": ""
  }]
}
```

Tres trampas, todas contempladas en [`FuelPriceSynchronizer`](../app/Services/Fuel/FuelPriceSynchronizer.php):

1. **Los precios son cadenas con coma decimal** (`"1,974"`), y **cadena vacía** cuando la estación no
   vende ese producto. Se normalizan a enteros de milésimas de euro al ingerir.
2. **Los nombres de campo llevan espacios, tildes y paréntesis**: `Rótulo`, `Longitud (WGS84)`,
   `C.P.`, `Dirección`. Nada de asignación masiva: mapeo explícito campo a campo.
3. **Nunca se llama dentro de una petición de usuario.** Es lenta y a veces se cae. La sincroniza un
   comando y la aplicación lee siempre de la copia local.

El catálogo de productos tiene 30 entradas (`G95E5`, `GOA`, `GLP`, `GNC`, `H2`, `DREN`…); la
aplicación sincroniza los cinco que mueven a un coche particular.

## Red Eléctrica

```
GET /es/datos/mercados/precios-mercados-tiempo-real?start_date=...T00:00&end_date=...T23:59&time_trunc=hour
```

- **`time_trunc=day` devuelve HTTP 400**; sólo funciona `hour`. Se promedian las 24 horas.
- Vienen **dos series**: `PVPC` (id 1001, minorista regulado) y `Precio mercado spot` (id 600,
  mayorista). Se usa **PVPC**; el spot no lo paga nadie. El 16/08/2026: PVPC 137,73 €/MWh frente a
  116,02 €/MWh de spot.
- El PVPC se publica **sin IVA**: se aplica un multiplicador configurable (1,21 por defecto).
- Aun así es un respaldo. Quien carga en casa con tarifa fija debería declarar su precio real, que es
  el que manda en [`EnergyPriceResolver`](../app/Services/Energy/EnergyPriceResolver.php).

## Degradación en cascada

Que se agote una cuota gratuita **no puede impedir apuntar un viaje**. El
[`RoutePlanner`](../app/Services/Routing/RoutePlanner.php) baja de escalón y avisa:

1. ORS con elevación → distancia y desnivel reales.
2. Línea recta × 1,25 + altitud de Open Topo Data → distancia estimada, desnivel real.
3. Línea recta × 1,25 y desnivel cero → todo estimado, con aviso en pantalla.
4. Los kilómetros escritos a mano **mandan siempre** sobre cualquiera de los anteriores.

Lo mismo con los precios: gasolinera más barata en 25 km → media nacional del último dato oficial →
precio de respaldo de configuración, marcado como tal para que se vea que no es real.
