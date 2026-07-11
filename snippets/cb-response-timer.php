<?php
/**
 * Plugin Name: CommonsBooking – Response Timer
 * Description: Drop-in diagnostic snippet. Measures end-to-end response duration
 *               for every 200-status HTTPS response that goes through CommonsBooking
 *               (frontend CPT pages, admin-ajax `cb_*` actions, `commonsbooking/v*` REST routes).
 *
 * Install: copy this file into wp-content/mu-plugins/ (or `require` it from a
 *          plugin/theme). No further setup needed - it self-registers on load.
 *
 * Extend:
 *   - filter `cb_response_timer_is_relevant` to change which requests are measured.
 *   - action `cb_response_timer_measured`   to change where/how a measurement is recorded
 *                                            (defaults to error_log()).
 */

defined( 'ABSPATH' ) || die;

class CB_Response_Timer {

	/** @var float Request start, set as early as PHP itself provides it. */
	private static $start;

	public static function init() {
		self::$start = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true );
		add_action( 'shutdown', array( __CLASS__, 'maybe_log' ) );
	}

	/**
	 * Runs on shutdown, i.e. after the response body/status has been decided,
	 * so http_response_code() and get_post_type() reflect the final state.
	 */
	public static function maybe_log() {
		if ( ! is_ssl() || http_response_code() !== 200 || ! self::is_relevant() ) {
			return;
		}

		do_action(
			'cb_response_timer_measured',
			array(
				'url'         => self::current_url(),
				'post_type'   => get_post_type() ?: null,
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
		$action = $_REQUEST['action'] ?? '';
		$uri    = $_SERVER['REQUEST_URI'] ?? '';

		$relevant = ( is_string( $action ) && str_starts_with( $action, 'cb_' ) )
			|| str_contains( $uri, '/wp-json/commonsbooking/' )
			|| str_contains( $uri, 'rest_route=/commonsbooking/' )
			|| in_array( get_post_type(), self::post_types(), true );

		return (bool) apply_filters( 'cb_response_timer_is_relevant', $relevant );
	}

	protected static function post_types(): array {
		return array( 'cb_item', 'cb_location', 'cb_timeframe', 'cb_booking', 'cb_map', 'cb_restriction' );
	}

	protected static function current_url(): string {
		$host = $_SERVER['HTTP_HOST'] ?? '';
		$uri  = $_SERVER['REQUEST_URI'] ?? '';
		return 'https://' . $host . $uri;
	}
}

CB_Response_Timer::init();

// Default sink: write one line per measurement to the PHP error log.
// Replace/remove this listener (or add your own) to send data elsewhere.
add_action(
	'cb_response_timer_measured',
	function ( array $data ) {
		error_log(
			sprintf(
				'[cb-response-timer] %sms | post_type=%s | %s',
				$data['duration_ms'],
				$data['post_type'] ?? '-',
				$data['url']
			)
		);
	}
);
