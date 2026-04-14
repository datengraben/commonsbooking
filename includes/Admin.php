<?php

function commonsbooking_admin() {
	// jQuery
	wp_enqueue_script( 'jquery' );

	// Datepicker extension
	wp_enqueue_script( 'jquery-ui-datepicker', '', array( 'jquery' ) );

	// Tooltip extension
	wp_enqueue_script( 'jquery-ui-tooltip', '', array( 'jquery' ) );

	wp_enqueue_style( 'admin-styles', COMMONSBOOKING_PLUGIN_ASSETS_URL . 'admin/css/admin.css', array(), COMMONSBOOKING_VERSION );

	// Scripts for the WordPress backend
	if ( WP_DEBUG ) {
		wp_enqueue_script(
			'cb-scripts-admin',
			COMMONSBOOKING_PLUGIN_ASSETS_URL . 'admin/js/admin.js',
			array(),
			(string) time()
		);
	} else {
		wp_enqueue_script(
			'cb-scripts-admin',
			COMMONSBOOKING_PLUGIN_ASSETS_URL . 'admin/js/admin.min.js',
			array(),
			COMMONSBOOKING_VERSION
		);
	}

	// CB 0.X migration
	wp_localize_script(
		'cb-scripts-admin',
		'cb_ajax_start_migration',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'cb_start_migration' ),
		)
	);

	// CB 2 bookings migration - from timeframe to separate cpt
	wp_localize_script(
		'cb-scripts-admin',
		'cb_ajax_start_booking_migration',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'cb_start_booking_migration' ),
		)
	);

	// AJAX action for exporting timeframes to CSV
	wp_localize_script(
		'cb-scripts-admin',
		'cb_ajax_export_timeframes',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'cb_export_timeframes' ),
		)
	);

	// \CommonsBooking\Service\Upgrade Ajax tasks
	wp_localize_script(
		'cb-scripts-admin',
		'cb_ajax_run_upgrade',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'cb_run_upgrade' ),
		)
	);

	// Additional info for CMB2 to handle booking rules
	wp_add_inline_script(
		'cb-scripts-admin',
		'cb_booking_rules=' . \CommonsBooking\Service\BookingRule::getRulesJSON() . ';'
		. 'cb_applied_booking_rules=' . \CommonsBooking\Service\BookingRuleApplied::getRulesJSON() . ';',
	);

	// orphaned bookings migration - re-assign booking when timeframe has changed
	wp_localize_script(
		'cb-scripts-admin',
		'cb_ajax_orphaned_booking_migration',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'cb_orphaned_booking_migration' ),
		)
	);
	/**
	 * Ajax - cache warmup
	 */
	wp_localize_script(
		'cb-scripts-admin',
		'cb_ajax_cache_warmup',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'cb_cache_warmup' ),
		)
	);

	/**
	 * Ajax - get location for item
	 */
	wp_localize_script(
		'cb-scripts-admin',
		'cb_ajax_get_bookable_location',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'cb_get_bookable_location' ),
		)
	);

	/**
	 * Ajax - get booking code for backend booking
	 */
	wp_localize_script(
		'cb-scripts-admin',
		'cb_ajax_get_booking_code',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'cb_get_booking_code' ),
		)
	);
}

add_action( 'admin_enqueue_scripts', 'commonsbooking_admin' );

/**
 * commonsbooking_sanitizeHTML
 * Filters text content and strips out disallowed HTML.
 *
 * @param mixed $string
 *
 * @return string
 */
function commonsbooking_sanitizeHTML( $string ): string {
	// Cache the merged allowed-tags array for the lifetime of the request.
	// Without this, $allowed_atts was rebuilt and $allowedposttags mutated on
	// every single call — hundreds of times per admin page load (issue #2043).
	static $allowed_tags = null;

	if ( empty( $string ) ) {
		return '';
	}

	if ( null === $allowed_tags ) {
		global $allowedposttags;

		$allowed_atts = array(
			'align'       => array(),
			'checked'     => array(),
			'class'       => array(),
			'type'        => array(),
			'id'          => array(),
			'dir'         => array(),
			'lang'        => array(),
			'style'       => array(),
			'xml:lang'    => array(),
			'src'         => array(),
			'alt'         => array(),
			'href'        => array(),
			'rel'         => array(),
			'rev'         => array(),
			'target'      => array(),
			'novalidate'  => array(),
			'value'       => array(),
			'name'        => array(),
			'tabindex'    => array(),
			'action'      => array(),
			'method'      => array(),
			'for'         => array(),
			'width'       => array(),
			'height'      => array(),
			'data'        => array(),
			'title'       => array(),
			'cellspacing' => array(),
			'cellpadding' => array(),
			'border'      => array(),
		);

		$extra_tags = array(
			'form'     => $allowed_atts,
			'label'    => $allowed_atts,
			'input'    => $allowed_atts,
			'textarea' => $allowed_atts,
			'iframe'   => $allowed_atts,
			'script'   => $allowed_atts,
			'style'    => $allowed_atts,
			'strong'   => $allowed_atts,
			'small'    => $allowed_atts,
			'table'    => $allowed_atts,
			'span'     => $allowed_atts,
			'abbr'     => $allowed_atts,
			'code'     => $allowed_atts,
			'pre'      => $allowed_atts,
			'div'      => $allowed_atts,
			'img'      => $allowed_atts,
			'h1'       => $allowed_atts,
			'h2'       => $allowed_atts,
			'h3'       => $allowed_atts,
			'h4'       => $allowed_atts,
			'h5'       => $allowed_atts,
			'h6'       => $allowed_atts,
			'ol'       => $allowed_atts,
			'ul'       => $allowed_atts,
			'li'       => $allowed_atts,
			'em'       => $allowed_atts,
			'hr'       => $allowed_atts,
			'br'       => $allowed_atts,
			'tr'       => $allowed_atts,
			'td'       => $allowed_atts,
			'p'        => $allowed_atts,
			'a'        => $allowed_atts,
			'b'        => $allowed_atts,
			'i'        => $allowed_atts,
			'select'   => $allowed_atts,
			'option'   => $allowed_atts,
		);

		// Merge with the WordPress core allowed-tags list rather than mutating it.
		$allowed_tags = array_merge( $allowedposttags, $extra_tags );
	}

	return wp_kses( $string, $allowed_tags );
}

/**
 * Create filter hooks for cmb2 fields
 *
 * @param array $field_args  Array of field args.
 *
 *
 * : https://cmb2.io/docs/field-parameters#-default_cb
 *
 * @return mixed
 */
function commonsbooking_filter_from_cmb2( $field_args ) {
	// Only return default value if we don't have a post ID (in the 'post' query variable)
	if ( isset( $_GET['post'] ) ) {
		// No default value.
		return '';
	} else {
		$filterName    = sprintf( 'commonsbooking_defaults_%s', $field_args['id'] );
		$default_value = array_key_exists( 'default_value', $field_args ) ? $field_args['default_value'] : '';

		/**
		 * Default value for cmb2 fields.
		 *
		 * The last part of the filter is the cmb2 field id.
		 *
		 * @since 2.8.0
		 *
		 * @param mixed $default_value default value for the field.
		 */
		return apply_filters( $filterName, $default_value );
	}
}

/**
 * Only return default value if we don't have a post ID (in the 'post' query variable)
 *
 * @since 2.10.3 removed non-existent param from phpdoc
 *
 * @return mixed          Returns true or '', the blank default
 */
function cmb2_set_checkbox_default_for_new_post() {
	return isset( $_GET['post'] )
		// No default value.
		? ''
		// Default to true.
		: true;
}

/**
 * Recursive sanitation for text or array
 *
 * @param array|string $data
 * @param string       $sanitizeFunction name of the sanitziation function, default = sanitize_text_field. You can use any method that accepts a string as parameter
 *
 *       See more wordpress sanitization functions: https://developer.wordpress.org/themes/theme-security/data-sanitization-escaping/
 *
 * @return array|string
 */
function commonsbooking_sanitizeArrayorString( $data, $sanitizeFunction = 'sanitize_text_field' ) {
	if ( is_array( $data ) ) {
		foreach ( $data as $key => $value ) {
			$data[ $key ] = commonsbooking_sanitizeArrayorString( $value, $sanitizeFunction );
		}
	} else {
		$data = call_user_func( $sanitizeFunction, $data );
	}

	return $data;
}


/**
 * writes messages to error_log file
 * only active if DEBUG_LOG is on
 *
 * @param mixed $log can be a string, array or object
 * @param bool  $backtrace if set true the file-path and line of the calling file will be added to the error message
 *
 * @return void
 */
function commonsbooking_write_log( $log, $backtrace = true ) {

	if ( ! WP_DEBUG_LOG ) {
		return;
	}

	if ( is_array( $log ) || is_object( $log ) ) {
		$logmessage = ( print_r( $log, true ) );
	} else {
		$logmessage = $log;
	}

	if ( $backtrace ) {
		$bt         = debug_backtrace();
		$file       = $bt[0]['file'];
		$line       = $bt[0]['line'];
		$logmessage = $file . ':' . $line . ' ' . $logmessage;
	}

	error_log( $logmessage );
}
