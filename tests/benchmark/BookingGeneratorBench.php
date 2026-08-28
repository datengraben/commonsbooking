<?php

namespace CommonsBooking\Tests\Benchmark;

use CommonsBooking\Helper\Helper;
use CommonsBooking\Repository\Booking;
use CommonsBooking\Tests\BookingGenerator;

/**
 * Benchmarks a booking query against a dynamically generated data source.
 *
 * setUp() uses the shared BookingGenerator (the same one behind the
 * scripts/generate-bookings.php CLI) to create a full year of bookings spread
 * over several geo-located locations. benchGetByTimerange() then measures a
 * whole-year booking lookup over that data set, so the benchmark reflects real,
 * populated-database performance rather than an empty install.
 *
 * @BeforeMethods({"setUp"})
 * @AfterMethods({"tearDown"})
 */
class BookingGeneratorBench {

	const BOOKINGS  = 365; // one booking per day for a year
	const LOCATIONS = 10;  // spread across this many locations
	const USER_ID   = 1;

	/** @var BookingGenerator */
	private $generator;

	/**
	 * @Iterations(3)
	 * @Revs(5)
	 */
	public function benchGetByTimerange(): void {
		$start = strtotime( '-1 day', strtotime( 'today midnight' ) );
		$end   = strtotime( '+' . ( self::BOOKINGS + 1 ) . ' days', strtotime( 'today midnight' ) );
		Booking::getByTimerange( $start, $end );
	}

	public function setUp(): void {
		error_reporting( E_ALL & ~E_DEPRECATED ); // deprecations make benchmarks fail

		// Disable caching so the query is actually executed each time.
		add_filter( 'commonsbooking_disableCache', '__return_true' );

		// Same bulk-insert speed-ups CalendarBench uses, so building the data set
		// (which is not what we measure) stays fast.
		global $wpdb;
		$wpdb->query( 'SET autocommit=0' );
		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );
		if ( ! defined( 'WP_IMPORTING' ) ) {
			define( 'WP_IMPORTING', true );
		}
		$randomSlug = fn( $override, $slug, $post_id, $post_status, $post_type, $post_parent ) => Helper::generateRandomString();
		add_filter( 'pre_wp_unique_post_slug', $randomSlug, 10, 6 );

		$this->generator = new BookingGenerator( self::USER_ID );
		$this->generator->generate(
			self::BOOKINGS,
			0,                // full-day bookings
			self::LOCATIONS,
			52.52,            // centre latitude (Berlin)
			13.405,           // centre longitude
			15.0              // scatter within 15 km
		);

		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );
		$wpdb->query( 'COMMIT;' );
		$wpdb->query( 'SET autocommit = 1;' );
		remove_filter( 'pre_wp_unique_post_slug', $randomSlug );
	}

	public function tearDown(): void {
		$this->generator->cleanup();
	}
}
