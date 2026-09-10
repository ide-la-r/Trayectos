# Libro de Trayectos

Libro de cuentas compartido para los viajes en coche de un grupo de amigos. Calcula lo que cuesta
**de verdad** cada trayecto —incluido el efecto del desnivel, que no es el mismo en un híbrido que en
un diésel— y lo reparte entre los ocupantes con contabilidad por partida doble.

Instalable en el móvil como aplicación (PWA), también en iOS y sin pasar por la App Store.

**Coste de funcionamiento: 0 €.** Ni una sola pieza de la arquitectura requiere tarjeta de crédito.

---

## Qué hace

- **Vehículos** de combustión, híbridos, híbridos enchufables y eléctricos, cada uno con su consumo
  homologado, su batería y su capacidad de recuperar energía al bajar.
- **Rutas reales** con distancia y desnivel acumulado desde OpenRouteService, con degradación
  automática a estimación por línea recta + altitud de Open Topo Data cuando se agota la cuota.
- **Precios de carburante oficiales** del Ministerio para la Transición Ecológica, sincronizados a
  una copia local; para eléctricos, el PVPC de Red Eléctrica o la tarifa que declares.
- **Coste por trayecto** = distancia × consumo ajustado por orografía y carga × precio, congelado en
  un snapshot inmutable en el momento de apuntarlo.
- **Libro mayor por partida doble**: cada viaje es un asiento cuyas líneas suman exactamente cero.
  Nada se borra; un error se corrige con un asiento contrario.
- **Plan de liquidación**: quién paga a quién para dejar todos los saldos a cero con el menor número
  de pagos.
- **Motor de decisión**: sugiere al próximo conductor priorizando la mayor deuda, filtrando por quién
  tiene coche con plazas suficientes y desempatando por turnos recientes.
- **Consumo real medido de lleno a lleno**: con el cuentakilómetros de cada llenado sale lo que gasta
  el coche de verdad, sin fiarse de la ficha ni del modelo, y con eso se calibra el cálculo.
- **Los viajes de siempre**: los trayectos que el grupo repite salen solos en la pantalla de apuntar,
  deducidos del histórico. No hay plantillas que crear ni mantener, y repetir uno deja el formulario
  escrito —coche, gente y sitios— para confirmar la fecha y poco más.
- **Avisos en el móvil**: cuando alguien apunta un viaje en el que vas, lo anula o te paga, con el
  importe en el propio aviso. Sólo lo que mueve dinero de alguien y lo ha movido otra persona.

## Documentación

| Documento | Contenido |
|---|---|
| [docs/modelo-de-coste.md](modelo-de-coste.md) | La física del cálculo, los factores por tecnología y sus límites |
| [docs/contabilidad.md](contabilidad.md) | Partida doble, invariantes y por qué los saldos no pueden descuadrar |
| [docs/apis.md](apis.md) | Contratos reales de las cuatro APIs externas, verificados, con sus trampas |
| [docs/despliegue.md](despliegue.md) | Puesta en producción paso a paso, sin pagar nada |

## Stack

- **Laravel 13** (PHP 8.4) como monolito: menos servicios, menos tiers gratuitos que puedan caducar
- **Blade + Alpine.js + Tailwind 4**, móvil primero
- **PostgreSQL** en producción (Neon o Supabase), **SQLite** en local y en los tests
- **FrankenPHP** en un único contenedor
- Sin dependencias de pago, sin CDN externo: las fuentes se compilan y se sirven desde el propio dominio

## Arranque en local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run build
```

Datos de ejemplo (grupo de cuatro personas, cuatro coches de tecnologías distintas y cuatro viajes
por la sierra de Madrid):

```bash
php artisan db:seed --class=DemoSeeder
```

Entra con `ana@ejemplo.es` y la contraseña `trayectos`.

```bash
composer run dev
```

## Comandos propios

```bash
php artisan trayectos:sync-prices                # precios oficiales de carburante y electricidad
php artisan trayectos:sync-prices --provinces=28  # sólo una provincia
php artisan trayectos:sync-prices --all           # descarga nacional (~11.500 estaciones)
php artisan trayectos:calibrate                   # reajusta los coches con sus repostajes
php artisan trayectos:icons                       # regenera los iconos de la PWA
```

## Tests

```bash
php artisan test
```

Cubren la física del coste (incluida la saturación de la batería de un híbrido en bajadas largas),
el reparto de céntimos, las invariantes del libro mayor, la degradación de las APIs cuando fallan y
el flujo web completo. Ninguna prueba sale a internet.

## Configuración

Todo lo ajustable vive en [`config/trayectos.php`](../config/trayectos.php): cuotas de las APIs,
constantes físicas, factores por tecnología, precios de respaldo y parámetros del motor de
sugerencia. Cambiar una constante del modelo obliga a subir `formula_version`; los viajes ya
apuntados conservan la suya.

## Aviso honesto

El coste de un trayecto es una **estimación**. Los modelos digitales del terreno tienen errores de
varios metros, el consumo homologado nunca coincide del todo con el real y el precio aplicado es el
de una gasolinera de referencia, no necesariamente el del repostaje. La calibración con repostajes
reales existe justamente para cerrar esa brecha. En un libro de cuentas entre amigos, que todos
acepten el número importa más que su exactitud física: por eso los kilómetros se pueden escribir a
mano y cualquier viaje se puede anular.
