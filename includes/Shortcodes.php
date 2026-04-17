<?php

/**
 * Shortcode handler for [cb] shortcode.
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string
 */
function commonsbooking_tag( array|string $atts ): string {
	$atts = shortcode_atts(
		array(
			'tag' => '',
		),
		$atts,
		'cb'
	);

	return commonsbooking_sanitizeHTML( commonsbooking_parse_shortcode( $atts['tag'] ) );
}

add_shortcode( 'cb', 'commonsbooking_tag' );
