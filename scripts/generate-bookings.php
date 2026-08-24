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
 *   --count=N       How many bookings to create (default 1).
 *   --hours=H       Make each booking an H-hour slot instead of a full day.
 *                   The start hour rotates across the day per booking, so one run
 *                   covers many hours-of-day (handy for UTC/timezone testing).
 *                   Default 0 = full-day bookings.
 *   --locations=N   Spread the bookings across N locations (default 1).
 *   --lat=Y --lon=X Give the locations coordinates centred on this point.
 *   --distancekm=D  Scatter the locations randomly within D km of the centre
 *                   (default 0 = all exactly at the centre). Needs --lat/--lon.
 *   --verify        Check a few bookings with Booking::isValid() and report.
 *   --cleanup       Delete everything this script ever created, then exit.
 *   --help          Show this help.
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
	echo "Usage: php generate-bookings.php [--count=N] [--hours=H] [--locations=N]\n" .
		"                                 [--lat=Y --lon=X [--distancekm=D]]\n" .
		"                                 [--verify] [--cleanup] [--help]\n";
	exit( 0 );
}
$count     = max( 0, (int) ( $options['count'] ?? 1 ) );
$hours     = max( 0, min( 23, (int) ( $options['hours'] ?? 0 ) ) ); // 0 = full day
$locations = max( 1, (int) ( $options['locations'] ?? 1 ) );
$verify    = isset( $options['verify'] );

// Geo: --lat + --lon set the center; --distancekm scatters around it (0 = exactly
// at center). Both lat and lon must be given, otherwise no coordinates are set.
$hasCenter  = isset( $options['lat'], $options['lon'] );
$centerLat  = (float) ( $options['lat'] ?? 0 );
$centerLon  = (float) ( $options['lon'] ?? 0 );
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

	public function location( int $index = 0, ?float $lat = null, ?float $lon = null ): int {
		$id = $this->createLocation( 'CBGen Location ' . $index, 'publish', [], CBGEN_AUTHOR );
		if ( $lat !== null && $lon !== null ) {
			update_post_meta( $id, 'geo_latitude', $lat );
			update_post_meta( $id, 'geo_longitude', $lon );
		}
		update_post_meta( $id, CBGEN_MARKER, 1 );
		return $id;
	}

	public function item(): int {
		$id = $this->createItem( 'CBGen Item', 'publish', [], CBGEN_AUTHOR );
		update_post_meta( $id, CBGEN_MARKER, 1 );
		return $id;
	}

	/**
	 * Bookable timeframe covering [today-1 .. today+days+1] for this location+item.
	 * $hours = 0 makes a full-day timeframe; $hours >= 1 makes an hourly one
	 * (every hour of the day bookable), so hourly bookings fit inside it.
	 */
	public function timeframe( int $location, int $item, int $days, int $hours ): int {
		$today  = strtotime( 'today midnight' );
		$fullDay = ( $hours === 0 ) ? 'on' : '';
		$grid    = ( $hours === 0 ) ? 0 : 1; // 1 = hourly grid
		$id      = $this->createTimeframe(
			$location,
			$item,
			strtotime( '-1 day', $today ),
			strtotime( '+' . ( $days + 1 ) . ' days', $today ),
			Timeframe::BOOKABLE_ID,
			$fullDay,
			'w',
			$grid,
			$hours === 0 ? '8:00 AM' : '00:00',
			$hours === 0 ? '12:00 PM' : '23:59',
			'publish',
			[ '1', '2', '3', '4', '5', '6', '7' ],
			'',
			CBGEN_AUTHOR
		);
		update_post_meta( $id, CBGEN_MARKER, 1 );
		return $id;
	}

	/**
	 * One booking on day (today + $dayOffset). $hours = 0 books the full day;
	 * $hours >= 1 books an $hours-long slot whose start hour rotates across the
	 * day per booking (so a run spans many hours-of-day for UTC testing).
	 */
	public function booking( int $location, int $item, int $dayOffset, int $hours ): int {
		$day = strtotime( "+$dayOffset days", strtotime( 'today midnight' ) );

		if ( $hours === 0 ) {
			$start     = $day;
			$end       = strtotime( '+1 day midnight', $day ) - 1;
			$startTime = '12:00 AM';
			$endTime   = '23:59';
			$gridSize  = '';
		} else {
			$startHour = $dayOffset % ( 24 - $hours + 1 ); // keeps start+hours within the day
			$start     = $day + $startHour * HOUR_IN_SECONDS;
			$end       = $start + $hours * HOUR_IN_SECONDS - 1; // last second of the slot
			$startTime = sprintf( '%02d:00', $startHour );
			$endTime   = date( 'H:i', $end ); // e.g. 2h slot from 10:00 -> "11:59"
			$gridSize  = (string) $hours;
		}

		$id = $this->createBooking(
			$location,
			$item,
			$start,
			$end,
			$startTime,
			$endTime,
			'confirmed',
			CBGEN_AUTHOR,
			'w',
			3,
			'CBGen Booking ' . $dayOffset,
			0,
			[ '1', '2', '3', '4', '5', '6', '7' ],
			$gridSize,
			$gridSize
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

/**
 * A random point within $distanceKm of ($lat, $lon). Uses a simple flat-earth
 * approximation, which is plenty accurate for test data over a city/region.
 *
 * @return array{0:float,1:float} [ latitude, longitude ]
 */
function cbgen_random_geo( float $lat, float $lon, float $distanceKm ): array {
	if ( $distanceKm <= 0 ) {
		return [ round( $lat, 6 ), round( $lon, 6 ) ];
	}
	$radiusKm = $distanceKm * sqrt( mt_rand() / mt_getrandmax() ); // sqrt = even spread over area
	$bearing  = ( mt_rand() / mt_getrandmax() ) * 2 * M_PI;
	$kmPerDeg = 111.32; // km per degree of latitude
	$newLat   = $lat + ( $radiusKm * cos( $bearing ) ) / $kmPerDeg;
	$newLon   = $lon + ( $radiusKm * sin( $bearing ) ) / ( $kmPerDeg * cos( deg2rad( $lat ) ) );
	return [
		round( max( -90, min( 90, $newLat ) ), 6 ),
		round( max( -180, min( 180, $newLon ) ), 6 ),
	];
}

// --- Create the shared item and the located locations (each with a timeframe). ---
$item        = $gen->item();
$locationIds = [];
for ( $i = 0; $i < $locations; $i++ ) {
	if ( $hasCenter ) {
		[ $lat, $lon ] = cbgen_random_geo( $centerLat, $centerLon, $distanceKm );
	} else {
		$lat = $lon = null;
	}
	$loc = $gen->location( $i, $lat, $lon );
	$gen->timeframe( $loc, $item, $count, $hours );
	$locationIds[] = $loc;
}
$mode = $hours === 0 ? 'full-day' : $hours . 'h slots';
$geo  = $hasCenter ? sprintf( ' around %.5f,%.5f within %gkm', $centerLat, $centerLon, $distanceKm ) : '';
echo "Item #$item, $locations location(s)$geo, bookable timeframes ($mode).\n";

// --- Create the bookings, spread across the locations. ---
$start   = microtime( true );
$created = [];
for ( $i = 0; $i < $count; $i++ ) {
	$location  = $locationIds[ $i % $locations ]; // round-robin; day $i keeps them non-overlapping
	$created[] = $gen->booking( $location, $item, $i, $hours );
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
