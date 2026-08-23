<?php
/**
 * CommonsBooking booking data generator (local dev seed script).
 *
 * Creates N bookings plus the related objects they need to be valid:
 * one location, one item, and one bookable timeframe that covers them.
 * Each booking sits on its own day, so they never overlap and are valid
 * by construction (see \CommonsBooking\Model\Booking::isValid()).
 *
 * It reuses the plugin's own test factory (tests/php/CPTCreationTrait.php)
 * for everything, so generated data matches what the plugin expects.
 * Run `composer install` in the plugin dir first so that factory is loadable.
 *
 * USAGE (with this repo's wp-env):
 *   npm run env -- run cli -- \
 *     php wp-content/plugins/commonsbooking/scripts/generate-bookings.php --count=10 --verify
 *
 * USAGE (standalone; finds wp-load.php by itself, or set WP_ROOT):
 *   php scripts/generate-bookings.php --count=100 --verify
 *
 * OPTIONS:
 *   --count=N   How many bookings to create (default 1).
 *   --verify    Check a few of them with Booking::isValid() and report.
 *   --cleanup   Delete everything this script ever created, then exit.
 *   --help      Show this help.
 *
 * Note: this is the simple, readable path (~14 DB writes per booking). Great
 * up to a few thousand; for 100k it will take a while but still works.
 *
 * @package CommonsBooking
 */

// Edit this if user id 1 is not the author you want the data owned by.
const CBGEN_AUTHOR = 1;

// Meta key stamped on every created post, so --cleanup can find them again.
const CBGEN_MARKER = '_cb_datagen';

// --- Only run on the command line. ---
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	exit( "Run this from the command line, not the web.\n" );
}

// --- Read the options. ---
$options = [];
foreach ( $argv as $arg ) {
	if ( preg_match( '/^--([a-z]+)(?:=(.*))?$/', $arg, $m ) ) {
		$options[ $m[1] ] = $m[2] ?? true;
	}
}
if ( isset( $options['help'] ) ) {
	echo "Usage: php generate-bookings.php [--count=N] [--verify] [--cleanup] [--help]\n";
	exit( 0 );
}
$count  = max( 0, (int) ( $options['count'] ?? 1 ) );
$verify = isset( $options['verify'] );

// --- Load WordPress (unless we are already inside it). ---
if ( ! defined( 'ABSPATH' ) ) {
	$wpLoad = getenv( 'WP_ROOT' ) ? rtrim( getenv( 'WP_ROOT' ), '/' ) . '/wp-load.php' : null;
	for ( $dir = __DIR__; ! $wpLoad && $dir !== dirname( $dir ); $dir = dirname( $dir ) ) {
		if ( file_exists( "$dir/wp-load.php" ) ) {
			$wpLoad = "$dir/wp-load.php";
		}
	}
	if ( ! $wpLoad || ! file_exists( $wpLoad ) ) {
		exit( "Could not find wp-load.php. Set WP_ROOT=/path/to/wordpress or run via wp-env.\n" );
	}
	require $wpLoad;
}

if ( ! trait_exists( \CommonsBooking\Tests\CPTCreationTrait::class ) ) {
	exit( "CommonsBooking test factory not found. Run `composer install` in the plugin dir first.\n" );
}

use CommonsBooking\Wordpress\CustomPostType\Timeframe;

/**
 * Tiny host for the plugin's test factory. The factory methods are protected,
 * so we wrap the couple we need in public methods and stamp our marker.
 */
final class CBGen {
	use \CommonsBooking\Tests\CPTCreationTrait;

	public function location(): int {
		$id = $this->createLocation( 'CBGen Location', 'publish', [], CBGEN_AUTHOR );
		update_post_meta( $id, CBGEN_MARKER, 1 );
		return $id;
	}

	public function item(): int {
		$id = $this->createItem( 'CBGen Item', 'publish', [], CBGEN_AUTHOR );
		update_post_meta( $id, CBGEN_MARKER, 1 );
		return $id;
	}

	/** Bookable timeframe covering [today-1 .. today+days+1] for this location+item. */
	public function timeframe( int $location, int $item, int $days ): int {
		$today = strtotime( 'today midnight' );
		$id    = $this->createTimeframe(
			$location,
			$item,
			strtotime( '-1 day', $today ),
			strtotime( '+' . ( $days + 1 ) . ' days', $today ),
			Timeframe::BOOKABLE_ID,
			'on',
			'w',
			0,
			'8:00 AM',
			'12:00 PM',
			'publish',
			[ '1', '2', '3', '4', '5', '6', '7' ],
			'',
			CBGEN_AUTHOR
		);
		update_post_meta( $id, CBGEN_MARKER, 1 );
		return $id;
	}

	/** One full-day booking on day (today + $dayOffset). */
	public function booking( int $location, int $item, int $dayOffset ): int {
		$start = strtotime( "+$dayOffset days", strtotime( 'today midnight' ) );
		$end   = strtotime( '+1 day midnight', $start ) - 1;
		$id    = $this->createBooking(
			$location,
			$item,
			$start,
			$end,
			'12:00 AM',
			'23:59',
			'confirmed',
			CBGEN_AUTHOR,
			'w',
			3,
			'CBGen Booking ' . $dayOffset
		);
		update_post_meta( $id, CBGEN_MARKER, 1 );
		return $id;
	}
}

$gen = new CBGen();

// --- Cleanup mode: delete everything we ever made. ---
if ( isset( $options['cleanup'] ) ) {
	global $wpdb;
	$ids = $wpdb->get_col(
		$wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", CBGEN_MARKER )
	);
	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
	echo 'Deleted ' . count( $ids ) . " generated post(s).\n";
	exit( 0 );
}

// --- Create the shared, valid related objects. ---
$location  = $gen->location();
$item      = $gen->item();
$timeframe = $gen->timeframe( $location, $item, $count );
echo "Location #$location, item #$item, bookable timeframe #$timeframe.\n";

// --- Create the bookings. ---
$start   = microtime( true );
$created = [];
for ( $i = 0; $i < $count; $i++ ) {
	$created[] = $gen->booking( $location, $item, $i );
}
$elapsed = microtime( true ) - $start;
printf( "Created %d booking(s) in %.2fs (%.1f/s).\n", $count, $elapsed, $count / max( $elapsed, 0.001 ) );

// --- Optionally verify a few. ---
if ( $verify && $created ) {
	$sample = array_slice( $created, 0, 5 );
	$sample[] = end( $created );
	$ok = 0;
	foreach ( array_unique( $sample ) as $id ) {
		try {
			if ( ( new \CommonsBooking\Model\Booking( (int) $id ) )->isValid() ) {
				$ok++;
			}
		} catch ( \Throwable $e ) {
			echo "  booking #$id invalid: " . $e->getMessage() . "\n";
		}
	}
	echo "Verified $ok/" . count( array_unique( $sample ) ) . " sampled booking(s) valid.\n";
}

echo "Done. Remove this data any time with --cleanup.\n";
