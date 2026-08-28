# Developer scripts

Small, hand-runnable helper scripts for local development. Not part of the
shipped plugin — every developer can read, change and run them.

## `generate-bookings.php` — booking data generator

Creates `N` bookings plus the objects they need to be valid: one location, one
item, and one bookable timeframe that covers them. Each booking sits on its own
day, so they never overlap and are valid by construction
(`\CommonsBooking\Model\Booking::isValid()`).

The actual generating lives in a reusable class,
`tests/php/BookingGenerator.php`, which in turn builds everything through the
plugin's own test factory (`tests/php/CPTCreationTrait.php`), so generated data
matches what the plugin expects. This script is just the command-line wrapper.
Run `composer install` in the plugin directory first so that class is loadable.

The same `BookingGenerator` is used as a data source in the benchmark suite
(`tests/benchmark/BookingGeneratorBench.php`), which the CI benchmark workflow
runs — so the CLI seed data and the benchmarked data come from one place.

### Static dataset files

Instead of passing flags you can point at a JSON manifest describing the data
set. This is what the benchmark uses (`tests/benchmark/fixtures/benchmark-dataset.json`)
so its data is the same every run:

```json
{ "start": "2026-01-01", "count": 365, "hours": 0, "locations": 10,
  "lat": 52.52, "lon": 13.405, "distancekm": 15, "seed": 42 }
```

Add `"spread": N` to place the bookings within ±`N` days of `start` (past and
future) instead of marching forward from it — capacity is `(2·N + 1) × locations`
bookings.

`start` anchors the dates (booking range is `start` .. `start`+`count` days; the
covering timeframe is derived from that) and `seed` fixes the random location
placement, so the same file always produces the same data — identical on every
run and every branch, which is what the benchmark needs. Reproduce the exact
benchmark data locally with:

```bash
php scripts/generate-bookings.php --dataset=tests/benchmark/fixtures/benchmark-dataset.json --verify
```

### Running it

With this repo's `wp-env`:

```bash
npm run env -- run cli -- \
  php wp-content/plugins/commonsbooking/scripts/generate-bookings.php --count=10 --verify
```

Standalone (finds `wp-load.php` by itself, or set `WP_ROOT`):

```bash
php scripts/generate-bookings.php --count=100 --verify
```

### Options

| Option | Meaning |
| --- | --- |
| `--count=N` | Number of bookings to create (default 1). |
| `--hours=H` | Make each booking an `H`-hour slot instead of a full day. The start hour rotates across the day per booking, so one run spans many hours-of-day — handy for UTC/timezone testing. Default `0` = full-day bookings. |
| `--locations=N` | Spread the bookings across `N` locations (default 1). |
| `--start=DATE` | Anchor the bookings to start on `DATE` (e.g. `2026-01-01`) instead of today. |
| `--spread=N` | Place bookings within ±`N` days of the start date (past *and* future) instead of marching forward. Capacity is `(2·N + 1) × locations` bookings. |
| `--lat=Y --lon=X` | Give the locations coordinates centred on this point. |
| `--distancekm=D` | Scatter the locations randomly within `D` km of the centre (default `0` = all exactly at the centre). Needs `--lat`/`--lon`. |
| `--verify` | Check a few with `Booking::isValid()` and report. |
| `--cleanup` | Delete everything this script ever created, then exit. |
| `--help` | Show usage. |

Place bookings across a map (e.g. for map/geo testing) — 20 locations
scattered within 15 km of central Berlin, 200 bookings spread over them:

```bash
php scripts/generate-bookings.php --count=200 --locations=20 \
  --lat=52.52 --lon=13.405 --distancekm=15 --verify
```

For UTC/timezone testing, create hourly-slot bookings:

```bash
# 24 one-hour bookings, one per hour-of-day across consecutive days
php scripts/generate-bookings.php --count=24 --hours=1 --verify

# 10 two-hour slots
php scripts/generate-bookings.php --count=10 --hours=2 --verify
```

Change the author user id by editing the `CBGEN_AUTHOR` constant at the top of
the script.

### Suggested walk

```bash
php scripts/generate-bookings.php --count=1    --verify
php scripts/generate-bookings.php --count=10   --verify
php scripts/generate-bookings.php --count=100  --verify   # note the time printed
php scripts/generate-bookings.php --count=1000 --verify

php scripts/generate-bookings.php --cleanup                # remove it all again
```

Each run prints how long it took and how many bookings per second, so you can
measure before scaling up. This is the simple, readable path (~14 DB writes per
booking): fine up to a few thousand; for 100k it works but takes a while.
