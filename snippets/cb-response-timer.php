<?php
/**
 * Plugin Name: CommonsBooking – Response Timer
 * Description: Drop-in diagnostic snippet. Measures end-to-end response duration,
 *               cache hit/miss counts and a URL-derived workload route for every
 *               200-status response that goes through CommonsBooking (frontend CPT
 *               pages, admin-ajax `cb_*` actions, `commonsbooking/v*` REST routes).
 *               Writes one NDJSON line per measurement, suitable for load-test analysis.
 *
 * Install: copy this file into wp-content/mu-plugins/ (or `require` it from a
 *          plugin/theme). No further setup needed - it self-registers on load.
 *
 * Note: CommonsBooking's cache layer short-circuits to "miss" whenever WP_DEBUG is
 *       on (see Cache::getCacheItem()), so cache stats are only meaningful with
 *       WP_DEBUG off.
 *
 * Extend:
 *   - filter `cb_response_timer_is_relevant` to change which requests are measured.
 *   - filter `cb_response_timer_log_file`    to change the NDJSON log destination
 *                                             (default: wp-content/cb-response-timer.log).
 *   - action `cb_response_timer_measured`    to change where/how a measurement is
 *                                             recorded instead (receives the data array).
 */

defined( 'ABSPATH' ) || die;

class CB_Response_Timer {

	/** @var float Request start, set as early as PHP itself provides it. */
	private static $start;

	private static int $cache_hits = 0;
	private static int $cache_misses = 0;

	public static function init() {
		self::$start = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true );

		add_action( 'commonsbooking_cache_hit', array( __CLASS__, 'record_cache_hit' ) );
		add_action( 'commonsbooking_cache_miss', array( __CLASS__, 'record_cache_miss' ) );
		add_action( 'shutdown', array( __CLASS__, 'maybe_log' ) );
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

	protected static function current_url(): string {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host   = $_SERVER['HTTP_HOST'] ?? '';
		$uri    = $_SERVER['REQUEST_URI'] ?? '';
		return $scheme . $host . $uri;
	}
}

CB_Response_Timer::init();

// Default sink: append one NDJSON line per measurement to a dedicated log file
// (kept out of the shared PHP error log to avoid noise/contention under load).
// Replace/remove this listener (or add your own) to send data elsewhere.
add_action(
	'cb_response_timer_measured',
	function ( array $data ) {
		$log_file = apply_filters( 'cb_response_timer_log_file', WP_CONTENT_DIR . '/cb-response-timer.log' );
		error_log( wp_json_encode( $data ) . "\n", 3, $log_file );
	}
);
