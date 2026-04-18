# Plugin-Vergleich

CommonsBooking wurde speziell für die gemeinschaftliche Nutzung von Gegenständen und Ressourcen entwickelt – nicht nur für die Terminbuchung. Die folgende Tabelle zeigt, wie es im Vergleich zu anderen beliebten WordPress-Buchungs-Plugins in zehn Schlüsselbereichen abschneidet.

| Merkmal | CommonsBooking | Bookly | Amelia | WooCommerce Bookings | Booking Calendar |
|---|:---:|:---:|:---:|:---:|:---:|
| **Kostenlos & Open Source** | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Karte / Standortanzeige** | ✅ | ❌ | ❌ | ❌ | ❌ |
| **GBFS-Unterstützung** | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Physische Buchungscodes** | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Delegierte Manager-Rolle** | ✅ | ❌ | ❌ | ❌ | ❌ |
| **iCal-Export** | ✅ | ❌ | ❌ | ❌ | ✅ |
| **iCal-Import** | ❌ | ❌ | ❌ | ❌ | ✅ |
| **Google-Kalender-Synchronisation** | ❌ | ⚠️ | ✅ | ✅ | ⚠️ |
| **Warteliste** | ❌ | ⚠️ | ✅ | ❌ | ❌ |
| **Buchungskalender** | ✅ | ✅ | ✅ | ✅ | ✅ |

_✅ Nativ · ⚠️ Kostenpflichtiges Add-on / Erweiterung · ❌ Nicht unterstützt_

## Merkmale im Detail

### Kostenlos & Open Source

CommonsBooking wird unter GPLv2+ veröffentlicht und ist vollständig kostenlos. Alle anderen aufgeführten Plugins sind kommerzielle Produkte (kostenpflichtig oder kommerziell-freemium). Für gemeinnützige Organisationen und ehrenamtliche Sharing-Initiativen ist dies oft ausschlaggebend.

### Karte / Standortanzeige

Eine interaktive Karte, die zeigt, wo sich Artikel und Leihstationen befinden, mit Filtermöglichkeiten. CommonsBooking verwendet [Leaflet](https://leafletjs.com/) und unterstützt Artikel-Clustering und Verfügbarkeitsfilter. Diese Funktion ist bei allgemeinen Buchungs-Plugins selten, da diese typischerweise für ortsgebundene Dienstleistungen und nicht für verteilte Leihressourcen konzipiert sind.

### GBFS-Unterstützung

Das [General Bikeshare Feed Spec](https://gbfs.org/) (GBFS) ist ein offener Datenstandard für geteilte Mobilität. CommonsBooking stellt eine GBFS-kompatible REST-API bereit, damit externe Apps, Stadtportale und Mobilitätsplattformen die geteilten Artikel auffinden und anzeigen können. Diese Funktion ist einzigartig unter WordPress-Buchungs-Plugins. Details: [GBFS](./documentation/api/gbfs.md).

### Physische Buchungscodes

Nach Buchungsabschluss erhält das Datum einen vorgenerierten, menschenlesbaren Code. Die ausleihende Person zeigt diesen Code an der Leihstation als Buchungsnachweis vor. Codes sind pro Zeitrahmen konfigurierbar, aus einem benutzerdefinierten Pool oder einer integrierten Liste auswählbar und als CSV exportierbar. Details: [Buchungscodes](./documentation/settings/booking-codes.md).

### Delegierte Manager-Rolle

CommonsBooking führt eine **CB-Manager:in**-WordPress-Rolle ein. Administrator:innen können Personen bestimmten Artikeln oder Standorten zuweisen; diese Personen können Zeitrahmen und Buchungen nur für die ihnen zugewiesenen Artikel verwalten – ohne allgemeine WP-Adminrechte. Konzipiert für verteilte ehrenamtliche Netzwerke. Details: [Berechtigungsverwaltung](./documentation/basics/permission-management.md).

### iCal-Export

Ein persönlicher iCalendar-Feed (`.ics`), den Nutzer:innen in ihrer Kalender-App (Google Calendar, Thunderbird, Apple Calendar, …) abonnieren können, um ihre Buchungen zu sehen. CommonsBooking erstellt benutzerspezifische Feeds, die durch einen persönlichen Hash gesichert sind. Details zur Einrichtung: [iCalendar Feed](./documentation/manage-bookings/icalendar-feed.md).

### iCal-Import

CommonsBooking unterstützt keinen Import externer iCal-Feeds. Booking Calendar (wpbooking.com) kann externe `.ics`-Links (z. B. von Airbnb, Booking.com oder Google Calendar) einlesen, um Verfügbarkeiten automatisch zu sperren.

### Google-Kalender-Synchronisation

CommonsBooking bietet keine Google-Kalender-Integration. Amelia synchronisiert einseitig nativ; WooCommerce Bookings unterstützt bidirektionale Synchronisation via einer Drittanbieter-Erweiterung; Bookly benötigt ein kostenpflichtiges Add-on.

### Warteliste

CommonsBooking bietet keine Warteliste. Amelia (Pro/Elite) stellt eine native Warteliste mit automatischen Benachrichtigungen bereit, sobald ein Platz frei wird. Bookly bietet dies als kostenpflichtiges Add-on.

### Buchungskalender

Ein interaktiver Kalender, der die Verfügbarkeit von Artikeln anzeigt und es Nutzer:innen ermöglicht, einen Buchungszeitraum auszuwählen. Alle oben aufgeführten Plugins bieten diese Grundfunktion.

---

> Die Informationen basieren auf öffentlich zugänglichen Plugin-Dokumentationen. Funktionen von Drittanbieter-Plugins können je nach Version oder Preisniveau variieren.
