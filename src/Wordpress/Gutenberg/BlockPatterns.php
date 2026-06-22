<?php

namespace CommonsBooking\Wordpress\Gutenberg;

/**
 * Registers Gutenberg block patterns for CommonsBooking shortcodes.
 *
 * Patterns appear in the block inserter under the "CommonsBooking" category,
 * making it easy for editors to insert booking features without knowing shortcode
 * syntax. Each pattern wraps a shortcode in a Classic block, which is the
 * zero-JavaScript approach — no React code needed.
 */
class BlockPatterns {

	/**
	 * Registers the block pattern category and all shortcode-based patterns on init.
	 */
	public static function init(): void {
		add_action( 'init', array( static::class, 'registerPatternCategory' ) );
		add_action( 'init', array( static::class, 'registerPatterns' ) );
	}

	/**
	 * Registers the "CommonsBooking" block pattern category with WordPress.
	 */
	public static function registerPatternCategory(): void {
		if ( ! function_exists( 'register_block_pattern_category' ) ) {
			return;
		}
		register_block_pattern_category(
			'commonsbooking',
			[ 'label' => __( 'CommonsBooking', 'commonsbooking' ) ]
		);
	}

	/**
	 * Registers all CommonsBooking shortcode-based block patterns.
	 */
	public static function registerPatterns(): void {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		register_block_pattern(
			'commonsbooking/search-map',
			[
				'title'       => __( 'CommonsBooking: Search & Map', 'commonsbooking' ),
				'description' => __( 'Interactive map with search filters showing all bookable items and locations.', 'commonsbooking' ),
				'categories'  => [ 'commonsbooking' ],
				'content'     => '<!-- wp:shortcode -->[cb_search]<!-- /wp:shortcode -->',
			]
		);

		register_block_pattern(
			'commonsbooking/map',
			[
				'title'       => __( 'CommonsBooking: Map', 'commonsbooking' ),
				'description' => __( 'Simple map displaying all configured locations.', 'commonsbooking' ),
				'categories'  => [ 'commonsbooking' ],
				'content'     => '<!-- wp:shortcode -->[cb_map]<!-- /wp:shortcode -->',
			]
		);

		register_block_pattern(
			'commonsbooking/my-bookings',
			[
				'title'       => __( 'CommonsBooking: My Bookings', 'commonsbooking' ),
				'description' => __( "Shows the current user's booking list (upcoming and past bookings).", 'commonsbooking' ),
				'categories'  => [ 'commonsbooking' ],
				'content'     => '<!-- wp:shortcode -->[cb_bookings]<!-- /wp:shortcode -->',
			]
		);

		register_block_pattern(
			'commonsbooking/items-calendar',
			[
				'title'       => __( 'CommonsBooking: Items Calendar Table', 'commonsbooking' ),
				'description' => __( 'Calendar-style availability overview for all items at all locations.', 'commonsbooking' ),
				'categories'  => [ 'commonsbooking' ],
				'content'     => '<!-- wp:shortcode -->[cb_items_table]<!-- /wp:shortcode -->',
			]
		);
	}
}
