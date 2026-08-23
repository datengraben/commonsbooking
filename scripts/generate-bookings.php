<?php
/**
 * CommonsBooking booking data generator (scalable seed script).
 *
 * Creates N bookings together with the related object state they need to be
 * *valid* in CommonsBooking's own sense (see \CommonsBooking\Model\Booking::isValid):
 *   - a published location
 *   - a published item
 *   - a bookable timeframe covering item + location + the booking's start day
 *   - non-overlapping, start <= end
 *
 * It is meant to be a plain PHP file that any developer in the repo can read,
 * tweak and run. It reuses the codebase as much as possible:
 *   - fixtures and the "wp" backend reuse the test factory in
 *     tests/php/CPTCreationTrait.php (the same wp_insert_post + update_post_meta
 *     path the unit tests use), so generated objects match what the plugin expects.
 *   - the fast "sql" backend does not re-describe the meta blueprint by hand:
 *     it creates ONE template booking through that same factory, reads the real
 *     postmeta back, and bulk-inserts clones of it. Blueprint = codebase,
 *     throughput = raw SQL.
 *
 * ---------------------------------------------------------------------------
 * USAGE
 * ---------------------------------------------------------------------------
 * With wp-env (this repo's default dev environment):
 *
 *   npm run env -- run cli -- \
 *     php wp-content/plugins/commonsbooking/scripts/generate-bookings.php --count=10
 *
 * With WP-CLI directly (self-bootstraps WordPress by walking up to wp-load.php):
 *
 *   php scripts/generate-bookings.php --count=100 --backend=sql --verify
 *
 * Or via `wp eval-file` if you prefer WP-CLI to bootstrap:
 *
 *   wp eval-file scripts/generate-bookings.php --count=100
 *
 * ---------------------------------------------------------------------------
 * OPTIONS
 * ---------------------------------------------------------------------------
 *   --count=N        How many bookings to create. Default: 1
 *   --backend=wp|sql Write path. "wp" = test factory (valid, fires hooks, slow).
 *                    "sql" = bulk insert derived from a template (fast).
 *                    Default: wp
 *   --verify         Load a sample of created bookings as Model\Booking and
 *                    assert isValid(). Recommended for small N.
 *   --author=ID      Post author user id. Default: 1
 *   --batch=N        SQL backend: rows per bulk INSERT. Default: 2000
 *   --cleanup        Delete everything this generator ever created (all runs),
 *                    then exit. Matched via the `_cb_datagen` marker meta.
 *   --help           Show this help.
 *
 * ---------------------------------------------------------------------------
 * SUGGESTED SCALING WALK (matches the "measure before 1000" plan)
 * ---------------------------------------------------------------------------
 *   php scripts/generate-bookings.php --count=1   --verify
 *   php scripts/generate-bookings.php --count=10  --verify
 *   php scripts/generate-bookings.php --count=100 --verify
 *   # compare backends at 100 before deciding to go bigger:
 *   php scripts/generate-bookings.php --count=100 --backend=wp
 *   php scripts/generate-bookings.php --count=100 --backend=sql --verify
 *   # then, once the numbers look right:
 *   php scripts/generate-bookings.php --count=1000   --backend=sql --verify
 *   php scripts/generate-bookings.php --count=100000 --backend=sql --verify
 *
 * @package CommonsBooking
 */

// ---------------------------------------------------------------------------
// 0. CLI guard + argument parsing
// ---------------------------------------------------------------------------

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 403 );
	exit( "This script is a CLI data generator and must not be run over the web.\n" );
}

/**
 * Tiny argv parser supporting --key=value and boolean --flag.
 * Works both when run standalone and via `wp eval-file` (which passes args in $argv).
 */
function cbgen_parse_args( array $argv ): array {
	$args = [];
	foreach ( $argv as $token ) {
		if ( strpos( $token, '--' ) !== 0 ) {
			continue;
		}
		$token = substr( $token, 2 );
		if ( strpos( $token, '=' ) !== false ) {
			[ $k, $v ]  = explode( '=', $token, 2 );
			$args[ $k ] = $v;
		} else {
			$args[ $token ] = true;
		}
	}
	return $args;
}

$cbgen_args = cbgen_parse_args( $argv ?? [] );

if ( isset( $cbgen_args['help'] ) ) {
	// Print the doc block at the top of this file.
	$src = file_get_contents( __FILE__ );
	if ( preg_match( '#/\*\*(.*?)\*/#s', $src, $m ) ) {
		echo preg_replace( '/^\s*\*?/m', '', $m[1] ), "\n";
	}
	exit( 0 );
}

$cbgen_count   = max( 0, (int) ( $cbgen_args['count'] ?? 1 ) );
$cbgen_backend = in_array( ( $cbgen_args['backend'] ?? 'wp' ), [ 'wp', 'sql' ], true )
	? $cbgen_args['backend']
	: 'wp';
$cbgen_author  = (int) ( $cbgen_args['author'] ?? 1 );
$cbgen_batch   = max( 1, (int) ( $cbgen_args['batch'] ?? 2000 ) );
$cbgen_verify  = isset( $cbgen_args['verify'] );
$cbgen_cleanup = isset( $cbgen_args['cleanup'] );

// ---------------------------------------------------------------------------
// 1. Bootstrap WordPress
// ---------------------------------------------------------------------------

if ( ! defined( 'ABSPATH' ) ) {
	// Not already inside WP (e.g. run as a standalone `php` script). Locate wp-load.php
	// by walking up from this file, or honour an explicit WP_ROOT override.
	$wp_load = null;
	$root    = getenv( 'WP_ROOT' );
	if ( $root && file_exists( rtrim( $root, '/' ) . '/wp-load.php' ) ) {
		$wp_load = rtrim( $root, '/' ) . '/wp-load.php';
	} else {
		$dir = __DIR__;
		while ( $dir && $dir !== dirname( $dir ) ) {
			if ( file_exists( $dir . '/wp-load.php' ) ) {
				$wp_load = $dir . '/wp-load.php';
				break;
			}
			$dir = dirname( $dir );
		}
	}
	if ( ! $wp_load ) {
		fwrite( STDERR, "Could not locate wp-load.php. Set WP_ROOT=/path/to/wordpress or run via wp-env / wp eval-file.\n" );
		exit( 1 );
	}
	// WP-CLI-ish constants so wp-load boots cleanly for a CLI request.
	if ( ! defined( 'WP_USE_THEMES' ) ) {
		define( 'WP_USE_THEMES', false );
	}
	require $wp_load;
}

if ( ! class_exists( \CommonsBooking\Wordpress\CustomPostType\Booking::class ) ) {
	fwrite( STDERR, "CommonsBooking is not loaded/active in this WordPress install.\n" );
	exit( 1 );
}

// ---------------------------------------------------------------------------
// 2. Generator
// ---------------------------------------------------------------------------

use CommonsBooking\Model\Booking as BookingModel;
use CommonsBooking\Model\Timeframe as TimeframeMeta;
use CommonsBooking\Wordpress\CustomPostType\Booking as BookingCPT;
use CommonsBooking\Wordpress\CustomPostType\Item as ItemCPT;
use CommonsBooking\Wordpress\CustomPostType\Location as LocationCPT;
use CommonsBooking\Wordpress\CustomPostType\Timeframe as TimeframeCPT;

/**
 * Marker meta stamped on every post this script creates, so `--cleanup` can find
 * and remove exactly (and only) generated data.
 */
const CBGEN_MARKER = '_cb_datagen';

/**
 * The generator reuses the plugin's own test factory (CPTCreationTrait) for the
 * "wp" backend and for the SQL template, so we never re-describe meta by hand.
 *
 * The trait's methods are protected and its default argument values reference a
 * PHPUnit-only class; we host it in this tiny class and always pass explicit
 * arguments so that PHPUnit class is never touched at runtime.
 */
final class CBGenFactory {
	use \CommonsBooking\Tests\CPTCreationTrait;

	/** @var int */
	public $author;

	/** Base day (midnight) that booking #0 sits on. */
	private int $baseDay;

	public function __construct( int $author, int $baseDay ) {
		$this->author = $author;
		$this->baseDay = $baseDay;
	}

	/** Stamp the generator marker on a post. */
	private function mark( int $postId ): void {
		update_post_meta( $postId, CBGEN_MARKER, 1 );
	}

	/**
	 * Create (once) the shared, valid related object state: one location, one item
	 * and one bookable timeframe wide enough to cover every booking day we will use.
	 *
	 * @return array{location:int,item:int,timeframe:int}
	 */
	public function createFixtures( int $count ): array {
		$location = $this->createLocation( 'CBGen Location', 'publish', [], $this->author );
		$item     = $this->createItem( 'CBGen Item', 'publish', [], $this->author );

		// Timeframe must cover booking days [base .. base + count]. Give it slack
		// on both ends. Full-day, weekly, all weekdays => every day in range is bookable.
		$tfStart = strtotime( '-1 day', $this->baseDay );
		$tfEnd   = strtotime( '+' . ( $count + 2 ) . ' days', $this->baseDay );

		$timeframe = $this->createTimeframe(
			$location,
			$item,
			$tfStart,
			$tfEnd,
			TimeframeCPT::BOOKABLE_ID,
			'on',                                   // full day
			'w',                                    // weekly repetition
			0,                                      // grid
			'8:00 AM',
			'12:00 PM',
			'publish',
			[ '1', '2', '3', '4', '5', '6', '7' ], // all weekdays
			'',
			$this->author
		);

		foreach ( [ $location, $item, $timeframe ] as $id ) {
			$this->mark( $id );
		}

		return [
			'location'  => $location,
			'item'      => $item,
			'timeframe' => $timeframe,
		];
	}

	/**
	 * Full-day booking window for booking index $i: [base+i 00:00 .. base+i 23:59:59].
	 * Distinct day per index => guaranteed non-overlapping => valid by construction.
	 *
	 * @return array{start:int,end:int}
	 */
	public function windowForIndex( int $i ): array {
		$start = strtotime( "+$i days", $this->baseDay );
		$end   = strtotime( '+1 day midnight', $start ) - 1;
		return [ 'start' => $start, 'end' => $end ];
	}

	/**
	 * "wp" backend: create one booking through the test factory (wp_insert_post +
	 * update_post_meta). Correct and hook-firing, but ~14 queries per booking.
	 */
	public function createBookingWp( int $location, int $item, int $i ): int {
		$w  = $this->windowForIndex( $i );
		$id = $this->createBooking(
			$location,
			$item,
			$w['start'],
			$w['end'],
			'12:00 AM',
			'23:59',
			'confirmed',
			$this->author,
			'w',
			3,
			'CBGen Booking ' . $i
		);
		$this->mark( $id );
		return $id;
	}

	/**
	 * Build a single template booking via the factory and return its full postmeta
	 * map (unserialized values). The SQL backend clones this so its blueprint is
	 * literally the codebase's own output rather than a hand-written list.
	 *
	 * @return array{id:int,meta:array<string,mixed>}
	 */
	public function buildTemplateBooking( int $location, int $item ): array {
		$id   = $this->createBookingWp( $location, $item, 0 );
		$raw  = get_post_meta( $id ); // [ key => [ value, ... ] ]
		$meta = [];
		foreach ( $raw as $key => $values ) {
			$meta[ $key ] = maybe_unserialize( $values[0] );
		}
		return [ 'id' => $id, 'meta' => $meta ];
	}
}

// ---------------------------------------------------------------------------
// 3. Cleanup mode
// ---------------------------------------------------------------------------

/** Delete every post carrying the generator marker, across all CB post types. */
function cbgen_cleanup(): int {
	global $wpdb;
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
			CBGEN_MARKER
		)
	);
	$deleted = 0;
	foreach ( $ids as $id ) {
		if ( wp_delete_post( (int) $id, true ) ) {
			$deleted++;
		}
	}
	wp_cache_flush();
	return $deleted;
}

if ( $cbgen_cleanup ) {
	$t0 = microtime( true );
	$n  = cbgen_cleanup();
	printf( "Cleanup: deleted %d generated post(s) in %.2fs.\n", $n, microtime( true ) - $t0 );
	exit( 0 );
}

// ---------------------------------------------------------------------------
// 4. Sanity: the test factory (our reuse target) must be autoloadable
// ---------------------------------------------------------------------------

if ( ! trait_exists( \CommonsBooking\Tests\CPTCreationTrait::class ) ) {
	fwrite(
		STDERR,
		"The test factory CommonsBooking\\Tests\\CPTCreationTrait is not autoloadable.\n" .
		"Run `composer install` (with dev dependencies) in the plugin directory first.\n"
	);
	exit( 1 );
}

// ---------------------------------------------------------------------------
// 5. Generate
// ---------------------------------------------------------------------------

global $wpdb;

$baseDay = strtotime( 'today midnight' );
$factory = new CBGenFactory( $cbgen_author, $baseDay );

echo str_repeat( '=', 68 ), "\n";
printf( "CommonsBooking booking generator  |  count=%d  backend=%s\n", $cbgen_count, $cbgen_backend );
echo str_repeat( '=', 68 ), "\n";

// 5a. Fixtures (shared valid related object state).
$t_fix = microtime( true );
$fx    = $factory->createFixtures( $cbgen_count );
printf(
	"Fixtures: location #%d, item #%d, bookable timeframe #%d  (%.3fs)\n",
	$fx['location'],
	$fx['item'],
	$fx['timeframe'],
	microtime( true ) - $t_fix
);

$createdIds = [];

if ( $cbgen_count > 0 && $cbgen_backend === 'wp' ) {
	// -------- WP backend: reuse the factory per booking --------
	$t0 = microtime( true );
	for ( $i = 0; $i < $cbgen_count; $i++ ) {
		$createdIds[] = $factory->createBookingWp( $fx['location'], $fx['item'], $i );
	}
	$elapsed = microtime( true ) - $t0;
	printf(
		"WP backend: created %d booking(s) in %.3fs  (%.1f/s, %.2f ms each)\n",
		$cbgen_count,
		$elapsed,
		$cbgen_count / max( $elapsed, 1e-9 ),
		( $elapsed / $cbgen_count ) * 1000
	);
} elseif ( $cbgen_count > 0 && $cbgen_backend === 'sql' ) {
	// -------- SQL backend: build one template, bulk-insert clones --------

	// Template booking #0 goes through the real factory (so it is also a real,
	// valid booking) and gives us the exact meta blueprint.
	$t_tpl = microtime( true );
	$tpl   = $factory->buildTemplateBooking( $fx['location'], $fx['item'] );
	$createdIds[] = $tpl['id'];
	printf( "Template booking #%d built via factory  (%.3fs)\n", $tpl['id'], microtime( true ) - $t_tpl );

	// Remaining N-1 bookings are bulk-inserted clones, one per subsequent day.
	$remaining = $cbgen_count - 1;
	$t0        = microtime( true );
	$now       = current_time( 'mysql' );
	$nowGmt    = current_time( 'mysql', true );

	for ( $offset = 0; $offset < $remaining; $offset += $cbgen_batch ) {
		$chunk = min( $cbgen_batch, $remaining - $offset );

		// --- 5b. bulk INSERT into wp_posts ---
		$rows   = [];
		$params = [];
		for ( $j = 0; $j < $chunk; $j++ ) {
			$index   = $offset + $j + 1;                 // +1: template already used index 0
			$title   = 'CBGen Booking ' . $index;
			$rows[]  = '(%d,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%d,%s,%d,%s,%s,%d)';
			array_push(
				$params,
				$cbgen_author,   // post_author
				$now,            // post_date
				$nowGmt,         // post_date_gmt
				'',              // post_content
				$title,          // post_title
				'',              // post_excerpt
				'confirmed',     // post_status
				'closed',        // comment_status
				'closed',        // ping_status
				'',              // post_password
				'',              // post_name (WP will not backfill; fine for test data)
				'',              // to_ping
				'',              // pinged
				$now,            // post_modified
				$nowGmt,         // post_modified_gmt
				'',              // post_content_filtered
				0,               // post_parent
				'',              // guid
				0,               // menu_order
				BookingCPT::$postType, // post_type
				'',              // post_mime_type
				0                // comment_count
			);
		}
		$sql = "INSERT INTO {$wpdb->posts}
			(post_author,post_date,post_date_gmt,post_content,post_title,post_excerpt,
			 post_status,comment_status,ping_status,post_password,post_name,to_ping,pinged,
			 post_modified,post_modified_gmt,post_content_filtered,post_parent,guid,menu_order,
			 post_type,post_mime_type,comment_count)
			VALUES " . implode( ',', $rows );

		$wpdb->query( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$firstId = (int) $wpdb->insert_id;

		// A single multi-row INSERT yields consecutive auto-increment ids
		// (InnoDB, default innodb_autoinc_lock_mode). Map index -> new post id.
		$idsThisChunk = range( $firstId, $firstId + $chunk - 1 );

		// --- 5c. bulk INSERT into wp_postmeta (clone template + per-row overrides) ---
		$metaRows   = [];
		$metaParams = [];
		foreach ( $idsThisChunk as $k => $postId ) {
			$index  = $offset + $k + 1;
			$window = $factory->windowForIndex( $index );

			// Start from the template meta, override only what must differ per booking.
			$meta                                = $tpl['meta'];
			$meta[ TimeframeMeta::REPETITION_START ] = $window['start'];
			$meta[ TimeframeMeta::REPETITION_END ]   = $window['end'];
			$meta[ CBGEN_MARKER ]                    = 1;

			foreach ( $meta as $key => $value ) {
				$metaRows[]   = '(%d,%s,%s)';
				$metaParams[] = $postId;
				$metaParams[] = $key;
				$metaParams[] = maybe_serialize( $value );
			}
			$createdIds[] = $postId;
		}

		// Insert postmeta in sub-batches to keep single statements a sane size.
		$metaChunkRows = 5000; // meta rows per statement
		$total         = count( $metaRows );
		for ( $m = 0; $m < $total; $m += $metaChunkRows ) {
			$sliceRows   = array_slice( $metaRows, $m, $metaChunkRows );
			$sliceParams = array_slice( $metaParams, $m * 3, $metaChunkRows * 3 );
			$metaSql     = "INSERT INTO {$wpdb->postmeta} (post_id,meta_key,meta_value) VALUES "
				. implode( ',', $sliceRows );
			$wpdb->query( $wpdb->prepare( $metaSql, $sliceParams ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
	}

	// Bulk writes bypass the object cache; drop it so subsequent reads see the data.
	wp_cache_flush();

	$elapsed = microtime( true ) - $t0;
	$rate    = $remaining > 0 ? $remaining / max( $elapsed, 1e-9 ) : 0;
	printf(
		"SQL backend: bulk-inserted %d booking(s) in %.3fs  (%.1f/s, %.3f ms each)\n",
		$remaining,
		$elapsed,
		$rate,
		$remaining > 0 ? ( $elapsed / $remaining ) * 1000 : 0
	);
}

// ---------------------------------------------------------------------------
// 6. Verify a sample
// ---------------------------------------------------------------------------

if ( $cbgen_verify && $createdIds ) {
	$sample = array_slice( $createdIds, 0, min( 20, count( $createdIds ) ) );
	// Include the last one too, so we check both ends of the range.
	if ( count( $createdIds ) > 1 ) {
		$sample[] = end( $createdIds );
	}
	$ok   = 0;
	$fail = [];
	foreach ( $sample as $id ) {
		try {
			$booking = new BookingModel( (int) $id );
			if ( $booking->isValid() ) {
				$ok++;
			} else {
				$fail[ $id ] = 'isValid() returned false';
			}
		} catch ( \Throwable $e ) {
			$fail[ $id ] = $e->getMessage();
		}
	}
	printf( "Verify: %d/%d sampled booking(s) valid.\n", $ok, count( $sample ) );
	foreach ( $fail as $id => $why ) {
		printf( "  - booking #%d INVALID: %s\n", $id, $why );
	}
}

// ---------------------------------------------------------------------------
// 7. Summary
// ---------------------------------------------------------------------------

$total = count( $createdIds );
echo str_repeat( '-', 68 ), "\n";
printf( "Done. %d booking(s) created.\n", $total );
echo "Remove all generated data with:  --cleanup\n";
