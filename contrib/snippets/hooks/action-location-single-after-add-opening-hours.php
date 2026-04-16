<?php
/**
 * Snippet Title:    Display custom opening hours below a location page
 * Description:      Reads an optional custom field "_cb_opening_hours" from
 *                   the location post and renders it below the location-single
 *                   template. Add the field via a custom plugin (e.g. ACF or
 *                   CMB2) or CommonsBooking's own commonsbooking_custom_metadata
 *                   filter. Falls back silently when the field is empty.
 * Hook/Filter:      commonsbooking_after_location-single
 * CB Version:       2.10.8+
 * Tested up to:     2.10.10
 * Author:           CommonsBooking contributors
 * License:          GPL-2.0+
 */

/**
 * Render opening hours below the location-single template.
 *
 * @param int                            $location_id The location post ID.
 * @param \CommonsBooking\Model\Location $location    The location model instance.
 */
function myplugin_cb_after_location_single_opening_hours( $location_id, $location ) {
    $opening_hours = get_post_meta( $location_id, '_cb_opening_hours', true );

    if ( empty( $opening_hours ) ) {
        return;
    }
    ?>
    <div class="cb-opening-hours" style="margin-top:1.5em;">
        <h3><?php esc_html_e( 'Opening hours', 'commonsbooking' ); ?></h3>
        <p><?php echo wp_kses_post( $opening_hours ); ?></p>
    </div>
    <?php
}
add_action( 'commonsbooking_after_location-single', 'myplugin_cb_after_location_single_opening_hours', 10, 2 );
