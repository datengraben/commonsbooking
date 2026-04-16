<?php
/**
 * Snippet Title:    [cb_my_next_booking] — show the current user's next booking
 * Description:      Renders a small summary of the logged-in user's next
 *                   confirmed booking (item name, location, pickup date/time).
 *                   Returns nothing when the user is not logged in or has no
 *                   upcoming confirmed bookings. Place it in a sidebar widget,
 *                   a page header, or a member-area template.
 *
 *                   Usage: [cb_my_next_booking]
 *
 * Hook/Filter:      — (registers a new shortcode, no CB hook needed)
 * CB Version:       2.10+
 * Tested up to:     2.10.10
 * Author:           CommonsBooking contributors
 * License:          GPL-2.0+
 */

/**
 * Render the current user's next confirmed booking.
 *
 * @return string HTML output or empty string.
 */
function myplugin_cb_my_next_booking_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '';
    }

    // Fetch confirmed bookings for the current user from today onwards.
    $bookings = \CommonsBooking\Repository\Booking::getForCurrentUser(
        true,                  // $asModel — return Model\Booking objects
        strtotime( 'today' ),  // $startDate — only future / ongoing bookings
        [ 'confirmed' ]        // $postStatus
    );

    if ( empty( $bookings ) ) {
        return '';
    }

    // Sort ascending by start date and take the earliest.
    usort( $bookings, fn( $a, $b ) => $a->getStartDate() - $b->getStartDate() );
    $next = $bookings[0];

    $item     = $next->getItem();
    $location = $next->getLocation();

    ob_start();
    ?>
    <div class="cb-my-next-booking">
        <strong><?php esc_html_e( 'Your next booking', 'commonsbooking' ); ?>:</strong>
        <?php if ( $item ) : ?>
            <span class="cb-mnb-item"><?php echo esc_html( $item->getPost()->post_title ); ?></span>
        <?php endif; ?>
        <?php if ( $location ) : ?>
            &mdash; <span class="cb-mnb-location"><?php echo esc_html( $location->getPost()->post_title ); ?></span>
        <?php endif; ?>
        &mdash; <span class="cb-mnb-date"><?php echo esc_html( $next->pickupDatetime() ); ?></span>
        <a href="<?php echo esc_url( $next->bookingLinkUrl() ); ?>">
            <?php esc_html_e( 'Details', 'commonsbooking' ); ?>
        </a>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'cb_my_next_booking', 'myplugin_cb_my_next_booking_shortcode' );
