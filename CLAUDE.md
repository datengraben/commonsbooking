# CLAUDE.md

Guidance for Claude (and other AI assistants) contributing to CommonsBooking.

> This is an intentionally minimal first version. It will grow over time.

## Project

CommonsBooking is a WordPress plugin for managing and booking shared common
goods (e.g. cargo bikes, tools). PHP 8.1+, namespaced under `CommonsBooking\`
(PSR-4, `src/`).

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
