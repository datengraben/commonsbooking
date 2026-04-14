[![PHP Composer](https://github.com/wielebenwir/commonsbooking/actions/workflows/phpunit.yml/badge.svg)](https://github.com/wielebenwir/commonsbooking/actions/workflows/phpunit.yml)
[![E2E Tests](https://github.com/wielebenwir/commonsbooking/actions/workflows/e2e.yml/badge.svg)](https://github.com/wielebenwir/commonsbooking/actions/workflows/e2e.yml)
[![WP compatibility](https://plugintests.com/plugins/wporg/commonsbooking/wp-badge.svg)](https://plugintests.com/plugins/wporg/commonsbooking/latest) 
[![PHP compatibility](https://plugintests.com/plugins/wporg/commonsbooking/php-badge.svg)](https://plugintests.com/plugins/wporg/commonsbooking/latest)
[![codecov](https://codecov.io/gh/wielebenwir/commonsbooking/branch/master/graph/badge.svg?token=STJC8WPWIC)](https://codecov.io/gh/wielebenwir/commonsbooking)

# CommonsBooking

Contributors: wielebenwirteam, m0rb, flegfleg, chriwen  
Donate link: https://www.wielebenwir.de/verein/unterstutzen  
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html  

CommonsBooking is a plugin for the management and booking of common goods. This plugin provides associations, groups, and individuals with the ability to share items (such as cargo bikes and tools) among users. It is based on the concept of Commons, where resources are shared for the benefit of the community.

## Links

* [WordPress Plugin Page](https://wordpress.org/plugins/commonsbooking/)
* [View Changelog](https://wordpress.org/plugins/commonsbooking/#developers)
* [Official Website](https://commonsbooking.org)
* For users get [Support](https://commonsbooking.org/kontakt/)
* For developers use the [Bug-Tracker](https://github.com/wielebenwir/commonsbooking/issues) 

## Installation

### Using The WordPress Dashboard

1. Navigate to the 'Add New' in the plugins dashboard
2. Search for 'commonsbooking'
3. Click 'Install Now'
4. Activate the plugin in the plugins dashboard
 

### Uploading in WordPress Dashboard 

1. Navigate to the 'Add New' in the plugins dashboard
2. Navigate to the 'Upload' area
3. Select `commonsbooking.zip` from your computer
4. Click 'Install Now'
5. Activate the plugin in the plugins dashboard

### Using FTP

1. Download `commonsbooking.zip`
2. Extract the `commonsbooking` directory to your computer
3. Upload the `commonsbooking` directory to the `/wp-content/plugins/` directory
4. Activate the plugin in the plugins dashboard

### Using GitHub (developers only)

1. Make sure that composer is installed on your system
2. Navigate into your wp-content/plugins directory
3. Open a terminal and run `git clone https://github.com/wielebenwir/commonsbooking`
4. cd into the directory commonsbooking and run `composer install`
> This might fail, if you don't have the PHP extension [uopz](https://www.php.net/manual/en/book.uopz.php) installed. Try running `composer install --no-dev` if you just quickly want to test a specific branch without installing the extension.
5. Activate the plugin in the plugins dashboard

## Contribute

Either through translating WordPress into your native tongue ([see the already existing WordPress Plugin Translations](https://translate.wordpress.org/projects/wp-plugins/commonsbooking/)) or through developing and testing new versions of the application.

See [CONTRIBUTING.md](CONTRIBUTING.md) for the full developer guide, including local setup, running tests, code standards, and how to submit a pull request.

## Development

### Run plugin

Install all dependencies and build assets:
```
npm run start
```

The easiest way to start hacking WordPress plugins (if you have no other development environment set up) is using [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/). Install it and its dependencies (mainly Docker) and run:
```
npm run env:start
```

WordPress will be available at **http://localhost:1000** (credentials: `admin` / `password`).

The provided `.wp-env.json` is sufficient for normal development. See the [wp-env config docs](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/#wp-env-json) for details. Create a `.wp-env.override.json` for local overrides you don't want to check in.

To activate the [Kasimir theme](https://github.com/flegfleg/kasimir-theme) via WP-CLI:
```
npm run env run cli wp theme activate kasimir-theme
```

### Test plugin

First set up the test database. The database port is printed when you run `npm run env:start`:
```
bash bin/install-wp-tests.sh wordpress root '' 127.0.0.1:<PORT> latest
```

Then run the PHP unit tests:
```bash
composer test
```

E2E (end to end) tests are written in [Cypress](https://www.cypress.io/). To run them:
```bash
npm run env:start        # environment must be running
npm run cypress:setup    # import test fixture data (once per environment)
npm run cypress:run      # run headlessly
npm run cypress:open     # open the interactive Cypress UI
```

### Code standards

```bash
composer lint        # check for violations
composer lint:fix    # auto-fix fixable violations
```

### Update translations

Currently, we only manage German and English translations as po files in the repository, so they are available at build time. 
See the [WordPress plugin translation page](https://translate.wordpress.org/projects/wp-plugins/commonsbooking/) for other languages available at runtime.

Create a new .pot file using:
```
wp i18n make-pot . languages/commonsbooking.pot
```
Make sure that all of your strings use the `__` function with the domain `commonsbooking`. Then you can use `poedit` to open `commonsbooking-de_DE.po` and update the strings from the `pot` file.

### Build plugin zip

To create the plugin zip file for uploading to a development server:
```
bin/build-zip.sh
```
