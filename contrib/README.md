# CommonsBooking Community Snippets

A community-maintained library of PHP snippets for extending [CommonsBooking](https://github.com/wielebenwir/commonsbooking) — the WordPress plugin for commons-based item sharing.

**Target audience:** Developers and technically fluent site admins who add PHP code via a [Code Snippets plugin](https://wordpress.org/plugins/code-snippets/) or their theme's `functions.php`. No UI, no settings, no installers — just copy, understand, paste.

---

## How to use a snippet

1. Find a snippet that matches your use case in the table below
2. Read the file header to confirm the required CommonsBooking version
3. Copy the code into your Code Snippets plugin or `functions.php`
4. Test on a staging site before deploying to production

---

## Snippets

### Filters

| File | Description | CB Version |
|------|-------------|------------|
| [filter-mail-body-add-custom-text.php](snippets/filters/filter-mail-body-add-custom-text.php) | Append custom text to all booking confirmation emails | 2.8+ |

### Hooks (Actions)

| File | Description | CB Version |
|------|-------------|------------|
| [action-booking-single-before-show-user-info.php](snippets/hooks/action-booking-single-before-show-user-info.php) | Display extra user info before the booking single template | 2.10.8+ |

### Shortcodes

*No snippets yet — [contribute one!](CONTRIBUTING.md)*

### Integrations

*No snippets yet — [contribute one!](CONTRIBUTING.md)*

### Mini-plugins

*No snippets yet — [contribute one!](CONTRIBUTING.md)*

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). The short version: copy `_TEMPLATE.php`, fill the header, open a PR.

## License

All snippets in this repository are licensed under [GPL-2.0+](LICENSE).
