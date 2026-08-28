<?php

namespace CommonsBooking\Tests\Benchmark;

use CommonsBooking\Helper\Helper;
use CommonsBooking\Repository\Booking;
use CommonsBooking\Tests\BookingGenerator;

/**
 * Benchmarks a booking query against a static, file-defined data source.
 *
 * The data set is described by tests/benchmark/fixtures/benchmark-dataset.json
 * and built in setUp() through the shared BookingGenerator (the same one behind
 * the scripts/generate-bookings.php CLI). The manifest's fixed "seed" makes the
 * generated data deterministic, so the PR branch and master build byte-for-byte
 * the same data set and the benchmark compares code, not random data.
 * benchGetByTimerange() then measures a whole-year booking lookup over it, so
 * the benchmark reflects a real, populated database rather than an empty install.
 *
 * @BeforeMethods({"setUp"})
 * @AfterMethods({"tearDown"})
 */
class BookingGeneratorBench {

	const DATASET = __DIR__ . '/fixtures/benchmark-dataset.json';
	const USER_ID = 1;

	/** @var BookingGenerator */
	private $generator;

	/** @var array Parsed dataset manifest. */
	private $manifest;

	/**
	 * @Iterations(3)
	 * @Revs(5)
	 */
	public function benchGetByTimerange(): void {
		$base  = $this->generator->baseDay(); // honours the manifest's "start"
		$start = strtotime( '-1 day', $base );
		$end   = strtotime( '+' . ( $this->manifest['count'] + 1 ) . ' days', $base );
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

		$this->manifest  = BookingGenerator::readManifest( self::DATASET );
		$this->generator = new BookingGenerator( self::USER_ID );
		$this->generator->generateFromManifest( $this->manifest );

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
