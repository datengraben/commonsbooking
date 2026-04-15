# Probleme und Antworten

## Was sendet der „Support E-Mail"-Link?

Der Link **Support E-Mail** im CommonsBooking-Dashboard (`CommonsBooking → Dashboard`) öffnet dein E-Mail-Programm mit einer vorausgefüllten Nachricht, die eine Diagnosezusammenfassung deiner Installation enthält. So kann das Support-Team dein Problem nachvollziehen und lösen, ohne zunächst Rückfragen stellen zu müssen.

Folgende Informationen werden automatisch eingefügt:

- **Website-URL** — die Adresse deiner WordPress-Installation
- **WordPress-Version**, **PHP-Version**, **CommonsBooking-Version**
- **Aktives Theme** (Name und Version)
- **Locale** (Spracheinstellung)
- **WP_DEBUG**-Status (aktiviert/deaktiviert)
- **PHP-Speicherlimit**
- **Permalink-Struktur**
- **CB-Einstellungen** — ob Buchungskommentare, der iCal-Feed und die API aktiviert sind, der verwendete Cache-Adapter sowie die konfigurierte Buchungsseite
- **Maximale Upload-Größe**
- **Alle aktiven Plugins** mit ihren Versionen
- Alle derzeit aktiven **bekannten inkompatiblen Plugins oder Themes** (siehe Abschnitte weiter unten)

Falls deine Installation ein **WordPress-Multisite**-Netzwerk ist, **WP Cron deaktiviert** hat oder einen **externen Objekt-Cache** (z. B. Redis oder Memcached) verwendet, wird dies ebenfalls vermerkt — jedoch nur, wenn es zutrifft, damit die E-Mail übersichtlich bleibt.

Passwörter, Nutzerdaten oder Buchungsinhalte werden nicht übermittelt. Du kannst die vorausgefüllte Nachricht vor dem Senden einsehen und bearbeiten.

###  Anzeige Kalender-Widget im Admin-Bereich

Treten Probleme bei der Anzeige des Kalenders im Admin-Bereich der Buchungen
auf (sog. Admin-Backend), siehe das folgende Bild rechts unten, kann eine
mögliche Lösung sein, das [ Plugin "Lightstart" (wp-maintenance-mode)
](https://wordpress.org/plugins/wp-maintenance-mode) zu deaktivieren oder zu
entfernen und neu zu installieren. Das Problem ist eine Inkompatibilität von
Lightstart mit CommonsBooking und kein Fehler im Code von CommonsBooking. Das
Problem tritt nicht mehr auf, wenn eine Neuinstallation von Lightstart
vorgenommen wurde. Mehr dazu auf [ Github im CommonsBooking Quellcode-
Repository ](https://github.com/wielebenwir/commonsbooking/issues/1646) .

![](/img/backend-booking-list-bug.png)

###  Inkompatibles Theme Gridbulletin

In der letzten Version von [ GridBulletin
](https://wordpress.org/themes/gridbulletin) kommt es zu einer
Inkompatibilität mit CommonsBooking. Probleme tauchen auf, wenn der Footer
aktiviert ist. Konkrete Probleme sind z.B. das Fehlen des Buchungs-Kalenders
auf der Artikelseite. Aus technischer Sicht liegt es daran, dass die nötigen
Javascript-Quellen von CommonsBooking nicht ausgeliefert werden. Der Grund
innerhalb des GridBulletin Themes oder eine Lösung konnte bisher nicht
gefunden werden.

