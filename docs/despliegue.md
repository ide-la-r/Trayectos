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
        │ ping cada 5 min
   UptimeRobot free
```

## 1. Base de datos — Neon

1. Cuenta en [neon.tech](https://neon.tech) (GitHub, sin tarjeta).
2. Proyecto nuevo, región **Frankfurt**.
3. Copia la cadena de conexión: `postgresql://usuario:clave@ep-xxx.eu-central-1.aws.neon.tech/neondb?sslmode=require`

El plan gratuito da 0,5 GB y 100 horas de cómputo al mes. Para un grupo de amigos sobra: lo que más
ocupa es el catálogo de gasolineras (~5 MB por provincia sincronizada).

**Alternativa**: Supabase (500 MB, tampoco pide tarjeta). Ambos suspenden el proyecto por
inactividad; el ping de UptimeRobot lo evita.

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
- 750 horas al mes; un contenedor 24/7 consume ~744. Cabe justo, y el sueño ayuda.
- 512 MB de RAM. FrankenPHP en un solo proceso y OPcache configurado van holgados.
- **El Postgres de Render caduca a los 30 días**: por eso la base de datos va en Neon.
- Los *cron jobs* de Render son de pago: por eso el cron va en GitHub Actions.

## 3. Mantenerlo despierto — UptimeRobot

1. Cuenta en [uptimerobot.com](https://uptimerobot.com) (50 monitores gratis, sin tarjeta).
2. Monitor HTTP(s) a `https://tu-app.onrender.com/up` cada **5 minutos**.

Sirve de vigilancia y de despertador. `/up` es el endpoint de salud de Laravel: responde sin tocar
sesión ni vistas.

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

## Coste total

| Concepto | Precio |
|---|---|
| Render Free Web Service | 0 € |
| Neon PostgreSQL Free | 0 € |
| GitHub Actions | 0 € |
| UptimeRobot Free | 0 € |
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
