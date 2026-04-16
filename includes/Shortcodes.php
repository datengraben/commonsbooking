<?php

function commonsbooking_tag( $atts ) {
	$atts = shortcode_atts(
		array(
			'tag' => '',
		),
		$atts,
		'cb'
	);

	echo commonsbooking_sanitizeHTML( commonsbooking_parse_shortcode( $atts['tag'] ) );
}

add_shortcode( 'cb', 'commonsbooking_tag' );

/**
 * Shortcode [cb_support_link] – renders a support/funding link for CommonsBooking.
 *
 * Usage: [cb_support_link]
 * Optional attributes:
 *   text  – link label (default: translated string)
 *   class – additional CSS classes on the <a> tag
 *
 * Example: [cb_support_link text="Donate now" class="my-button"]
 */
function commonsbooking_support_link( $atts ) {
	$atts = shortcode_atts(
		array(
			'text'  => __( 'Support CommonsBooking &amp; wielebenwir e.V.', 'commonsbooking' ),
			'class' => '',
		),
		$atts,
		'cb_support_link'
	);

	$url   = 'https://www.betterplace.org/de/donate/platform/projects/26362-unterstuetzung-der-projekte-des-wielebenwir-e-v';
	$class = $atts['class'] ? ' ' . esc_attr( $atts['class'] ) : '';

	return sprintf(
		'<a href="%s" target="_blank" rel="noopener noreferrer" class="cb-support-link%s">%s</a>',
		esc_url( $url ),
		$class,
		wp_kses( $atts['text'], array() )
	);
}

add_shortcode( 'cb_support_link', 'commonsbooking_support_link' );
