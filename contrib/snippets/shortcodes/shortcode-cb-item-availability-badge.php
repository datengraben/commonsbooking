<?php
/**
 * Snippet Title:    [cb_item_availability_badge] — available / unavailable badge for an item
 * Description:      Renders a small coloured badge showing whether a specific
 *                   item is currently bookable. Useful on landing pages, item
 *                   overview posts, or news articles that highlight a particular
 *                   piece of equipment.
 *
 *                   Usage: [cb_item_availability_badge id="42"]
 *
 *                   Parameters:
 *                     id (required) — the post ID of the cb_item
 *
 * Hook/Filter:      — (registers a new shortcode, no CB hook needed)
 * CB Version:       2.10+
 * Tested up to:     2.10.10
 * Author:           CommonsBooking contributors
 * License:          GPL-2.0+
 */

/**
 * Render an availability badge for a given CB item.
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML badge or empty string on error.
 */
function myplugin_cb_item_availability_badge_shortcode( $atts ) {
    $atts = shortcode_atts( [ 'id' => 0 ], $atts, 'cb_item_availability_badge' );
    $item_id = (int) $atts['id'];

    if ( ! $item_id ) {
        return '<!-- cb_item_availability_badge: missing id attribute -->';
    }

    $post = get_post( $item_id );
    if ( ! $post || $post->post_type !== 'cb_item' ) {
        return '<!-- cb_item_availability_badge: invalid item id -->';
    }

    try {
        $item      = new \CommonsBooking\Model\Item( $item_id );
        $bookable  = $item->isBookable();
    } catch ( \Exception $e ) {
        return '';
    }

    if ( $bookable ) {
        return '<span class="cb-availability-badge cb-available" style="display:inline-block;padding:2px 10px;border-radius:3px;background:#3a7d44;color:#fff;font-size:.875em;">'
            . esc_html__( 'Available', 'commonsbooking' )
            . '</span>';
    }

    return '<span class="cb-availability-badge cb-unavailable" style="display:inline-block;padding:2px 10px;border-radius:3px;background:#b0200c;color:#fff;font-size:.875em;">'
        . esc_html__( 'Not available', 'commonsbooking' )
        . '</span>';
}
add_shortcode( 'cb_item_availability_badge', 'myplugin_cb_item_availability_badge_shortcode' );
