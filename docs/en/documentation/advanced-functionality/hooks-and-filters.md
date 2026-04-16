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
  * commonsbooking_booking_pre_validate *(since 2.10.11)*
  * commonsbooking_booking_created *(since 2.10.11)*
  * commonsbooking_booking_confirmed *(since 2.10.11)*
  * commonsbooking_save_booking_meta *(since 2.10.11)*

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

## Filter hooks

Filter hooks (https://developer.wordpress.org/plugins/hooks/filters) work
just like action hooks, but with the difference that the callback function
receives a value, modifies it, and then returns it.

### Overview of all filter hooks

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
  * commonsbooking_booking_form_fields *(since 2.10.11)*
  * commonsbooking_booking_confirmation_fields *(since 2.10.11)*
  * commonsbooking_booking_redirect_url *(since 2.10.11)*
  * commonsbooking_booking_meta_input *(since 2.10.11)*

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

### Filter `commonsbooking_mobile_calendar_month_count`

::: tip Since version 2.10.5
:::

How many months are displayed in the mobile calendar view can be adjusted using this filter.

```php
// Sets the mobile calendar view to display 2 months
add_filter('commonsbooking_mobile_calendar_month_count', fn(): int => 2);
```

---

## Booking process hooks (since 2.10.11)

The following hooks let you intercept and extend the booking process at every key step —
from the initial date-selection form all the way to the final confirmation.
They can be used to add extra form fields, run custom validation, redirect users to
intermediate pages, or save additional data alongside a booking.

### Filter `commonsbooking_booking_form_fields`

Inject extra HTML into the **initial booking form** (step 1 — date selection), rendered
just before the submit button. Useful for hidden inputs, visible prompts, or checkboxes
that should be collected before the booking is created.

Parameters passed to the callback:

| # | Type | Description |
|---|------|-------------|
| 1 | `string` | `$html` — accumulated HTML to inject (start with an empty string and append) |
| 2 | `array` | `$templateData` — template data array (contains `item`, `location`, `calendar_data`, etc.) |

```php
add_filter( 'commonsbooking_booking_form_fields', function( $html, $templateData ) {
    $html .= '<p><label>';
    $html .= '<input type="checkbox" name="cb_accept_terms" value="1" required> ';
    $html .= esc_html__( 'I accept the terms of use', 'my-plugin' );
    $html .= '</label></p>';
    return $html;
}, 10, 2 );
```

### Filter `commonsbooking_booking_confirmation_fields`

Inject extra HTML into the **booking action forms on the booking-single page** (step 2),
rendered just before the submit button. The `$form_post_status` parameter tells you which
form is being rendered so you can target only the confirmation form, for example.

Parameters:

| # | Type | Description |
|---|------|-------------|
| 1 | `string` | `$html` — accumulated HTML to inject |
| 2 | `\CommonsBooking\Model\Booking` | `$booking` — the current booking object |
| 3 | `string` | `$form_post_status` — target status: `confirmed`, `canceled`, or `delete_unconfirmed` |

```php
add_filter( 'commonsbooking_booking_confirmation_fields', function( $html, $booking, $status ) {
    if ( $status === 'confirmed' ) {
        $html .= '<p><label>';
        $html .= '<input type="checkbox" name="cb_accept_terms" value="1" required> ';
        $html .= esc_html__( 'I accept the terms of use', 'my-plugin' );
        $html .= '</label></p>';
    }
    return $html;
}, 10, 3 );
```

### Filter `commonsbooking_booking_redirect_url`

Override the URL the user is redirected to **after the initial booking form is submitted**
(i.e. after an unconfirmed booking has been created). This is the primary hook for adding
**intermediate pages** to the booking flow — for example a page that collects additional
information before the user reaches the confirmation page.

Parameters:

| # | Type | Description |
|---|------|-------------|
| 1 | `string` | `$url` — the default booking-single URL |
| 2 | `int` | `$postId` — the post ID of the newly created booking |

```php
add_filter( 'commonsbooking_booking_redirect_url', function( $url, $postId ) {
    // Send the user to a custom intermediate page, passing the booking ID along
    return add_query_arg( [ 'cb_booking_id' => $postId ], get_permalink( MY_EXTRA_PAGE_ID ) );
}, 10, 2 );
```

### Filter `commonsbooking_booking_meta_input`

Filter the **meta input array** used when a **new booking is inserted** into the database.
Use this together with `commonsbooking_booking_form_fields` to persist custom form fields
directly inside the booking's post meta.

Parameters:

| # | Type | Description |
|---|------|-------------|
| 1 | `array` | `$metaInput` — associative array of `meta_key => value` pairs |
| 2 | `int` | `$itemId` — the item post ID |
| 3 | `int` | `$locationId` — the location post ID |
| 4 | `int` | `$repetitionStart` — Unix timestamp of the booking start |
| 5 | `int` | `$repetitionEnd` — Unix timestamp of the booking end |

```php
add_filter( 'commonsbooking_booking_meta_input', function( $meta, $itemId, $locationId, $start, $end ) {
    $meta['my_custom_field'] = sanitize_text_field( wp_unslash( $_REQUEST['my_custom_field'] ?? '' ) );
    return $meta;
}, 10, 5 );
```

### Action `commonsbooking_booking_pre_validate`

Fires **before the built-in booking rules are checked**. Throw a
`\CommonsBooking\Exception\BookingDeniedException` to reject the booking;
the exception is caught by the same handler as core validation errors and its
message is shown to the user.

Parameters:

| # | Type | Description |
|---|------|-------------|
| 1 | `int` | `$itemId` |
| 2 | `int` | `$locationId` |
| 3 | `string` | `$post_status` — target status (`unconfirmed`, `confirmed`, `canceled`, …) |
| 4 | `int` | `$repetitionStart` — Unix timestamp |
| 5 | `int` | `$repetitionEnd` — Unix timestamp |

```php
add_action( 'commonsbooking_booking_pre_validate', function( $itemId, $locationId, $status, $start, $end ) {
    if ( empty( $_REQUEST['access_code'] ) || $_REQUEST['access_code'] !== 'SECRET' ) {
        throw new \CommonsBooking\Exception\BookingDeniedException(
            __( 'Invalid access code.', 'my-plugin' )
        );
    }
}, 10, 5 );
```

### Action `commonsbooking_booking_created`

Fires **after a brand-new booking has been saved** with status `unconfirmed` (step 1 completed).

Parameters:

| # | Type | Description |
|---|------|-------------|
| 1 | `int` | `$postId` — the booking post ID |
| 2 | `\CommonsBooking\Model\Booking` | `$booking` — the booking model object |

```php
add_action( 'commonsbooking_booking_created', function( $postId, $booking ) {
    // e.g. log the event or notify a third-party system
    error_log( 'New unconfirmed booking created: ' . $postId );
}, 10, 2 );
```

### Action `commonsbooking_booking_confirmed`

Fires **after a booking has been transitioned to status `confirmed`** (step 2 completed).

Parameters:

| # | Type | Description |
|---|------|-------------|
| 1 | `int` | `$postId` — the booking post ID |
| 2 | `\CommonsBooking\Model\Booking` | `$booking` — the booking model object |

```php
add_action( 'commonsbooking_booking_confirmed', function( $postId, $booking ) {
    // e.g. trigger an external calendar sync
}, 10, 2 );
```

### Action `commonsbooking_save_booking_meta`

Fires **after every booking form submission** — both when a booking is first created and
when it is updated (confirmed, canceled, etc.). Use this hook to save any custom
`$_REQUEST` fields you injected via `commonsbooking_booking_form_fields` or
`commonsbooking_booking_confirmation_fields` as post meta on the booking.

Parameters:

| # | Type | Description |
|---|------|-------------|
| 1 | `int` | `$postId` — the booking post ID |
| 2 | `string` | `$post_status` — the booking status after this request |

```php
add_action( 'commonsbooking_save_booking_meta', function( $postId, $status ) {
    if ( isset( $_REQUEST['my_custom_field'] ) ) {
        update_post_meta(
            $postId,
            'my_custom_field',
            sanitize_text_field( wp_unslash( $_REQUEST['my_custom_field'] ) )
        );
    }
}, 10, 2 );
```
