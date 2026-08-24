<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Commonsbooking
 */


$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

if ( ! function_exists( '_manually_load_plugin' ) ) {
	/**
	 * Manually load the plugin being tested.
	 */
	function _manually_load_plugin() {
		require dirname( __DIR__, 2 ) . '/commonsbooking.php';
	}
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require_once dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

// Start up the WP testing environment.
require_once "{$_tests_dir}/includes/bootstrap.php";

/**
 * Mock outbound WordPress HTTP requests by default.
 *
 * Several tests dispatch admin-ajax actions that make WordPress core call
 * wp_version_check(), which issues a real request to api.wordpress.org. With
 * network egress blocked (or simply to keep unit tests hermetic) that request
 * fails and wp_trigger_error() emits a warning that phpunit - configured with
 * convertWarningsToExceptions - turns into a test error.
 *
 * Returning a fake successful (200, empty body) response short-circuits the WP
 * HTTP API so no real network call happens and core stays silent. Tests that
 * genuinely need external HTTP are tagged `@group external-http` (skipped by
 * default); when that group is explicitly requested we leave the real HTTP
 * stack in place so such tests can reach the network.
 *
 * Note: this only affects WordPress' own HTTP API (wp_remote_*). The Nominatim
 * geocoder uses its own PSR-18 client and is unaffected - its integration test
 * is gated purely by the external-http group tag.
 */
if ( false === strpos( implode( ' ', (array) ( $_SERVER['argv'] ?? array() ) ), 'external-http' ) ) {
	add_filter(
		'pre_http_request',
		static function () {
			return array(
				'headers'       => array(),
				'body'          => '',
				'response'      => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'       => array(),
				'http_response' => null,
			);
		}
	);
}
