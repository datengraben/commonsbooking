# CommonsBooking — WordPress Playground demo

A one-click [WordPress Playground](https://wordpress.github.io/wordpress-playground/)
blueprint that boots a throwaway WordPress in the browser, installs CommonsBooking,
and fills it with **sample bookings whose dates are relative offsets from the
current time** — so the demo always looks "current", whenever it is opened.

## Try it

Open this URL (it encodes the raw `blueprint.json`):

<https://playground.wordpress.net/#https%3A%2F%2Fraw.githubusercontent.com%2Fdatengraben%2Fcommonsbooking%2Fclaude%2Fwordpress-playground-booking-blueprint-uuyfi1%2Fplayground%2Fblueprint.json>

> The URL points at the blueprint on the development branch. After this is merged,
> swap `claude/wordpress-playground-booking-blueprint-uuyfi1` for `master`.

You land — already logged in as `admin` — on the **Bookings** admin list, showing
the generated bookings. The site front page (`Book an item`) shows the
`[cb_items_table]` overview for exploring the booking calendar.

## What the blueprint does

`blueprint.json` runs these steps (see the
[Blueprints API](https://wordpress.github.io/wordpress-playground/blueprints-api/)):

1. **`preferredVersions`** — pins PHP `8.1` + WordPress `6.9` (matches `.wp-env.json`)
   so the demo does not drift as Playground's defaults move.
2. **`login`** — logs in as `admin` / `password`.
3. **`setSiteOptions`** — sets `timezone_string` **before** any data is generated,
   so `current_time()` offsets resolve in the intended zone (`Europe/Berlin`).
4. **`installPlugin`** — installs & activates CommonsBooking from the
   [wordpress.org plugin directory](https://wordpress.org/plugins/commonsbooking/).
5. **`writeFile`** — fetches [`test-data.php`](test-data.php) by URL and drops it into `wp-content/mu-plugins/`.

## The data generator

[`test-data.php`](test-data.php) is a must-use plugin, so it runs
inside a **fully booted WordPress request** — no `runPHP` step and no static WXR
import. On the first request after boot it:

- anchors everything to a single `current_time('timestamp')` value
  (see the [`current_time()` docs](https://developer.wordpress.org/reference/functions/current_time/)),
- creates locations, items and wide bookable timeframes, and
- inserts bookings at **relative offsets** via `strtotime('%+d days', $now)` —
  never hardcoded dates: a completed past booking, one ending today, one starting
  today, upcoming ones, and a pending (unconfirmed) request.

A one-shot option guard makes generation run once per instance; because Playground
rebuilds a clean site on every session, the data is **regenerated fresh each
session** with dates relative to that moment.

`blueprint.json` references this file by URL in its `writeFile` step (a `url`
resource), so `test-data.php` is the single source of truth — just edit it, no
re-embedding needed. The URL points at `master`, so it resolves once this is
merged (before then, test against the file on the development branch).

## Scope / roadmap

This blueprint installs the **released** CommonsBooking from wordpress.org, which
needs no build step or hosting. Installing the **latest `master` (nightly) build**
instead is a planned follow-up: the plugin's `vendor-prefixed/` and built assets
are gitignored, so it requires a CI workflow (reusing `.github/actions/build-plugin`)
to publish a built zip to a stable URL that `installPlugin` can then reference.
