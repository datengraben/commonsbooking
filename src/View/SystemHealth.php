<?php

namespace CommonsBooking\View;

use CommonsBooking\Service\ErrorMonitor;

/**
 * Admin page that shows system health checks and the persistent error log.
 */
class SystemHealth extends View {

	/** Capability required to view and manage this page. */
	const REQUIRED_CAP = 'manage_commonsbooking';

	/** Nonce action for the "clear error log" form. */
	const NONCE_ACTION = 'cb_clear_error_log';

	/** Nonce field name in the form. */
	const NONCE_FIELD = 'cb_clear_error_log_nonce';

	/** Page slug registered with add_submenu_page(). */
	const PAGE_SLUG = 'cb-system-health';

	/**
	 * Page callback registered with add_submenu_page().
	 */
	public static function index(): void {
		ob_start();
		commonsbooking_get_template_part( 'system', 'health' );
		echo ob_get_clean();
	}

	/**
	 * Runs all registered health checks and returns their results.
	 *
	 * Each result is an array with keys:
	 *   'label'  (string)  — human-readable check name
	 *   'status' (string)  — 'ok', 'warn', or 'fail'
	 *   'detail' (string)  — explanation or current value
	 *
	 * Third-party plugins can add custom checks via the filter
	 * 'commonsbooking_health_checks'.
	 *
	 * @return array<int, array{label:string, status:string, detail:string}>
	 */
	public static function getChecks(): array {
		$checks = [
			self::checkPhpVersion(),
			self::checkWordPressVersion(),
			self::checkPhpExtensions(),
			self::checkDatabase(),
			self::checkCronJobs(),
			self::checkRestApi(),
			self::checkRecentErrors(),
		];

		return apply_filters( 'commonsbooking_health_checks', $checks );
	}

	// -------------------------------------------------------------------------
	// Individual health checks
	// -------------------------------------------------------------------------

	private static function checkPhpVersion(): array {
		$required = '8.1';
		$ok       = version_compare( PHP_VERSION, $required, '>=' );
		return [
			'label'  => __( 'PHP Version', 'commonsbooking' ),
			'status' => $ok ? 'ok' : 'fail',
			'detail' => sprintf( __( 'Current: %1$s (required: >= %2$s)', 'commonsbooking' ), PHP_VERSION, $required ),
		];
	}

	private static function checkWordPressVersion(): array {
		global $wp_version;
		$required = '5.6';
		$ok       = version_compare( $wp_version, $required, '>=' );
		return [
			'label'  => __( 'WordPress Version', 'commonsbooking' ),
			'status' => $ok ? 'ok' : 'fail',
			'detail' => sprintf( __( 'Current: %1$s (required: >= %2$s)', 'commonsbooking' ), $wp_version, $required ),
		];
	}

	private static function checkPhpExtensions(): array {
		$required = [ 'json', 'mbstring', 'curl', 'dom', 'libxml' ];
		$missing  = array_values( array_filter( $required, fn( $ext ) => ! extension_loaded( $ext ) ) );
		return [
			'label'  => __( 'PHP Extensions', 'commonsbooking' ),
			'status' => empty( $missing ) ? 'ok' : 'fail',
			'detail' => empty( $missing )
				? implode( ', ', $required ) . ' — ' . __( 'all loaded', 'commonsbooking' )
				: __( 'Missing:', 'commonsbooking' ) . ' ' . implode( ', ', $missing ),
		];
	}

	private static function checkDatabase(): array {
		global $wpdb;
		$result = $wpdb->get_var( 'SELECT 1' );
		$ok     = $result !== null && $wpdb->last_error === '';
		return [
			'label'  => __( 'Database', 'commonsbooking' ),
			'status' => $ok ? 'ok' : 'fail',
			'detail' => $ok
				? __( 'Connection OK', 'commonsbooking' )
				: ( $wpdb->last_error ?: __( 'Query returned null', 'commonsbooking' ) ),
		];
	}

	private static function checkCronJobs(): array {
		$hooks  = [
			COMMONSBOOKING_PLUGIN_SLUG . '_cleanup',
			COMMONSBOOKING_PLUGIN_SLUG . '_reminder',
			COMMONSBOOKING_PLUGIN_SLUG . '_feedback',
			COMMONSBOOKING_PLUGIN_SLUG . '_email_bookingcodes',
		];
		$missing   = [];
		$scheduled = [];
		foreach ( $hooks as $hook ) {
			$next = wp_next_scheduled( $hook );
			if ( $next ) {
				$scheduled[] = basename( $hook ) . ' (' . human_time_diff( $next ) . ')';
			} else {
				$missing[] = basename( $hook );
			}
		}
		$status = empty( $missing ) ? 'ok' : 'warn';
		$detail = empty( $missing )
			? implode( ', ', $scheduled )
			: __( 'Not scheduled:', 'commonsbooking' ) . ' ' . implode( ', ', $missing );
		return [
			'label'  => __( 'Scheduled Jobs', 'commonsbooking' ),
			'status' => $status,
			'detail' => $detail,
		];
	}

	private static function checkRestApi(): array {
		$url = get_rest_url();
		$ok  = ! empty( $url );
		return [
			'label'  => __( 'REST API', 'commonsbooking' ),
			'status' => $ok ? 'ok' : 'warn',
			'detail' => $ok
				? $url
				: __( 'REST API URL could not be determined — map and API features may not work', 'commonsbooking' ),
		];
	}

	private static function checkRecentErrors(): array {
		$count = ErrorMonitor::count();
		if ( $count === 0 ) {
			$status = 'ok';
		} elseif ( $count < 10 ) {
			$status = 'warn';
		} else {
			$status = 'fail';
		}
		return [
			'label'  => __( 'Recent Errors', 'commonsbooking' ),
			'status' => $status,
			/* translators: %d: number of errors recorded */
			'detail' => sprintf( _n( '%d error recorded', '%d errors recorded', $count, 'commonsbooking' ), $count ),
		];
	}
}
