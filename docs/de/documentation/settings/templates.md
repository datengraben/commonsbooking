#  Vorlagen


Unter Einstellungen -> CommonsBooking findest du den Reiter **Vorlagen**

##  E-Mail Vorlagen

In den Vorlagen kannst du die Inhalte der Buchungsmails und die Absender-Adresse der Buchungs-Mails festlegen. 

::: tip TIPP
Möchtest du den Standardtext wieder herstellen, so lösche einfach alle Einträge die zurückgesetzt werden sollen und speichere die Änderungen, die Standard-Vorlagen werden dann wieder geladen.
:::

Um Daten aus der Buchung (wie Artikel, Buchungszeitraum etc.) in die Mail zu
integrieren, verwendet CommonsBooking so genannte [Template-Tags](../administration/template-tags). Dies sind Platzhalter, die in der E-Mail dann durch die entsprechenden Daten ersetzt
werden.

In den Standard-Vorlagen sind bereits die wichtigsten Template-Tags enthalten. Du kannst sie an jeder beliebige Stelle in den Vorlagen verwenden. Darüber hinaus kannst du auch HTML tags in den Vorlagen verwenden.

Du kannst weitere Template-Tags verwenden, wenn dir die standardmäßig
enthaltenen nicht ausreichen.

[Eine Übersicht zur Verwendung der Template-Tags findest du hier](../administration/template-tags)

##  iCalendar Dateien

CommonsBooking ist in der Lage aus den getätigten Buchungen eine .ics Datei zu generieren, die mit den meisten digitalen Kalendern kompatibel ist. Du kannst hier, genau wie in den E-Mail Vorlagen, die entsprechenden Template Tags verwenden. Die resultierende Kalenderdatei wird an die E-Mail angehängt und die Nutzenden können sie in ihren digitalen Kalender importieren. Die meisten E-Mail Programme unterstützen diesen Import mit einem Klick. Aktuell löscht die Stornierung einer Buchung noch nicht den erzeugten Kalendereintrag.
Darüber hinaus kannst du auch einen abonnierbaren Kalender erstellen, mehr dazu : [iCalendar Feed](../manage-bookings/icalendar-feed) .

##  Template und Buchungsprozess-Meldungen

In diesem Abschnitt findest du verschiedene Textbausteine, die an
unterschiedlichen Stellen ausgegeben werden. Die Felder enthalten jeweils eine
Beschreibung über die Verwendung der Textbausteine.

###  Benutzer\*innen-Details auf der Buchungsseite

In diesem Abschnitt definierst du, welche Benutzer\*innen Daten in der Buchungsdetailansicht angezeigt werden. Hier ist es z.B. möglich, Adressdaten
(Straße), Telefonnummer hinzuzufügen. CommonsBooking verwaltet die Nutzendendaten nicht selbst. [Bitte greife dafür auf externe Plugins zurück](../administration/custom-registration-user-fields). Bitte prüft, wie die Feldnamen in eurer Nutzer_ innen-Verwaltung heißen und fügt diese dann entsprechend hinzu. In der Vorlage könnt ihr auch einfache HTML-Formatierungen z.B. für Zeilenumbrüche (`<br>`) verwenden.
Hier ein Beispiel, um das Feld "phone" und das Feld "address" aus den Userdaten anzuzeigen:
```
{{[Telefon: ]user:phone}} <br>
{{[Adresse: ]user:address }}
```
::: warning ACHTUNG
Bitte beachtet, dass die Feldnamen (z.B. "phone" und "address") exakt so geschrieben werden müssen, wie sie in eurer Nutzenden-Verwaltung hinterlegt sind.
:::

In den eckigen Klammern steht die Bezeichnung, die vor dem jeweiligen Wert angezeigt werden soll.

##  Bildformatierung

::: warning ACHTUNG
Dieses Feauture funktioniert aktuell nicht. Wir arbeiten an einer Lösung.
:::

Wenn du die Shortcodes [cb_items] oder [cb_locations] nutzt, erzeugt CommonsBooking entsprechende Listenansichten mit Vorschaubildern der Artikel
und Standorte. In dieser Einstellung kannst du die Standardgröße dieser Vorschaubilder anpassen.

##  Stationen & Artikel in der Nähe

Diese Einstellungen steuern den [`[cb_nearby]`-Shortcode](../administration/shortcodes#stationen-oder-artikel-in-der-nahe), der ein Karussell der nächstgelegenen Stationen oder Artikel anzeigt. Die Entfernungen werden aus den Geo-Koordinaten der Stationen berechnet.

* **Artikel in der Nähe auf Artikelseiten anzeigen** / **Stationen in der Nähe auf Stationsseiten anzeigen** – ist dies aktiviert, wird das Karussell automatisch unter jeder Artikel- bzw. Stationsseite angezeigt, ohne den Shortcode manuell einzufügen.
* **Was auf Artikel- / Stationsseiten angezeigt wird** – ob das automatisch angezeigte Karussell Artikel oder Stationen in der Nähe auflistet.
* **Maximale Entfernung (km)** – weiter entfernte Objekte werden nicht angezeigt. Wird auch als Standard für den Shortcode verwendet, wenn kein `max_distance`-Parameter angegeben ist.
* **Maximale Anzahl an Ergebnissen** – wie viele Karten das Karussell höchstens enthält.
* **Gleichzeitig sichtbare Karten** – wie viele Karten auf breiten Bildschirmen nebeneinander angezeigt werden.
* **Globale Konfiguration überschreibt Shortcode-Parameter** – standardmäßig hat ein direkt am `[cb_nearby]`-Shortcode gesetzter Parameter Vorrang vor diesen globalen Einstellungen. Aktiviere dies, damit stattdessen die globalen Einstellungen gewinnen.
* **Text, wenn nichts in der Nähe ist** – optionale eigene Meldung. Leer lassen, um den Standardtext zu verwenden.

##  Farben

Sämtliche Farben in der Benutzeroberfläche sind anpassbar. Um Farben wieder auf die Standardwerte zurückzusetzen kannst du in der entsprechenden Farbe auf den "Leeren" Knopf drücken und anschließend deine Änderungen speichern. Jetzt sollte für das entsprechende Feld wieder der Standardwert eingestellt sein.