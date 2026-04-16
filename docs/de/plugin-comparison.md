# Plugin-Vergleich

CommonsBooking wurde speziell für die gemeinschaftliche Nutzung von Gegenständen und Ressourcen entwickelt – nicht nur für die Terminbuchung. Die folgende Tabelle zeigt, wie es im Vergleich zu anderen beliebten WordPress-Buchungs-Plugins in vier Schlüsselbereichen abschneidet.

| Merkmal | CommonsBooking | Bookly | Amelia | WooCommerce Bookings | Booking Calendar |
|---|:---:|:---:|:---:|:---:|:---:|
| **Buchungskalender** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Karte / Standortanzeige** | ✅ | ❌ | ❌ | ❌ | ❌ |
| **iCal-Export** | ✅ | ❌ | ❌ | ❌ | ✅ |
| **GBFS-Unterstützung** | ✅ | ❌ | ❌ | ❌ | ❌ |

## Merkmale im Detail

### Buchungskalender

Ein interaktiver Kalender, der die Verfügbarkeit von Artikeln anzeigt und es Nutzer:innen ermöglicht, einen Buchungszeitraum auszuwählen. Alle oben aufgeführten Plugins bieten diese Grundfunktion.

### Karte / Standortanzeige

Eine interaktive Karte, die zeigt, wo sich Artikel und Leihstationen befinden, mit Filtermöglichkeiten. CommonsBooking verwendet [Leaflet](https://leafletjs.com/) und unterstützt Artikel-Clustering und Verfügbarkeitsfilter. Diese Funktion ist bei allgemeinen Buchungs-Plugins selten, da diese typischerweise für ortsgebundene Dienstleistungen und nicht für verteilte Leihressourcen konzipiert sind.

### iCal-Export

Ein persönlicher iCalendar-Feed (`.ics`), den Nutzer:innen in ihrer Kalender-App (Google Calendar, Thunderbird, Apple Calendar, …) abonnieren können, um ihre Buchungen zu sehen. CommonsBooking erstellt benutzerspezifische Feeds, die durch einen persönlichen Hash gesichert sind. Details zur Einrichtung: [iCalendar Feed](./documentation/manage-bookings/icalendar-feed.md).

### GBFS-Unterstützung

Das [General Bikeshare Feed Spec](https://gbfs.org/) (GBFS) ist ein offener Datenstandard für geteilte Mobilität. CommonsBooking stellt eine GBFS-kompatible REST-API bereit, damit externe Apps, Stadtportale und Mobilitätsplattformen die geteilten Artikel auffinden und anzeigen können. Diese Funktion ist einzigartig unter WordPress-Buchungs-Plugins. Details: [GBFS](./documentation/api/gbfs.md).

---

> Die Informationen basieren auf öffentlich zugänglichen Plugin-Dokumentationen. Funktionen von Drittanbieter-Plugins können je nach Version oder Preisniveau variieren.
