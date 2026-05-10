<?php

namespace CommonsBooking\Repository;

use CommonsBooking\Model\Timeframe;
use CommonsBooking\Wordpress\CustomPostType\Timeframe as TimeframeCPT;
use CommonsBooking\Wordpress\CustomPostType\Booking as BookingCPT;

class AvailabilityIndex {

	public static string $indexTable     = 'cb_availability_index';
	public static string $locationsTable = 'cb_timeframe_locations';
	public static string $itemsTable     = 'cb_timeframe_items';

	private const ALLOWED_TYPES = [
		TimeframeCPT::BOOKABLE_ID,
		TimeframeCPT::HOLIDAYS_ID,
		TimeframeCPT::OFF_HOLIDAYS_ID,
		TimeframeCPT::REPAIR_ID,
		TimeframeCPT::BOOKING_ID,
	];

	private const SKIP_STATUSES = [ 'auto-draft', 'trash' ];

	/**
	 * Creates the three index tables if they do not yet exist.
	 * Safe to call multiple times (uses dbDelta).
	 */
	public static function initTables(): void {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$indexTable     = $wpdb->prefix . self::$indexTable;
		$locationsTable = $wpdb->prefix . self::$locationsTable;
		$itemsTable     = $wpdb->prefix . self::$itemsTable;

		$sql = "CREATE TABLE $indexTable (
    id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    timeframe_id BIGINT(20) UNSIGNED NOT NULL,
    type         TINYINT(3) UNSIGNED NOT NULL,
    start_date   DATE NOT NULL,
    end_date     DATE DEFAULT NULL,
    post_status  VARCHAR(20) NOT NULL DEFAULT 'publish',
    PRIMARY KEY (id),
    UNIQUE KEY uk_timeframe_id (timeframe_id),
    KEY idx_type_date (type, start_date, end_date),
    KEY idx_date_range (start_date, end_date)
) $charset_collate;
CREATE TABLE $locationsTable (
    timeframe_id BIGINT(20) UNSIGNED NOT NULL,
    location_id  BIGINT(20) UNSIGNED NOT NULL,
    PRIMARY KEY (timeframe_id, location_id),
    KEY idx_location_id (location_id)
) $charset_collate;
CREATE TABLE $itemsTable (
    timeframe_id BIGINT(20) UNSIGNED NOT NULL,
    item_id      BIGINT(20) UNSIGNED NOT NULL,
    PRIMARY KEY (timeframe_id, item_id),
    KEY idx_item_id (item_id)
) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		add_option( 'cb_availability_index_db_version', '1.0' );
	}

	/**
	 * Inserts or replaces all three index rows for a timeframe.
	 * Silently removes the timeframe from the index when it does not qualify
	 * (wrong type, trashed status, missing location/item assignments).
	 */
	public static function upsertTimeframe( Timeframe $timeframe ): void {
		$type = $timeframe->getType();

		if ( ! in_array( $type, self::ALLOWED_TYPES, true ) ) {
			self::deleteByTimeframeId( $timeframe->ID );
			return;
		}

		if ( in_array( $timeframe->post_status, self::SKIP_STATUSES, true ) ) {
			self::deleteByTimeframeId( $timeframe->ID );
			return;
		}

		$locationIds = $timeframe->getLocationIDs();
		$itemIds     = $timeframe->getItemIDs();

		if ( empty( $locationIds ) || empty( $itemIds ) ) {
			self::deleteByTimeframeId( $timeframe->ID );
			return;
		}

		$rawStart = $timeframe->getStartDate();
		if ( ! $rawStart ) {
			self::deleteByTimeframeId( $timeframe->ID );
			return;
		}

		$startDate = date( 'Y-m-d', $rawStart );
		$rawEnd    = $timeframe->getRawEndDate();
		$endDate   = $rawEnd ? date( 'Y-m-d', $rawEnd ) : null;

		global $wpdb;
		$indexTable     = $wpdb->prefix . self::$indexTable;
		$locationsTable = $wpdb->prefix . self::$locationsTable;
		$itemsTable     = $wpdb->prefix . self::$itemsTable;

		$wpdb->query( 'START TRANSACTION' );

		try {
			// Delete-then-reinsert inside the transaction for atomicity
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $indexTable, [ 'timeframe_id' => $timeframe->ID ], [ '%d' ] );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $locationsTable, [ 'timeframe_id' => $timeframe->ID ], [ '%d' ] );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $itemsTable, [ 'timeframe_id' => $timeframe->ID ], [ '%d' ] );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->insert(
				$indexTable,
				[
					'timeframe_id' => $timeframe->ID,
					'type'         => $type,
					'start_date'   => $startDate,
					'end_date'     => $endDate,
					'post_status'  => $timeframe->post_status,
				],
				[ '%d', '%d', '%s', '%s', '%s' ]
			);

			if ( $result === false ) {
				throw new \RuntimeException( "Failed to insert timeframe {$timeframe->ID} into availability index" );
			}

			// Bulk-insert location junction rows
			$locPlaceholders = implode( ', ', array_fill( 0, count( $locationIds ), '(%d, %d)' ) );
			$locValues       = [];
			foreach ( $locationIds as $locationId ) {
				$locValues[] = $timeframe->ID;
				$locValues[] = (int) $locationId;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$locationsTable} (timeframe_id, location_id) VALUES {$locPlaceholders}", ...$locValues ) );

			// Bulk-insert item junction rows
			$itemPlaceholders = implode( ', ', array_fill( 0, count( $itemIds ), '(%d, %d)' ) );
			$itemValues       = [];
			foreach ( $itemIds as $itemId ) {
				$itemValues[] = $timeframe->ID;
				$itemValues[] = (int) $itemId;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$itemsTable} (timeframe_id, item_id) VALUES {$itemPlaceholders}", ...$itemValues ) );

			$wpdb->query( 'COMMIT' );

		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
		}
	}

	/**
	 * Removes a timeframe from all three index tables.
	 */
	public static function deleteByTimeframeId( int $postId ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . self::$indexTable, [ 'timeframe_id' => $postId ], [ '%d' ] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . self::$locationsTable, [ 'timeframe_id' => $postId ], [ '%d' ] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . self::$itemsTable, [ 'timeframe_id' => $postId ], [ '%d' ] );
	}

	/**
	 * Removes all junction rows for a location that has been permanently deleted.
	 */
	public static function removeLocation( int $locationId ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . self::$locationsTable, [ 'location_id' => $locationId ], [ '%d' ] );
	}

	/**
	 * Removes all junction rows for an item that has been permanently deleted.
	 */
	public static function removeItem( int $itemId ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . self::$itemsTable, [ 'item_id' => $itemId ], [ '%d' ] );
	}

	/**
	 * Returns index rows matching a location + item + date-range overlap.
	 *
	 * @param int    $locationId
	 * @param int    $itemId
	 * @param string $startDate  Window start in 'Y-m-d'
	 * @param string $endDate    Window end in 'Y-m-d'
	 * @param int[]  $types      Optional type whitelist (TimeframeCPT constants)
	 * @param string[] $postStatuses
	 * @return \stdClass[]
	 */
	public static function getByLocationAndItemAndDateRange(
		int $locationId,
		int $itemId,
		string $startDate,
		string $endDate,
		array $types = [],
		array $postStatuses = [ 'publish', 'confirmed', 'unconfirmed' ]
	): array {
		global $wpdb;

		$indexTable     = $wpdb->prefix . self::$indexTable;
		$locationsTable = $wpdb->prefix . self::$locationsTable;
		$itemsTable     = $wpdb->prefix . self::$itemsTable;

		$typeClause   = '';
		$statusClause = '';
		$extraValues  = [];

		if ( ! empty( $types ) ) {
			$typePlaceholders = implode( ', ', array_fill( 0, count( $types ), '%d' ) );
			$typeClause       = "AND ai.type IN ($typePlaceholders)";
			$extraValues      = array_merge( $extraValues, $types );
		}

		if ( ! empty( $postStatuses ) ) {
			$statusPlaceholders = implode( ', ', array_fill( 0, count( $postStatuses ), '%s' ) );
			$statusClause       = "AND ai.post_status IN ($statusPlaceholders)";
			$extraValues        = array_merge( $extraValues, $postStatuses );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT ai.*
		        FROM {$indexTable} ai
		        JOIN {$locationsTable} tl ON tl.timeframe_id = ai.timeframe_id
		        JOIN {$itemsTable}     ti ON ti.timeframe_id = ai.timeframe_id
		        WHERE tl.location_id = %d
		          AND ti.item_id     = %d
		          AND ai.start_date  <= %s
		          AND (ai.end_date IS NULL OR ai.end_date >= %s)
		          {$typeClause}
		          {$statusClause}";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results(
			$wpdb->prepare( $sql, $locationId, $itemId, $endDate, $startDate, ...$extraValues )
		);
	}

	/**
	 * Rebuilds the index from all published timeframe and booking posts.
	 * Designed as a paginated AJAX upgrade task: returns true when done, next page number otherwise.
	 */
	public static function rebuildFromAllTimeframes( int $page = 1 ) {
		global $wpdb;

		if ( $page === 1 ) {
			// Ensure the tables exist for upgrades that haven't run activation()
			self::initTables();
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . self::$indexTable );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . self::$locationsTable );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . self::$itemsTable );
		}

		$response = \CommonsBooking\Repository\Timeframe::getAllPaginated(
			$page,
			10,
			[
				'post_type'   => [
					TimeframeCPT::$postType,
					BookingCPT::$postType,
				],
				'post_status' => [ 'publish', 'confirmed', 'unconfirmed' ],
			]
		);

		foreach ( $response->posts as $post ) {
			try {
				self::upsertTimeframe( new Timeframe( $post ) );
			} catch ( \Throwable $e ) {
				// skip unindexable posts silently
			}
		}

		return $response->done ? true : $page + 1;
	}
}
