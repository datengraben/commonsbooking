<?php

namespace CommonsBooking\View;

/**
 * Renders the plugin comparison page in the WordPress admin backend.
 */
class Comparison extends View {

	public static function index() {
		ob_start();
		commonsbooking_sanitizeHTML( commonsbooking_get_template_part( 'comparison', 'index' ) );
		echo ob_get_clean();
	}
}
