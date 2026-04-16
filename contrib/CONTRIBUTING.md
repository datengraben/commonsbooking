# Contributing to CommonsBooking Community Snippets

Thanks for sharing your snippet! The goal of this library is to make useful CommonsBooking customizations discoverable and reusable without requiring changes to the core plugin.

---

## Before you start

- Your snippet must use CommonsBooking's public hook/filter/shortcode API — no monkey-patching private methods or modifying plugin files directly.
- All contributions must be licensed **GPL-2.0+** (required for WordPress compatibility).
- This library targets **technical users**: developers and admins who paste PHP into a Code Snippets plugin or `functions.php`. No UI code, no settings pages.

---

## Step-by-step

### 1. Copy the template

```
cp _TEMPLATE.php snippets/filters/filter-my-feature-description.php
```

### 2. Name the file

Use the pattern: `{type}-{hook-name}-{what-it-does}.php`

| Type | Prefix | Example |
|------|--------|---------|
| `apply_filters` | `filter-` | `filter-mail-body-add-location-phone.php` |
| `add_action` | `action-` | `action-booking-single-before-add-map.php` |
| `add_shortcode` | `shortcode-` | `shortcode-cb-item-qr-code.php` |
| Integration with another plugin | `integration-` | `integration-woocommerce-sync-booking.php` |

### 3. Fill the file header

Every snippet **must** start with this header (all fields required unless marked optional):

```php
/**
 * Snippet Title: Short human-readable title
 * Description:   One-sentence explanation of what it does and why it is useful.
 * Hook/Filter:   commonsbooking_[hook_name]
 * CB Version:    2.10+
 * Tested up to:  2.10.8
 * Author:        Your name or GitHub handle
 * Author URI:    https://github.com/yourhandle  (optional)
 * License:       GPL-2.0+
 * Requires Plugins: other-plugin-slug  (optional, only if another plugin is needed)
 */
```

### 4. Security checklist

Before opening a PR, confirm your snippet does **not**:

- [ ] Pass user input directly to database queries (`$wpdb->query( $_GET['x'] )`)
- [ ] Output user-controlled data without escaping (`echo $_POST['x']`)
- [ ] Accept form submissions without nonce verification
- [ ] Use `eval()` or dynamic `include`/`require` with user input
- [ ] Hard-code credentials, API keys, or email addresses (use variables or settings instead)

### 5. Update README.md

Add a row to the relevant table in `README.md`:

```markdown
| [your-file.php](snippets/filters/your-file.php) | One-sentence description | 2.10+ |
```

### 6. Open a PR

The review bar is intentionally low:
- Does the snippet work?
- Is the header complete?
- Does it pass the security checklist above?

No architectural review. No "this should be done differently" gatekeeping. If it works and it's safe, it gets merged.

---

## File structure

```
contrib/
├── README.md
├── CONTRIBUTING.md
├── LICENSE
├── _TEMPLATE.php              ← copy this for every new snippet
├── futureplan.md              ← planned improvements not yet implemented
├── snippets/
│   ├── filters/               ← apply_filters customizations
│   ├── hooks/                 ← add_action customizations
│   ├── shortcodes/            ← custom shortcodes using CB APIs
│   └── integrations/         ← CB + another plugin
└── mini-plugins/              ← full WP plugin packages that depend on CB
    └── example-addon/
        ├── example-addon.php
        └── README.md
```
