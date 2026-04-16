# Plugin Comparison

CommonsBooking is purpose-built for community sharing of items and resources — not just appointment booking. The table below shows how it compares to other popular WordPress booking plugins on four key features.

| Feature | CommonsBooking | Bookly | Amelia | WooCommerce Bookings | Booking Calendar |
|---|:---:|:---:|:---:|:---:|:---:|
| **Booking Calendar** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Map / Location Display** | ✅ | ❌ | ❌ | ❌ | ❌ |
| **iCal Export** | ✅ | ❌ | ❌ | ❌ | ✅ |
| **GBFS Support** | ✅ | ❌ | ❌ | ❌ | ❌ |

## Feature details

### Booking Calendar

An interactive calendar that shows item availability and lets users select a booking period. All plugins listed above provide this core feature.

### Map / Location Display

An interactive map that shows where items and lending stations are located, with filter options. CommonsBooking uses [Leaflet](https://leafletjs.com/) and supports item clustering and availability filters. This feature is rare in general-purpose booking plugins, which are typically designed for fixed-location services rather than distributed shared resources.

### iCal Export

A personal iCalendar feed (`.ics`) that users can subscribe to in their calendar app (Google Calendar, Thunderbird, Apple Calendar, …) to see their bookings. CommonsBooking generates per-user feeds secured by a personal hash. See [iCalendar Feed](./documentation/manage-bookings/icalendar-feed.md) for setup details.

### GBFS Support

The [General Bikeshare Feed Spec](https://gbfs.org/) (GBFS) is an open data standard for shared mobility. CommonsBooking exposes a GBFS-compatible REST API so that external apps, city portals, and mobility platforms can discover and display your shared items. This feature is unique to CommonsBooking among WordPress booking plugins. See [GBFS](./documentation/api/gbfs.md) for details.

---

> Feature information is based on publicly available plugin documentation. Competitor features may vary by version or pricing tier.
