<?php

namespace CommonsBooking\Service;

/**
 * Persistent error ring-buffer stored in wp_options.
 * Accessible to plugin admins without WP_DEBUG_LOG.
 */
class ErrorMonitor {

	const OPTION_KEY  = 'commonsbooking_error_log';
	const MAX_ENTRIES = 100;

	const SEVERITY_ERROR   = 'error';
	const SEVERITY_WARNING = 'warning';
	const SEVERITY_INFO    = 'info';

	/**
	 * Records a message into the ring buffer.
	 *
	 * @param string $message    Human-readable description.
	 * @param string $severity   One of the SEVERITY_* constants.
	 * @param array  $context    Extra key/value context merged with auto-captured caller info.
	 * @param int    $traceDepth Frames to skip in debug_backtrace (0 = direct caller of record()).
	 */
	public static function record(
		string $message,
		string $severity = self::SEVERITY_ERROR,
		array $context = [],
		int $traceDepth = 0
	): void {
		if ( empty( $message ) ) {
			return;
		}

		// Capture caller — skip record() itself + $traceDepth additional frames
		$bt    = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, $traceDepth + 3 );
		$frame = $bt[ $traceDepth + 1 ] ?? $bt[0];

		$entry = [
			'timestamp' => time(),
			'severity'  => $severity,
			'message'   => $message,
			'context'   => array_merge(
				[
					'file' => $frame['file'] ?? '',
					'line' => $frame['line'] ?? 0,
				],
				$context
			),
		];

		$log = get_option( self::OPTION_KEY, [] );
		array_unshift( $log, $entry );
		if ( count( $log ) > self::MAX_ENTRIES ) {
			$log = array_slice( $log, 0, self::MAX_ENTRIES );
		}
		// autoload=false: this option can grow large; only load on-demand
		update_option( self::OPTION_KEY, $log, false );
	}

	/**
	 * Returns the most recent entries, newest first.
	 *
	 * @param int $limit 0 returns up to MAX_ENTRIES.
	 * @return array<int, array{timestamp:int, severity:string, message:string, context:array}>
	 */
	public static function getEntries( int $limit = 50 ): array {
		$log = get_option( self::OPTION_KEY, [] );
		return $limit > 0 ? array_slice( $log, 0, $limit ) : $log;
	}

	/** Returns the total number of stored entries. */
	public static function count(): int {
		return count( get_option( self::OPTION_KEY, [] ) );
	}

	/** Returns the count of entries matching a specific severity level. */
	public static function countBySeverity( string $severity ): int {
		return count(
			array_filter(
				get_option( self::OPTION_KEY, [] ),
				fn( $e ) => ( $e['severity'] ?? '' ) === $severity
			)
		);
	}

	/** Deletes all stored entries. */
	public static function clear(): void {
		delete_option( self::OPTION_KEY );
	}
}
