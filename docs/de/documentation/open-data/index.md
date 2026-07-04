# Offene Daten & Interoperabilität

CommonsBooking betreibt eine große und wachsende Zahl nicht-kommerzieller Lastenrad-Verleih- und
Leihladen-Initiativen in ganz Europa. Für Verbände, Forschende, politische Entscheidungsträger:innen und
Interessenvertretungen im Bereich Shared Mobility bietet das Plugin nicht nur die lokale Buchungsverwaltung
— es veröffentlicht diese Aktivität auch über offene, standardisierte Schnittstellen, sodass sie
aggregiert, ausgewertet und in größere Mobilitätsdaten-Ökosysteme eingebunden werden kann.

Diese Seite fasst die dafür relevanten Schnittstellen zusammen. Technische Details und Einrichtung findest
du in der [Schnittstellen / API](../api/) Dokumentation.

## GBFS (General Bikeshare Feed Specification)

Seit Version 2.5 kann CommonsBooking Stations-, Artikel- und Echtzeit-Verfügbarkeitsdaten über
[GBFS](https://www.gbfs.org/documentation/) veröffentlichen — den offenen Standard, den Bikesharing-Systeme,
MaaS-Apps und städtische/regionale Mobilitätsdatenplattformen nutzen, um geteilte Fahrzeugflotten
einzubinden. Damit können gemeinschaftlich betriebene Lastenrad-Flotten grundsätzlich in denselben
Datenpipelines wie kommerzielle Bikesharing-Systeme erscheinen.

→ [GBFS-Dokumentation](../api/gbfs)

## Die CommonsAPI

Die CommonsAPI ist ein eigens entwickeltes, offenes Schema, um einzelne CommonsBooking-Installationen mit
zentralen, organisationsübergreifenden Plattformen zu verbinden — zum Beispiel bundesweiten Verzeichnissen
verfügbarer Lastenräder. Sie stellt standardisierte Daten zu Trägerorganisationen (Projekten), Stationen,
Artikeln und deren Verfügbarkeit bereit.

→ [Was ist die CommonsAPI?](../api/what-is-the-commonsapi)
→ [CommonsBooking API nutzen](../api/commonsbooking-api)

## Größenordnung und Community-Kontext

CommonsBooking entstand aus den Initiativen der "Freien Lastenräder" in Köln und bildet heute die
technische Grundlage für den [Verband Freie Lastenräder e.V.](https://freies-lastenrad.org/verband/) (VFL),
der mehr als 100 freie Lastenrad-Initiativen listet. Hintergrundinfos gibt es auf der
[Über uns](../../about/) Seite.

## Kontakt

Wenn deine Organisation an Datenstandards für Shared Mobility, Lastenrad-Interessenvertretung oder
Forschung arbeitet und Interesse an einer Anbindung oder Zusammenarbeit hat, melde dich gerne über unsere
[Kontaktseite](../../contact).
