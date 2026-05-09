<?php

namespace CommonsBooking\Service;

use CommonsBooking\View\SystemHealth;

/**
 * Assembles and downloads a local troubleshooting report (JSON).
 * No data is sent anywhere — the file is downloaded by the browser only.
 */
class TroubleshootingReport {

	const NONCE_ACTION = 'cb_download_troubleshooting_report';
	const AJAX_ACTION  = 'cb_troubleshooting_report';

	/**
	 * Entry point registered via admin_post_{AJAX_ACTION}.
	 * Validates capability and nonce, then streams the file.
	 */
	public static function handleDownload(): void {
		if ( ! current_user_can( 'manage_' . COMMONSBOOKING_PLUGIN_SLUG ) ) {
			wp_die(
				esc_html__( 'You do not have permission to download this report.', 'commonsbooking' ),
				403
			);
		}
		check_admin_referer( self::NONCE_ACTION );
		self::download();
	}

	/**
	 * Sends the report as a downloadable JSON file and exits.
	 */
	public static function download(): void {
		$filename = 'commonsbooking-report-' . gmdate( 'Y-m-d-His' ) . '.json';
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_json_encode( self::generate(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Assembles all report sections. Extensible via filter 'commonsbooking_troubleshooting_report'.
	 *
	 * @return array{generated_at:string, system:array, health:array, error_log:array, stats:array, cron:array}
	 */
	public static function generate(): array {
		$report = [
			'generated_at' => gmdate( 'c' ),
			'system'       => self::systemInfo(),
			'health'       => SystemHealth::getChecks(),
			'error_log'    => ErrorMonitor::getEntries( 0 ),
			'stats'        => self::pluginStats(),
			'cron'         => self::cronInfo(),
		];

		return apply_filters( 'commonsbooking_troubleshooting_report', $report );
	}

	// -------------------------------------------------------------------------
	// Report sections
	// -------------------------------------------------------------------------

	private static function systemInfo(): array {
		global $wp_version;

		return [
			'plugin_version'    => COMMONSBOOKING_VERSION,
			'wordpress_version' => $wp_version,
			'php_version'       => PHP_VERSION,
			'php_extensions'    => get_loaded_extensions(),
			'wp_multisite'      => is_multisite(),
			'wp_debug'          => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'wp_debug_log'      => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'active_plugins'    => (array) get_option( 'active_plugins', [] ),
			'active_theme'      => wp_get_theme()->get( 'Name' ),
			'site_url'          => home_url(),
		];
	}

	private static function pluginStats(): array {
		$counts   = wp_count_posts( 'cb_booking' );
		$statuses = [ 'confirmed', 'unconfirmed', 'canceled' ];
		$bookings = [];
		foreach ( $statuses as $status ) {
			$bookings[ $status ] = isset( $counts->$status ) ? (int) $counts->$status : 0;
		}

		return [
			'items'             => (int) ( wp_count_posts( 'cb_item' )->publish ?? 0 ),
			'locations'         => (int) ( wp_count_posts( 'cb_location' )->publish ?? 0 ),
			'timeframes'        => (int) ( wp_count_posts( 'cb_timeframe' )->publish ?? 0 ),
			'restrictions'      => (int) ( wp_count_posts( 'cb_restriction' )->publish ?? 0 ),
			'bookings'          => $bookings,
			'orphaned_bookings' => count( \CommonsBooking\Repository\Booking::getOrphaned() ),
		];
	}

	private static function cronInfo(): array {
		$hooks = [
			COMMONSBOOKING_PLUGIN_SLUG . '_cleanup',
			COMMONSBOOKING_PLUGIN_SLUG . '_reminder',
			COMMONSBOOKING_PLUGIN_SLUG . '_feedback',
			COMMONSBOOKING_PLUGIN_SLUG . '_email_bookingcodes',
			COMMONSBOOKING_PLUGIN_SLUG . '_export',
			COMMONSBOOKING_PLUGIN_SLUG . '_cache_warmup',
		];

		$result = [];
		foreach ( $hooks as $hook ) {
			$next            = wp_next_scheduled( $hook );
			$result[ $hook ] = [
				'next_run'      => $next ? gmdate( 'c', $next ) : null,
				'next_run_diff' => $next ? human_time_diff( $next ) : 'not scheduled',
			];
		}

		return $result;
	}
}
