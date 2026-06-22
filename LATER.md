# Deferred Styling / WordPress Standards Work

These items were identified during a WordPress community standards review but
deferred to keep the initial fix scope small. Pick them up as separate tasks.

---

## Medium severity

### 1. Runtime SCSS compilation — cache the result
`src/View/View.php::getColorCSS()` runs the SCSSPHP compiler on every public
page load to generate the user-defined colour overrides. Cache the compiled
output in a transient and invalidate it when the template settings option is
updated (`update_option_commonsbooking_options_templates` hook).  
Alternatively, replace the compiled output with CSS custom properties set on
`:root` — the stylesheet already uses `var(--commonsbooking-*)` throughout.

### 2. All public CSS/JS loaded on every page
`includes/Public.php` enqueues all plugin styles and scripts globally via
`wp_enqueue_scripts`. Only the map/search shortcodes conditionally enqueue
their assets. Best practice is to check for plugin content (e.g.
`has_shortcode()`, post type checks) before enqueueing, or move enqueuing into
the shortcode callbacks themselves.

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
