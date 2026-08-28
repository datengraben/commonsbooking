<?php

namespace CommonsBooking\Tests;

use CommonsBooking\Wordpress\CustomPostType\Timeframe;

/**
 * Reusable booking data generator.
 *
 * Creates bookings plus the related objects they need to be valid: one item,
 * one or more (optionally geo-located) locations, and a bookable timeframe per
 * location. Each booking sits on its own day, so bookings never overlap and are
 * valid by construction (see \CommonsBooking\Model\Booking::isValid()).
 *
 * It builds everything through the plugin's own test factory (CPTCreationTrait),
 * so the generated data matches what the plugin expects. It is used by:
 *   - scripts/generate-bookings.php (the hand-runnable CLI seed script), and
 *   - tests/benchmark/BookingGeneratorBench.php (a phpbench data source).
 *
 * @package CommonsBooking
 */
class BookingGenerator {
	use CPTCreationTrait;

	/** Meta key stamped on every created post, so it can be found again later. */
	public const MARKER = '_cb_datagen';

	/** User id that owns the generated posts. */
	private int $author;

	public function __construct( int $author = 1 ) {
		$this->author = $author;
	}

	/**
	 * Read a JSON dataset manifest and return its parameters, with defaults
	 * filled in for any missing keys. A manifest describes what to generate:
	 *
	 *   { "count": 365, "hours": 0, "locations": 10,
	 *     "lat": 52.52, "lon": 13.405, "distancekm": 15, "seed": 42 }
	 *
	 * @return array{count:int,hours:int,locations:int,lat:?float,lon:?float,distancekm:float,seed:?int}
	 */
	public static function readManifest( string $path ): array {
		$json = is_readable( $path ) ? file_get_contents( $path ) : false;
		if ( $json === false ) {
			throw new \RuntimeException( "Cannot read dataset manifest: $path" );
		}
		$m = json_decode( $json, true );
		if ( ! is_array( $m ) ) {
			throw new \RuntimeException( "Invalid JSON in dataset manifest: $path" );
		}
		return [
			'count'      => (int) ( $m['count'] ?? 1 ),
			'hours'      => (int) ( $m['hours'] ?? 0 ),
			'locations'  => (int) ( $m['locations'] ?? 1 ),
			'lat'        => isset( $m['lat'] ) ? (float) $m['lat'] : null,
			'lon'        => isset( $m['lon'] ) ? (float) $m['lon'] : null,
			'distancekm' => (float) ( $m['distancekm'] ?? 0 ),
			'seed'       => isset( $m['seed'] ) ? (int) $m['seed'] : null,
		];
	}

	/**
	 * Generate from a manifest array (as returned by readManifest()).
	 *
	 * @return int[] The created booking ids.
	 */
	public function generateFromManifest( array $m ): array {
		return $this->generate(
			$m['count'],
			$m['hours'] ?? 0,
			$m['locations'] ?? 1,
			$m['lat'] ?? null,
			$m['lon'] ?? null,
			$m['distancekm'] ?? 0,
			$m['seed'] ?? null
		);
	}

	/**
	 * Create the whole data set: one item, $locations location(s), a bookable
	 * timeframe per location, and $count bookings spread across the locations.
	 *
	 * @param int        $count      Number of bookings.
	 * @param int        $hours      0 = full-day bookings; >= 1 = H-hour slots.
	 * @param int        $locations  How many locations to spread bookings over.
	 * @param float|null $lat        Centre latitude (with $lon) for the locations.
	 * @param float|null $lon        Centre longitude (with $lat) for the locations.
	 * @param float      $distanceKm Random scatter radius around the centre.
	 * @param int|null   $seed       Seed the RNG so the random geo placement is
	 *                               reproducible (same file -> same coordinates).
	 *
	 * @return int[] The created booking ids.
	 */
	public function generate(
		int $count,
		int $hours = 0,
		int $locations = 1,
		?float $lat = null,
		?float $lon = null,
		float $distanceKm = 0,
		?int $seed = null
	): array {
		if ( $seed !== null ) {
			mt_srand( $seed ); // deterministic geo scatter
		}
		$locations = max( 1, $locations );
		$item      = $this->item();

		$locationIds = [];
		for ( $i = 0; $i < $locations; $i++ ) {
			if ( $lat !== null && $lon !== null ) {
				[ $pLat, $pLon ] = $this->randomGeo( $lat, $lon, $distanceKm );
			} else {
				$pLat = $pLon = null;
			}
			$loc = $this->location( $i, $pLat, $pLon );
			$this->timeframe( $loc, $item, $count, $hours );
			$locationIds[] = $loc;
		}

		$bookingIds = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$loc          = $locationIds[ $i % $locations ]; // round-robin; day $i keeps them non-overlapping
			$bookingIds[] = $this->booking( $loc, $item, $i, $hours );
		}

		return $bookingIds;
	}

	/** Delete everything this instance created. */
	public function cleanup(): void {
		$this->tearDownAllPosts();
	}

	public function item(): int {
		$id = $this->createItem( 'CBGen Item', 'publish', [], $this->author );
		update_post_meta( $id, self::MARKER, 1 );
		return $id;
	}

	public function location( int $index = 0, ?float $lat = null, ?float $lon = null ): int {
		$id = $this->createLocation( 'CBGen Location ' . $index, 'publish', [], $this->author );
		if ( $lat !== null && $lon !== null ) {
			update_post_meta( $id, 'geo_latitude', $lat );
			update_post_meta( $id, 'geo_longitude', $lon );
		}
		update_post_meta( $id, self::MARKER, 1 );
		return $id;
	}

	/**
	 * Bookable timeframe covering [today-1 .. today+days+1] for this location+item.
	 * $hours = 0 makes a full-day timeframe; $hours >= 1 makes an hourly one
	 * (every hour of the day bookable), so hourly bookings fit inside it.
	 */
	public function timeframe( int $location, int $item, int $days, int $hours ): int {
		$today   = strtotime( 'today midnight' );
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
			$this->author
		);
		update_post_meta( $id, self::MARKER, 1 );
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
			$this->author,
			'w',
			3,
			'CBGen Booking ' . $dayOffset,
			0,
			[ '1', '2', '3', '4', '5', '6', '7' ],
			$gridSize,
			$gridSize
		);
		update_post_meta( $id, self::MARKER, 1 );
		return $id;
	}

	/**
	 * A random point within $distanceKm of ($lat, $lon). Uses a simple flat-earth
	 * approximation, which is plenty accurate for test data over a city/region.
	 *
	 * @return array{0:float,1:float} [ latitude, longitude ]
	 */
	public function randomGeo( float $lat, float $lon, float $distanceKm ): array {
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
}
