# CPT Admin Wizard — Full Plan (saved for later)

Goal: make CommonsBooking CPT creation in the admin backend (and optionally a
frontend shortcode) more user-friendly via a guided, step-based "configurator"
wizard — **without redefining the CPT data model**. The wizard is a thin
presentation layer that only defines steps + friendly labels and reuses the
existing CMB2 field definitions.

## Codebase findings

- Six CPT classes in `src/Wordpress/CustomPostType/`. `Timeframe.php` (~1,405
  lines, ~30 CMB2 fields with technical labels/IDs) is the main pain point.
- Conditional visibility is already hand-rolled in jQuery in
  `assets/admin/js/src/timeframe.js` (show/hide `.cmb-row` by field ID based on
  the `type` / `repetition` / `full-day` selects). The "which field matters
  when" logic already exists — imperatively.
- Field definitions are already data for most CPTs: `Timeframe`, `Booking`,
  `Restriction`, `Map` return fields from `getCustomFields()`; `registerMetabox()`
  loops and calls `$cmb->add_field()`.
  - Exception: `Item` and `Location` build fields inline inside
    `registerMetabox()` — not reusable yet.
- No frontend CMB2 forms exist (`cmb2_metabox_form` unused).
- No onboarding/setup-wizard admin page. `Plugin.php::addMenuPages()` already
  registers the CB admin menu — natural home for a wizard entry.

Key point: field definitions are (mostly) a single source of truth already, so a
labels-only wizard that does not own the data model is the natural shape.

## Ecosystem survey

No drop-in "CMB2 wizard" plugin exists. Adjacent building blocks:

- CMB2 Conditionals (`jtsternberg/cmb2-conditionals`) — declarative show/hide;
  could replace the hand-written JS, but not a stepper.
- cmb2-metatabs / tabs snippets — group existing fields into tabs (low effort,
  low payoff; tabs != guided wizard).
- CMB2 frontend forms (`cmb2_get_metabox` + `cmb2_metabox_form()`) — officially
  supported; renders the same field defs on a frontend page. Proven path for a
  frontend shortcode.
- WooCommerce-style Setup Wizard (`WC_Admin_Setup_Wizard`) — canonical WP
  pattern: standalone `admin.php?page=…`, N steps, each a form, saving via
  normal meta APIs. Reference implementation for a backend wizard.
- Gravity/Fluent/Formidable multi-step + "create post" add-on — no-code but
  duplicates the field→meta mapping, adds a dependency, drifts from CMB2. Not
  recommended.

## Recommended approach

A thin "wizard config" layer that consumes existing `getCustomFields()` arrays
and only adds step grouping + friendly labels. Data model, field IDs,
save/validation, and `default_cb` filters stay unchanged.

Wizard = presentation metadata referencing existing field IDs:

```php
[
  ['label' => __('What are you offering?'),  'fields' => ['type', Timeframe::META_ITEM_ID, Timeframe::META_LOCATION_ID]],
  ['label' => __('When is it available?'),   'fields' => ['full-day', 'grid', 'start-time', 'end-time']],
  ['label' => __('How often does it repeat?'),'fields' => [Timeframe::META_REPETITION, 'weekdays', /*start*/, /*end*/]],
  // …
]
```

Two delivery surfaces, same core:
1. Backend — a CB submenu page (`admin.php?page=cb-new-timeframe`) rendering CMB2
   boxes per step with a Back/Next stepper (WooCommerce pattern).
2. Frontend shortcode — `[cb_timeframe_wizard]` using `cmb2_metabox_form()`,
   paginated by step, with capability + nonce/spam guards.

## Full plan (all CPTs)

1. Normalize the source of truth: refactor `Item` and `Location` to return their
   fields from `getCustomFields()` (pure refactor, no behavior change).
2. Add a `WizardConfig` per CPT — static method returning the step array;
   labels/descriptions live here + in `.po` files, nothing else.
3. Build a reusable `Wizard` renderer taking `(getCustomFields(), WizardConfig())`,
   resolving field IDs → definitions, rendering one step at a time. Generalize
   `timeframe.js` show/hide into a small config-driven helper.
4. Wire the backend page into `Plugin::addMenuPages()` ("Add new (guided)").
5. Optional frontend shortcode reusing the same renderer.
6. Tests: assert every field ID referenced by a `WizardConfig` exists in that
   CPT's `getCustomFields()` (drift guard); smoke-test a full submission.

Effort/risk: step 1 small/safe; the renderer (step 3) is the real work. Start
with Timeframe backend-only for the biggest win / least surface, then expand.
