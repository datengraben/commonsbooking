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
