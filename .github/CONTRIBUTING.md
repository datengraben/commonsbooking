# Contributing to CommonsBooking

Contributions are welcome! There are several ways to help.

## Ways to contribute

- **Translate** — help translate the plugin into your language via the
  [WordPress plugin translations](https://translate.wordpress.org/projects/wp-plugins/commonsbooking/).
- **Improve the documentation** — the docs at [commonsbooking.org](https://commonsbooking.org)
  are built from the [`docs/`](../docs) directory in this repository.
- **Develop & test** — fix bugs or build new features (see below).

## Reporting bugs & requesting features

Use the [issue tracker](https://github.com/wielebenwir/commonsbooking/issues) and pick the
matching template. Please search existing issues first to avoid duplicates. For bug
reports, include your plugin version, PHP version, and steps to reproduce.

## Support questions

The issue tracker is **not** for general support. For setup and usage help, see
[SUPPORT.md](SUPPORT.md).

## Development setup

See the **Development** section of the [README](../Readme.md#development) for prerequisites
(PHP, Composer, Node.js), local setup with `wp-env`, running PHPUnit and Cypress tests, the
translation workflow, and building a release zip.

## Pull requests

Open your PR against the `master` branch and fill in the
[pull request template](PULL_REQUEST_TEMPLATE.md). Make sure the coding standards
(`composer run phpcs`) and the test suite pass, and add a changelog entry to `readme.txt`
when your change is user-facing.
