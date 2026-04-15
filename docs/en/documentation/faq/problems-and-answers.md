# Problems and answers

## What does the Support E-Mail link send?

The **Support E-Mail** link on the CommonsBooking dashboard (`CommonsBooking → Dashboard`) opens your email client with a pre-filled message body containing a diagnostic snapshot of your installation. This helps the support team reproduce and diagnose your issue without needing to ask follow-up questions.

The following information is included automatically:

- **Site URL** — the address of your WordPress installation
- **WordPress version**, **PHP version**, **CommonsBooking version**
- **Active theme** name and version
- **Locale** (language setting)
- **WP_DEBUG** status (enabled/disabled)
- **PHP memory limit**
- **Permalink structure**
- **CB settings** — whether booking comments, the iCal feed, and the API are enabled, the cache adapter in use, and the configured bookings page
- **Max upload size**
- **All active plugins** with their versions
- Any **known-incompatible plugins or themes** that are currently active (see sections below)

If your installation is a **WordPress Multisite**, has **WP Cron disabled**, or uses an **external object cache** (e.g. Redis or Memcached), those facts are noted as well — but only when they apply, to keep the email tidy otherwise.

No passwords, user data, or booking content is included. You can review and edit the pre-filled message before sending it.

### Calendar widget display in the admin area

If there are problems displaying the calendar in the booking admin area (the admin backend), see the image below on the right, one possible solution is to disable or remove and reinstall the ["Lightstart" (wp-maintenance-mode) plugin](https://wordpress.org/plugins/wp-maintenance-mode). 
The issue is an incompatibility between Lightstart and CommonsBooking and not a bug in CommonsBooking's code.
The problem does not occur after reinstalling Lightstart. More details on [GitHub in the CommonsBooking source repository](https://github.com/wielebenwir/commonsbooking/issues/1646).

![](/img/backend-booking-list-bug.png)

### Incompatible theme: GridBulletin

The latest version of [GridBulletin](https://wordpress.org/themes/gridbulletin) is incompatible with CommonsBooking.
Problems occur when the footer is enabled. One concrete issue is the missing booking calendar on the item page. From a technical perspective, the required JavaScript sources from CommonsBooking are not being loaded. The root cause within the GridBulletin theme or a solution has not yet been found.
