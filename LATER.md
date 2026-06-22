# Deferred Styling / WordPress Standards Work

These items were identified during a WordPress community standards review but
deferred to keep the initial fix scope small. Pick them up as separate tasks.

---

## Medium severity

### ~~1. Runtime SCSS compilation — cache the result~~ ✓ Done
Transient `cb_color_css` caches the compiled output. Invalidated in
`OptionsTab::savePostOptions()` whenever template settings are saved.

### ~~2. All public CSS/JS loaded on every page~~ ✓ Done
`commonsbooking_is_cb_page()` helper added to `includes/Public.php`. Guards
`commonsbooking_public()` with an early return; checks `is_singular()` for CB
CPTs and `has_shortcode()` for all CB shortcode tags. The
`commonsbooking_load_public_assets` filter allows site owners to opt in on
other pages (widget-embedded shortcodes etc.).

### 3. Heavy `!important` usage in theme-compatibility CSS
46 `!important` declarations across SCSS files; 18 in `kasimir.scss` alone.
These are symptoms of insufficient specificity in the base stylesheet. Scoping
more rules under `.cb-wrapper` should reduce the need for theme overrides.

---

## Low severity

### 4. Deprecated HTML table attributes
`src/View/BookingCodes.php` outputs `cellspacing='0' cellpadding='20'` on a
`<table>` element. These are deprecated since HTML5. Replace with CSS.

### 5. BEM naming consistency
CSS classes use a mixed flat / partial-BEM approach. A consistent BEM
convention (`cb-block__element--modifier`) would make the stylesheet easier to
scale and reduce specificity conflicts.

### 6. Template loader `return` path bug
In `includes/Template.php` line 97, when `$include = false`, the function
returns `$before_html . $template . $after_html` where `$template` is a **file
path string**, not rendered content. Callers that use `$include = false`
(Dashboard, MassOperations) silently discard this via
`commonsbooking_sanitizeHTML()`. The function should use output buffering
(`ob_start` / `ob_get_clean`) when `$include = false` to return actual rendered
HTML.
