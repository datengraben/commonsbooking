<?php
/**
 * CommonsBooking booking data generator (local dev seed script).
 *
 * Creates N bookings plus the related objects they need to be valid:
 * one item, one or more (optionally geo-located) locations, and a bookable
 * timeframe per location. Each booking sits on its own day, so they never
 * overlap and are valid by construction (\CommonsBooking\Model\Booking::isValid()).
 *
 * All the actual work lives in the reusable generator
 * tests/php/BookingGenerator.php, which the benchmark suite uses too. This file
 * is just the command-line wrapper around it. Run `composer install` in the
 * plugin dir first so that class is loadable.
 *
 * USAGE (with this repo's wp-env):
 *   npm run env -- run cli -- \
 *     php wp-content/plugins/commonsbooking/scripts/generate-bookings.php --count=10 --verify
 *
 * USAGE (standalone; finds wp-load.php by itself, or set WP_ROOT):
 *   php scripts/generate-bookings.php --count=100 --verify
 *
 * OPTIONS:
 *   --count=N       How many bookings to create (default 1).
 *   --hours=H       Make each booking an H-hour slot instead of a full day.
 *                   The start hour rotates across the day per booking, so one run
 *                   covers many hours-of-day (handy for UTC/timezone testing).
 *                   Default 0 = full-day bookings.
 *   --locations=N   Spread the bookings across N locations (default 1).
 *   --start=DATE    Anchor the bookings to start on DATE (e.g. 2026-01-01)
 *                   instead of today. The range is DATE .. DATE+count days.
 *   --spread=N      Place bookings within +/- N days of the start date (past and
 *                   future) instead of marching forward. Capacity is
 *                   (2*N + 1) * locations bookings.
 *   --random        With --spread, scatter bookings randomly across the window
 *                   (seeded via --seed) instead of filling it evenly.
 *   --lat=Y --lon=X Give the locations coordinates centred on this point.
 *   --distancekm=D  Scatter the locations randomly within D km of the centre
 *                   (default 0 = all exactly at the centre). Needs --lat/--lon.
 *   --seed=N        Seed the random geo placement so runs are reproducible.
 *   --dataset=FILE  Ignore the options above and build exactly what the JSON
 *                   manifest FILE describes (see tests/benchmark/fixtures/).
 *   --verify        Check a few bookings with Booking::isValid() and report.
 *   --cleanup       Delete everything this script ever created, then exit.
 *   --help          Show this help.
 *
 * Note: this is the simple, readable path (~14 DB writes per booking). Great
 * up to a few thousand; for 100k it will take a while but still works.
 *
 * @package CommonsBooking
 */

use CommonsBooking\Tests\BookingGenerator;

// Edit this if user id 1 is not the author you want the data owned by.
const CBGEN_AUTHOR = 1;

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
	echo "Usage: php generate-bookings.php [--count=N] [--hours=H] [--locations=N]\n" .
		"                                 [--start=DATE] [--spread=N] [--random] [--seed=N]\n" .
		"                                 [--lat=Y --lon=X [--distancekm=D]]\n" .
		"                                 [--dataset=FILE] [--verify] [--cleanup] [--help]\n";
	exit( 0 );
}
$count     = max( 0, (int) ( $options['count'] ?? 1 ) );
$hours     = max( 0, min( 23, (int) ( $options['hours'] ?? 0 ) ) ); // 0 = full day
$locations = max( 1, (int) ( $options['locations'] ?? 1 ) );
$verify    = isset( $options['verify'] );

// Geo: --lat + --lon set the center; --distancekm scatters around it (0 = exactly
// at center). Both lat and lon must be given, otherwise no coordinates are set.
$hasCenter  = isset( $options['lat'], $options['lon'] );
$centerLat  = $hasCenter ? (float) $options['lat'] : null;
$centerLon  = $hasCenter ? (float) $options['lon'] : null;
$distanceKm = max( 0.0, (float) ( $options['distancekm'] ?? 0 ) );
if ( ! $hasCenter && ( isset( $options['lat'] ) || isset( $options['lon'] ) || isset( $options['distancekm'] ) ) ) {
	echo "Note: geo options ignored -- pass both --lat and --lon to place locations.\n";
}

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

if ( ! class_exists( BookingGenerator::class ) ) {
	exit( "CommonsBooking test helpers not found. Run `composer install` in the plugin dir first.\n" );
}

$gen = new BookingGenerator( CBGEN_AUTHOR );

// --- Cleanup mode: delete everything we ever made (across all runs). ---
if ( isset( $options['cleanup'] ) ) {
	global $wpdb;
	$ids = $wpdb->get_col(
		$wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", BookingGenerator::MARKER )
	);
	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
	echo 'Deleted ' . count( $ids ) . " generated post(s).\n";
	exit( 0 );
}

// --- Generate. ---
$start = microtime( true );
if ( isset( $options['dataset'] ) ) {
	// Static data source: build exactly what the manifest file describes.
	$manifest = BookingGenerator::readManifest( (string) $options['dataset'] );
	echo "Generating from dataset {$options['dataset']} (" . json_encode( $manifest ) . ")...\n";
	$created = $gen->generateFromManifest( $manifest );
} else {
	$seed   = isset( $options['seed'] ) ? (int) $options['seed'] : null;
	$spread = isset( $options['spread'] ) ? (int) $options['spread'] : null;
	$random = isset( $options['random'] );
	$gen->setBaseDay( $options['start'] ?? null ); // null = today
	$around = $spread === null
		? 'from ' . date( 'Y-m-d', $gen->baseDay() )
		: ( $random ? 'randomly ' : '' ) . "within +/-$spread days of " . date( 'Y-m-d', $gen->baseDay() );
	$mode   = $hours === 0 ? 'full-day' : $hours . 'h slots';
	$geo    = $hasCenter ? sprintf( ' around %.5f,%.5f within %gkm', $centerLat, $centerLon, $distanceKm ) : '';
	echo "Generating $count booking(s) $around over $locations location(s)$geo ($mode)...\n";
	$created = $gen->generate( $count, $hours, $locations, $centerLat, $centerLon, $distanceKm, $seed, $spread, $random );
}
$elapsed = microtime( true ) - $start;
printf( "Created %d booking(s) in %.2fs (%.1f/s).\n", count( $created ), $elapsed, count( $created ) / max( $elapsed, 0.001 ) );

// --- Optionally verify a few. ---
if ( $verify && $created ) {
	$sample   = array_slice( $created, 0, 5 );
	$sample[] = end( $created );
	$ok       = 0;
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
