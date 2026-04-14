# Contributing to CommonsBooking

Thank you for taking the time to contribute! This document covers everything you need to go from a fresh clone to a submitted pull request.

---

## Table of Contents

- [Prerequisites](#prerequisites)
- [Quick Start](#quick-start)
- [Local Development](#local-development)
- [Running Tests](#running-tests)
- [Code Standards](#code-standards)
- [Changelog Entries](#changelog-entries)
- [Submitting a Pull Request](#submitting-a-pull-request)
- [Building the Plugin ZIP](#building-the-plugin-zip)

---

## Prerequisites

| Tool | Minimum version | Notes |
|---|---|---|
| PHP | 7.4 | With [uopz](https://www.php.net/manual/en/book.uopz.php) extension for tests |
| Composer | 2.x | |
| Node.js | 20.x | Use `nvm use` — version pinned in `.nvmrc` |
| Docker | Latest stable | Required by `wp-env` |
| @wordpress/env | bundled | Installed via `npm ci` |

---

## Quick Start

```bash
# 1. Clone and install all dependencies + build assets
git clone https://github.com/datengraben/commonsbooking
cd commonsbooking
npm run start

# 2. Start the local WordPress environment (requires Docker)
npm run env:start

# 3. Open WordPress in your browser
#    Site:  http://localhost:1000   (admin / password)
#    Tests: http://localhost:1001
```

The `.wp-env.json` pre-installs several useful development plugins (Query Monitor, WP Crontrol, WP Mail Logging) and activates the Kasimir theme.

> **Custom configuration**: create a `.wp-env.override.json` for local overrides (e.g. a different port or extra plugins). This file is gitignored.

---

## Local Development

### Install dependencies

```bash
# PHP dependencies
composer install --ignore-platform-reqs

# Node dependencies (use --legacy-peer-deps to match CI)
npm ci --legacy-peer-deps

# Compile assets (SCSS → CSS, JS bundles)
npm run dist
```

### Start / stop the environment

```bash
npm run env:start   # starts WordPress at http://localhost:1000
npm run env:stop    # shuts it down
```

`env:start` also installs WP-CLI inside the test container, which is needed for E2E test setup.

### Activate the development theme via WP-CLI

```bash
npm run env run cli wp theme activate kasimir-theme
```

### Watching assets during development

```bash
npm run dist        # one-off build
# (no watch task is wired up yet — contributions welcome!)
```

---

## Running Tests

### PHP Unit Tests

The test suite requires a WordPress test database. `bin/install-wp-tests.sh` sets it up automatically against the wp-env MySQL container.

**1. Find the database port** — it is printed when you run `npm run env:start`:

```
ℹ︎ MySQL port: 49153   ← use this in the next step
```

**2. Set up the test database:**

```bash
bash bin/install-wp-tests.sh wordpress root '' 127.0.0.1:<PORT> latest
```

**3. Run the tests:**

```bash
composer test
```

This runs `composer dump-autoload -o` then `phpunit` using the `phpunit.xml.dist` configuration. Code coverage reports are written to `build/logs/`.

### E2E Tests (Cypress)

```bash
# Environment must already be running
npm run env:start

# Import the test fixture data (only needed once per environment)
npm run cypress:setup

# Run headlessly
npm run cypress:run

# Open the interactive Cypress UI
npm run cypress:open
```

Screenshots from failed runs are saved to `tests/cypress/screenshots/`.

---

## Code Standards

The project follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/). The ruleset is in `.phpcs.xml.dist`.

```bash
# Check for violations
composer lint

# Auto-fix fixable violations
composer lint:fix
```

Results are cached in `.cache-phpcs-free.cache` (gitignored) so re-runs are fast.

---

## Changelog Entries

Every PR that changes **user-facing behaviour** (new features, bug fixes, UI changes) needs a changelog entry. Pure tooling, test, CI, or documentation PRs are exempt.

```bash
composer changelog:add
```

This launches an interactive wizard:

| Prompt | Options |
|---|---|
| Significance | `patch` (bug fix), `minor` (new feature), `major` (breaking change) |
| Type | `added`, `changed`, `deprecated`, `removed`, `fixed`, `security` |
| Entry | A short sentence describing the change for end users |

The wizard creates a file in `changelog/`. Commit it with your PR. The CI will validate it.

To preview how all pending entries will look when collapsed:

```bash
composer changelog:write --dry-run
```

---

## Submitting a Pull Request

1. **Fork** the repository and create a branch from `master`:
   ```bash
   git checkout -b fix/description-of-fix
   # or
   git checkout -b feature/description-of-feature
   ```

2. **Make your changes.** Keep PRs focused on one concern.

3. **Run the checks locally** before pushing:
   ```bash
   composer lint
   composer test
   ```

4. **Add a changelog entry** if your change is user-facing:
   ```bash
   composer changelog:add
   ```

5. **Push and open a PR** against the `master` branch. Fill in the PR template — it has a short checklist to make sure nothing is missed.

The CI will run PHP unit tests (PHP 7.4 + 8.2), E2E tests across multiple WordPress versions, and validate your changelog entry.

---

## Building the Plugin ZIP

To produce a production-ready zip (e.g. for manual upload to a staging site):

```bash
bin/build-zip.sh
```

The zip is built into `build/` and excludes development files listed in `.distignore`.
