<?php
/**
 * Plugin Name: CommonsBooking Playground Demo Data
 * Description: Generates sample CommonsBooking locations, items, timeframes and
 *              bookings with dates expressed as RELATIVE offsets from the current
 *              time, so the demo always looks "current" no matter when the
 *              WordPress Playground session is booted.
 *
 * playground/blueprint.json fetches this file by URL and drops it into
 * wp-content/mu-plugins/ (via a `writeFile` step with a `url` resource), so it
 * runs on every request inside a fully booted WordPress — no `runPHP` step and
 * no static WXR import required. This file is the single source of truth; the
 * blueprint references it rather than embedding a copy.
 *
 * A one-shot guard option makes generation run exactly once per fresh Playground
 * instance. Because Playground rebuilds a clean site (and re-applies the
 * blueprint) on every session, the data is regenerated fresh each session with
 * dates anchored to `current_time()`.
 *
 * The post-type slugs, meta keys and numeric type ids below are intentionally
 * kept as literals (with the corresponding CommonsBooking constant named in a
 * comment) so this generator has no hard compile-time dependency on plugin
 * classes and stays robust across boot ordering. They mirror the proven pattern
 * in tests/php/CPTCreationTrait.php.
 *
 * @see https://developer.wordpress.org/reference/functions/current_time/
 * @package CommonsBooking\Playground
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'cb_playground_generate_demo_data', 99 );

/**
 * Entry point. Guarded so it only ever runs once per WordPress instance.
 */
function cb_playground_generate_demo_data() {
	// Only proceed once CommonsBooking is actually active (its CPTs exist).
	if ( ! post_type_exists( 'cb_item' ) ) {
		return;
	}

	// One-shot guard: bail if we already seeded this instance.
	if ( get_option( 'cb_playground_demo_generated' ) ) {
		return;
	}
	// Set the guard immediately to avoid re-entrancy from nested inserts.
	update_option( 'cb_playground_demo_generated', 1 );

	// Single time anchor. current_time('timestamp') returns a WordPress
	// "local" timestamp that already respects the timezone_string site option
	// (set by the blueprint before this runs).
	$now = current_time( 'timestamp' );

	// --- Locations & items -------------------------------------------------
	$location_id = cb_playground_create_post( 'cb_location', 'Community Workshop' );
	$item_id     = cb_playground_create_post( 'cb_item', 'Cargo Bike "Hannah"' );

	// A second pair, so the map/overview has more than one entry.
	$location_id_2 = cb_playground_create_post( 'cb_location', 'Neighbourhood Library' );
	$item_id_2     = cb_playground_create_post( 'cb_item', 'Beamer / Projector' );

	// --- Bookable timeframes ----------------------------------------------
	// Wide bookable window around "today" so every sample booking falls inside
	// an available timeframe: from 7 days ago to 30 days ahead.
	cb_playground_create_timeframe( $location_id, $item_id, $now, -7, 30 );
	cb_playground_create_timeframe( $location_id_2, $item_id_2, $now, -7, 30 );

	// --- Sample bookings (all relative to $now) ----------------------------
	// A past, completed booking.
	cb_playground_create_booking( $location_id, $item_id, $now, -6, -4, 'confirmed', 'Past booking (completed)' );
	// A booking that ends today.
	cb_playground_create_booking( $location_id, $item_id, $now, -1, 0, 'confirmed', 'Booking ending today' );
	// A booking that starts today.
	cb_playground_create_booking( $location_id, $item_id, $now, 0, 2, 'confirmed', 'Booking starting today' );
	// Upcoming confirmed bookings.
	cb_playground_create_booking( $location_id, $item_id, $now, 5, 7, 'confirmed', 'Upcoming booking' );
	cb_playground_create_booking( $location_id_2, $item_id_2, $now, 12, 14, 'confirmed', 'Booking in two weeks' );
	// An unconfirmed (pending) request.
	cb_playground_create_booking( $location_id_2, $item_id_2, $now, 3, 4, 'unconfirmed', 'Pending booking request' );

	// --- Front page: an item overview so the demo has a friendly landing ---
	cb_playground_setup_front_page();
}

/**
 * Insert a published CommonsBooking post of the given type.
 *
 * @param string $post_type e.g. 'cb_item' or 'cb_location'.
 * @param string $title     Post title.
 * @return int New post id.
 */
function cb_playground_create_post( $post_type, $title ) {
	return wp_insert_post(
		array(
			'post_title'  => $title,
			'post_type'   => $post_type,
			'post_status' => 'publish',
			'post_author' => 1,
		)
	);
}

/**
 * Midnight timestamp of the day that is $offset_days away from $now.
 */
function cb_playground_day_start( $now, $offset_days ) {
	return strtotime( 'midnight', strtotime( sprintf( '%+d days', $offset_days ), $now ) );
}

/**
 * Last second (23:59:59) of the day that is $offset_days away from $now.
 */
function cb_playground_day_end( $now, $offset_days ) {
	return strtotime( '+1 day midnight', strtotime( sprintf( '%+d days', $offset_days ), $now ) ) - 1;
}

/**
 * Create a full-day, weekly-repeating bookable timeframe (type 2 = BOOKABLE_ID).
 *
 * @param int $location_id
 * @param int $item_id
 * @param int $now           Anchor timestamp.
 * @param int $start_offset  Days from now the timeframe becomes available.
 * @param int $end_offset    Days from now the timeframe stops being available.
 * @return int Timeframe post id.
 */
function cb_playground_create_timeframe( $location_id, $item_id, $now, $start_offset, $end_offset ) {
	$timeframe_id = cb_playground_create_post( 'cb_timeframe', 'Bookable' );

	$meta = array(
		'type'                          => 2,   // Timeframe::BOOKABLE_ID
		'location-id'                   => $location_id,       // Timeframe::META_LOCATION_ID
		'item-id'                       => $item_id,           // Timeframe::META_ITEM_ID
		'location-select'               => 0,   // Timeframe::SELECTION_MANUAL_ID
		'item-select'                   => 0,   // Timeframe::SELECTION_MANUAL_ID
		'full-day'                      => 'on',
		'timeframe-repetition'          => 'w', // weekly
		'start-time'                    => '00:00',
		'end-time'                      => '23:59',
		'grid'                          => 0,
		'weekdays'                      => array( '1', '2', '3', '4', '5', '6', '7' ),
		'timeframe-max-days'            => 14,
		'timeframe-advance-booking-days' => 60, // Timeframe::META_TIMEFRAME_ADVANCE_BOOKING_DAYS
		'booking-startday-offset'       => 0,
		'show-booking-codes'            => 'off',
		'create-booking-codes'          => 'off',
		'repetition-start'              => cb_playground_day_start( $now, $start_offset ),
		'repetition-end'                => cb_playground_day_end( $now, $end_offset ),
	);
	foreach ( $meta as $key => $value ) {
		update_post_meta( $timeframe_id, $key, $value );
	}

	return $timeframe_id;
}

/**
 * Create a booking (type 6 = BOOKING_ID) spanning whole days.
 *
 * @param int    $location_id
 * @param int    $item_id
 * @param int    $now
 * @param int    $start_offset Days from now the booking starts (00:00).
 * @param int    $end_offset   Days from now the booking ends (23:59).
 * @param string $status       'confirmed' or 'unconfirmed'.
 * @param string $title
 * @return int Booking post id.
 */
function cb_playground_create_booking( $location_id, $item_id, $now, $start_offset, $end_offset, $status, $title ) {
	$booking_id = wp_insert_post(
		array(
			'post_title'  => $title,
			'post_type'   => 'cb_booking',
			'post_status' => $status,
			'post_author' => 1,
		)
	);

	$meta = array(
		'type'                 => 6,   // Timeframe::BOOKING_ID
		'timeframe-repetition' => 'w',
		'start-time'           => '00:00',
		'end-time'             => '23:59',
		'timeframe-max-days'   => 14,
		'location-id'          => $location_id,
		'item-id'              => $item_id,
		'grid'                 => 0,
		'weekdays'             => array( '1', '2', '3', '4', '5', '6', '7' ),
		'repetition-start'     => cb_playground_day_start( $now, $start_offset ),
		'repetition-end'       => cb_playground_day_end( $now, $end_offset ),
	);
	foreach ( $meta as $key => $value ) {
		update_post_meta( $booking_id, $key, $value );
	}

	return $booking_id;
}

/**
 * Create a front page showing the CommonsBooking item overview, so visitors
 * land on something useful instead of a blank theme homepage.
 */
function cb_playground_setup_front_page() {
	$page_id = wp_insert_post(
		array(
			'post_title'   => 'Book an item',
			'post_content' => "[cb_items_table]",
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_author'  => 1,
		)
	);

	if ( $page_id && ! is_wp_error( $page_id ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );
	}
}
