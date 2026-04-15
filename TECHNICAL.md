### Support Email Diagnostic Info

The "Support E-Mail" link on the CommonsBooking admin dashboard (`CommonsBooking → Dashboard`) pre-fills an email to `mail@commonsbooking.org` with a diagnostic snapshot of the installation. This reduces back-and-forth by ensuring support requests arrive with the context needed to reproduce issues.

**Code location:** `templates/dashboard-index.php`

The body is built as a plain PHP string and passed through `rawurlencode()` before being placed in the `mailto:` href. The link is output via `esc_attr()`.

#### Fields included

| Field | Source | Notes |
|---|---|---|
| Installations-URL | `home_url()` | Always |
| WP-Version | `get_bloginfo('version')` | Always |
| PHP-Version | `phpversion()` | Always |
| CB-Version | `COMMONSBOOKING_VERSION` | Always |
| Theme | `wp_get_theme()->get('Name'/'Version')` | Always |
| Locale | `get_locale()` | Always |
| WP_DEBUG | `defined('WP_DEBUG') && WP_DEBUG` | Always |
| PHP-Memory-Limit | `ini_get('memory_limit')` | Always |
| Permalink-Structure | `get_option('permalink_structure')` | Always |
| Multisite | `is_multisite()` | Only when true |
| WP-Cron | `DISABLE_WP_CRON` constant | Only when disabled |
| External-Object-Cache | `wp_using_ext_object_cache()` | Only when active |
| Active known-problematic plugins/themes | `is_plugin_active()` + `wp_get_theme()` | Only when any are active |
| Max-Upload-Size | `wp_max_upload_size()` | Always |
| CB Settings | `get_option('commonsbooking_options_*')` | Always (booking comments, iCal, API, cache adapter, bookings page) |
| Active plugins | `get_plugins()` + `get_option('active_plugins')` | Always |

#### Adding a new field

Append a line to `$support_body` inside the PHP block before `$support_href` is built:

```php
$support_body .= 'My-Field: ' . my_wp_function() . "\r\n";
```

For conditional fields (only shown when non-default), wrap in an `if`:

```php
if ( some_condition() ) {
    $support_body .= 'My-Flag: active' . "\r\n";
}
```

#### Maintaining the known-problematic plugin list

The `$known_problematic_plugins` array maps plugin file paths (as registered in WordPress) to human-readable names. The list is sourced from the [FAQ](docs/en/documentation/faq/problems-and-answers.md). When a new incompatibility is documented in the FAQ, add it here too:

```php
$known_problematic_plugins = [
    // ...
    'new-plugin/new-plugin.php' => 'New Plugin Display Name',
];
```

The plugin file path is always `folder-name/main-file.php` — the same string stored in the `active_plugins` option.

---

### Formatter

We adhere to [PHPCS](https://github.com/PHPCSStandards/PHP_CodeSniffer) rules defined in the [phpcs.xml](https://github.com/wielebenwir/commonsbooking/blob/master/.phpcs.xml.dist) rules file, as  it is a mature tool and well established in the Wordpress-Plugin development scene.
A program to apply auto-formattable rules of PHPCS is `phpcbf` and we encourage everyone
to configure this tool in their IDE so that contribution commits consist of properly formatted code.
Both are already in the dev dependencies of the repository code.

We have an automatic check [as Github Action](https://github.com/wielebenwir/commonsbooking/tree/master/.github/workflows/phpcbf-check.yml) in our CI/CD-Pipeline, which prevents code contributions that not adhere to the rules.

#### Ignore formatter revisions

We use .git-blame-ignore-revs to track repo-wide cosmetic refactorings by auto format tools like prettier/phpcbf.
See [Github Documentation](https://docs.github.com/de/repositories/working-with-files/using-files/viewing-and-understanding-files#ignore-commits-in-the-blame-view)

You can also configure your local git so it always ignores the revs in that file:

```bash
git config blame.ignoreRevsFile .git-blame-ignore-revs
```
