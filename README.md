# Libro de Trayectos

A shared ledger for a group of friends' car journeys. It works out what each trip **actually costs**
— including the effect of elevation gain, which is not the same in a hybrid as in a diesel — and
splits it between the passengers using double-entry bookkeeping.

Installable on a phone as an app (PWA), iOS included, without going through the App Store.

**Running cost: €0.** Not one piece of the architecture requires a credit card.

**Live:** [trayectos.onrender.com](https://trayectos.onrender.com) — the free tier sleeps after 15
minutes of inactivity, so the first request may take up to a minute to wake the container.

*[Versión en español de este documento](docs/README.es.md).*

---

## Why it exists

A group sharing a car splits the fuel by eye: *put in fifteen euros each*. That fails for two
unrelated reasons, and this application tackles both.

**The physics.** A manufacturer's rated consumption applies to essentially flat ground. Climbing a
mountain pass demands energy the engine has to deliver, and only part of the descent is recovered —
a part that depends radically on the car's drivetrain. Using plain kilometres for a mountain trip is
off by around 50 %.

**The bookkeeping.** A group's accounts kept in phone notes or a spreadsheet always end up out of
balance: one duplicate entry, one deletion, one recalculation. And once the number has been wrong
even once, nobody trusts it again.

## What it does

- **Vehicles** — combustion, hybrid, plug-in hybrid and electric, each with its rated consumption,
  battery and ability to recover energy downhill.
- **Real routes** — distance and cumulative elevation from OpenRouteService, degrading automatically
  to a straight-line estimate plus Open Topo Data altitudes when the quota runs out.
- **Official fuel prices** from the Spanish Ministry for Ecological Transition, synchronised into a
  local copy; for EVs, the regulated PVPC rate from Red Eléctrica or whatever tariff you declare.
- **Cost per trip** = distance × consumption adjusted for terrain and load × price, frozen in an
  immutable snapshot the moment it is recorded.
- **Double-entry ledger** — every trip is a journal entry whose lines sum to exactly zero. Nothing is
  ever deleted; a mistake is corrected with a reversing entry.
- **Settlement plan** — who pays whom to bring every balance back to zero in the fewest payments.
- **Driver suggestion** — proposes the next driver by largest debt, filtered by who owns a car with
  enough seats, breaking ties on recent turns.
- **Calibration from real refuels** — the model tunes itself to the specific car by comparing what it
  predicted against what actually went into the tank.

## Engineering notes worth a look

If you are reading this as a code sample, these are the parts with actual decisions behind them:

- **The regeneration ceiling** — a hybrid coming down a 1,200 m pass does *not* recover 1,200 m of
  energy: it fills its small battery in the first few hundred metres and dumps the rest through the
  brakes. For a typical hybrid that ceiling is around **226 m** of elevation, and the model captures
  it instead of pretending otherwise. See
  [`TripCostCalculator`](app/Services/Costing/TripCostCalculator.php).
- **No balance column.** A stored per-user balance always drifts out of sync, so balances are not
  stored at all: they are the sum of the ledger lines, read through a SQL view.
- **The invariant is defended in the database, not only in the code.** A `DEFERRABLE INITIALLY
  DEFERRED` trigger validates the entry at `COMMIT`, so no script, no `tinker` session and no future
  migration can unbalance the ledger, whether or not it goes through the service layer.
- **Money is integers of cents throughout.** Not one float in any monetary operation. Fuel prices are
  thousandths of a euro, which is how the ministry publishes them.
- **The leftover cent** — €25 between three is 8.33 + 8.33 + 8.34. It is allocated by largest
  remainder, with ties broken on an order rotated by trip id, so it never lands on the same person
  twice running. See [`MoneySplitter`](app/Services/Ledger/MoneySplitter.php).
- **Idempotency by design** — every automatic entry carries a unique `external_ref` per group, so a
  double submit from a PWA on a bad connection returns the existing entry instead of duplicating it.
- **Graceful degradation** — running out of a free API quota can never stop someone recording a
  trip. [`RoutePlanner`](app/Services/Routing/RoutePlanner.php) steps down through real routing,
  straight-line plus real altitude, straight-line only, and hand-typed kilometres, telling the user
  which one is in play.

## Documentation

| Document | Contents |
|---|---|
| [docs/modelo-de-coste.md](docs/modelo-de-coste.md) | The physics of the calculation, per-technology factors and their limits (Spanish) |
| [docs/contabilidad.md](docs/contabilidad.md) | Double entry, invariants and why balances cannot drift (Spanish) |
| [docs/apis.md](docs/apis.md) | Real contracts of the five external APIs, verified, with their gotchas (Spanish) |
| [docs/despliegue.md](docs/despliegue.md) | Step-by-step production deployment, paying nothing (Spanish) |

## Stack

- **Laravel 13** (PHP 8.4) as a monolith: fewer services, fewer free tiers that can disappear
- **Blade + Alpine.js + Tailwind 4**, mobile first
- **PostgreSQL** in production (Neon or Supabase), **SQLite** locally and in tests
- **FrankenPHP** in a single container
- No paid dependencies and no third-party CDN: fonts are compiled and served from our own domain

## Running it locally

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run build
```

Sample data (a group of four people, four cars of different drivetrains and four trips through the
mountains north of Madrid):

```bash
php artisan db:seed --class=DemoSeeder
```

Sign in with `ana@ejemplo.es` and the password `trayectos`.

```bash
composer run dev
```

## Custom commands

```bash
php artisan trayectos:sync-prices                 # official fuel and electricity prices
php artisan trayectos:sync-prices --provinces=29  # a single province
php artisan trayectos:sync-prices --all           # full national download (~11,500 stations)
php artisan trayectos:calibrate                   # retune cars from their refuels
php artisan trayectos:icons                       # regenerate the PWA icons
```

## Tests

```bash
php artisan test
```

They cover the cost physics (including a hybrid's battery saturating on long descents), the cent
allocation, the ledger invariants, API degradation when the services fail, and the full web flow. No
test reaches the internet.

Note one deliberate asymmetry: the suite runs on SQLite, so the PostgreSQL trigger is not exercised
there — in tests the invariant is enforced by the service-layer exception instead.

## Configuration

Everything tunable lives in [`config/trayectos.php`](config/trayectos.php): API quotas, physical
constants, per-technology factors, fallback prices and the suggestion engine's parameters. Changing a
constant of the model requires bumping `formula_version`; trips already recorded keep their own.

## An honest disclaimer

A trip's cost is an **estimate**. Digital terrain models carry errors of several metres, rated
consumption never quite matches reality, and the price applied is that of a reference filling
station, not necessarily of the actual refuel. Calibration from real refuels exists precisely to
close that gap. In a ledger between friends, everyone accepting the number matters more than its
physical accuracy: that is why kilometres can be typed by hand and any trip can be reversed.
