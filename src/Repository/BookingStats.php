<?php

namespace CommonsBooking\Repository;

use CommonsBooking\Model\Booking;

/**
 * Manages the cb_booking_stats table, which stores pre-aggregated booking
 * counts and durations per day per entity (global / item / location).
 *
 * Schema:
 *   stat_date    date         – the booking's start date
 *   entity_type  varchar(20)  – 'all', 'item', or 'location'
 *   entity_id    bigint       – 0 for 'all', otherwise the WP post ID
 *   booking_count int         – confirmed bookings starting on stat_date
 *   booking_days  int         – sum of getDuration() for those bookings
 */
class BookingStats {

	public static string $tablename = 'cb_booking_stats';

	// -------------------------------------------------------------------------
	// Table lifecycle
	// -------------------------------------------------------------------------

	public static function initTable(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::$tablename;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            stat_date    date         NOT NULL,
            entity_type  varchar(20)  NOT NULL,
            entity_id    bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            booking_count int(11)    NOT NULL DEFAULT 0,
            booking_days  int(11)    NOT NULL DEFAULT 0,
            PRIMARY KEY (stat_date, entity_type, entity_id),
            KEY entity_lookup (entity_type, entity_id, stat_date)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	// -------------------------------------------------------------------------
	// Write operations
	// -------------------------------------------------------------------------

	/**
	 * Increment stats for a newly confirmed booking.
	 * Safe to call multiple times – uses INSERT … ON DUPLICATE KEY UPDATE.
	 */
	public static function recordBooking( Booking $booking ): void {
		$date     = self::bookingDateString( $booking );
		$duration = max( 0, (int) $booking->getDuration() );
		$itemId   = (int) $booking->getItemID();
		$locId    = (int) $booking->getLocationID();

		if ( ! $date ) {
			return;
		}

		self::upsertRow( $date, 'all', 0, 1, $duration );
		if ( $itemId ) {
			self::upsertRow( $date, 'item', $itemId, 1, $duration );
		}
		if ( $locId ) {
			self::upsertRow( $date, 'location', $locId, 1, $duration );
		}
	}

	/**
	 * Decrement stats when a booking is cancelled or deleted.
	 */
	public static function removeBooking( Booking $booking ): void {
		$date     = self::bookingDateString( $booking );
		$duration = max( 0, (int) $booking->getDuration() );
		$itemId   = (int) $booking->getItemID();
		$locId    = (int) $booking->getLocationID();

		if ( ! $date ) {
			return;
		}

		self::decrementRow( $date, 'all', 0, 1, $duration );
		if ( $itemId ) {
			self::decrementRow( $date, 'item', $itemId, 1, $duration );
		}
		if ( $locId ) {
			self::decrementRow( $date, 'location', $locId, 1, $duration );
		}
	}

	/**
	 * Rebuild the entire stats table from scratch.
	 * Should be run as a background task or triggered manually by an admin.
	 */
	public static function recomputeAll(): void {
		global $wpdb;

		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . self::$tablename ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$bookings = \CommonsBooking\Repository\Booking::get( [], [], null, true, null, [ 'confirmed' ] );
		foreach ( $bookings as $booking ) {
			self::recordBooking( $booking );
		}
	}

	// -------------------------------------------------------------------------
	// Read operations
	// -------------------------------------------------------------------------

	/**
	 * Return aggregated booking_count and booking_days for a date range and entity.
	 *
	 * @param \DateTimeImmutable $from        Inclusive start date.
	 * @param \DateTimeImmutable $to          Inclusive end date.
	 * @param string             $entityType  'all', 'item', or 'location'.
	 * @param int                $entityId    0 for global; post ID for item/location.
	 *
	 * @return array{count: int, days: int}
	 */
	public static function getAggregated(
		\DateTimeImmutable $from,
		\DateTimeImmutable $to,
		string $entityType = 'all',
		int $entityId = 0
	): array {
		global $wpdb;

		$table = $wpdb->prefix . self::$tablename;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COALESCE(SUM(booking_count),0) AS cnt, COALESCE(SUM(booking_days),0) AS days
				 FROM $table
				 WHERE stat_date BETWEEN %s AND %s
				   AND entity_type = %s
				   AND entity_id   = %d",
				$from->format( 'Y-m-d' ),
				$to->format( 'Y-m-d' ),
				$entityType,
				$entityId
			),
			ARRAY_A
		);

		return [
			'count' => isset( $row['cnt'] ) ? (int) $row['cnt'] : 0,
			'days'  => isset( $row['days'] ) ? (int) $row['days'] : 0,
		];
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	private static function upsertRow( string $date, string $entityType, int $entityId, int $count, int $days ): void {
		global $wpdb;

		$table = $wpdb->prefix . self::$tablename;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"INSERT INTO $table (stat_date, entity_type, entity_id, booking_count, booking_days)
				 VALUES (%s, %s, %d, %d, %d)
				 ON DUPLICATE KEY UPDATE
				     booking_count = booking_count + VALUES(booking_count),
				     booking_days  = booking_days  + VALUES(booking_days)",
				$date,
				$entityType,
				$entityId,
				$count,
				$days
			)
		);
	}

	private static function decrementRow( string $date, string $entityType, int $entityId, int $count, int $days ): void {
		global $wpdb;

		$table = $wpdb->prefix . self::$tablename;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE $table
				 SET booking_count = GREATEST(0, booking_count - %d),
				     booking_days  = GREATEST(0, booking_days  - %d)
				 WHERE stat_date   = %s
				   AND entity_type = %s
				   AND entity_id   = %d",
				$count,
				$days,
				$date,
				$entityType,
				$entityId
			)
		);
	}

	private static function bookingDateString( Booking $booking ): ?string {
		try {
			$ts = $booking->getStartDate();
			if ( ! $ts ) {
				return null;
			}
			return date( 'Y-m-d', $ts );
		} catch ( \Exception $e ) {
			return null;
		}
	}
}
