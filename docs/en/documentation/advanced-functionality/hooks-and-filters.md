# Hooks and filters

## Action Hooks

Using hooks (https://developer.wordpress.org/plugins/hooks/), you can insert your own
code snippets at specific points in the CommonsBooking templates. This allows you to
add your own code to the templates without having to replace the template files.

Code snippets are usually very short pieces of PHP code that can be included via a
[Child Theme](https://developer.wordpress.org/themes/advanced-topics/child-themes)
or through special code snippet plugins (e.g. Code Snippets). No advanced PHP knowledge is
required, it is however also possible to use these snippets to deeply interfere with the
functionality of CommonsBooking or even to make the booking system unusable. If you see examples
in the documentation, these are reasonably safe and tested. However, a certain residual risk remains.
If you encounter problems, please feel free to contact us. However, please also provide
all code snippets you are using. This will help us to better understand the problem.

Action hooks are patterned according to the principle

`commonsbooking_(before/after)_(template-file)`

Using _add_action_ you can integrate your own callback function. Example:

```php
function itemsingle_callback() {
    // what should appear before the item single template
}
add_action( 'commonsbooking_before_item-single', 'itemsingle_callback' );
```

### Overview of all of the action hooks

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
  * [commonsbooking_booking_created](#booking-lifecycle-hooks-since-2-11-0)
  * [commonsbooking_booking_confirmed](#booking-lifecycle-hooks-since-2-11-0)
  * [commonsbooking_booking_cancelled](#booking-lifecycle-hooks-since-2-11-0)
  * [commonsbooking_booking_status_changed](#booking-lifecycle-hooks-since-2-11-0)

### Hooks in the context of an object (since 2.10.8)

Some action hooks also pass the post ID of the current object and an instance of the object as a \CommonsBooking\Model\<object class> object. Those are:

  * `commonsbooking_before_booking-single` and `commonsbooking_after_booking-single`
    * Parameters: `int $booking_id`, `\CommonsBooking\Model\Booking $booking`
  * `commonsbooking_before_location-single` and `commonsbooking_after_location-single`
    * Parameters: `int $location_id`, `\CommonsBooking\Model\Location $location`
  * `commonsbooking_before_item-single` and `commonsbooking_after_item-single`
    * Parameters: `int $item_id`, `\CommonsBooking\Model\Item $item`
  * `commonsbooking_before_item-calendar-header` and `commonsbooking_after_item-calendar-header`
    * Parameters: `int $item_id`, `\CommonsBooking\Model\Item $item`
  * `commonsbooking_before_location-calendar-header` and `commonsbooking_after_location-calendar-header`
    * Parameters: `int $location_id`, `\CommonsBooking\Model\Location $location`

Example usage:
```php
function my_cb_before_booking_single( $booking_id, $booking ) {
    echo 'Booking ID: ' . $booking_id;
    echo 'The booking status is ' . $booking->getStatus();
}
add_action( 'commonsbooking_before_booking-single', 'my_cb_before_booking_single', 10, 2 );
```

### Booking lifecycle hooks (since 2.11.0)

These action hooks let you react to the lifecycle of a booking — for example to
program a smart lock, sync an external calendar, or write an audit log. They fire
regardless of where the change originated (frontend calendar, admin backend, REST
API or WP-CLI), because they are dispatched from WordPress' post status
transition rather than from a single form handler.

Every hook receives the booking post ID and the corresponding
`\CommonsBooking\Model\Booking` instance (except `..._status_changed`, which also
passes the old and new status).

  * `commonsbooking_booking_created`
    * Fires **once** when a booking first enters a real status.
    * Parameters: `int $booking_id`, `\CommonsBooking\Model\Booking $booking`
  * `commonsbooking_booking_confirmed`
    * Fires whenever a booking becomes `confirmed`. This is the recommended hook for integrations that need to react to a booking becoming active.
    * Parameters: `int $booking_id`, `\CommonsBooking\Model\Booking $booking`
  * `commonsbooking_booking_cancelled`
    * Fires when a booking is cancelled.
    * Parameters: `int $booking_id`, `\CommonsBooking\Model\Booking $booking`
  * `commonsbooking_booking_status_changed`
    * Fires on every booking status transition (audit superset of the hooks above). Note: because cancellation writes the status directly to the database, cancellations are best observed via `commonsbooking_booking_cancelled` rather than this hook.
    * Parameters: `int $booking_id`, `string $old_status`, `string $new_status`, `\CommonsBooking\Model\Booking $booking`

#### Example: trigger a smart lock when a booking is confirmed

```php
add_action( 'commonsbooking_booking_confirmed', function ( $booking_id, $booking ) {
    $item     = $booking->getItem();
    $lockCode = $booking->formattedBookingCode();
    // hand the code / booking window over to your locking system here …
    my_locking_system_program( $item->ID, $lockCode, $booking->getStartDate(), $booking->getEndDate() );
}, 10, 2 );
```

#### Example: release the lock again when a booking is cancelled

```php
add_action( 'commonsbooking_booking_cancelled', function ( $booking_id, $booking ) {
    my_locking_system_revoke( $booking->getItem()->ID, $booking_id );
}, 10, 2 );
```

## Filter hooks

Filter hooks (https://developer.wordpress.org/plugins/hooks/filters) work
just like action hooks, but with the difference that the callback function
receives a value, modifies it, and then returns it.

### Overview of all filter hooks

  * commonsbooking_custom_metadata
  * [commonsbooking_isUserAdmin](../basics/permission-management#filterhook-isUserAdmin)
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
  * commonsbooking_gbfs_feeds
  * commonsbooking_can_cancel_booking
  * commonsbooking_booking_before_save
  * commonsbooking_is_timeframe_bookable
  * commonsbooking_day_availability
  * commonsbooking_bookable_timeframes
  * commonsbooking_calendar_data
  * commonsbooking_api_item_response
  * commonsbooking_api_availability_response

There are also filter hooks that allow you to add additional user roles
akin to the CB Manager that can manage items and locations.
Read more: [Permission management](../basics/permission-management) (not translated yet)

In addition to that, there are filter hooks that allow you to change the default
values when creating timeframes. More about that [here](../advanced-functionality/change-timeframe-creation-defaults)

### Filter Hook: commonsbooking_custom_metadata

Using this hook, you can add [CMB2 meta fields](https://cmb2.io) to one of the
[custom post types of CommonsBooking](../basics/concepts). The fields can be accessed via the
admin backend. Note that there is a special structure of the parameter
`$metaDataFields`, a nested assoc array.

```
array => [
  "cb_bookings" => [
    [ "id" => ..., "name" => ..., "type" => ..., "desc" => ..., ...],
    ...
  ],
  ...
]
```

Since extending these fields requires technical expertise, we want to point you to the source file
[`OptionsArray.php`](https://github.com/wielebenwir/commonsbooking/blob/master/includes/OptionsArray.php)
as additional reference for the usage of cmb2 fields in the plugin.

###  Filter Hook: commonsbooking_tag_$key_$property

::: tip
Since version 2.10.9 the object context is also passed to this filter hook.
The examples below only apply to versions >= 2.10.9.
:::

This filter hook allows you to modify the default behavior of template tags.
The values of $key and $property need to be replaced with the respective key and property of the template tag.
$key corresponds to the post_type of the object (e.g. `cb_location`, `cb_item`, ...), while $property corresponds to the property / meta field of the template tag to be overwritten (e.g. `_cb_location_email`, `phone`, ...).
You may also define your own template tags and use this filter hook to define their behavior.

####  Example: Overwrite who receives booking emails

This filter hook can be used in a staging environment to override
who receives booking confirmation emails.

```php
/**
 * This adds a filter to send all booking confirmations to one email address.
 */
add_filter('commonsbooking_tag_cb_location__cb_location_email', function($value) {
    return 'yourname@example.com';
});
```

#### Example: Define a custom function for an item's template tags

This hook will be called for the template tag <span v-pre>`{{item:yourFunction}}`</span>.
Possible use cases include, for example, lock codes that are generated by another function based on booking data.
In this example, the item's ID is simply returned.

```php
add_filter('commonsbooking_tag_cb_item_yourFunction', function( $value, $obj) {
    // $obj is in this case an instance of the class \CommonsBooking\Model\Item, but it can also be another model or WP_Post
    return $obj->ID;
}, 10, 2);
```

### Filter `commonsbooking_api_availability_response`

::: tip Since version 2.11.0
:::

Adjusts the availability slots exposed through the CommonsAPI, e.g. to reflect an
external booking source. Receives the slots array and the item ID (or `null` for
all items).

```php
add_filter('commonsbooking_api_availability_response', function (array $slots, $id): array {
    // adjust $slots as needed
    return $slots;
}, 10, 2);
```

### Filter `commonsbooking_api_item_response`

::: tip Since version 2.11.0
:::

Adds or adjusts fields exposed for an item in the CommonsAPI. Receives the
prepared item object and the source `WP_Post`. Added fields must conform to the
CommonsAPI JSON schema, otherwise response validation will reject them.

```php
add_filter('commonsbooking_api_item_response', function ($preparedItem, $item) {
    $preparedItem->myField = get_post_meta($item->ID, 'my_field', true);
    return $preparedItem;
}, 10, 2);
```

### Filter `commonsbooking_calendar_data`

::: tip Since version 2.11.0
:::

Adjusts the data array that drives the frontend booking calendar (and its AJAX
endpoint) before it is rendered or returned as JSON. Receives the calendar data
and the item and location it is for.

```php
add_filter('commonsbooking_calendar_data', function (array $calendarData, $item, $location): array {
    // adjust $calendarData as needed
    return $calendarData;
}, 10, 3);
```

### Filter `commonsbooking_bookable_timeframes`

::: tip Since version 2.11.0
:::

Restricts or extends which timeframes are offered for booking. Receives the
timeframes and the location/item IDs the query was scoped to. The element type
follows the repository's `$returnAsModel` argument (post IDs, `WP_Post` or
`\CommonsBooking\Model\Timeframe`).

```php
add_filter('commonsbooking_bookable_timeframes', function (array $timeframes, array $locations, array $items): array {
    // filter $timeframes as needed
    return $timeframes;
}, 10, 3);
```

### Filter `commonsbooking_day_availability`

::: tip Since version 2.11.0
:::

Adjusts the bookable slots computed for a single day, e.g. to reflect an external
availability source. Receives the slots array and the `\CommonsBooking\Model\Day`.

```php
add_filter('commonsbooking_day_availability', function (array $slots, $day): array {
    // inspect $day->getDate() and filter $slots as needed
    return $slots;
}, 10, 2);
```

### Filter `commonsbooking_is_timeframe_bookable`

::: tip Since version 2.11.0
:::

Adds custom booking-window rules on top of the default advance-booking-days check.
Receives the default decision (`bool`) and the `\CommonsBooking\Model\Timeframe`.

```php
// Block bookings on the timeframe's item while it is flagged for maintenance.
add_filter('commonsbooking_is_timeframe_bookable', function (bool $bookable, $timeframe): bool {
    return $bookable && ! get_post_meta($timeframe->getItem()->ID, 'in_maintenance', true);
}, 10, 2);
```

### Filter `commonsbooking_booking_before_save`

::: tip Since version 2.11.0
:::

Adjusts the post array right before a booking is inserted or updated, e.g. to add
meta data. Receives the `wp_insert_post()`/`wp_update_post()` array and the
existing `\CommonsBooking\Model\Booking` (or `null` for a new booking); return the
modified array.

```php
add_filter('commonsbooking_booking_before_save', function (array $postarr, $booking): array {
    $postarr['meta_input']['my_external_ref'] = 'ext-123';
    return $postarr;
}, 10, 2);
```

### Filter `commonsbooking_can_cancel_booking`

::: tip Since version 2.11.0
:::

Overrides whether the current user may cancel a booking. Receives the default
decision (`bool`) and the `\CommonsBooking\Model\Booking` instance.

```php
// Forbid cancelling a booking that starts within the next 24 hours.
add_filter('commonsbooking_can_cancel_booking', function (bool $canCancel, $booking): bool {
    return $canCancel && $booking->getStartDate() > time() + DAY_IN_SECONDS;
}, 10, 2);
```

### Filter `commonsbooking_mobile_calendar_month_count`

::: tip Since version 2.10.5
:::

How many months are displayed in the mobile calendar view can be adjusted using this filter.

```php
// Sets the mobile calendar view to display 2 months
add_filter('commonsbooking_mobile_calendar_month_count', fn(): int => 2);
```
