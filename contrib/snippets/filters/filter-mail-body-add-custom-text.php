<?php
/**
 * Snippet Title:    Append custom text to all booking emails
 * Description:      Adds a fixed paragraph at the end of every CommonsBooking
 *                   email body (confirmation, cancellation, reminder, …).
 *                   Useful for adding site-specific legal notices, support
 *                   contacts, or seasonal greetings without touching core templates.
 * Hook/Filter:      commonsbooking_mail_body
 * CB Version:       2.7.3+
 * Tested up to:     2.10.10
 * Author:           CommonsBooking contributors
 * License:          GPL-2.0+
 */

/**
 * Append a custom paragraph to every CommonsBooking email body.
 *
 * @param string $body          The current email body (may contain HTML).
 * @param string $messageAction The email action type (e.g. 'booking-confirmed').
 * @return string Modified email body.
 */
function myplugin_cb_append_custom_email_text( $body, $messageAction ) {
    $custom_text = '<p>Questions? Contact us at <a href="mailto:info@example.org">info@example.org</a> or call +49 30 123456.</p>';

    return $body . $custom_text;
}
add_filter( 'commonsbooking_mail_body', 'myplugin_cb_append_custom_email_text', 10, 2 );
