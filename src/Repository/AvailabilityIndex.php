<?php

namespace CommonsBooking\Repository;

use CommonsBooking\Model\Timeframe;
use CommonsBooking\Wordpress\CustomPostType\Booking as BookingCPT;
use CommonsBooking\Wordpress\CustomPostType\Timeframe as TimeframeCPT;

/**
 * Index of the timeframes that affect availability, kept in sync with the
 * cb_timeframe / cb_booking posts.
 *
 * Timeframes store their locations and items as serialized meta arrays, which can
 * only be queried with LIKE '%:"ID";%' scans. This index holds the same relations
 * in two junction tables so they can be resolved with indexed integer joins.
 */
class AvailabilityIndex {

	public static string $indexTable     = 'cb_availability_index';
	public static string $locationsTable = 'cb_timeframe_locations';
	public static string $itemsTable     = 'cb_timeframe_items';

	/**
	 * Timeframe types that make a slot un-/bookable. Everything else is not indexed.
	 */
	public const INDEXED_TYPES = [
		TimeframeCPT::BOOKABLE_ID,
		TimeframeCPT::HOLIDAYS_ID,
		TimeframeCPT::OFF_HOLIDAYS_ID,
		TimeframeCPT::REPAIR_ID,
		TimeframeCPT::BOOKING_ID,
	];

	/**
	 * Post statuses that count towards availability. Drafts, trashed timeframes and
	 * canceled bookings are kept out of the index entirely.
	 */
	public const INDEXED_STATUSES = [ 'publish', 'confirmed', 'unconfirmed' ];

	/**
	 * The post types held in the index.
	 *
	 * @return string[]
	 */
	public static function getIndexedPostTypes(): array {
		return [ TimeframeCPT::$postType, BookingCPT::$postType ];
	}

	/**
	 * Creates the index tables. Safe to call repeatedly (uses dbDelta).
	 */
	public static function initTables(): void {
		global $wpdb;

		$charsetCollate = $wpdb->get_charset_collate();
		$indexTable     = $wpdb->prefix . self::$indexTable;
		$locationsTable = $wpdb->prefix . self::$locationsTable;
		$itemsTable     = $wpdb->prefix . self::$itemsTable;

		$sql = "CREATE TABLE $indexTable (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			timeframe_id bigint(20) unsigned NOT NULL,
			type tinyint(3) unsigned NOT NULL,
			start_date date NOT NULL,
			end_date date DEFAULT NULL,
			post_status varchar(20) NOT NULL DEFAULT 'publish',
			PRIMARY KEY (id),
			UNIQUE KEY timeframe_id (timeframe_id),
			KEY type_date (type, start_date, end_date),
			KEY date_range (start_date, end_date)
		) $charsetCollate;
		CREATE TABLE $locationsTable (
			timeframe_id bigint(20) unsigned NOT NULL,
			location_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY (timeframe_id, location_id),
			KEY location_id (location_id)
		) $charsetCollate;
		CREATE TABLE $itemsTable (
			timeframe_id bigint(20) unsigned NOT NULL,
			item_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY (timeframe_id, item_id),
			KEY item_id (item_id)
		) $charsetCollate;";

		// Include dbDelta since it's not part of autoloaded modules
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Writes a timeframe and its location/item relations into the index, replacing any
	 * previous entry. Timeframes that no longer qualify are removed instead.
	 */
	public static function upsertTimeframe( Timeframe $timeframe ): void {
		global $wpdb;

		$type        = $timeframe->getType();
		$startDate   = $timeframe->getStartDate();
		$locationIds = $timeframe->getLocationIDs();
		$itemIds     = $timeframe->getItemIDs();

		self::deleteByTimeframeId( $timeframe->ID );

		if (
			! in_array( $type, self::INDEXED_TYPES, true ) ||
			! in_array( $timeframe->post_status, self::INDEXED_STATUSES, true ) ||
			! $startDate || ! $locationIds || ! $itemIds
		) {
			return;
		}

		$endDate = $timeframe->getRawEndDate();

		$wpdb->insert(
			$wpdb->prefix . self::$indexTable,
			array(
				'timeframe_id' => $timeframe->ID,
				'type'         => $type,
				'start_date'   => date( 'Y-m-d', $startDate ),
				'end_date'     => $endDate ? date( 'Y-m-d', $endDate ) : null,
				'post_status'  => $timeframe->post_status,
			)
		);

		self::insertRelations( self::$locationsTable, 'location_id', $timeframe->ID, $locationIds );
		self::insertRelations( self::$itemsTable, 'item_id', $timeframe->ID, $itemIds );
	}

	/**
	 * Inserts the junction rows of one timeframe in a single query.
	 *
	 * @param string $table      Unprefixed junction table name.
	 * @param string $column     Name of the related-id column.
	 * @param int    $timeframeId
	 * @param int[]  $relatedIds
	 */
	private static function insertRelations( string $table, string $column, int $timeframeId, array $relatedIds ): void {
		global $wpdb;

		$rows = array();
		foreach ( array_unique( array_map( 'intval', $relatedIds ) ) as $relatedId ) {
			$rows[] = $wpdb->prepare( '(%d, %d)', $timeframeId, $relatedId );
		}

		$wpdb->query(
			"INSERT IGNORE INTO {$wpdb->prefix}{$table} (timeframe_id, $column) VALUES " . implode( ', ', $rows )
		);
	}

	/**
	 * Removes a timeframe from all three tables.
	 */
	public static function deleteByTimeframeId( int $timeframeId ): void {
		global $wpdb;

		foreach ( array( self::$indexTable, self::$locationsTable, self::$itemsTable ) as $table ) {
			$wpdb->delete( $wpdb->prefix . $table, array( 'timeframe_id' => $timeframeId ) );
		}
	}

	/**
	 * Drops the relations of a location that was permanently deleted.
	 */
	public static function removeLocation( int $locationId ): void {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . self::$locationsTable, array( 'location_id' => $locationId ) );
	}

	/**
	 * Drops the relations of an item that was permanently deleted.
	 */
	public static function removeItem( int $itemId ): void {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . self::$itemsTable, array( 'item_id' => $itemId ) );
	}

	/**
	 * Returns the index rows of all timeframes that apply to a location/item pair and
	 * overlap the given date range.
	 *
	 * @param string   $startDate Range start, 'Y-m-d'.
	 * @param string   $endDate   Range end, 'Y-m-d'.
	 * @param int[]    $types
	 * @param string[] $postStatuses
	 *
	 * @return \stdClass[]
	 */
	public static function getByLocationAndItemAndDateRange(
		int $locationId,
		int $itemId,
		string $startDate,
		string $endDate,
		array $types = self::INDEXED_TYPES,
		array $postStatuses = self::INDEXED_STATUSES
	): array {
		global $wpdb;

		$indexTable     = $wpdb->prefix . self::$indexTable;
		$locationsTable = $wpdb->prefix . self::$locationsTable;
		$itemsTable     = $wpdb->prefix . self::$itemsTable;

		$typePlaceholders   = implode( ', ', array_fill( 0, count( $types ), '%d' ) );
		$statusPlaceholders = implode( ', ', array_fill( 0, count( $postStatuses ), '%s' ) );

		$sql = $wpdb->prepare(
			"SELECT ai.* FROM $indexTable ai
			JOIN $locationsTable tl ON tl.timeframe_id = ai.timeframe_id
			JOIN $itemsTable ti ON ti.timeframe_id = ai.timeframe_id
			WHERE tl.location_id = %d
				AND ti.item_id = %d
				AND ai.start_date <= %s
				AND (ai.end_date IS NULL OR ai.end_date >= %s)
				AND ai.type IN ($typePlaceholders)
				AND ai.post_status IN ($statusPlaceholders)",
			$locationId,
			$itemId,
			$endDate,
			$startDate,
			...$types,
			...$postStatuses
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Rebuilds the index from all existing timeframes and bookings.
	 * Paginated AJAX upgrade task: returns true when done, the next page otherwise.
	 *
	 * @return int|bool
	 */
	public static function rebuildFromAllTimeframes( int $page = 1 ) {
		global $wpdb;

		if ( $page === 1 ) {
			// The tables do not exist yet on installations upgrading from an older version.
			self::initTables();

			foreach ( array( self::$indexTable, self::$locationsTable, self::$itemsTable ) as $table ) {
				$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . $table );
			}
		}

		$response = \CommonsBooking\Repository\Timeframe::getAllPaginated(
			$page,
			10,
			array(
				'post_type'   => self::getIndexedPostTypes(),
				'post_status' => self::INDEXED_STATUSES,
			)
		);

		foreach ( $response->posts as $post ) {
			self::upsertTimeframe( new Timeframe( $post ) );
		}

		return $response->done ? true : $page + 1;
	}
}
