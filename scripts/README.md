# Developer scripts

Local, hand-runnable helper scripts for the CommonsBooking plugin. These are
developer tools, not part of the shipped plugin — every developer can read,
change and run them.

## `generate-bookings.php` — scalable booking data generator

Creates `N` bookings plus the related object state they need to be **valid** in
CommonsBooking's own sense (`\CommonsBooking\Model\Booking::isValid()`): a
published location, a published item, and a bookable timeframe that covers each
booking's day. Bookings are placed on distinct back-to-back days, so they are
non-overlapping and valid by construction at any scale.

### How it reuses the codebase

- Fixtures and the `wp` backend call the plugin's own test factory
  (`tests/php/CPTCreationTrait.php`) — the same `wp_insert_post` +
  `update_post_meta` path the unit tests use.
- The fast `sql` backend does **not** re-describe the postmeta by hand. It
  creates one template booking through that same factory, reads the real
  postmeta back, and bulk-inserts clones of it. Blueprint comes from the
  codebase; throughput comes from raw `$wpdb` batch inserts.

Because it depends on the test factory, run `composer install` (with dev
dependencies) in the plugin directory first so the `CommonsBooking\Tests`
namespace is autoloadable.

### Running it

With this repo's `wp-env`:

```bash
npm run env -- run cli -- \
  php wp-content/plugins/commonsbooking/scripts/generate-bookings.php --count=10 --verify
```

Standalone (self-bootstraps WordPress by finding `wp-load.php`; set `WP_ROOT`
to override):

```bash
php scripts/generate-bookings.php --count=100 --backend=sql --verify
```

### Options

| Option | Meaning | Default |
| --- | --- | --- |
| `--count=N` | Number of bookings to create | `1` |
| `--backend=wp\|sql` | `wp` = test factory (valid, hooks, slow); `sql` = bulk insert (fast) | `wp` |
| `--verify` | Load a sample as `Model\Booking` and assert `isValid()` | off |
| `--author=ID` | Post author user id | `1` |
| `--batch=N` | `sql` backend rows per bulk INSERT | `2000` |
| `--cleanup` | Delete everything the generator ever created, then exit | — |
| `--help` | Show usage | — |

### Suggested scaling walk

```bash
php scripts/generate-bookings.php --count=1   --verify
php scripts/generate-bookings.php --count=10  --verify
php scripts/generate-bookings.php --count=100 --verify
# compare backends at 100 before going bigger:
php scripts/generate-bookings.php --count=100 --backend=wp
php scripts/generate-bookings.php --count=100 --backend=sql --verify
# then, once the numbers look right:
php scripts/generate-bookings.php --count=1000   --backend=sql --verify
php scripts/generate-bookings.php --count=100000 --backend=sql --verify

# remove all generated data afterwards:
php scripts/generate-bookings.php --cleanup
```

### Notes / limitations

- The `sql` backend relies on a single multi-row `INSERT` yielding consecutive
  auto-increment ids (InnoDB default). This is standard but worth knowing if you
  run against an exotic MySQL config.
- Bulk inserts bypass WordPress hooks and the object cache (the script flushes
  the cache afterwards). Any per-booking meta that the plugin's save hooks would
  normally make unique (e.g. booking codes) is cloned from the template rather
  than regenerated — fine for load/scale test data, not for exercising those
  hooks. Use `--backend=wp` when you need the full hook behaviour.
