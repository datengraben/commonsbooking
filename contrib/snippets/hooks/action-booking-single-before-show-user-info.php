<?php
/**
 * Snippet Title:    Show extra user info above the booking single view
 * Description:      Outputs the booking user's display name and email address
 *                   directly above the booking-single template. Useful for
 *                   location admins who want quick access to renter contact
 *                   details without navigating to the WP Users screen.
 *                   Only visible to users who can edit the booking post.
 * Hook/Filter:      commonsbooking_before_booking-single
 * CB Version:       2.10.8+
 * Tested up to:     2.10.10
 * Author:           CommonsBooking contributors
 * License:          GPL-2.0+
 */

/**
 * Render renter contact info before the booking-single template.
 *
 * @param int                           $booking_id The booking post ID.
 * @param \CommonsBooking\Model\Booking $booking    The booking model instance.
 */
function myplugin_cb_before_booking_single_user_info( $booking_id, $booking ) {
    // Only show to users who can edit this booking (admins / CB managers).
    if ( ! current_user_can( 'edit_post', $booking_id ) ) {
        return;
    }

    $author_id = (int) get_post_field( 'post_author', $booking_id );
    if ( ! $author_id ) {
        return;
    }

    $user = get_userdata( $author_id );
    if ( ! $user ) {
        return;
    }

    printf(
        '<div class="cb-admin-user-info" style="background:#f0f0f0;padding:8px 12px;margin-bottom:12px;border-left:4px solid #0073aa;">'
        . '<strong>%s</strong> &lt;<a href="mailto:%s">%s</a>&gt;</div>',
        esc_html( $user->display_name ),
        esc_attr( $user->user_email ),
        esc_html( $user->user_email )
    );
}
add_action( 'commonsbooking_before_booking-single', 'myplugin_cb_before_booking_single_user_info', 10, 2 );
