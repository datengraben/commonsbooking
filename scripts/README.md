# Developer scripts

Small, hand-runnable helper scripts for local development. Not part of the
shipped plugin — every developer can read, change and run them.

## `generate-bookings.php` — booking data generator

Creates `N` bookings plus the objects they need to be valid: one location, one
item, and one bookable timeframe that covers them. Each booking sits on its own
day, so they never overlap and are valid by construction
(`\CommonsBooking\Model\Booking::isValid()`).

It reuses the plugin's own test factory (`tests/php/CPTCreationTrait.php`) for
everything, so generated data matches what the plugin expects. Run
`composer install` in the plugin directory first so that factory is loadable.

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
| `--verify` | Check a few with `Booking::isValid()` and report. |
| `--cleanup` | Delete everything this script ever created, then exit. |
| `--help` | Show usage. |

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
