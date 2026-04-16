# Future plan — items not in scope for the initial release

This file tracks ideas and improvements that are deliberately out of scope for the
MVP of the `contrib/` directory. Nothing here is a commitment. Open an issue to
discuss any of these before starting work.

---

## Automated testing for snippets

Snippets are currently verified only by human code review. A lightweight test
harness (e.g. a WordPress unit test bootstrap with CB installed) would let
contributors add a simple smoke test alongside their snippet file.

**Blocker:** Requires a reproducible CB test environment that runs in CI without
a full database, which is non-trivial to set up for external contributors.

---

## Snippet metadata index

Generate a machine-readable `index.json` from the PHP file headers so that
tooling (a website, a CLI, an IDE plugin) can list and search snippets without
parsing PHP.

**Approach:** A small GitHub Actions workflow that runs `php -r` to extract
PHPDoc-style headers and emits JSON.

---

## Mini-plugin scaffold / generator

A `bin/new-mini-plugin.sh` script that scaffolds a minimal WordPress plugin
package (main PHP file, readme.txt, basic structure) so contributors don't
start from scratch.

---

## Translations / i18n guidance

Snippets that output user-facing strings should wrap them in `__()` / `esc_html__()`.
A short i18n guide for snippet authors would help establish the pattern early.

---

## Version compatibility matrix

A table (or automated badge) showing which CB version each snippet was last
tested against, fetched from the file headers and rendered in the README.

---

## Snippet deprecation policy

Define what happens when a CB core hook is renamed or removed: how do we mark
snippets as deprecated, and how long do we keep them in the library?

---

## Promotion pathway: community snippet → `CommonsBooking\Contrib\*`

This is the most significant future direction for this library.

### The idea

Snippets that prove broadly useful can be promoted by the core team into an
official optional namespace inside the main plugin — `src/Contrib/` —
mirroring the pattern of `django.contrib` in Django. These modules:

- Ship in the release zip (unlike community snippets, which are `.distignore`d)
- Are maintained and tested by the CB core team
- Live under the PHP namespace `CommonsBooking\Contrib\<ModuleName>`
- Are **optional by default** — shortcodes/features activate only when used,
  no settings toggle required
- Carry the signal "this started as a community idea and proved its value"

### Three-tier model

```
contrib/snippets/          Community, copy-paste, not shipped
        ↓ (proven useful for many sites)
src/Contrib/               Official optional, shipped, CB-maintained
        ↓ (universally needed)
src/                       Core plugin
```

### Promotion criteria (suggested)

A snippet becomes a candidate for `src/Contrib/` when it meets all of:

1. Requested or adopted independently by multiple sites / networks
2. Uses only public CB hooks, filters, or shortcode APIs (no internals)
3. Has a single, well-scoped responsibility
4. The community snippet has been stable across ≥ 1 CB release cycle

### What NOT to promote

- Highly site-specific snippets (custom email text, local contact details)
- Snippets that require configuration not easily expressible in PHP constants
- Anything that would need a settings UI — those belong in a separate plugin

### Implementation notes (when ready)

- Create `src/Contrib/` directory with its own `README.md`
- Each module is a single class file loaded via the existing autoloader
- Module classes register their hooks/filters in a `register()` method called
  from the main plugin bootstrap (conditionally, e.g. only if a constant is set
  or always — depending on the module)
- Add integration tests under `tests/php/Contrib/`
