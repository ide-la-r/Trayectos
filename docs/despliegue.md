# Despliegue con coste cero

Ninguno de estos servicios pide tarjeta de crédito. Tiempo estimado: media hora.

```
GitHub (repo + Actions)
   ├─ push a main ──────────► Render construye y despliega
   └─ cron cada 5 h ────────► POST /internal/sync-prices  (y despierta el contenedor)

Render · Free Web Service ──────► Neon · PostgreSQL free
  Docker · 512 MB · 750 h/mes      0,5 GB · sin tarjeta
  HTTPS y dominio incluidos
        ▲
        | ping cada 10 min
   Actions (06:00-21:00 UTC)
```

## 1. Base de datos — Neon

1. Cuenta en [neon.tech](https://neon.tech) (GitHub, sin tarjeta).
2. Proyecto nuevo, región **Frankfurt**.
3. Copia la cadena de conexión: `postgresql://usuario:clave@ep-xxx.eu-central-1.aws.neon.tech/neondb?sslmode=require`

El plan gratuito da 0,5 GB y 100 horas de cómputo al mes. Para un grupo de amigos sobra: lo que más
ocupa es el catálogo de gasolineras (~5 MB por provincia sincronizada).

**Alternativa**: Supabase (500 MB, tampoco pide tarjeta). Ambos suspenden el proyecto por
inactividad; el ping de keepalive.yml lo evita en horario diurno.

## 2. Aplicación — Render

1. Cuenta en [render.com](https://render.com) con GitHub. **No pide tarjeta para el plan gratuito.**
2. *New → Blueprint* y apunta al repositorio: `render.yaml` ya define el servicio.
3. Rellena las variables marcadas `sync: false`:

   | Variable | Valor |
   |---|---|
   | `APP_KEY` | `php artisan key:generate --show` |
   | `APP_URL` | `https://trayectos.onrender.com` |
   | `DB_URL` | la cadena de Neon |
   | `ORS_API_KEY` | alta gratuita en [openrouteservice.org/dev](https://openrouteservice.org/dev/) |
   | `MAIL_*` | Brevo (300 correos/día sin tarjeta), opcional al principio |

4. `INTERNAL_TASK_TOKEN` lo genera Render solo. **Cópialo**, hace falta en el paso 4.

El contenedor migra la base de datos al arrancar (`docker/entrypoint.sh`).

### Lo que hay que saber del plan gratuito

- **Duerme tras 15 minutos sin tráfico** y tarda 30-50 s en despertar. Se resuelve en el paso 3.
- 750 horas al mes; despierto las 24 horas consume ~730, así que el paso 3 lo limita a una franja.
- 512 MB de RAM. FrankenPHP en un solo proceso y OPcache configurado van holgados.
- **El Postgres de Render caduca a los 30 días**: por eso la base de datos va en Neon.
- Los *cron jobs* de Render son de pago: por eso el cron va en GitHub Actions.

## 3. Mantenerlo despierto — GitHub Actions

Lo hace [`.github/workflows/keepalive.yml`](../.github/workflows/keepalive.yml) sin configurar nada
fuera del repositorio: un ping a `/up` cada 10 minutos **entre las 06:00 y las 21:00 UTC**. Solo
necesita el secreto `APP_URL`, el mismo que ya usa el cron de tareas.

La franja es deliberada, y esta es la cuenta que la justifica: un ping continuo lo mantendría
despierto siempre, pero el plan da **750 horas de instancia al mes** y estar despierto las 24 horas
consume unas **730**. Cabe, pero sin margen, y el castigo por agotarlas es que Render **suspende
todos los servicios gratuitos hasta el mes siguiente**. Con 15 horas al día son unas 450, y fuera de
la franja el contenedor duerme y no gasta nada.

`/up` es el endpoint de salud nativo de Laravel y vive fuera del grupo `web`: responde sin abrir
sesión ni tocar la base de datos. Ese detalle es el que mantiene a Neon dormido, porque sus 100 horas
de cómputo mensuales solo corren cuando alguien consulta de verdad.

**Aviso honesto:** la programación de GitHub Actions es *best effort* y puede retrasarse varios
minutos con carga alta, así que algún arranque en frío suelto es posible. Si quisieras garantía
total, un monitor de [UptimeRobot](https://uptimerobot.com) (50 monitores gratis, sin tarjeta)
apuntando a `/up` cada 5 minutos es más fiable, a cambio de volver a las ~730 horas.

## 4. Tareas programadas — GitHub Actions

En *Settings → Secrets and variables → Actions* del repositorio:

| Secreto | Valor |
|---|---|
| `APP_URL` | `https://trayectos.onrender.com` |
| `INTERNAL_TASK_TOKEN` | el que generó Render |

`.github/workflows/cron.yml` se ejecuta cuatro veces al día: despierta el servicio con reintentos,
sincroniza los precios oficiales y recalibra los coches. Gratis e ilimitado en repos públicos; 2.000
minutos al mes en privados, de los que esto consume unos pocos.

Para probarlo sin esperar: pestaña *Actions → Tareas programadas → Run workflow*.

## 5. Primer arranque

```bash
# Comprobar que responde
curl https://tu-app.onrender.com/up

# Primera carga de precios (o lánzala desde Actions)
curl -X POST -H "Authorization: Bearer $INTERNAL_TASK_TOKEN" \
     https://tu-app.onrender.com/internal/sync-prices
```

Después, desde el móvil: regístrate, crea el grupo, pasa el código de invitación al resto y añade tu
coche. En iOS, *Compartir → Añadir a pantalla de inicio* (la propia aplicación lo recuerda).

## 6. Avisos en el móvil (opcional)

Hacen falta un par de claves VAPID. Se generan **una vez**:

```bash
php artisan trayectos:vapid
```

Las dos líneas que imprime van al `.env` local, y **otras propias** a Render → *Environment*, junto
con `VAPID_SUBJECT` (un `mailto:` tuyo o la URL de la aplicación). La privada es un secreto: no la
pegues en un chat ni la subas al repositorio.

No las cambies después. Cambiarlas invalida todas las suscripciones, y cada persona tendría que
volver a activar los avisos desde el panel.

Sin claves configuradas la función no existe: el interruptor no aparece y no se manda nada. Es
gratis — ni Apple ni Google cobran por esto.

**En iPhone los avisos sólo funcionan con la aplicación instalada en la pantalla de inicio.** Desde
Safari, sin instalar, el navegador ni siquiera define la interfaz; la aplicación lo detecta y lo
explica en vez de enseñar un interruptor que no haría nada.

Los avisos salen dentro de la petición de quien apunta el viaje, no por una cola: en el plan
gratuito de Render no hay trabajador, y un aviso que espera al cron llegaría seis horas tarde. Son
unas pocas peticiones en paralelo, y si fallan se anotan en el registro sin tocar el viaje.

## Coste total

| Concepto | Precio |
|---|---|
| Render Free Web Service | 0 € |
| Neon PostgreSQL Free | 0 € |
| GitHub Actions (cron y despertador) | 0 € |
| OpenRouteService, Open Topo Data, Photon, MITECO, REE | 0 € |
| **Total** | **0 €/mes** |

Un dominio propio, si alguna vez lo quieres, es lo único que costaría dinero (~10 €/año). Render
admite dominios personalizados con HTTPS gratis en el plan libre.

## Si algún tier gratuito cambia

Es un riesgo real: los planes gratuitos se mueven. El diseño lo tiene en cuenta.

- **Render deja de ser viable** → el `Dockerfile` es estándar; cualquier PaaS con Docker sirve
  (Fly.io, Koyeb, Railway… aunque hoy pidan tarjeta).
- **Neon deja de ser viable** → Supabase, o incluso SQLite en un volumen persistente: sólo cambia
  `DB_CONNECTION`.
- **ORS cierra el plan libre** → el planificador ya funciona sin él, degradando a Open Topo Data y a
  entrada manual.

Lo único difícil de sustituir es la API del Ministerio, y esa es pública por ley.
