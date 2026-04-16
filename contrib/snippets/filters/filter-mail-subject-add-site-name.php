<?php
/**
 * Snippet Title:    Prepend site name to all booking email subjects
 * Description:      Prefixes every CommonsBooking email subject with the
 *                   WordPress site name so recipients immediately recognise
 *                   which platform the email came from. Useful when users
 *                   belong to multiple networks that all run CommonsBooking.
 *                   Example result: "[My Bikesharing] Your booking is confirmed"
 * Hook/Filter:      commonsbooking_mail_subject
 * CB Version:       2.7.3+
 * Tested up to:     2.10.10
 * Author:           CommonsBooking contributors
 * License:          GPL-2.0+
 */

/**
 * Prepend the WordPress site name to every CommonsBooking email subject.
 *
 * @param string $subject       The current email subject line.
 * @param string $messageAction The email action type (e.g. 'booking-confirmed').
 * @return string Modified subject line.
 */
function myplugin_cb_prepend_site_name_to_subject( $subject, $messageAction ) {
    $site_name = get_bloginfo( 'name' );

    return '[' . $site_name . '] ' . $subject;
}
add_filter( 'commonsbooking_mail_subject', 'myplugin_cb_prepend_site_name_to_subject', 10, 2 );
