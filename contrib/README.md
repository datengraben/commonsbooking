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
| [filter-mail-body-add-custom-text.php](snippets/filters/filter-mail-body-add-custom-text.php) | Append custom text to all booking emails | 2.7.3+ |
| [filter-mail-subject-add-site-name.php](snippets/filters/filter-mail-subject-add-site-name.php) | Prepend site name to all booking email subjects | 2.7.3+ |
| [filter-mobile-calendar-show-3-months.php](snippets/filters/filter-mobile-calendar-show-3-months.php) | Show 3 months in the mobile booking calendar | 2.10.5+ |

### Hooks (Actions)

| File | Description | CB Version |
|------|-------------|------------|
| [action-booking-single-before-show-user-info.php](snippets/hooks/action-booking-single-before-show-user-info.php) | Display renter contact info above the booking details (admins only) | 2.10.8+ |
| [action-item-single-after-add-custom-notice.php](snippets/hooks/action-item-single-after-add-custom-notice.php) | Add a custom notice box below every item page | 2.10.8+ |
| [action-location-single-after-add-opening-hours.php](snippets/hooks/action-location-single-after-add-opening-hours.php) | Display opening hours from a custom field below a location page | 2.10.8+ |

### Shortcodes

| File | Shortcode | Description | CB Version |
|------|-----------|-------------|------------|
| [shortcode-cb-my-next-booking.php](snippets/shortcodes/shortcode-cb-my-next-booking.php) | `[cb_my_next_booking]` | Show the current user's next confirmed booking | 2.10+ |
| [shortcode-cb-item-availability-badge.php](snippets/shortcodes/shortcode-cb-item-availability-badge.php) | `[cb_item_availability_badge id="42"]` | Render an available / not-available badge for a specific item | 2.10+ |

### Integrations

*No snippets yet — [contribute one!](CONTRIBUTING.md)*

### Mini-plugins

*No snippets yet — [contribute one!](CONTRIBUTING.md)*

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). The short version: copy `_TEMPLATE.php`, fill the header, open a PR.

## License

All snippets in this repository are licensed under [GPL-2.0+](LICENSE).
