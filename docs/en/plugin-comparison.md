# Plugin Comparison

CommonsBooking is purpose-built for community sharing of items and resources — not just appointment booking. The table below shows how it compares to other popular WordPress booking plugins on ten key features.

| Feature | CommonsBooking | Bookly | Amelia | WooCommerce Bookings | Booking Calendar |
|---|:---:|:---:|:---:|:---:|:---:|
| **Free & Open Source** | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Map / Location Display** | ✅ | ❌ | ❌ | ❌ | ❌ |
| **GBFS Support** | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Physical Booking Codes** | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Delegated Manager Role** | ✅ | ❌ | ❌ | ❌ | ❌ |
| **iCal Export** | ✅ | ❌ | ❌ | ❌ | ✅ |
| **iCal Import** | ❌ | ❌ | ❌ | ❌ | ✅ |
| **Google Calendar Sync** | ❌ | ⚠️ | ✅ | ✅ | ⚠️ |
| **Waiting List** | ❌ | ⚠️ | ✅ | ❌ | ❌ |
| **Booking Calendar** | ✅ | ✅ | ✅ | ✅ | ✅ |

_✅ Native · ⚠️ Paid add-on / extension · ❌ Not supported_

## Feature details

### Free & Open Source

CommonsBooking is released under GPLv2+ and is entirely free. The other plugins listed are commercial products (paid or commercial freemium). For community organizations, NGOs, and volunteer-run sharing initiatives this is often a deciding factor.

### Map / Location Display

An interactive map that shows where items and lending stations are located, with filter options. CommonsBooking uses [Leaflet](https://leafletjs.com/) and supports item clustering and availability filters. This feature is rare in general-purpose booking plugins, which are typically designed for fixed-location services rather than distributed shared resources.

### GBFS Support

The [General Bikeshare Feed Spec](https://gbfs.org/) (GBFS) is an open data standard for shared mobility. CommonsBooking exposes a GBFS-compatible REST API so that external apps, city portals, and mobility platforms can discover and display your shared items. This feature is unique to CommonsBooking among WordPress booking plugins. See [GBFS](./documentation/api/gbfs.md) for details.

### Physical Booking Codes

When a booking is confirmed, CommonsBooking assigns a pre-generated, human-readable code to that date. The user shows this code at the lending station as proof of reservation. Codes are configured per timeframe, drawn from a custom pool or built-in list, and exportable as CSV. See [Booking Codes](./documentation/settings/booking-codes.md) for details.

### Delegated Manager Role

CommonsBooking introduces a **CB Manager** WordPress role. An admin can assign a manager to specific items or locations; that person can manage timeframes and bookings only for their assigned items — no full WP admin access required. Designed for distributed volunteer networks. See [Permission Management](./documentation/basics/permission-management.md) for details.

### iCal Export

A personal iCalendar feed (`.ics`) that users can subscribe to in their calendar app (Google Calendar, Thunderbird, Apple Calendar, …) to see their bookings. CommonsBooking generates per-user feeds secured by a personal hash. See [iCalendar Feed](./documentation/manage-bookings/icalendar-feed.md) for setup details.

### iCal Import

CommonsBooking does not support importing external iCal feeds. Booking Calendar (wpbooking.com) can ingest external `.ics` links (e.g., from Airbnb, Booking.com, or Google Calendar) to automatically block unavailability.

### Google Calendar Sync

CommonsBooking has no Google Calendar integration. Amelia offers native one-way sync; WooCommerce Bookings supports two-way sync via a third-party extension; Bookly requires a paid add-on.

### Waiting List

CommonsBooking does not offer a waiting list. Amelia (Pro/Elite plans) provides native waitlist functionality with automatic notifications when a slot opens up. Bookly offers this as a paid add-on.

### Booking Calendar

An interactive calendar that shows item availability and lets users select a booking period. All plugins listed above provide this core feature.

---

> Feature information is based on publicly available plugin documentation. Competitor features may vary by version or pricing tier.
