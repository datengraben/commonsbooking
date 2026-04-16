<?php
/**
 * Snippet Title:    Add a custom notice below every item page
 * Description:      Outputs a styled info box after the item-single template.
 *                   Use this for site-wide notices such as pickup rules,
 *                   insurance reminders, or seasonal availability warnings
 *                   that apply to all items equally.
 *                   To target a specific item, check $item_id inside the callback.
 * Hook/Filter:      commonsbooking_after_item-single
 * CB Version:       2.10.8+
 * Tested up to:     2.10.10
 * Author:           CommonsBooking contributors
 * License:          GPL-2.0+
 */

/**
 * Render a custom notice after the item-single template.
 *
 * @param int                        $item_id The item post ID.
 * @param \CommonsBooking\Model\Item $item    The item model instance.
 */
function myplugin_cb_after_item_single_notice( $item_id, $item ) {
    ?>
    <div class="cb-custom-notice" style="margin-top:1.5em;padding:12px 16px;border-left:4px solid #e6a817;background:#fdf6e3;">
        <strong><?php esc_html_e( 'Please note', 'commonsbooking' ); ?>:</strong>
        <?php esc_html_e( 'Return the item by 18:00 on the last day of your booking. Late returns affect other users.', 'commonsbooking' ); ?>
    </div>
    <?php
}
add_action( 'commonsbooking_after_item-single', 'myplugin_cb_after_item_single_notice', 10, 2 );
