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

	$lang       = strncmp( get_locale(), 'de', 2 ) === 0 ? 'de' : 'en';
	$source     = wp_parse_url( home_url(), PHP_URL_HOST );
	$donate_url = add_query_arg(
		array(
			'utm_source'   => $source,
			'utm_medium'   => 'website',
			'utm_campaign' => 'support_link',
		),
		'https://commonsbooking.org/' . $lang . '/donate'
	);
	$class = $atts['class'] ? ' ' . esc_attr( $atts['class'] ) : '';

	return sprintf(
		'<a href="%s" target="_blank" rel="noopener noreferrer" class="cb-support-link%s">%s</a>',
		esc_url( $donate_url ),
		$class,
		wp_kses( $atts['text'], array() )
	);
}

add_shortcode( 'cb_support_link', 'commonsbooking_support_link' );
