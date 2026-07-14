# CommonsBooking Extensibility Plan

> **Goal:** Set CommonsBooking core up for success as the *booking engine* of an
> ecosystem — solid at its core purpose (managing and booking shared common
> goods) and cleanly extensible so third parties can adapt it to their specific
> booking use case (smart locks, vehicle sharing, PDF confirmations, custom
> pricing, external calendars, …) **without forking core or overriding
> templates**.

Status: proposal / design document. Author basis: audit of the current
`master` (v2.10.x → 2.11 line) merged with `wielebenwir/master`.

---

## 1. What CommonsBooking is

CommonsBooking is a WordPress plugin for the **management and booking of common
goods** — associations, collectives and individuals share items (cargo bikes,
tools, rooms, equipment) with a community. It is built around a small, stable
domain model expressed as WordPress custom post types (CPTs):

| Concept | CPT | Role |
|---|---|---|
| **Item** | `cb_item` | The shared good that is lent out |
| **Location** | `cb_location` | The physical place an item is lent from |
| **Timeframe** | `cb_timeframe` | Groups all events: availability, bookable periods, holidays, **and bookings** |
| **Booking** | `cb_timeframe` (`type = BOOKING_ID`) | A concrete slot: item @ location, for a user, in a time range |
| **Restriction** | `cb_restriction` | Usage notes / breakdowns that overlay a bookable timeframe |
| **Map** | — | Frontend discovery of items/locations |

Supporting subsystems:

- **Availability engine** — `Model\Calendar`, `Model\Day`, `Model\Week`,
  `Model\Timeframe`, `Repository\*` compute what can be booked when.
- **Booking lifecycle** — `Wordpress\CustomPostType\Booking` (request handling,
  save, status transitions) + `Model\Booking` (`cancel()`, validation, codes).
- **Booking rules** — `Service\BookingRule` / `BookingRuleApplied` enforce
  policy (max bookings, chains, …).
- **Messages** — `Messages\Message` and subclasses (confirmation, cancellation,
  reminder, booking-code emails, iCal).
- **Booking codes** — `Service\BookingCodes` generate offline verification codes.
- **APIs** — a REST **CommonsAPI** (`API\*`) and a **GBFS** feed family
  (`API\GBFS\*`) for interoperability with mobility/sharing platforms.
- **Templates & template tags** — `includes/Template*`, `CB\CB`, `templates/*`.

### The ecosystem around it

CommonsBooking is deliberately a **platform**, not a closed app. Real
downstream consumers already exist and drive the extensibility requirements:

- **`cb-map`** — map/discovery front-end that consumes CB data.
- **`cb-vehicles`** — adds vehicle-specific GBFS feeds on top of core
  (see upstream issue #2143 → PR #2154: a `commonsbooking_gbfs_feeds` filter was
  added precisely so this plugin can register new feeds).
- **flotte-berlin** — a fork/deployment contributing bookings fixes upstream
  (e.g. PR #2218).
- **Smart-lock integrations** — the concrete motivation behind the open request
  for a `commonsbooking_booking_confirmed` action hook (issue #2052): "people
  that are currently extending CB to work with automatic locking systems."
- **Custom deployments** (this fork, `datengraben/commonsbooking`, and many
  library-of-things installations) that need per-site behavior.

The pattern is clear: **extensions want to react to booking events, augment
data feeds, add metadata, and adjust availability/pricing — and today they
mostly can't without touching core.** Hooks have historically been added
reactively, one issue at a time (#2130 custom meta, #1938 tag object context,
#2143 GBFS feeds, #2156 API response, #2052 booking action — still open). This
plan turns that reactive trickle into a deliberate, documented extension
surface.

---

## 2. How WordPress plugins expose extensibility (the model we follow)

WordPress has one idiomatic mechanism, and mature booking/e‑commerce plugins
(WooCommerce, Easy Digital Downloads, The Events Calendar) all apply it the same
way. We adopt their conventions:

- **Actions** (`do_action`) — "something happened; react to it." Side effects:
  notify a lock system, write a log, sync an external calendar. No return value.
- **Filters** (`apply_filters`) — "here is a value; reshape it before I use it."
  Must return the (possibly modified) value. Used for data, markup, config,
  query args, permissions.
- **Pluggable data via CPT/meta** — third parties add their own meta through a
  registration filter rather than editing forms.
- **Overridable presentation** — template resolution runs through a filter so a
  theme/plugin can substitute a template part.

**Best-practice conventions (WooCommerce-grade) we commit to:**

1. **Namespaced prefix**: every hook starts with `commonsbooking_`.
2. **Consistent verb/tense**: `..._before_x` / `..._after_x` for render points;
   past-tense `..._x_confirmed` / `..._x_cancelled` for lifecycle events;
   `..._x_args` / `..._x_query` for query shaping.
3. **Rich, stable payloads**: pass the model object *and* its ID, not just a
   scalar — so callbacks don't have to re-query. (Core already started doing
   this in 2.10.8 for template hooks; we make it the rule.)
4. **Fire once, at the right layer**: lifecycle hooks belong in the **model /
   service layer** (so they fire regardless of whether the change came from the
   frontend calendar, the admin, the REST API, or WP‑CLI) — not only in the
   admin `save_post` path.
5. **Filters must always return a value**; document the default and the type.
6. **Backward compatibility is a contract**: once shipped, a hook name and its
   signature are stable. Deprecate via `apply_filters_deprecated` /
   `do_action_deprecated`, never silently.
7. **Every public hook is documented and, where it guards behavior, unit-tested.**

---

## 3. Current extension surface (audit)

CommonsBooking already exposes ~26 filters and ~6 actions. They cluster well in
some areas and are absent in the most important one.

**Reasonably covered today:**

- *Presentation*: `commonsbooking_before/after_*` template actions (item,
  location, booking, calendar headers, timeframe) — now with object context;
  `commonsbooking_get_template_part`, `commonsbooking_template_tag`,
  `commonsbooking_tag_{key}_{property}`, `commonsbooking_mobile_calendar_month_count`.
- *Email*: `commonsbooking_mail_to/subject/body/attachment`,
  `commonsbooking_mail_sent`, reminder-specific filters, iCal title/description.
- *Metadata*: `commonsbooking_custom_metadata` (add CMB2 fields to any CPT).
- *Permissions*: `commonsbooking_manager_roles`, `commonsbooking_admin_roles`,
  `commonsbooking_privileged_roles`, `commonsbooking_isUserAdmin`,
  `commonsbooking_isCurrentUserSubscriber`.
- *Policy/config*: `commonsbooking_booking-rules`, `commonsbooking_disableCache`.
- *APIs*: `commonsbooking_gbfs_feeds`, `commonsbooking_booking_filter` (list view).

**The critical gap — the booking lifecycle has no hooks at all.**

The core purpose of the plugin — a booking being created, confirmed, changed,
cancelled — emits **nothing** an extension can subscribe to:

- `Wordpress\CustomPostType\Booking::savePost()` fires the confirmation mail at
  line ~162 but no action. (`src/Wordpress/CustomPostType/Booking.php`)
- `Booking::handleBookingRequest()` inserts/updates the booking post (lines
  ~231–400) with no pre/post hooks and no way to veto or annotate a request.
- `Booking::postUpdated()` handles status transitions (lines ~504–528) silently.
- `Model\Booking::cancel()` updates the DB directly and mails the user
  (`src/Model/Booking.php:96`) with no `..._cancelled` action.

That single gap is why smart-lock, external-calendar and audit integrations have
to override templates or fork. It is the highest-value work in this plan.

Secondary gaps: availability/query results are not filterable, booking-code
generation isn't pluggable, item/location repository queries can't be adjusted,
and the CommonsAPI response (unlike GBFS) still lacks a filter (#2156).

---

## 4. Proposed extension-point catalog

Concrete hooks to add, grouped by subsystem. Names follow §2. Each lifecycle
action passes the model object plus IDs; each filter documents its default.

### 4.1 Booking lifecycle — **Priority 1** (closes #2052)

Fire from the **model/service layer** so they trigger for frontend, admin, REST
and CLI alike. Introduce a small internal `Booking::transitionStatus()` /
`fireLifecycleHook()` helper invoked by both `savePost`, `postUpdated`, and
`Model\Booking::cancel()` to avoid double-firing.

| Hook | Type | Signature | When |
|---|---|---|---|
| `commonsbooking_booking_before_save` | filter | `array $postarr, ?Booking $existing` | Before insert/update in `handleBookingRequest` — lets extensions annotate/adjust the booking (e.g. inject meta). |
| `commonsbooking_booking_created` | action | `int $booking_id, \CommonsBooking\Model\Booking $booking` | A new booking post is created (any status). |
| `commonsbooking_booking_confirmed` | action | `int $booking_id, \CommonsBooking\Model\Booking $booking` | Status → `confirmed` (the exact hook #2052 asks for; smart-lock trigger). |
| `commonsbooking_booking_cancelled` | action | `int $booking_id, \CommonsBooking\Model\Booking $booking` | `Model\Booking::cancel()` completes. |
| `commonsbooking_booking_status_changed` | action | `int $booking_id, string $old, string $new, \CommonsBooking\Model\Booking $booking` | Any status transition (superset; audit/logging). |
| `commonsbooking_booking_request_denied` | action | `\Exception $reason, array $requestData` | A request was rejected (double-booking, rule violation). |
| `commonsbooking_can_cancel_booking` | filter | `bool $canCancel, \CommonsBooking\Model\Booking $booking, \WP_User $user` | Override cancellation policy. |

### 4.2 Availability & query engine — **Priority 2**

Let extensions shape *what is bookable* (custom pricing plugins, blackout
integrations, capacity rules) without reimplementing the calendar.

| Hook | Type | Signature | Where |
|---|---|---|---|
| `commonsbooking_calendar_data` | filter | `array $calendarData, \CommonsBooking\Model\Calendar $calendar` | `Model\Calendar` / `View\Calendar` before render. |
| `commonsbooking_day_availability` | filter | `array $slots, \CommonsBooking\Model\Day $day, int $itemId, int $locationId` | `Model\Day` slot computation. |
| `commonsbooking_bookable_timeframes` | filter | `array $timeframes, int $itemId, int $locationId` | `Repository\Timeframe`/`BookablePost` result. |
| `commonsbooking_is_timeframe_bookable` | filter | `bool $bookable, \CommonsBooking\Model\Timeframe $tf, \WP_User $user` | `Timeframe::isBookable()`. |
| `commonsbooking_booking_query_args` | filter | `array $args, string $context` | Central point in `Repository\Booking` query builders. |

### 4.3 Booking codes — **Priority 3**

Make offline-verification codes pluggable (some deployments want deterministic
codes tied to external lock systems, per #2052's lock-code use case).

| Hook | Type | Signature |
|---|---|---|
| `commonsbooking_generate_booking_code` | filter | `?string $code, \CommonsBooking\Model\Timeframe $tf, int $date` (return non-null to supply your own) |
| `commonsbooking_booking_code_pool` | filter | `array $wordPool` (customize the code alphabet/word list) |

### 4.4 Data model / repositories — **Priority 3**

| Hook | Type | Signature | Where |
|---|---|---|---|
| `commonsbooking_item_query_args` / `commonsbooking_location_query_args` | filter | `array $args` | `Repository\Item` / `Repository\Location`. |
| `commonsbooking_post_types` | filter | `array $cpts` | `Plugin::getCustomPostTypes()` — register related CPTs / relations. |
| `commonsbooking_map_item_data` | filter | `array $data, \WP_Post $item` | `Map\*` before serialization. |

### 4.5 APIs — **Priority 2** (parity with GBFS; closes #2156)

GBFS already has `commonsbooking_gbfs_feeds` and per-feed response filters. Give
the CommonsAPI the same treatment.

| Hook | Type | Signature |
|---|---|---|
| `commonsbooking_api_item_response` | filter | `array $data, \WP_Post $item` |
| `commonsbooking_api_availability_response` | filter | `array $data, array $query` |
| `commonsbooking_gbfs_{feed}_response` | filter | `array $feed` (generalize the pattern already used for feed registration to each feed body) |

### 4.6 Presentation — **already strong; small additions**

- `commonsbooking_booking_form_fields` (filter) — add fields to the frontend
  booking form so extensions capture extra data at booking time.
- `commonsbooking_before/after_booking_form` (actions) — render points around
  the form.

---

## 5. Design principles for this work

1. **Model layer is the source of truth for events.** Lifecycle actions fire in
   `Model\Booking` / a dedicated `Service\BookingLifecycle`, never only in the
   admin `save_post` callback. This is what makes REST/CLI/admin/frontend all
   emit the same events — the property extensions depend on.
2. **Idempotent firing.** Guard against WordPress's double-save behavior (the
   codebase already uses `hasRunBefore()` and `remove_action` self-unhooking —
   reuse those patterns so a hook fires exactly once per real transition).
3. **Pass objects, not just scalars.** Every model hook gets the hydrated
   `Model\*` instance; filters document default value + type.
4. **Filters are safe by default.** A no-op callback returning the input must
   leave behavior unchanged. Never require a filter to be present.
5. **No breaking changes.** Existing hook names/signatures are frozen. New hooks
   are additive. Any future rename goes through `*_deprecated` wrappers.
6. **Document + test every public hook.** A hook without a docs entry and (for
   behavior-guarding hooks) a test does not ship.

---

## 6. Phased roadmap

**Phase 0 — Foundation (docs + convention).**
Adopt this document. Add a "Hook reference" appendix generated/maintained
alongside `docs/*/advanced-functionality/hooks-and-filters.md`. Define the
naming convention in `TECHNICAL.md`. Establish the `Service\BookingLifecycle`
helper and a test pattern (`WP_UnitTestCase` asserting `did_action()` and filter
application).

**Phase 1 — Booking lifecycle actions (closes #2052).**
Implement §4.1. This is the single highest-impact deliverable and unblocks the
smart-lock ecosystem. Ship with docs + tests + a worked example
(`commonsbooking_booking_confirmed` → call an external lock API).

**Phase 2 — Availability & API filters.**
Implement §4.2 and §4.5. Enables custom pricing/capacity extensions and gives
CommonsAPI response parity with GBFS.

**Phase 3 — Booking codes, repositories, form fields.**
Implement §4.3, §4.4, §4.6. Rounds out the surface for deployment-specific
customization.

**Phase 4 — Consolidate & document.**
Publish a complete, versioned hook reference; add an "Extending CommonsBooking"
developer guide with end-to-end examples (smart lock, extra booking metadata,
custom feed). Consider a lightweight `commonsbooking-extension-boilerplate`.

Each phase is independently shippable and backward compatible.

---

## 7. Testing, docs & compatibility strategy

- **Tests:** for every behavior-guarding hook, a PHPUnit test that (a) asserts
  the action fires with the documented arguments (`did_action`, spy callbacks)
  and (b) asserts a filter actually alters the outcome. Lifecycle tests cover
  all four entry paths (frontend request, admin save, REST, `Model::cancel`).
- **Docs:** extend `hooks-and-filters.md` (EN + DE) with each new hook, its
  signature, default value, "since" version, and a copy-paste example. Keep the
  existing "Hooks in the context of an object" tone.
- **Versioning:** introduce hooks in minor releases; note them in the changelog
  under a dedicated "For developers" heading (the readme already has this).
- **Backward compatibility:** never change a shipped signature; use
  `apply_filters_deprecated`/`do_action_deprecated` if a hook must evolve.

---

## 8. Summary

CommonsBooking is already a well-factored domain platform with a good
*presentation* and *email* extension surface, but its **core purpose — the
booking lifecycle — is currently a black box** to extensions. The community is
asking for exactly the hooks this gap implies (smart locks #2052, vehicle feeds
#2143, API responses #2156, custom meta #2130). By (1) adding lifecycle actions
in the model layer, (2) making availability/queries and API responses
filterable, and (3) making booking codes and forms pluggable — all additively,
documented and tested — CommonsBooking becomes a booking *engine* that people
can adapt to their specific use case without forking core.
