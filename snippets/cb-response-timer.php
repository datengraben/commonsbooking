<?php
/**
 * Plugin Name: CommonsBooking – Response Timer
 * Description: Drop-in diagnostic snippet. Measures end-to-end response duration,
 *               cache hit/miss counts, DB query cost and a URL-derived workload route
 *               for every 200-status response that goes through CommonsBooking
 *               (frontend CPT pages, admin-ajax `cb_*` actions, `commonsbooking/v*`
 *               REST routes). Rows are queued to a file per request (cheap) and bulk-
 *               inserted into a `{$wpdb->prefix}cb_response_timer` SQL table by a
 *               WP-Cron job, so no request pays for a synchronous DB write.
 *
 * Install: copy this file into wp-content/mu-plugins/ (or `require` it from a
 *          plugin/theme). No further setup needed - it self-registers on load and
 *          creates its table lazily on first `init`.
 *
 * Note: CommonsBooking's cache layer short-circuits to "miss" whenever WP_DEBUG is
 *       on (see Cache::getCacheItem()), so cache stats are only meaningful with
 *       WP_DEBUG off.
 * Note: DB query stats are collected via $wpdb->save_queries, which stores every
 *       query's SQL and a backtrace for the request - real overhead, intended for
 *       load-test/staging use, not left running against full production traffic.
 *       Disable it with the `cb_response_timer_track_queries` filter if needed.
 * Note: the flush relies on WP-Cron's normal pseudo-cron (a non-blocking loopback
 *       request triggered by site traffic). If `DISABLE_WP_CRON` is set with no real
 *       system cron configured, the queue file will grow until one is set up.
 *
 * Extend:
 *   - filter `cb_response_timer_is_relevant`      to change which requests are measured.
 *   - filter `cb_response_timer_payload_denylist`  to change which $_REQUEST keys are
 *                                                   stripped from the captured payload.
 *   - filter `cb_response_timer_track_queries`     to turn DB query stats off (default on).
 *   - filter `cb_response_timer_log_file`          to change the queue file location
 *                                                   (default: wp-content/cb-response-timer-queue.log).
 *   - filter `cb_response_timer_flush_interval`    seconds between cron flushes (default 300).
 *   - action `cb_response_timer_measured`          to change where/how a measurement is
 *                                                   recorded instead (receives the full
 *                                                   data array, incl. url/post_type/slowest_sql
 *                                                   which the default SQL sink drops).
 */

defined( 'ABSPATH' ) || die;

class CB_Response_Timer {

	const DB_VERSION     = '1.0';
	const TABLE          = 'cb_response_timer';
	const FLUSH_HOOK     = 'cb_response_timer_flush';
	const FLUSH_SCHEDULE = 'cb_response_timer_interval';

	/** @var float Request start, set as early as PHP itself provides it. */
	private static $start;

	private static int $cache_hits = 0;
	private static int $cache_misses = 0;

	public static function init() {
		self::$start = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true );

		if ( apply_filters( 'cb_response_timer_track_queries', true ) ) {
			global $wpdb;
			$wpdb->save_queries = true;
		}

		add_action( 'commonsbooking_cache_hit', array( __CLASS__, 'record_cache_hit' ) );
		add_action( 'commonsbooking_cache_miss', array( __CLASS__, 'record_cache_miss' ) );
		add_action( 'shutdown', array( __CLASS__, 'maybe_log' ) );

		add_filter( 'cron_schedules', array( __CLASS__, 'register_cron_schedule' ) );
		add_action( self::FLUSH_HOOK, array( __CLASS__, 'flush_queue' ) );

		add_action( 'init', array( __CLASS__, 'maybe_create_table' ) );
		add_action( 'init', array( __CLASS__, 'maybe_schedule_flush' ) );
	}

	public static function record_cache_hit() {
		++self::$cache_hits;
	}

	public static function record_cache_miss() {
		++self::$cache_misses;
	}

	/**
	 * Runs on shutdown, i.e. after the response body/status has been decided,
	 * so http_response_code() and get_post_type() reflect the final state.
	 */
	public static function maybe_log() {
		if ( http_response_code() !== 200 || ! self::is_relevant() ) {
			return;
		}

		do_action(
			'cb_response_timer_measured',
			array(
				'time'        => gmdate( 'c' ),
				'url'         => self::current_url(),
				'route'       => self::derive_route(),
				'post_type'   => get_post_type() ?: null,
				'cache'       => array(
					'hits'   => self::$cache_hits,
					'misses' => self::$cache_misses,
				),
				'payload'     => self::derive_payload(),
				'db'          => self::derive_db_stats(),
				'duration_ms' => round( ( microtime( true ) - self::$start ) * 1000, 1 ),
			)
		);
	}

	/**
	 * Whether the current request touches CommonsBooking: a `cb_*` ajax action,
	 * a `commonsbooking/v*` REST route, or a page whose queried post type belongs
	 * to the plugin. Override entirely via the `cb_response_timer_is_relevant` filter.
	 */
	protected static function is_relevant(): bool {
		$relevant = 'other' !== self::derive_route();

		return (bool) apply_filters( 'cb_response_timer_is_relevant', $relevant );
	}

	/**
	 * Groups the current request into a workload/route label derived purely from the
	 * URL and query state - no custom header required. Examples:
	 *   ajax:cb_calendar_data
	 *   rest:/commonsbooking/v1/items/:id
	 *   cb_item:single
	 *   cb_location:archive
	 *   other
	 */
	protected static function derive_route(): string {
		$action = $_REQUEST['action'] ?? '';
		if ( is_string( $action ) && str_starts_with( $action, 'cb_' ) ) {
			return 'ajax:' . $action;
		}

		$uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( preg_match( '#/wp-json(/commonsbooking/v[^/?]+.*?)(?:\?|$)#', $uri, $matches )
			|| preg_match( '#[?&]rest_route=(/commonsbooking/v[^&]+)#', $uri, $matches )
		) {
			$path = preg_replace( '#/\d+(?=/|$)#', '/:id', rawurldecode( $matches[1] ) );
			return 'rest:' . $path;
		}

		$post_type = get_post_type();
		if ( in_array( $post_type, self::post_types(), true ) ) {
			$context = is_singular() ? 'single' : ( is_post_type_archive() ? 'archive' : 'other' );
			return $post_type . ':' . $context;
		}

		return 'other';
	}

	protected static function post_types(): array {
		return array( 'cb_item', 'cb_location', 'cb_timeframe', 'cb_booking', 'cb_map', 'cb_restriction' );
	}

	/**
	 * Sanitized snapshot of the request params, captured generically - whatever a hot
	 * route's params are (date range, item/location ids, ...) comes along for free,
	 * with no per-route parsing. Array values collapse to a count to bound row size and
	 * avoid leaking list contents; noise/security keys are stripped via a denylist,
	 * overridable through `cb_response_timer_payload_denylist`.
	 */
	protected static function derive_payload(): array {
		$denylist = apply_filters(
			'cb_response_timer_payload_denylist',
			array( 'action', '_wpnonce', 'nonce', '_wp_http_referer', 'apikey', 'rest_route' )
		);

		$payload = array();
		foreach ( $_REQUEST as $key => $value ) {
			if ( in_array( $key, $denylist, true ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$payload[ $key ] = array( '_count' => count( $value ) );
				continue;
			}
			$payload[ $key ] = mb_substr( (string) $value, 0, 200 );
		}

		return $payload;
	}

	/**
	 * Aggregate DB cost for the request, sourced from $wpdb->queries (populated because
	 * init() turns save_queries on). Each entry is [sql, exec_time_seconds, caller, ...].
	 * The slowest query's SQL is normalized (literals stripped) so rows are groupable by
	 * query shape and don't leak literal data values into the log.
	 */
	protected static function derive_db_stats(): ?array {
		global $wpdb;

		if ( empty( $wpdb->save_queries ) || empty( $wpdb->queries ) ) {
			return null;
		}

		$times   = array_column( $wpdb->queries, 1 );
		$slowest = $wpdb->queries[ array_search( max( $times ), $times, true ) ];

		return array(
			'query_count' => count( $wpdb->queries ),
			'time_ms'     => round( array_sum( $times ) * 1000, 1 ),
			'slowest_ms'  => round( $slowest[1] * 1000, 1 ),
			'slowest_sql' => self::normalize_sql( $slowest[0] ),
		);
	}

	protected static function normalize_sql( string $sql ): string {
		$sql = preg_replace( "/'[^']*'/", "'?'", $sql );
		return preg_replace( '/\b\d+\b/', '?', $sql );
	}

	protected static function current_url(): string {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host   = $_SERVER['HTTP_HOST'] ?? '';
		$uri    = $_SERVER['REQUEST_URI'] ?? '';
		return $scheme . $host . $uri;
	}

	public static function queue_file(): string {
		return apply_filters( 'cb_response_timer_log_file', WP_CONTENT_DIR . '/cb-response-timer-queue.log' );
	}

	/**
	 * Creates {$wpdb->prefix}cb_response_timer via dbDelta(), same pattern as
	 * BookingCodes::initBookingCodesTable() (src/Repository/BookingCodes.php). Runs
	 * lazily on `init` since a drop-in mu-plugin has no activation hook; guarded by a
	 * versioned option so it's a single get_option() call on every request after the
	 * first successful run.
	 */
	public static function maybe_create_table() {
		if ( get_option( 'cb_response_timer_db_version' ) === self::DB_VERSION ) {
			return;
		}

		global $wpdb;
		$table           = $wpdb->prefix . self::TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			measured_at DATETIME NOT NULL,
			route VARCHAR(191) NOT NULL,
			duration_ms FLOAT NOT NULL,
			cache_hits SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			cache_misses SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			db_query_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			db_time_ms FLOAT NOT NULL DEFAULT 0,
			payload TEXT NULL,
			PRIMARY KEY  (id),
			KEY route_measured_at (route, measured_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'cb_response_timer_db_version', self::DB_VERSION );
	}

	public static function register_cron_schedule( array $schedules ): array {
		$schedules[ self::FLUSH_SCHEDULE ] = array(
			'interval' => apply_filters( 'cb_response_timer_flush_interval', 300 ),
			'display'  => 'CB Response Timer flush',
		);

		return $schedules;
	}

	public static function maybe_schedule_flush() {
		if ( ! wp_next_scheduled( self::FLUSH_HOOK ) ) {
			wp_schedule_event( time(), self::FLUSH_SCHEDULE, self::FLUSH_HOOK );
		}
	}

	/**
	 * Flushes the queue into the SQL table. Rotates the live queue file out of the way
	 * first (rename() is atomic on POSIX filesystems, so concurrent per-request writers
	 * safely resume onto a fresh file - error_log()'s mode-3 append recreates it as
	 * needed). Also sweeps up any `.processing.*` file left behind by a previous flush
	 * that rotated but failed before its insert completed, so nothing is silently lost.
	 */
	public static function flush_queue() {
		$queue = self::queue_file();

		if ( file_exists( $queue ) ) {
			$processing = $queue . '.processing.' . uniqid();
			if ( rename( $queue, $processing ) ) {
				self::process_file( $processing );
			}
		}

		foreach ( glob( $queue . '.processing.*' ) ?: array() as $leftover ) {
			self::process_file( $leftover );
		}
	}

	protected static function process_file( string $file ) {
		$lines = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( ! $lines ) {
			unlink( $file );
			return;
		}

		$rows = array();
		foreach ( $lines as $line ) {
			$row = json_decode( $line );
			if ( $row !== null ) {
				$rows[] = $row;
			}
		}

		if ( ! self::bulk_insert( $rows ) ) {
			return; // Leave the file for the next flush to retry.
		}

		unlink( $file );
	}

	/**
	 * Bulk-inserts rows in chunks (wpdb has no native multi-row insert, so the query is
	 * built by repeating a placeholder group per row and flattening the args).
	 */
	protected static function bulk_insert( array $rows ): bool {
		if ( empty( $rows ) ) {
			return true;
		}

		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		$ok    = true;

		foreach ( array_chunk( $rows, 500 ) as $chunk ) {
			$placeholders = array();
			$args         = array();

			foreach ( $chunk as $row ) {
				$placeholders[] = '(%s, %s, %f, %d, %d, %d, %f, %s)';
				$measured_at    = ! empty( $row->time ) ? gmdate( 'Y-m-d H:i:s', strtotime( $row->time ) ) : gmdate( 'Y-m-d H:i:s' );
				array_push(
					$args,
					$measured_at,
					$row->route ?? 'other',
					$row->duration_ms ?? 0,
					$row->cache->hits ?? 0,
					$row->cache->misses ?? 0,
					$row->db->query_count ?? 0,
					$row->db->time_ms ?? 0,
					wp_json_encode( $row->payload ?? array() )
				);
			}

			$sql = "INSERT INTO $table (measured_at, route, duration_ms, cache_hits, cache_misses, db_query_count, db_time_ms, payload) VALUES "
				. implode( ', ', $placeholders );

			if ( false === $wpdb->query( $wpdb->prepare( $sql, $args ) ) ) {
				$ok = false;
			}
		}

		return $ok;
	}
}

CB_Response_Timer::init();

// Default sink: append one trimmed JSON line per measurement to the queue file, which
// a WP-Cron job (see flush_queue()) bulk-inserts into the SQL table. This keeps the
// per-request cost to a single small error_log() call - no synchronous DB write.
// Replace/remove this listener (or add your own) to send data elsewhere.
add_action(
	'cb_response_timer_measured',
	function ( array $data ) {
		$row = array(
			'time'        => $data['time'],
			'route'       => $data['route'],
			'duration_ms' => $data['duration_ms'],
			'cache'       => $data['cache'],
			'db'          => array(
				'query_count' => $data['db']['query_count'] ?? 0,
				'time_ms'     => $data['db']['time_ms'] ?? 0,
			),
			'payload'     => $data['payload'],
		);
		error_log( wp_json_encode( $row ) . "\n", 3, CB_Response_Timer::queue_file() );
	}
);
