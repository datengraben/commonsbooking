# CLAUDE.md

Guidance for Claude (and other AI assistants) contributing to CommonsBooking.

> This is an intentionally minimal first version. It will grow over time.

## Project

CommonsBooking is a WordPress plugin for managing and booking shared common
goods (e.g. cargo bikes, tools). PHP 8.1+, namespaced under `CommonsBooking\`
(PSR-4, `src/`).

## Philosophy

> Maintainers: edit this freely. These are the values we want contributions
> to respect, in plain words.

- **Real communities depend on this.** People use CommonsBooking to share
  bikes and tools every day. Bookings and their data must stay reliable;
  don't break existing sites or lose user data.
- **We are guests on WordPress.** The plugin runs inside other people's
  WordPress installs, alongside other plugins. Follow WordPress conventions
  (hooks/filters, translations, escaping/sanitizing, coding standards) and
  stay compatible with the WordPress and PHP versions we support. Don't fight
  the platform.
- **Small, understandable changes.** Prefer changes a maintainer can read and
  review in one sitting. Understand the existing code before changing it.
- **Ask when unsure.** Maintainers have little time and mixed technical
  backgrounds. When a change involves a real trade-off or is hard to reverse,
  pause and ask rather than guessing.

## Repository layout

```
src/            # Plugin source (PSR-4: CommonsBooking\)
templates/      # PHP view templates
includes/       # Bundled/legacy includes
assets/         # JS/SCSS/images (built via Grunt)
tests/php/      # PHPUnit tests
tests/cypress/  # Cypress end-to-end tests
docs/           # VitePress developer docs
```

## Common commands

```bash
# Build frontend assets
npm run dist

# PHP unit tests
php vendor/bin/phpunit

# Static analysis
vendor/bin/phpstan analyse

# Auto-fix code style (PHPCS/WPCS rules)
./vendor/bin/phpcbf src templates includes tests commonsbooking.php

# Local WordPress dev environment (wp-env)
npm run env:start

# End-to-end tests
npm run cypress:run
```

## Code style

Follow existing patterns in surrounding code. Code style is enforced by PHPCS
(`.phpcs.xml.dist`); run `phpcbf` before committing. CI rejects contributions
that don't pass. See `TECHNICAL.md`.

## Commit discipline

- **One idea per commit.** Each commit should be a single, coherent change
  that a reviewer can understand on its own.
- **Write a clear message.** Say *why* the change is needed, not just what
  changed.
- **Keep commits clean.** Run `phpcbf` first so code-style fixes don't clutter
  the diff. Cosmetic, repo-wide reformatting goes in its own commit and is
  listed in `.git-blame-ignore-revs`.
- **Don't commit broken work.** Tests and static analysis should pass before
  you commit.

## Testing

- **PHP unit tests** live in `tests/php/` (PHPUnit). Run `php vendor/bin/phpunit`.
- **End-to-end tests** live in `tests/cypress/` and run against a local
  WordPress (`npm run env:start`, then `npm run cypress:run`).
- **Add tests with behaviour changes.** New features and bug fixes should come
  with tests; for a bug, a test that fails before the fix and passes after is
  ideal.
- **CI runs the full suite** on every pull request and must pass before merge.

## What not to do

- **Don't trust user input.** Always sanitize input and escape output
  (WordPress `sanitize_*`, `esc_*`); booking forms and admin fields are
  attack surface.
- **Don't hardcode user-facing text.** Wrap it for translation so the plugin
  stays localizable.
- **Don't break existing data or bookings.** Be careful with database changes;
  many live sites hold real booking data.
- **Don't bypass the checks.** Don't silence PHPCS/PHPStan or skip tests to get
  a green build — fix the underlying issue.
- **Don't reinvent WordPress.** Use the platform's hooks, APIs, and helpers
  instead of working around them.

## Self-review checklist

Before opening a pull request, check that:

- [ ] The change does one thing and the commits are clean.
- [ ] `phpcbf` has been run and PHPCS/PHPStan pass.
- [ ] Tests pass, and behaviour changes come with tests.
- [ ] User input is sanitized and output is escaped.
- [ ] User-facing text is wrapped for translation.
- [ ] Existing bookings and data are not put at risk.
