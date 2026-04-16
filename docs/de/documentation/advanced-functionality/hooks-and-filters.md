#  Hooks und Filter


##  Action Hooks

Mit Hooks (https://developer.wordpress.org/plugins/hooks/) kannst du deine eigenen
Code-Schnipsel an bestimmten Stellen in den CommonsBooking Vorlagen einbinden.
So kannst du deinen eigenen Code in die Templates einfügen, ohne die
entsprechenden Template Dateien ersetzen zu müssen.

Code Schnipsel sind meist sehr kurzer Code in PHP und kann über ein [ Child
Theme ](https://developer.wordpress.org/themes/advanced-topics/child-themes)
eingebunden werden oder über spezielle Plugins für Code Schnipsel (z.B. Code
Snippets). Dafür musst du nicht sonderlich viel PHP können, es ist aber auch
möglich mit diesen Snippets tief in die Funktion von CommonsBooking einzugreifen
oder auch Fehler zu erzeugen, die das Buchungssystem nicht mehr
nutzbar machen. Wenn du in der Dokumentation Beispiele siehst, dann sind diese
einigermaßen sicher und getestet. Ein gewisses Restrisiko bleibt aber. Falls
du Probleme haben solltest, dann kannst du dich gerne an uns wenden. Bitte gib
aber auch sämtliche Codeschnipsel mit an, die ihr verwendet. Dadurch können
wir das Problem besser nachvollziehen.

Die Action Hooks sind nach dem Prinzip

`commonsbooking_(before/after)_(template-file)`

strukturiert. Mit der Funktion _add_action_ kannst du deine eigene Callback
Funktion integrieren. Beispiel:


```php
function itemsingle_callback() {
    // wird vor dem item-single template angezeigt
}
add_action( 'commonsbooking_before_item-single', 'itemsingle_callback' );
```

###  Alle Action Hooks im Überblick:

  * commonsbooking_before_booking-single
  * commonsbooking_after_booking-single
  * commonsbooking_before_location-calendar-header
  * commonsbooking_after_location-calendar-header
  * commonsbooking_before_item-calendar-header
  * commonsbooking_after_item-calendar-header
  * commonsbooking_before_location-single
  * commonsbooking_after_location-single
  * commonsbooking_before_timeframe-calendar
  * commonsbooking_after_timeframe-calendar
  * commonsbooking_before_item-single
  * commonsbooking_after_item-single
  * commonsbooking_mail_sent
  * commonsbooking_booking_pre_validate *(ab 2.10.11)*
  * commonsbooking_booking_created *(ab 2.10.11)*
  * commonsbooking_booking_confirmed *(ab 2.10.11)*
  * commonsbooking_save_booking_meta *(ab 2.10.11)*

### Hooks im Objektkontext (seit 2.10.8)

Manche Action Hooks übergeben noch zusätzlich die Post ID des aktuellen Objekts und eine Instanz aus der Klasse \CommonsBooking\Model\<Objektklasse>. Das sind:

  * `commonsbooking_before_booking-single` bzw. `commonsbooking_after_booking-single`
    * Parameter: `int $booking_id`, `\CommonsBooking\Model\Booking $booking`
  * `commonsbooking_before_location-single` bzw. `commonsbooking_after_location-single`
    * Parameter: `int $location_id`, `\CommonsBooking\Model\Location $location`
  * `commonsbooking_before_item-single` bzw. `commonsbooking_after_item-single`
    * Parameter: `int $item_id`, `\CommonsBooking\Model\Item $item`
  * `commonsbooking_before_item-calendar-header` bzw. `commonsbooking_after_item-calendar-header`
    * Parameter: `int $item_id`, `\CommonsBooking\Model\Item $item`
  * `commonsbooking_before_location-calendar-header` bzw. `commonsbooking_after_location-calendar-header`
    * Parameter: `int $location_id`, `\CommonsBooking\Model\Location $location`

Beispielverwendung:
```php
function my_cb_before_booking_single( $booking_id, $booking ) {
    echo 'Buchungs ID: ' . $booking_id;
    echo 'Der Buchungsstatus ist ' . $booking->getStatus();
}
add_action( 'commonsbooking_before_booking-single', 'my_cb_before_booking_single', 10, 2 );
```

##  Filter Hooks

Filter Hooks (https://developer.wordpress.org/plugins/hooks/filters) funktionieren
ähnlich wie Action Hooks jedoch mit dem Unterschied, dass die Callback
Funktion einen Wert übergeben bekommt, diesen modifiziert und ihn dann wieder
zurückgibt.

###  Alle Filter Hooks im Überblick:

  * commonsbooking_custom_metadata
  * [commonsbooking_isCurrentUserAdmin](../basics/permission-management#filterhook-isCurrentUserAdmin)
  * commonsbooking_isCurrentUserSubscriber
  * commonsbooking_get_template_part
  * commonsbooking_template_tag
  * commonsbooking_tag_$key_$property
  * commonsbooking_booking_filter
  * commonsbooking_mail_to
  * commonsbooking_mail_subject
  * commonsbooking_mail_body
  * commonsbooking_mail_attachment
  * commonsbooking_disableCache
  * commonsbooking_booking_form_fields *(ab 2.10.11)*
  * commonsbooking_booking_confirmation_fields *(ab 2.10.11)*
  * commonsbooking_booking_redirect_url *(ab 2.10.11)*
  * commonsbooking_booking_meta_input *(ab 2.10.11)*

Es gibt auch Filter Hooks, mit denen du zusätzliche Benutzerrollen, die
zusätzlich zum CB Manager Artikel und Standorte administrieren können,
hinzufügen kannst.
Mehr dazu: [Zugriffsrechte vergeben (CB-Manager)](../basics/permission-management#andere-rollen-einem-artikel-standort-zuweisen-ab-2-8-2)

Darüber hinaus gibt es Filter Hooks, mit denen du die voreingestellten
Standardwerte bei der Zeitrahmenerstellung ändern kannst, mehr dazu [hier](../advanced-functionality/change-timeframe-creation-defaults):

### Filter Hook: commonsbooking_custom_metadata

Über diesen Hook lassen sich neue [CMB2-Metadaten-Felder](https://cmb2.io) zu einem
der [Custom-Post-Types von CommonsBooking](../basics/concepts) hinzufügen.
Diese Felder können dann üblicherweise über den Admin-Bereich gepflegt werden.
Beachtung benötigt dabei die Struktur des übergebenen `$metaDataFields`, ein
verschachteltes assoziatives Array.

```
array => [
  "cb_bookings" => [
    [ "id" => ..., "name" => ..., "type" => ..., "desc" => ..., ...],
    ...
  ],
  ...
]
```

Da zur ordentlichen Erweiterung der Felder technische Expertise nötig ist, verweisen wir an dieser Stelle
auf die [Quelldatei `OptionsArray.php`](https://github.com/wielebenwir/commonsbooking/blob/master/includes/OptionsArray.php)
als Referenz für die Nutzung von CMB2-Feldern in CommonsBooking.

###  Filter Hook: commonsbooking_tag_$key_$property

::: tip INFO
Ab Version 2.10.9 wird auch der Objektkontext mit übergeben.
Die u.g. Beispiele sind nur für die Version 2.10.9 oder höher geeignet.
:::

Dieser Filter Hook ist dazu da, um das Standardverhalten der Template Tags zu überschreiben.
Dabei muss der Wert $key und $property mit den entsprechenden Werten der Template Tags ersetzt werden. Das $key entspricht dabei dem post_type des Objekts, z.B. `cb_location` oder `cb_item` und $property entspricht der Eigenschaft bzw. dem Metafeld, dass du überschreiben möchtest, z.B. `_cb_location_email` oder `phone`.
Du kannst auch komplett eigene Funktionen mit diesem Hook definieren, die dann durch ein Template Tag aufgerufen werden.


####  Beispiel: Stations-Betreibende als E-Mail Empfänger der Buchungs-Mails überschreiben

Ein Anwendungsfall für diesen Hook, stellt z.B. die Verwendung innerhalb einer
Staging-Umgebung dar. Du möchtest dort Buchungs-Vorgänge einer neuen Version
von Commonsbooking mit verschiedenen Zeitrahmen-Stations-Artikel-Kombinationen
testen, aber gleichzeitig nicht Mails an alle möglichen Stationsbetreibende
verschicken. Dann kannst das mit folgendem Filter-Hook via eingebundenem Code-
Snippet (gleichnamiges Plugin) oder Theme-/Plugin-Datei-Editor erreichen:


```php
/**
 * This adds a filter to send all booking confirmations to one email address.
 */
add_filter('commonsbooking_tag_cb_location__cb_location_email', function($value) {
    return 'yourname@example.com';
});
```

#### Beispiel: Eigene Funktion für die Template Tags eines Artikels definieren

Dieser Hook würde bei dem Template Tag <span v-pre>`{{item:yourFunction}}`</span> aufgerufen werden.
Mögliche Einsatzzwecke sind z.B. Schlosscodes, die mit einer weiteren Funktion anhand der Buchungsdaten generiert werden.
In diesem Beispiel wird einfach nur die ID des Artikels zurückgegeben.

```php
add_filter('commonsbooking_tag_cb_item_yourFunction', function( $value, $obj) {
    // $obj ist in diesem Fall eine Instanz der Klasse \CommonsBooking\Model\Item, kann aber auch ein anderes Model sein oder WP_Post
    return $obj->ID;
}, 10, 2);
```

### Filter `commonsbooking_mobile_calendar_month_count`

::: tip Ab Version 2.10.5
:::

Wie viel Monate standardmäßig in der mobilen Ansicht auf einer Seite angezeigt werden sollen (Standard: 2)
Nutzungs-Beispiel:

```php
// Sets the mobile calendar view to display 2 month
add_filter('commonsbooking_mobile_calendar_month_count', fn(): int => 2);
```

---

## Hooks für den Buchungsprozess (ab 2.10.11)

Die folgenden Hooks erlauben es, in den Buchungsprozess einzugreifen und ihn zu erweitern –
vom Datumsauswahl-Formular bis zur abschließenden Bestätigung.
Sie können genutzt werden, um zusätzliche Formularfelder einzufügen, eigene Validierungen
durchzuführen, Nutzer:innen auf Zwischenseiten weiterzuleiten oder weitere Daten
zusammen mit einer Buchung zu speichern.

### Filter `commonsbooking_booking_form_fields`

Fügt zusätzliches HTML in das **erste Buchungsformular** (Schritt 1 – Datumsauswahl) ein,
direkt vor dem Absende-Button. Nützlich für versteckte Eingaben, sichtbare Hinweise oder
Checkboxen, die vor der Buchungserstellung abgefragt werden sollen.

Übergebene Parameter:

| # | Typ | Beschreibung |
|---|-----|-------------|
| 1 | `string` | `$html` — bisher gesammeltes HTML (leer starten, dann ergänzen) |
| 2 | `array` | `$templateData` — Template-Daten-Array (enthält `item`, `location`, `calendar_data` usw.) |

```php
add_filter( 'commonsbooking_booking_form_fields', function( $html, $templateData ) {
    $html .= '<p><label>';
    $html .= '<input type="checkbox" name="cb_accept_terms" value="1" required> ';
    $html .= esc_html__( 'Ich akzeptiere die Nutzungsbedingungen', 'mein-plugin' );
    $html .= '</label></p>';
    return $html;
}, 10, 2 );
```

### Filter `commonsbooking_booking_confirmation_fields`

Fügt zusätzliches HTML in die **Aktionsformulare auf der Buchungs-Detailseite** (Schritt 2) ein,
direkt vor dem Absende-Button. Der Parameter `$form_post_status` zeigt an, welches Formular
gerade gerendert wird, sodass z.B. nur das Bestätigungsformular erweitert werden kann.

Parameter:

| # | Typ | Beschreibung |
|---|-----|-------------|
| 1 | `string` | `$html` — bisher gesammeltes HTML |
| 2 | `\CommonsBooking\Model\Booking` | `$booking` — das aktuelle Buchungsobjekt |
| 3 | `string` | `$form_post_status` — Zielstatus: `confirmed`, `canceled` oder `delete_unconfirmed` |

```php
add_filter( 'commonsbooking_booking_confirmation_fields', function( $html, $booking, $status ) {
    if ( $status === 'confirmed' ) {
        $html .= '<p><label>';
        $html .= '<input type="checkbox" name="cb_accept_terms" value="1" required> ';
        $html .= esc_html__( 'Ich akzeptiere die Nutzungsbedingungen', 'mein-plugin' );
        $html .= '</label></p>';
    }
    return $html;
}, 10, 3 );
```

### Filter `commonsbooking_booking_redirect_url`

Überschreibt die URL, zu der Nutzer:innen **nach dem Absenden des ersten Buchungsformulars**
weitergeleitet werden (also nachdem eine unbestätigte Buchung erstellt wurde). Dies ist der
zentrale Hook, um **Zwischenseiten** in den Buchungsprozess einzufügen – z.B. eine Seite,
die zusätzliche Angaben abfragt, bevor die Bestätigungsseite erscheint.

Parameter:

| # | Typ | Beschreibung |
|---|-----|-------------|
| 1 | `string` | `$url` — die Standard-URL der Buchungs-Detailseite |
| 2 | `int` | `$postId` — die Post-ID der neu erstellten Buchung |

```php
add_filter( 'commonsbooking_booking_redirect_url', function( $url, $postId ) {
    // Nutzer:in auf eine eigene Zwischenseite weiterleiten, Buchungs-ID mitgeben
    return add_query_arg( [ 'cb_booking_id' => $postId ], get_permalink( MEINE_ZWISCHENSEITE_ID ) );
}, 10, 2 );
```

### Filter `commonsbooking_booking_meta_input`

Filtert das **Meta-Input-Array**, das beim **Erstellen einer neuen Buchung** in der Datenbank
gespeichert wird. In Kombination mit `commonsbooking_booking_form_fields` lassen sich eigene
Formularfelder direkt als Post-Meta in der Buchung ablegen.

Parameter:

| # | Typ | Beschreibung |
|---|-----|-------------|
| 1 | `array` | `$metaInput` — assoziatives Array aus `meta_key => Wert`-Paaren |
| 2 | `int` | `$itemId` — Post-ID des Artikels |
| 3 | `int` | `$locationId` — Post-ID der Station |
| 4 | `int` | `$repetitionStart` — Unix-Zeitstempel des Buchungsbeginns |
| 5 | `int` | `$repetitionEnd` — Unix-Zeitstempel des Buchungsendes |

```php
add_filter( 'commonsbooking_booking_meta_input', function( $meta, $itemId, $locationId, $start, $end ) {
    $meta['mein_feld'] = sanitize_text_field( wp_unslash( $_REQUEST['mein_feld'] ?? '' ) );
    return $meta;
}, 10, 5 );
```

### Action `commonsbooking_booking_pre_validate`

Wird ausgelöst **bevor die internen Buchungsregeln geprüft werden**. Wirf eine
`\CommonsBooking\Exception\BookingDeniedException`, um die Buchung abzulehnen –
die Fehlermeldung wird dann genauso wie ein interner Validierungsfehler angezeigt.

Parameter:

| # | Typ | Beschreibung |
|---|-----|-------------|
| 1 | `int` | `$itemId` |
| 2 | `int` | `$locationId` |
| 3 | `string` | `$post_status` — Zielstatus (`unconfirmed`, `confirmed`, `canceled` …) |
| 4 | `int` | `$repetitionStart` — Unix-Zeitstempel |
| 5 | `int` | `$repetitionEnd` — Unix-Zeitstempel |

```php
add_action( 'commonsbooking_booking_pre_validate', function( $itemId, $locationId, $status, $start, $end ) {
    if ( empty( $_REQUEST['zugangscode'] ) || $_REQUEST['zugangscode'] !== 'GEHEIM' ) {
        throw new \CommonsBooking\Exception\BookingDeniedException(
            __( 'Ungültiger Zugangscode.', 'mein-plugin' )
        );
    }
}, 10, 5 );
```

### Action `commonsbooking_booking_created`

Wird ausgelöst, **nachdem eine neue Buchung mit dem Status `unconfirmed` gespeichert wurde**
(Schritt 1 abgeschlossen).

Parameter:

| # | Typ | Beschreibung |
|---|-----|-------------|
| 1 | `int` | `$postId` — Post-ID der Buchung |
| 2 | `\CommonsBooking\Model\Booking` | `$booking` — das Buchungs-Modell-Objekt |

```php
add_action( 'commonsbooking_booking_created', function( $postId, $booking ) {
    // z.B. Ereignis protokollieren oder Drittsystem benachrichtigen
    error_log( 'Neue unbestätigte Buchung erstellt: ' . $postId );
}, 10, 2 );
```

### Action `commonsbooking_booking_confirmed`

Wird ausgelöst, **nachdem eine Buchung auf den Status `confirmed` gesetzt wurde**
(Schritt 2 abgeschlossen).

Parameter:

| # | Typ | Beschreibung |
|---|-----|-------------|
| 1 | `int` | `$postId` — Post-ID der Buchung |
| 2 | `\CommonsBooking\Model\Booking` | `$booking` — das Buchungs-Modell-Objekt |

```php
add_action( 'commonsbooking_booking_confirmed', function( $postId, $booking ) {
    // z.B. externen Kalender synchronisieren
}, 10, 2 );
```

### Action `commonsbooking_save_booking_meta`

Wird **bei jeder Buchungsformular-Übermittlung** ausgelöst – sowohl beim erstmaligen Erstellen
als auch bei Aktualisierungen (Bestätigung, Stornierung usw.). Nutze diesen Hook, um eigene
`$_REQUEST`-Felder, die du über `commonsbooking_booking_form_fields` oder
`commonsbooking_booking_confirmation_fields` eingefügt hast, als Post-Meta zu speichern.

Parameter:

| # | Typ | Beschreibung |
|---|-----|-------------|
| 1 | `int` | `$postId` — Post-ID der Buchung |
| 2 | `string` | `$post_status` — Buchungsstatus nach dieser Anfrage |

```php
add_action( 'commonsbooking_save_booking_meta', function( $postId, $status ) {
    if ( isset( $_REQUEST['mein_feld'] ) ) {
        update_post_meta(
            $postId,
            'mein_feld',
            sanitize_text_field( wp_unslash( $_REQUEST['mein_feld'] ) )
        );
    }
}, 10, 2 );
```
