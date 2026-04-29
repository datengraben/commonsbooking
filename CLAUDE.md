# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Project Is

CommonsBooking is a WordPress plugin for managing and booking shared common goods (cargo bikes, tools, etc.). It is built on WordPress Custom Post Types (CPTs) and follows WordPress plugin conventions throughout.

## Commands

### Setup
```bash
npm run start        # composer install + npm install + grunt dist (full setup)
npm run env:start    # Start wp-env Docker environment for local development
npm run env:stop     # Stop the Docker environment
```

### Build
```bash
npm run dist         # Build assets via Grunt (SCSS → CSS, JS concat/minify, package JS deps)
```

### Testing (PHP Unit)
Tests require a WordPress test database to be set up first:
```bash
bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1:<port> latest
composer install
composer dump-autoload -o
php vendor/bin/phpunit                                        # run all tests
php vendor/bin/phpunit tests/php/Model/BookingTest.php       # run a single test file
php vendor/bin/phpunit --filter testMethodName               # run a single test by name
```
The database port is printed by `npm run env:start`. Tests require the PHP `uopz` extension.

### E2E Tests (Cypress)
```bash
npm run env:start
npm run cypress:setup   # install test data
npm run cypress:run     # run headless
npm run cypress:open    # interactive
```

### Linting & Static Analysis
```bash
./vendor/bin/phpcbf -q --parallel=1 src templates includes tests commonsbooking.php   # auto-fix code style
./vendor/bin/phpcs src templates includes tests commonsbooking.php                     # check code style
./vendor/bin/phpstan analyse --configuration=phpstan.neon                              # static analysis (level 5)
```

### Translations
```bash
wp i18n make-pot . languages/commonsbooking.pot   # regenerate .pot file
```
Only German (`de_DE`) and English `.po` files are managed in the repo. Use Poedit to update `commonsbooking-de_DE.po` from the `.pot` file.

### Build ZIP
```bash
bin/build-zip.sh
```

## Architecture

### Namespace & Autoloading
All PHP source lives under `src/` with PSR-4 autoloading as `CommonsBooking\`. Tests are under `tests/php/` as `CommonsBooking\Tests\`.

After `composer install`, a post-install hook runs [Strauss](https://github.com/BrianHenryIE/strauss) to copy and namespace-prefix all vendor dependencies into `vendor-prefixed/`, so they don't conflict with other WordPress plugins. Never edit files in `vendor-prefixed/` directly.

### Layer Structure

The codebase is organized into distinct layers under `src/`:

| Layer | Path | Purpose |
|---|---|---|
| **Wordpress/CustomPostType** | `src/Wordpress/CustomPostType/` | Registers CPTs with WordPress, defines CMB2 meta fields, handles admin list views and hooks |
| **Model** | `src/Model/` | Business logic wrappers around `WP_Post`. Extend `CustomPost`, which wraps `WP_Post` with magic `__get`/`__set` access to post fields and meta. |
| **Repository** | `src/Repository/` | Static database access layer using `WP_Query`. Extend `PostRepository`. Returns model instances. |
| **View** | `src/View/` | Renders HTML output. Each CPT has a matching View. |
| **Service** | `src/Service/` | Business logic services (booking rules, caching, scheduling, export, upgrade migrations). |
| **Messages** | `src/Messages/` | Email message types. Extend abstract `Message`. |
| **API** | `src/API/` | WordPress REST API routes, including GBFS (bike-share data feed spec) endpoints. |
| **Map** | `src/Map/` | Leaflet-based map shortcodes and admin page. |

### Custom Post Types
Six CPTs are registered: **Item**, **Location**, **Timeframe**, **Booking**, **Restriction**, **Map**.

- `Plugin::getCustomPostTypeClasses()` is the authoritative list. Every CPT class in `Wordpress/CustomPostType/` must have a corresponding model in `Model/`.
- The `Timeframe` CPT is multipurpose: its `type` meta field distinguishes bookable slots, holidays, repairs, official holidays, and the booking records themselves (type IDs 1–7, defined as constants on `Wordpress\CustomPostType\Timeframe`).
- **Bookings are stored as Timeframe-typed posts** with `type = BOOKING_ID (6)`. `Repository\PostRepository::getPostById()` reads the `type` meta to return the correct model class.

### Plugin Bootstrap
`commonsbooking.php` defines all global constants (`COMMONSBOOKING_*`), loads `vendor-prefixed/autoload.php`, and includes `includes/Plugin.php`. The `src/Plugin.php` class registers all WordPress hooks via its `init()` method and is the central wiring point.

### Cache
`Service\Cache` is a trait used by `Plugin`. It wraps Symfony Cache adapters (filesystem or Redis, configurable in settings). Cache is keyed by calling class/method and tagged by related post IDs. Cache is disabled when `WP_DEBUG` is true. In tests, call `wp_cache_flush()` after mutations to keep state consistent.

### Booking Rules
`Service\BookingRule` and `Service\BookingRuleApplied` implement a configurable rule system. Rules are stored in settings and validated on options save. Booking denial throws `Exception\BookingDeniedException`.

### Scheduler
`Service\Scheduler` wraps WordPress cron. Each scheduled job is re-registered on every page load; the constructor checks whether to schedule or unschedule based on settings. Plugin deactivation fires `Scheduler::UNSCHEDULER_HOOK` to clean up all jobs.

### Assets
Frontend assets are built by Grunt (`Gruntfile.js`):
- SCSS from `assets/{admin,public}/sass/` → `assets/{admin,public}/css/`
- JS from `assets/{admin,public}/js/` (concatenated/minified)
- NPM packages are copied into `assets/packaged/` with version hashes tracked in `assets/packaged/dist.json`

Scripts and styles are registered (not enqueued) in `Plugin::registerScriptsAndStyles()` and enqueued by individual CPT/View classes as needed.

### Templates
PHP templates in `templates/` are rendered via `includes/Template.php` and `includes/TemplateParser.php`. Template tags from Model classes are available inside templates via the magic `__get` on `CustomPost`.

### User Roles
Two custom roles: `administrator` (standard WP) and `cb_manager`. Role capabilities for all CPTs are defined in `Plugin::addRoleCaps()` and `Plugin::getRoleCapMapping()`.

## Key Conventions

### Code Style
- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) enforced via PHPCS (`.phpcs.xml.dist`). Run `phpcbf` before committing — CI blocks non-compliant code.
- Short array syntax `[]` is allowed (the PHPCS rule against it is excluded).
- All global functions/constants must be prefixed `cb_`, `commonsbooking_`, or `CommonsBooking`.
- i18n: use `__( 'string', 'commonsbooking' )` with the text domain `commonsbooking`.

### Adding a New Custom Post Type
1. Create the CPT class in `src/Wordpress/CustomPostType/` extending `CustomPostType`.
2. Create the model in `src/Model/` extending `CustomPost` (or `BookablePost` for bookable entities).
3. Create a repository in `src/Repository/` extending `PostRepository`.
4. Register the CPT class in `Plugin::getCustomPostTypeClasses()`.
5. `PluginTest::testGetCustomPostTypes()` will fail if there is no matching model.

### Tests
- PHP unit tests extend `BaseTestCase` (for simple tests) or use `CPTCreationTrait` to create WordPress posts in-database for integration-style tests.
- `CPTCreationTrait` provides `createItem()`, `createLocation()`, `createTimeframe()`, `createBooking()`, etc. Always call `tearDownAllPosts()` in `tearDown()` to clean up.
- Time-sensitive tests use `slope-it/clock-mock` for mocking `time()`. The reference date is `CustomPostTypeTest::CURRENT_DATE`.
- Geo/geocoding calls are mocked by default in `BaseTestCase::setUp()` via `GeoHelperTest::setUpGeoHelperMock()`.

### Upgrade Migrations
Add migration tasks to `Service\Upgrade::$upgradeTasks` keyed by the version string that introduces the change. Tasks run once on first page load after update.

### Git
The file `.git-blame-ignore-revs` tracks bulk formatting commits. Configure locally with:
```bash
git config blame.ignoreRevsFile .git-blame-ignore-revs
```
