<?php

namespace CommonsBooking\Service;

/**
 * Lightweight, reusable execution-time recorder.
 *
 * Usage:
 *   $result = QueryTimer::measure('my_label', fn() => expensive_call(), ['key' => 'value']);
 *
 * Samples are collected in memory and flushed to a WP option ring-buffer via a
 * shutdown hook, so the option write does NOT add latency to the measured call.
 * The ring buffer holds at most BUFFER_SIZE entries (oldest are evicted first).
 *
 * Samples are also written to the WP debug log (when WP_DEBUG_LOG is true) for
 * zero-overhead inspection without a database round-trip.
 *
 * @see QueryTimer::measure()
 * @see QueryTimer::getSamples()
 * @see QueryTimer::clearSamples()
 */
class QueryTimer {

	/** WP option key for the persistent ring buffer. */
	const OPTION_KEY = 'commonsbooking_query_timings';

	/** Maximum number of samples retained in the ring buffer. */
	const BUFFER_SIZE = 200;

	/**
	 * In-memory queue of samples collected in the current request.
	 * Flushed to the WP option at PHP shutdown via register_shutdown_function().
	 *
	 * @var array<int, array{label: string, duration: float, timestamp: int, context: array}>
	 */
	private static array $pending = [];

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Times $fn, queues a sample with $context, and returns $fn's return value.
	 *
	 * The sample is written to the persistent ring buffer at PHP shutdown, not
	 * inline, so this method adds no database overhead to the measured call.
	 *
	 * @param string   $label   Human-readable identifier for this measurement point.
	 * @param callable $fn      Callable to execute and time.
	 * @param array    $context Arbitrary key-value metadata attached to the sample.
	 *                          Typical keys: 'past_booking_flag', 'item_count', 'statuses'.
	 * @return mixed            The return value of $fn, passed through unchanged.
	 */
	public static function measure( string $label, callable $fn, array $context = [] ): mixed {
		$start  = hrtime( true );   // monotonic nanosecond clock — unaffected by NTP or DST
		$result = $fn();
		$ms     = ( hrtime( true ) - $start ) / 1_000_000;  // nanoseconds → milliseconds

		$sample = [
			'label'     => $label,
			'duration'  => round( $ms, 2 ),
			'timestamp' => time(),
			'context'   => $context,
		];

		self::schedulePersist( $sample );

		// Also emit to the WP debug log at no extra cost (no-op when WP_DEBUG_LOG is off)
		commonsbooking_write_log(
			sprintf( '[QueryTimer] %s: %.2f ms | %s', $label, $ms, json_encode( $context ) ),
			false
		);

		return $result;
	}

	/**
	 * Returns all stored samples from the persistent ring buffer, newest last.
	 *
	 * @return array<int, array{label: string, duration: float, timestamp: int, context: array}>
	 */
	public static function getSamples(): array {
		return get_option( self::OPTION_KEY, [] );
	}

	/**
	 * Removes all samples from the persistent ring buffer.
	 * Useful for testing and for operators who want a fresh baseline.
	 */
	public static function clearSamples(): void {
		delete_option( self::OPTION_KEY );
	}

	// -------------------------------------------------------------------------
	// Persistence (deferred write via shutdown hook)
	// -------------------------------------------------------------------------

	/**
	 * Merges the pending in-memory queue into the persistent ring buffer.
	 *
	 * Called automatically at PHP shutdown (registered on first queued sample).
	 * Can also be called explicitly in tests or long-running processes.
	 */
	public static function flushPending(): void {
		if ( empty( self::$pending ) ) {
			return;
		}

		$existing = get_option( self::OPTION_KEY, [] );
		$merged   = array_merge( $existing, self::$pending );

		if ( count( $merged ) > self::BUFFER_SIZE ) {
			$merged = array_slice( $merged, -self::BUFFER_SIZE );
		}

		// autoload=false: do not load this option on every page request
		update_option( self::OPTION_KEY, $merged, false );

		self::$pending = [];
	}

	/**
	 * Queues a sample and registers the shutdown flush on the first sample of the request.
	 */
	private static function schedulePersist( array $sample ): void {
		self::$pending[] = $sample;

		if ( count( self::$pending ) === 1 ) {
			// Register only once per request — subsequent calls just append to $pending
			register_shutdown_function( [ self::class, 'flushPending' ] );
		}
	}
}
