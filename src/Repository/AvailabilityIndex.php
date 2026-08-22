<?php

namespace CommonsBooking\Repository;

use CommonsBooking\Model\Timeframe;
use CommonsBooking\Settings\Settings;
use CommonsBooking\Wordpress\CustomPostType\Booking as BookingCPT;
use CommonsBooking\Wordpress\CustomPostType\Item as ItemCPT;
use CommonsBooking\Wordpress\CustomPostType\Location as LocationCPT;
use CommonsBooking\Wordpress\CustomPostType\Timeframe as TimeframeCPT;

/**
 * Optional index of the timeframes that affect availability.
 *
 * Timeframes store their locations and items as serialized meta arrays, which can only be
 * queried with LIKE '%:"ID";%' scans. This index holds the same relations in two junction
 * tables so they can be resolved with indexed integer joins.
 *
 * The feature is opt-in and self-contained: it registers its own hooks, owns its own tables
 * and reconciles them with the setting. Enabling it creates and fills the tables, disabling it
 * removes them again, so a site can try it and later stop without leaving anything behind.
 *
 * It is integrated in exactly two places:
 *  - Plugin::init()                        calls register()
 *  - Repository\Timeframe::getPostIdsByType() calls getPostIdsByType()
 */
class AvailabilityIndex {

	public static string $indexTable     = 'cb_availability_index';
	public static string $locationsTable = 'cb_timeframe_locations';
	public static string $itemsTable     = 'cb_timeframe_items';

	/**
	 * Settings tab and field the feature is switched on with.
	 */
	private const OPTION_KEY   = COMMONSBOOKING_PLUGIN_SLUG . '_options_advanced-options';
	private const OPTION_FIELD = 'availability_index_enabled';

	/**
	 * Set while the tables exist, so reconcile() knows what to create or drop.
	 */
	private const ACTIVE_OPTION = 'cb_availability_index_active';

	/**
	 * Set while the tables exist but have not been filled yet. Reads fall back to the
	 * default query until the rebuild has run.
	 */
	private const REBUILD_OPTION = 'cb_availability_index_needs_rebuild';

	public const AJAX_ACTION = 'cb_rebuild_availability_index';

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
	 * Only transient and deleted posts are kept out of the index.
	 *
	 * Repository\Timeframe::getPostIdsByType() does not filter by post status at all, that
	 * happens further down in getPostsByBaseParams(). Anything narrower than this would make
	 * the index return fewer posts than the query it replaces - callers do ask for canceled
	 * bookings, for instance.
	 */
	private const SKIP_STATUSES = [ 'auto-draft', 'trash' ];

	/**
	 * Statuses that count towards availability, used as the default of the date range lookup.
	 */
	public const AVAILABILITY_STATUSES = [ 'publish', 'confirmed', 'unconfirmed' ];

	/**
	 * The single integration point of the write path. Called from Plugin::init().
	 */
	public static function register(): void {
		// Create or drop the tables when the setting is switched.
		add_action( 'admin_init', array( self::class, 'reconcile' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( self::class, 'ajaxRebuild' ) );

		// Keep the index in sync. save_post fires inside wp_insert_post(), before the
		// wp_insert_post action, so these always see fully written meta.
		add_action( 'wp_insert_post', array( self::class, 'onSavePost' ), 12, 2 );
		add_action( 'untrashed_post', array( self::class, 'onSavePost' ), 10, 1 );
		add_action( 'wp_trash_post', array( self::class, 'onDeletePost' ), 10, 1 );
		add_action( 'before_delete_post', array( self::class, 'onDeletePost' ), 10, 1 );

		// Adding or removing an item/location changes which timeframes apply to it.
		add_action( 'save_post', array( self::class, 'onSaveItemOrLocation' ), 12, 2 );
	}

	/**
	 * Makes the tables match the setting. Idempotent, so it can run on every admin request.
	 */
	public static function reconcile(): void {
		$wanted = self::isSwitchedOn();
		$active = (bool) get_option( self::ACTIVE_OPTION );

		if ( $wanted === $active ) {
			return;
		}

		if ( $wanted ) {
			self::initTables();
			update_option( self::ACTIVE_OPTION, 1 );
			update_option( self::REBUILD_OPTION, 1 );
		} else {
			self::dropTables();
			delete_option( self::ACTIVE_OPTION );
			delete_option( self::REBUILD_OPTION );
		}
	}

	/**
	 * Whether the setting is checked.
	 */
	public static function isSwitchedOn(): bool {
		return Settings::getOption( self::OPTION_KEY, self::OPTION_FIELD ) === 'on';
	}

	/**
	 * Whether the index may be written to, meaning the tables exist.
	 */
	public static function isEnabled(): bool {
		return self::isSwitchedOn() && (bool) get_option( self::ACTIVE_OPTION );
	}

	/**
	 * Whether the index may be read from. False until the initial rebuild has run, so a
	 * freshly enabled index never answers with a half filled table.
	 */
	public static function isReadable(): bool {
		return self::isEnabled() && ! get_option( self::REBUILD_OPTION );
	}

	/**
	 * The post types held in the index.
	 *
	 * @return string[]
	 */
	public static function getIndexedPostTypes(): array {
		return array( TimeframeCPT::$postType, BookingCPT::$postType );
	}

	/**
	 * Returns the ids of all indexed timeframes matching the given types, items and locations.
	 *
	 * Mirrors Repository\Timeframe::getPostIdsByType() so it can stand in for it. An empty
	 * $items or $locations means "any", exactly as in the query it replaces, and the post
	 * status is deliberately not filtered here either.
	 *
	 * @return int[]|null Null when the feature is off, so the caller falls back.
	 */
	public static function getPostIdsByType( array $types = array(), array $items = array(), array $locations = array() ): ?array {
		if ( ! self::isReadable() ) {
			return null;
		}

		$types = $types ? array_map( 'intval', $types ) : self::INDEXED_TYPES;

		// The index only holds the availability types. Anything else - a canceled booking
		// timeframe during the 2.6.0 migration, for instance - has to fall back, otherwise
		// those posts would silently go missing.
		if ( array_diff( $types, self::INDEXED_TYPES ) ) {
			return null;
		}

		$items     = array_map( 'intval', array_filter( $items ) );
		$locations = array_map( 'intval', array_filter( $locations ) );

		global $wpdb;

		$indexTable = $wpdb->prefix . self::$indexTable;
		$joins      = '';

		if ( $locations ) {
			$joins .= sprintf(
				' JOIN %s tl ON tl.timeframe_id = ai.timeframe_id AND tl.location_id IN (%s)',
				$wpdb->prefix . self::$locationsTable,
				implode( ', ', $locations )
			);
		}

		if ( $items ) {
			$joins .= sprintf(
				' JOIN %s ti ON ti.timeframe_id = ai.timeframe_id AND ti.item_id IN (%s)',
				$wpdb->prefix . self::$itemsTable,
				implode( ', ', $items )
			);
		}

		$ids = $wpdb->get_col(
			"SELECT DISTINCT ai.timeframe_id FROM $indexTable ai" . $joins .
			' WHERE ai.type IN (' . implode( ', ', $types ) . ')'
		);

		return array_map( 'intval', $ids );
	}

	/**
	 * Returns the index rows of all timeframes that apply to a location/item pair and overlap
	 * the given date range.
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
		array $postStatuses = self::AVAILABILITY_STATUSES
	): array {
		global $wpdb;

		$indexTable     = $wpdb->prefix . self::$indexTable;
		$locationsTable = $wpdb->prefix . self::$locationsTable;
		$itemsTable     = $wpdb->prefix . self::$itemsTable;

		$typePlaceholders   = implode( ', ', array_fill( 0, count( $types ), '%d' ) );
		$statusPlaceholders = implode( ', ', array_fill( 0, count( $postStatuses ), '%s' ) );

		return $wpdb->get_results(
			$wpdb->prepare(
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
			)
		);
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
			in_array( $timeframe->post_status, self::SKIP_STATUSES, true ) ||
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
	 * Indexes a saved or restored timeframe/booking.
	 */
	public static function onSavePost( int $postId, ?\WP_Post $post = null ): void {
		if ( ! self::isEnabled() ) {
			return;
		}

		$post = $post ?: get_post( $postId );

		if ( $post && in_array( $post->post_type, self::getIndexedPostTypes(), true ) ) {
			self::upsertTimeframe( new Timeframe( $post ) );
		}
	}

	/**
	 * Removes trashed and deleted posts. Deleting a location or item only drops its
	 * relations, the timeframes themselves stay indexed.
	 */
	public static function onDeletePost( int $postId ): void {
		if ( ! self::isEnabled() ) {
			return;
		}

		$post = get_post( $postId );
		if ( ! $post ) {
			return;
		}

		if ( in_array( $post->post_type, self::getIndexedPostTypes(), true ) ) {
			self::deleteByTimeframeId( $postId );
		} elseif ( $post->post_type === LocationCPT::getPostType() ) {
			self::removeLocation( $postId );
		} elseif ( $post->post_type === ItemCPT::getPostType() ) {
			self::removeItem( $postId );
		}
	}

	/**
	 * Reindexes the timeframes with a dynamic item/location selection after an item or
	 * location was saved. Runs after Item/Location::savePost(), which updates their meta.
	 */
	public static function onSaveItemOrLocation( int $postId, ?\WP_Post $post = null ): void {
		if ( ! self::isEnabled() ) {
			return;
		}

		$post = $post ?: get_post( $postId );
		if ( ! $post || ! in_array( $post->post_type, array( ItemCPT::getPostType(), LocationCPT::getPostType() ), true ) ) {
			return;
		}

		$timeframes = \CommonsBooking\Repository\Timeframe::get(
			array(),
			array(),
			array( TimeframeCPT::HOLIDAYS_ID, TimeframeCPT::REPAIR_ID )
		);

		foreach ( $timeframes as $timeframe ) {
			self::upsertTimeframe( new Timeframe( $timeframe->ID ) );
		}
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
	 * Removes the index tables again, so switching the feature off leaves nothing behind.
	 */
	public static function dropTables(): void {
		global $wpdb;

		foreach ( array( self::$indexTable, self::$locationsTable, self::$itemsTable ) as $table ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . $table );
		}
	}

	/**
	 * Rebuilds the index from all existing timeframes and bookings.
	 * Paginated: returns true when done, the next page otherwise.
	 *
	 * @return int|bool
	 */
	public static function rebuildFromAllTimeframes( int $page = 1 ) {
		global $wpdb;

		if ( $page === 1 ) {
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
				'post_status' => 'any',
			)
		);

		foreach ( $response->posts as $post ) {
			self::upsertTimeframe( new Timeframe( $post ) );
		}

		if ( $response->done ) {
			delete_option( self::REBUILD_OPTION );

			return true;
		}

		return $page + 1;
	}

	/**
	 * Runs one page of the rebuild for the button on the settings page.
	 */
	public static function ajaxRebuild(): void {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'commonsbooking' ) ), 403 );
		}

		if ( ! self::isEnabled() ) {
			wp_send_json_error( array( 'message' => __( 'The availability index is not enabled.', 'commonsbooking' ) ), 400 );
		}

		$page   = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$result = self::rebuildFromAllTimeframes( max( 1, $page ) );

		wp_send_json_success(
			array(
				'done' => $result === true,
				'page' => $result === true ? $page : $result,
			)
		);
	}
}
