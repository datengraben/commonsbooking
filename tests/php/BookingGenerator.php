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

	/** Midnight timestamp that booking #0 sits on. Null = today. */
	private ?int $baseDay = null;

	public function __construct( int $author = 1 ) {
		$this->author = $author;
	}

	/**
	 * Anchor the generated data to a specific start date instead of "today".
	 * Accepts anything strtotime() understands (e.g. "2026-01-01") or a unix
	 * timestamp; null resets to today. The booking range is [start .. start+count]
	 * days and the timeframe range is derived to cover it.
	 *
	 * @param string|int|null $start
	 */
	public function setBaseDay( $start ): void {
		if ( $start === null || $start === '' ) {
			$this->baseDay = null;
			return;
		}
		$ts = is_numeric( $start ) ? (int) $start : strtotime( (string) $start );
		if ( $ts === false ) {
			throw new \RuntimeException( "Invalid start date: $start" );
		}
		$this->baseDay = strtotime( 'midnight', $ts ); // full-day math needs a midnight anchor
	}

	/** The midnight timestamp booking #0 sits on (today unless setBaseDay() was called). */
	public function baseDay(): int {
		return $this->baseDay ?? strtotime( 'today midnight' );
	}

	/**
	 * Read a JSON dataset manifest and return its parameters, with defaults
	 * filled in for any missing keys. A manifest describes what to generate:
	 *
	 *   { "start": "2026-01-01", "count": 365, "hours": 0, "locations": 10,
	 *     "lat": 52.52, "lon": 13.405, "distancekm": 15, "seed": 42 }
	 *
	 * @return array{start:?string,count:int,hours:int,locations:int,lat:?float,lon:?float,distancekm:float,seed:?int}
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
			'start'      => isset( $m['start'] ) ? (string) $m['start'] : null,
			'count'      => (int) ( $m['count'] ?? 1 ),
			'hours'      => (int) ( $m['hours'] ?? 0 ),
			'locations'  => (int) ( $m['locations'] ?? 1 ),
			'lat'        => isset( $m['lat'] ) ? (float) $m['lat'] : null,
			'lon'        => isset( $m['lon'] ) ? (float) $m['lon'] : null,
			'distancekm' => (float) ( $m['distancekm'] ?? 0 ),
			'seed'       => isset( $m['seed'] ) ? (int) $m['seed'] : null,
			'spread'     => isset( $m['spread'] ) ? (int) $m['spread'] : null,
			'random'     => (bool) ( $m['random'] ?? false ),
		];
	}

	/**
	 * Generate from a manifest array (as returned by readManifest()).
	 *
	 * @return int[] The created booking ids.
	 */
	public function generateFromManifest( array $m ): array {
		$this->setBaseDay( $m['start'] ?? null );
		return $this->generate(
			$m['count'],
			$m['hours'] ?? 0,
			$m['locations'] ?? 1,
			$m['lat'] ?? null,
			$m['lon'] ?? null,
			$m['distancekm'] ?? 0,
			$m['seed'] ?? null,
			$m['spread'] ?? null,
			$m['random'] ?? false
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
	 * @param int|null   $spread     If set, place bookings within +/- $spread days
	 *                               of the start date instead of marching forward.
	 *                               Capacity is (2*spread + 1) * locations bookings.
	 * @param bool       $random     With $spread, scatter the bookings randomly
	 *                               across the window (seeded, still non-overlapping)
	 *                               instead of filling it evenly. No effect without
	 *                               $spread.
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
		?int $seed = null,
		?int $spread = null,
		bool $random = false
	): array {
		if ( $seed !== null ) {
			mt_srand( $seed ); // deterministic geo scatter
		}
		$locations = max( 1, $locations );
		$item      = $this->item();

		// Work out each booking's day offset and location so that every
		// (day, location) pair is unique -> no overlaps -> valid by construction.
		if ( $spread === null ) {
			// Forward: one booking per day, marching from the start date.
			if ( $random ) {
				trigger_error( 'BookingGenerator: "random" has no effect without "spread"; ignoring.', E_USER_NOTICE );
			}
			$minDay = 0;
			$maxDay = max( 0, $count - 1 );
			$place  = fn( int $i ) => [ $i, $i % $locations ];
		} else {
			// Windowed: fill a +/- $spread day window across the locations.
			$spread   = max( 0, $spread );
			$window   = 2 * $spread + 1;
			$capacity = $window * $locations;
			if ( $count > $capacity ) {
				trigger_error(
					"BookingGenerator: $count bookings do not fit in +/-$spread days over $locations location(s) " .
					"(capacity $capacity); some will share a day and be invalid. Raise spread or locations.",
					E_USER_WARNING
				);
			}
			$minDay = -$spread;
			$maxDay = $spread;
			if ( $random ) {
				// Shuffle the window's (day, location) cells and take them in that
				// order, so bookings land on a random subset -- still one per cell.
				$cells = [];
				for ( $d = -$spread; $d <= $spread; $d++ ) {
					for ( $l = 0; $l < $locations; $l++ ) {
						$cells[] = [ $d, $l ];
					}
				}
				$this->seededShuffle( $cells );
				$n     = max( 1, count( $cells ) );
				$place = fn( int $i ) => $cells[ $i % $n ];
			} else {
				$place = fn( int $i ) => [ ( $i % $window ) - $spread, intdiv( $i, $window ) % $locations ];
			}
		}

		$locationIds = [];
		for ( $i = 0; $i < $locations; $i++ ) {
			if ( $lat !== null && $lon !== null ) {
				[ $pLat, $pLon ] = $this->randomGeo( $lat, $lon, $distanceKm );
			} else {
				$pLat = $pLon = null;
			}
			$loc = $this->location( $i, $pLat, $pLon );
			$this->timeframe( $loc, $item, $minDay, $maxDay, $hours );
			$locationIds[] = $loc;
		}

		$bookingIds = [];
		for ( $i = 0; $i < $count; $i++ ) {
			[ $dayOffset, $locIndex ] = $place( $i );
			$bookingIds[]             = $this->booking( $locationIds[ $locIndex ], $item, $dayOffset, $hours );
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
	 * Bookable timeframe covering the booking window (with one day of slack on
	 * each side) for this location+item, i.e. [base+minDay-1 .. base+maxDay+1].
	 * $hours = 0 makes a full-day timeframe; $hours >= 1 makes an hourly one
	 * (every hour of the day bookable), so hourly bookings fit inside it.
	 */
	public function timeframe( int $location, int $item, int $minDay, int $maxDay, int $hours ): int {
		$base    = $this->baseDay();
		$fullDay = ( $hours === 0 ) ? 'on' : '';
		$grid    = ( $hours === 0 ) ? 0 : 1; // 1 = hourly grid
		$id      = $this->createTimeframe(
			$location,
			$item,
			strtotime( sprintf( '%+d days', $minDay - 1 ), $base ),
			strtotime( sprintf( '%+d days', $maxDay + 1 ), $base ),
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
		$day = strtotime( sprintf( '%+d days', $dayOffset ), $this->baseDay() ); // %+d handles negative offsets

		if ( $hours === 0 ) {
			$start     = $day;
			$end       = strtotime( '+1 day midnight', $day ) - 1;
			$startTime = '12:00 AM';
			$endTime   = '23:59';
			$gridSize  = '';
		} else {
			$mod       = 24 - $hours + 1;
			$startHour = ( ( $dayOffset % $mod ) + $mod ) % $mod; // non-negative; keeps start+hours within the day
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
	 * Fisher-Yates shuffle using the (optionally seeded) RNG, so a run with a
	 * fixed seed always produces the same order.
	 */
	private function seededShuffle( array &$items ): void {
		for ( $i = count( $items ) - 1; $i > 0; $i-- ) {
			$j = mt_rand( 0, $i );
			[ $items[ $i ], $items[ $j ] ] = [ $items[ $j ], $items[ $i ] ];
		}
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
