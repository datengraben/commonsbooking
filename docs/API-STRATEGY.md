# CommonsBooking Public PHP API Strategy

> Status: **Proposal / plan** — no code changes yet. This document defines *how* CommonsBooking
> exposes a stable API to third-party developers, modelled on the WooCommerce approach, adapted
> to CommonsBooking's actual architecture.

## 1. Decision

CommonsBooking follows **the WooCommerce model**:

1. **Distributed only as a WordPress plugin.** One installed copy per site. It is *not* a Composer
   library that extensions vendor into their own `vendor/` tree. Publishing on Packagist is fine,
   but only as `"type": "wordpress-plugin"` (installed into `wp-content/plugins`), never `require`d
   into an extension.
2. **A deliberately small, versioned, documented public surface** sits on top of a much larger
   internal one. Everything not explicitly marked public may change without notice.
3. **Extensions declare a dependency, they do not bundle core.** They rely on the plugin being
   installed and integrate through the public surface (functions, hooks, REST).

### Why not a standalone / vendorable package (recap)

WordPress runs every plugin in **one shared PHP process and one global namespace**. If several
extensions each vendored a copy of `CommonsBooking\...`, the classes would collide (fatal
redeclare) or resolve to whichever autoloader registered first (version roulette). A vendored copy
is also *dead code*: the API only means anything wired to the one running plugin — its hooks
registered, its post types declared, its single database connection. Therefore the plugin **is**
the package. (A WP-agnostic domain library is the only thing that *could* be vendored safely; that
is an explicit non-goal here — see §12.)

## 2. Current state assessment

What exists today (grounded in the codebase):

| Area | Today | Implication |
| --- | --- | --- |
| Autoloading | PSR-4 `CommonsBooking\` → `src/` | Good base. But *everything* under it is currently reachable and implicitly "public". |
| Domain layering | `Model/`, `Repository/`, `Service/` split already exists | The right seam for a public/internal line already exists. |
| Repositories | Static method collections, e.g. `Repository\Booking::getByDate()`, `::getForUser()` | Public **read** API. Static, not instance CRUD — keep it that way. |
| Services | Static, e.g. `Service\Booking::cleanupBookings()`, `::sendReminderMessage()` | Public **command** API surface. |
| Models | `Model\Booking`, `Model\Item`, `Model\Location`, `Model\Timeframe`, … | Public **return types / DTOs**. |
| Bootstrap | `includes/Plugin.php`: `new Plugin(); ->init(); ->initRoutes(); ->initBookingcodes();` | No accessor, no `commonsbooking_loaded` action, no container. |
| Functions API | `includes/`: `commonsbooking_parse_template()`, `commonsbooking_isUserAllowedToEdit()`, `commonsbooking_getCBType()` | Precedent for a **procedural facade** (the `wc_get_*` analogue). |
| Hooks | 19 `apply_filters`, 5 `do_action` (2 third-party: `cmb2_init`, `wpml_*`) | Extension surface is **thin**; few lifecycle events; none versioned. |
| REST | `commonsbooking/v1` + GBFS, registered on `rest_api_init`, gated by settings flag; route list hardcoded in `Plugin::initRoutes()` | Versioned already. No filter for extensions to add routes. Auth via `apikey` / `ApiShares`. |
| Vendor isolation | Strauss prefixes deps into `CommonsBooking\` (e.g. `CommonsBooking\Opis\…`, `CommonsBooking\Symfony\…`) | Prevents cross-plugin version clashes. **But** prefixed types must never leak into the public API. |

Gaps to close, in priority order: (a) no explicit public/internal boundary; (b) no accessor /
`commonsbooking_loaded` lifecycle for extensions; (c) thin hook surface around the core booking
lifecycle; (d) no way for extensions to register REST routes; (e) no written BC policy.

## 3. The public API surface (what becomes `@api`)

WooCommerce promises a *small* surface and keeps the rest internal. CommonsBooking's promised
surface, in four tiers:

### Tier 1 — Procedural facade (the most stable contract)

Thin wrapper functions are the truly stable entry points, mirroring `wc_get_product()` /
`wc_get_order()`. They let us refactor the classes behind them without breaking callers. Proposed
(names illustrative — to be finalised in the implementation pass):

- `commonsbooking_get_booking( int $post_id ): ?\CommonsBooking\Model\Booking`
- `commonsbooking_get_item( int $post_id ): ?\CommonsBooking\Model\Item`
- `commonsbooking_get_location( int $post_id ): ?\CommonsBooking\Model\Location`
- `commonsbooking_get_bookings_for_user( \WP_User $user, … ): array`
- `commonsbooking()` — accessor returning the API entry object (see §5).

These wrap the existing static repositories; they add no new logic.

### Tier 2 — Read API: Repositories + Models

- `Repository\*` **selected static query methods** become `@api` (e.g. `Booking::getByDate()`,
  `Booking::getForUser()`, `Item`, `Location`, `Timeframe`). Internal/query-plumbing methods stay
  `@internal`.
- `Model\*` classes are `@api` as **return types and read accessors**. Their getters are the
  contract; direct property access and setters that mutate WP state are `@internal`.

### Tier 3 — Command API: Services

- `Service\*` operations that represent domain commands become `@api` (booking cleanup,
  messaging/reminders, booking-code generation). Scheduling/cron wiring stays `@internal`.

### Tier 4 — Hooks + REST (the WP-native contracts)

- **Hooks**: the documented actions/filters (see §6) are a versioned contract with `@since`.
- **REST**: `commonsbooking/v1` and the GBFS routes are a versioned contract (see §7).

## 4. What stays `@internal`

Explicitly *not* promised, may change any release:

- `src/Wordpress/` (CPT registration, meta boxes, admin glue), `src/Settings/`, `src/View/`,
  `src/Migration/`, `src/Map/`, `includes/OptionsArray.php`, `includes/Admin.php`.
- `src/Plugin.php` bootstrap methods (they are wiring, not contract).
- Everything Strauss-prefixed: `CommonsBooking\Opis\…`, `CommonsBooking\Symfony\…`,
  `CommonsBooking\CMB2\…`, etc. — **these types must never appear in an `@api` signature** (§8).
- Any method without an `@api` tag. Absence of `@api` means "internal" by default.

Marking convention: `@api` + `@since x.y.z` on promised symbols; `@internal` on anything public in
PHP-visibility terms that is *not* part of the contract (WooCommerce does exactly this on its
container and data stores).

## 5. Accessor & extension lifecycle

Introduce a minimal, WC-style entry without a heavy DI container (CommonsBooking is static-based;
a full container is out of scope — §12):

1. **`commonsbooking()` accessor** returning a small facade/registry object (a lazy singleton).
   Initially it can simply expose the Tier-1 functions and version info. It gives us a single,
   stable object to grow later without changing call sites.
2. **`do_action( 'commonsbooking_loaded' )`** fired once, after core is fully initialised
   (post-`init`, routes registered). This is the officially supported point for extensions to boot.
   Documented as `@since`.
3. **Version constant already exists** (`COMMONSBOOKING_VERSION`); expose it via the accessor too
   (`commonsbooking()->version()`), so extensions can feature-detect.

Extension integration contract (documented for third parties):

```php
// In an extension's main file:
// Requires Plugins: commonsbooking            // WP 6.5+ dependency header
add_action( 'commonsbooking_loaded', function () {
    if ( ! function_exists( 'commonsbooking' ) ) {
        return; // core inactive/old — bail quietly
    }
    // ... integrate via public functions + hooks ...
} );
```

## 6. Hook strategy (primary extension mechanism)

Hooks are the lowest-coupling, most WP-native extension path and should carry most integration
weight. Plan:

1. **Inventory & document** the existing 19 filters / 3 first-party actions; add `@since` to each
   and publish a hook reference (generated doc).
2. **Add core lifecycle hooks** where extensions actually need them but none exist today — around
   the booking lifecycle in particular. Candidate events (to be confirmed against the code paths
   in the implementation pass):
   - `commonsbooking_booking_created` / `_confirmed` / `_cancelled` (actions, pass the
     `Model\Booking`).
   - `commonsbooking_booking_status_changed` (action).
   - A filter to let extensions **register REST routes** (see §7).
   - A filter around availability/query results for extension-driven constraints.
3. **Naming**: keep the established `commonsbooking_` prefix; `snake_case`; actions named for the
   event, filters named for the value they filter.
4. **Deprecation**: retire hooks via `_deprecated_hook()` on a ≥2-minor cycle (§9).

## 7. REST API strategy

- **Keep versioned namespaces.** `commonsbooking/v1` is a frozen contract; breaking changes go to
  `v2`. GBFS follows its own upstream spec versioning.
- **Add an extension seam**: replace the hardcoded route list in `Plugin::initRoutes()` with a
  filter (e.g. `apply_filters( 'commonsbooking_rest_routes', $routes )`) so extensions can register
  routes under the namespace without patching core.
- **Formalise auth**: document the `apikey` / `ApiShares` model as the supported management-auth
  path; keep public read routes (GBFS-style) separate from authenticated ones — mirrors
  WooCommerce's Store API vs REST API split.
- **Schema stays the source of truth**: the JSON-schema files in `includes/commons-api-json-schema/`
  remain the versioned contract for payloads.

## 8. The Strauss prefixing rule (must-follow)

Strauss copies third-party deps into the `CommonsBooking\` namespace to avoid version clashes with
other plugins. Consequence for the public API:

- **No Strauss-prefixed type may appear in any `@api` signature** (parameter, return, or thrown).
  Returning a `CommonsBooking\Opis\JsonSchema\...` or `CommonsBooking\Symfony\...` object would leak
  an unstable, prefixed type into the contract and couple extensions to our vendored version.
- Public methods return **own Models, scalars, arrays, or WP core types** (`WP_User`,
  `WP_REST_Response`, `WP_Error`) only.
- A CI check (phpstan rule or a simple grep gate) should enforce this on `@api` symbols.

## 9. Versioning & backwards-compatibility policy

Publish a written promise (WooCommerce's real differentiator is the *policy*, not the code):

- **SemVer-flavoured.** The `@api` surface + documented hooks + REST `v1` do not break within a
  major version.
- **`@since` on every public symbol/hook**; **`@deprecated x.y.z`** with a pointer to the
  replacement when retiring.
- **Deprecation cycle**: a deprecated symbol/hook keeps working for at least two minor releases and
  emits via `_deprecated_function()` / `_deprecated_hook()` before removal in the next major.
- **Internal churn is free.** Anything `@internal` (or simply un-tagged) can change any release; the
  policy explicitly says so, so nobody builds on it by accident.

## 10. Distribution

- **Primary**: wordpress.org plugin repository + `commonsbooking.org` (unchanged).
- **Composer-managed sites**: keep `"type": "wordpress-plugin"` in `composer.json`; installable via
  `composer/installers` into `wp-content/plugins`. This is a *convenience for site builders*, not a
  library dependency for extensions.
- **Extensions**: depend via the `Requires Plugins: commonsbooking` header + `commonsbooking_loaded`
  — never `composer require wielebenwir/commonsbooking`.

## 11. Phased roadmap

**Phase 0 — Boundary & policy (docs + annotations, low risk)**
- Adopt this document.
- Add `@api`/`@since` to the Tier-2/3 symbols and `@internal` to the obvious internals.
- Write and publish the BC policy (§9) and a first hook reference (§6.1).

**Phase 1 — Entry points (small, additive code)**
- Add the `commonsbooking()` accessor + `commonsbooking_loaded` action.
- Add the Tier-1 procedural facade functions wrapping existing static repositories.
- Document the extension bootstrap contract (§5).

**Phase 2 — Extensibility seams**
- Add the REST route registration filter (§7).
- Add the core booking-lifecycle hooks (§6.2).
- Add the Strauss-leak CI gate (§8).

**Phase 3 — Hardening**
- Generated API + hook reference on the docs site.
- Deprecation helpers wired in; first `@deprecated` entries if any current symbols are renamed into
  the facade.
- Optional: a small example/starter extension demonstrating the supported integration path.

Each phase is independently shippable and backwards-compatible; nothing here breaks existing
installs or the current REST `v1`.

## 12. Non-goals (explicitly out of scope)

- **No standalone / vendorable Composer library** of the core (the WP singleton reasons in §1).
- **No WP-agnostic hexagonal domain extraction** (`commonsbooking/core` as `type: library`). It is
  the *only* thing that could also be a package, but it is a large rewrite and only justified by
  real non-WordPress consumers, which do not exist today. Revisit only if that demand appears.
- **No public DI container.** A container is composition-root wiring, not a contract; exposing it
  invites service-locator coupling to internal IDs. WooCommerce keeps its container `@internal`; so
  do we. CommonsBooking is static-based today and needs no container to ship this API.
- **No conversion of static repositories/services to instance CRUD objects.** Not required for a
  stable API and a large, risky refactor.

## 13. Open questions for maintainers

1. Facade naming: `commonsbooking_get_*()` free functions, methods on `commonsbooking()`, or both?
2. Which existing static repository/service methods are *intended* as public vs incidental? (Needs a
   maintainer pass — the annotation work in Phase 0 encodes those decisions.)
3. Minimum WordPress version for relying on the `Requires Plugins` header (WP 6.5). Current floor is
   5.9 — do we soft-depend (header + runtime bail) rather than hard-require?
4. Is there appetite for the REST `commonsbooking/v2` in the foreseeable future, or is `v1` frozen
   indefinitely?
