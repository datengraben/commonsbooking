<?php
/**
 * Plugin Name: CommonsBooking – Response Timer
 * Description: Drop-in diagnostic snippet, DB-only, zero setup. Measures end-to-end
 *               response duration, cache hit/miss counts, DB query cost, a redacted
 *               client IP and user agent, and a URL-derived workload route for every
 *               200-status response that goes through CommonsBooking (frontend CPT
 *               pages, admin-ajax `cb_*` actions, `commonsbooking/v*` REST routes).
 *               Each measured request writes one row directly to a dedicated table -
 *               no file, no cron, no queue.
 *
 * Install: copy this file into wp-content/mu-plugins/ (or `require` it from a
 *          plugin/theme). Nothing else to configure - it creates its own table lazily
 *          on first `init` and auto-generates an export secret on first use.
 *
 * Export: GET /cb-response-timer/{secret}/export.csv streams the raw table as CSV.
 *         Matched directly against the request path (no rewrite rule, so nothing to
 *         flush/configure). The secret is a plugin variable: define it yourself with
 *         `define('CB_RESPONSE_TIMER_SECRET', '...')` in wp-config.php, or leave it
 *         unset and read the auto-generated one via
 *         `get_option('cb_response_timer_export_secret')`.
 *
 * Note: writes are synchronous (one $wpdb->insert() per measured request) - simple and
 *       always-on, but real DB cost per request. If that becomes a bottleneck under
 *       heavy load-test volume, batch/defer it yourself via the
 *       `cb_response_timer_measured` action instead of using the default listener.
 * Note: CommonsBooking's cache layer short-circuits to "miss" whenever WP_DEBUG is on
 *       (see Cache::getCacheItem()), so cache stats are only meaningful with WP_DEBUG off.
 * Note: DB query stats are collected via $wpdb->save_queries, which stores every query's
 *       SQL and a backtrace for the request - real overhead, intended for load-test/
 *       staging use. Disable via the `cb_response_timer_track_queries` filter.
 * Note: the client IP is read from $_SERVER['REMOTE_ADDR'] and stored with its last
 *       octet (IPv4) / last colon-group (IPv6) zeroed out before it ever reaches the
 *       table - the raw IP is never persisted.
 *
 * Extend:
 *   - filter `cb_response_timer_is_relevant`     to change which requests are measured.
 *   - filter `cb_response_timer_payload_denylist` to change which $_REQUEST keys are
 *                                                  stripped from the captured payload.
 *   - filter `cb_response_timer_track_queries`    to turn DB query stats off (default on).
 *   - filter `cb_response_timer_client_ip`        to change how the client IP is sourced
 *                                                  (default: $_SERVER['REMOTE_ADDR']).
 *   - filter `cb_response_timer_export_secret`    to override the export secret.
 *   - action `cb_response_timer_measured`         to change where/how a measurement is
 *                                                  recorded instead (receives the full
 *                                                  data array, incl. url/post_type/
 *                                                  slowest_sql which the default SQL
 *                                                  sink drops).
 */

defined( 'ABSPATH' ) || die;

class CB_Response_Timer {

	const DB_VERSION   = '2.0';
	const TABLE        = 'cb_response_timer';
	const EXPORT_REGEX = '#^/cb-response-timer/([^/?]+)/export\.csv#';

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

		add_action( 'init', array( __CLASS__, 'maybe_create_table' ) );
		add_action( 'init', array( __CLASS__, 'maybe_handle_export' ) );
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
				'ip'          => self::redacted_ip(),
				'user_agent'  => mb_substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 255 ),
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
	 * The slowest query's SQL is normalized (literals stripped) so it's groupable by
	 * query shape and doesn't leak literal data values.
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

	/**
	 * Client IP with its last octet (IPv4) / last colon-group (IPv6) zeroed out before
	 * it's ever used elsewhere - only the redacted form is computed and stored, the raw
	 * IP is never persisted. Source is filterable for setups behind a trusted proxy.
	 */
	protected static function redacted_ip(): string {
		$ip = (string) apply_filters( 'cb_response_timer_client_ip', $_SERVER['REMOTE_ADDR'] ?? '' );

		if ( str_contains( $ip, ':' ) ) {
			$parts                        = explode( ':', $ip );
			$parts[ count( $parts ) - 1 ] = '0';
			return implode( ':', $parts );
		}

		$parts = explode( '.', $ip );
		if ( count( $parts ) === 4 ) {
			$parts[3] = '0';
			return implode( '.', $parts );
		}

		return $ip;
	}

	protected static function current_url(): string {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host   = $_SERVER['HTTP_HOST'] ?? '';
		$uri    = $_SERVER['REQUEST_URI'] ?? '';
		return $scheme . $host . $uri;
	}

	/**
	 * Creates {$wpdb->prefix}cb_response_timer via dbDelta(), same pattern as
	 * BookingCodes::initBookingCodesTable() (src/Repository/BookingCodes.php). Runs
	 * lazily on `init` since a drop-in mu-plugin has no activation hook; guarded by a
	 * versioned option so it's a single get_option() call on every request after the
	 * first successful (or schema-upgrading) run.
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
			ip VARCHAR(45) NULL,
			user_agent VARCHAR(255) NULL,
			payload TEXT NULL,
			PRIMARY KEY  (id),
			KEY route_measured_at (route, measured_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'cb_response_timer_db_version', self::DB_VERSION );
	}

	/**
	 * The secret path segment for /cb-response-timer/{secret}/export.csv. Prefers the
	 * `CB_RESPONSE_TIMER_SECRET` constant (set it in wp-config.php for a real deployment);
	 * otherwise auto-generates one on first use and persists it as an option, so the
	 * export route works with zero setup. Always overridable via the
	 * `cb_response_timer_export_secret` filter.
	 */
	public static function export_secret(): string {
		$secret = defined( 'CB_RESPONSE_TIMER_SECRET' ) ? CB_RESPONSE_TIMER_SECRET : '';

		if ( ! $secret ) {
			$secret = get_option( 'cb_response_timer_export_secret' );
			if ( ! $secret ) {
				$secret = wp_generate_password( 32, false );
				update_option( 'cb_response_timer_export_secret', $secret, false );
			}
		}

		return (string) apply_filters( 'cb_response_timer_export_secret', $secret );
	}

	/**
	 * Matches the request path directly against EXPORT_REGEX - no rewrite rule
	 * registered, so there's nothing to flush/configure. Falls through to normal WP
	 * routing (404) whenever the path doesn't match or the secret is wrong.
	 */
	public static function maybe_handle_export() {
		$path = (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );

		if ( ! preg_match( self::EXPORT_REGEX, $path, $matches ) ) {
			return;
		}

		if ( ! hash_equals( self::export_secret(), $matches[1] ) ) {
			return;
		}

		self::stream_csv();
		exit;
	}

	/**
	 * Streams the raw table as CSV in chunks (rather than loading the whole table into
	 * memory) so the export stays usable as the table grows.
	 */
	protected static function stream_csv() {
		global $wpdb;
		$table   = $wpdb->prefix . self::TABLE;
		$columns = array( 'id', 'measured_at', 'route', 'duration_ms', 'cache_hits', 'cache_misses', 'db_query_count', 'db_time_ms', 'ip', 'user_agent', 'payload' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="cb-response-timer.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, $columns );

		$chunk_size = 1000;
		$offset     = 0;
		do {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT ' . implode( ', ', $columns ) . " FROM $table ORDER BY id ASC LIMIT %d OFFSET %d",
					$chunk_size,
					$offset
				),
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				fputcsv( $out, $row );
			}
			flush();
			$offset += $chunk_size;
		} while ( count( $rows ) === $chunk_size );

		fclose( $out );
	}
}

CB_Response_Timer::init();

// Default sink: one direct, synchronous insert per measured request. See the file
// header for the tradeoff and how to swap this for a deferred/batched sink instead.
add_action(
	'cb_response_timer_measured',
	function ( array $data ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . CB_Response_Timer::TABLE,
			array(
				'measured_at'    => gmdate( 'Y-m-d H:i:s', strtotime( $data['time'] ) ),
				'route'          => $data['route'],
				'duration_ms'    => $data['duration_ms'],
				'cache_hits'     => $data['cache']['hits'],
				'cache_misses'   => $data['cache']['misses'],
				'db_query_count' => $data['db']['query_count'] ?? 0,
				'db_time_ms'     => $data['db']['time_ms'] ?? 0,
				'ip'             => $data['ip'],
				'user_agent'     => $data['user_agent'],
				'payload'        => wp_json_encode( $data['payload'] ),
			),
			array( '%s', '%s', '%f', '%d', '%d', '%d', '%f', '%s', '%s', '%s' )
		);
	}
);
