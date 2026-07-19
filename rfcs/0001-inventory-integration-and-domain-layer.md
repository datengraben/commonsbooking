# RFC 0001 — Inventory-manager integration, a provisioning domain layer, and a simple mode

- **Status:** Draft
- **Author(s):** (add yourself)
- **Created:** 2026-07-19
- **Branch:** `claude/cb-wp-inventory-integrations-fjfh5n`
- **Reference implementation:** `src/Service/WPInventoryImport.php` (WP Inventory one-click prefill)

## Summary

Make CommonsBooking (CB) easy to integrate with external inventory managers,
easy to enter for new users, and easy to enter for new developers — by
extracting CB's most common feature-verbs into a small, provider-agnostic
**domain/provisioning layer**, and by offering a **simple mode** in which a
single default location and a single default availability profile are implicit.

The three goals are projections of one underlying change: today CB's core
operations (create an item, make it bookable, book it, restrict it) live
*inside* WordPress plumbing (CMB2 save callbacks, CPT hooks). Lifting them into
named, documented core methods gives us a reusable integration API, a legible
domain for contributors, and the substrate for a simplified user experience.

## Motivation

### What we learned building the WP Inventory importer

The WP-Inventory-specific code turned out to be tiny — detect a table, read two
columns, map a title. Everything else was **generic CB provisioning that CB
does not expose**. To create one bookable timeframe, the importer had to copy an
~18-meta-key recipe out of a test trait (`tests/php/CPTCreationTrait.php`).

That recipe currently exists, independently, in **three places that can drift**:

1. the CMB2 admin save path — the real one
   (`CustomPostType/Timeframe::savePost` → `manageTimeframeMeta` →
   `sanitizeRepetitionEndDate`);
2. the test trait `CPTCreationTrait::createTimeframe()` — a reimplementation;
3. `Service/WPInventoryImport::createBookableTimeframe()` — a copy of the
   reimplementation.

Any new integration, a REST write path, or a "simple mode" would become copy #4.
The root cause is not duplication for its own sake; it is that **"make an item
bookable" is a domain operation that lives inside a save callback**, so anything
that is not the admin form cannot reuse it — it can only re-derive it.

### The two-persona onboarding argument

The same extraction lowers the barrier to entry for two audiences:

- **Users** hit CB's real learning cliff: the Item × Location × Timeframe model
  is powerful but abstract. Default singletons ("one location, always bookable")
  turn onboarding into *create item → it is bookable*, with locations and
  timeframes revealed later via progressive disclosure. The one-click importer is
  the extreme version of the same on-ramp: you enter CB with a populated,
  already-bookable catalog instead of a blank screen.
- **Developers** currently learn CB by reading CMB2 save hooks. Named domain
  verbs (`Item::create()`, `Timeframe::createBookable()`, `Booking::create()`)
  make the domain legible — the public methods *are* the documentation — and give
  integrators a ~30-line entry point instead of an archaeology project.

## Goals

- One canonical implementation of each common feature-verb.
- A stable, documented way for external code (importers, REST, future add-ons) to
  provision the CB triple `(item, location, bookable timeframe)`.
- Idempotent import / external-identity as a first-class primitive.
- A pluggable inventory-source registry, with the WP Inventory adapter as the
  reference implementation.
- A "simple mode" that hides the location/timeframe axes behind defaults.

## Non-goals

- Ongoing two-way sync, booking → external-stock write-back, quantity/count
  modelling, external locations, images/rich fields. (These are later phases; see
  the separate integration-directionality notes.)
- Changing CB's booking/availability engine. The internal
  `(item, location, timeframe)` model stays intact; everything here is a layer
  *above* it.

## Design

### 1. A provisioning façade (canonical feature-verbs)

Introduce core methods that are the single home for each common operation:

| Feature verb        | Canonical home                                        |
|---------------------|-------------------------------------------------------|
| create item         | `Item::create( $title, $args )`                       |
| ensure/assign place | `Location::ensureDefault()` / assign helpers          |
| **make bookable**   | `Timeframe::createBookable( $itemId, $locationId, $opts )` |
| book                | `Booking::create( … )`                                |
| restrict            | `Restriction::create( … )`                            |

`Timeframe::createBookable()` encapsulates the meta recipe (type, item/location
selection, repetition, full-day, grid, horizon, booking-code flags) so that no
caller needs to know the 18 meta keys. The admin save path, the test trait, the
importer, the REST API, and simple mode all become **thin callers** of this one
builder.

### 2. External-source identity

Promote the ad-hoc `_cb_wpi_source_id` convention (introduced by the importer)
to a provider-agnostic primitive:

- meta `_cb_source = { provider, id }` on provisioned posts;
- a lookup `Item::getBySource( $provider, $id )`.

Idempotent import and dedupe then become a supported primitive that every
importer shares, instead of each one inventing a meta key and `meta_query`.

### 3. Pluggable inventory-source registry

Turn the one-off service into a pipeline:

```php
interface InventorySource {
    public function detect(): bool;              // is this provider present?
    public function readItems(): iterable;       // yields normalized external items
}
```

- Core owns a generic `CatalogImporter` (admin notice, handler, default-location,
  idempotency via `_cb_source`, triple provisioning via the façade).
- Providers register via a `commonsbooking_inventory_sources` filter.
- `WPInventoryImport` is refactored into the reference `InventorySource` adapter.

This is the difference between "we integrated WP Inventory" and "CB integrates
any inventory manager, WP Inventory included."

### 4. Simple mode

A configuration + UI layer on top of the same singletons — **not** a fork of the
engine.

- Setting `commonsbooking_mode = simple | full`.
- Auto-create a singleton **default Location** and a singleton **default
  availability Timeframe** ("always, full-day, daily" — the importer's defaults).
- In simple mode: hide the Location and Timeframe admin menus, auto-bind new
  items to the singletons on save, and expose availability as a few global
  options (opening hours, booking horizon, day-vs-hour) instead of per-timeframe
  authoring.

The engine still sees a normal `(item, location, timeframe)` triple, so caching,
iCal, restrictions, booking codes, and availability keep working untouched.

#### Caveats for simple mode

- **"One timeframe" is not "no scheduling."** A single blanket timeframe still
  needs an availability profile; simple mode gives it a global editor, it does
  not remove scheduling.
- **It must be a mode, not the model.** CB's differentiator is items migrating
  between locations over time; simple mode deliberately forgoes that for the
  stationary-inventory segment. Switching full → simple with existing
  multi-location data needs a guard/migration.

## Rollout plan (phased, behaviour-preserving)

Risk is back-loaded: the admin save path is touched last.

1. **Extract `Timeframe::createBookable()`** from the validated meta recipe.
2. **Repoint the programmatic writers** — `WPInventoryImport` and
   `CPTCreationTrait` — at the builder. Zero change to production admin
   behaviour; existing timeframe tests act as characterization tests.
3. **Fold the CMB2 admin save path** into the same builder, incrementally, with
   the suite green from step 2. (Highest risk; do it last.)
4. **Add `_cb_source` + `Item::getBySource()`**; migrate the importer's
   `_cb_wpi_source_id` onto it.
5. **Introduce `InventorySource` + `CatalogImporter`**; refactor WP Inventory
   into the reference adapter.
6. **Add simple mode** (default singletons + config/UI layer + global
   availability options) on the now-stable provisioning core.

## Risks and open questions

- **A half-extracted layer is worse than none.** The façade only helps if it
  becomes the *single* path; adding it alongside the old scattered logic creates
  two ways to do everything. The refactor must repoint existing callers, not just
  bolt on a parallel API.
- **Behaviour parity of the admin path (step 3)** must be proven by tests; the CB
  test suite requires a WordPress + MySQL harness (`wp-env`) and must run green in
  CI at each step.
- **Simple↔full transitions** with existing data need a defined migration.
- Naming/location of the façade (model statics vs. a dedicated `Provisioning`
  service) is open.
- Whether `_cb_source` should be indexed for large catalogs is open.

## Alternatives considered

- **Keep integrations as bespoke services** (status quo, as the WP Inventory MVP
  is today): every integrator re-derives CB internals; recipe drift across copies;
  no user-facing onboarding benefit.
- **A parallel integration API without repointing core callers:** delivers the
  importer benefit but not the onboarding/legibility benefit, and adds a second
  source of truth.

## Appendix — current reference implementation

`src/Service/WPInventoryImport.php` already implements the runtime behaviour this
RFC generalizes: detect WP Inventory, mirror items to `cb_item`, attach to a
single default location, and create an open bookable timeframe per item
(idempotent via `_cb_wpi_source_id`). It is intentionally the narrow MVP; this
RFC describes how to grow it into a permanent, provider-agnostic core capability.
