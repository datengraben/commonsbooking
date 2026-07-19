<?php

namespace CommonsBooking\Service;

use CommonsBooking\Model\Timeframe as TimeframeModel;
use CommonsBooking\Wordpress\CustomPostType\Item;
use CommonsBooking\Wordpress\CustomPostType\Location;
use CommonsBooking\Wordpress\CustomPostType\Timeframe;

/**
 * One-click prefill of the CommonsBooking catalog from an existing
 * WP Inventory Manager installation.
 *
 * MVP scope: if WP Inventory Manager is present, offer a single admin-notice
 * button that mirrors every WP Inventory item into a bookable CommonsBooking
 * item (attached to a single default location, with an open bookable
 * timeframe) so the booking calendar works right away.
 *
 * Deliberately out of scope: ongoing sync, booking -> stock write-back,
 * WP Inventory locations, quantities/counts, images and rich fields.
 */
class WPInventoryImport {

	/**
	 * Post meta on a cb_item that stores the source WP Inventory id.
	 * Used to avoid importing the same item twice.
	 */
	public const SOURCE_ID_META = '_cb_wpi_source_id';

	/**
	 * Post meta flag marking the location we auto-created for the import.
	 */
	public const DEFAULT_LOCATION_META = '_cb_wpi_default_location';

	/**
	 * Option flag set once the import has been run, to hide the notice.
	 */
	public const DONE_OPTION = 'commonsbooking_wpi_import_done';

	/**
	 * admin-post.php action name for the import handler.
	 */
	public const ACTION = 'cb_wpi_import';

	/**
	 * Registers admin hooks. Only does anything when WP Inventory is present.
	 */
	public static function init(): void {
		if ( ! self::isWpInventoryActive() ) {
			return;
		}

		add_action( 'admin_notices', array( self::class, 'renderNotice' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handleImport' ) );
	}

	/**
	 * Detects whether WP Inventory Manager is installed by looking for its
	 * database table.
	 */
	public static function isWpInventoryActive(): bool {
		global $wpdb;
		$table = self::getTableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Detecting a third-party plugin's own table; no CB table or cache applies.
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * @return string WP Inventory main items table name.
	 */
	protected static function getTableName(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpinventory';
	}

	/**
	 * Shows the one-click import notice to users who may manage items.
	 */
	public static function renderNotice(): void {
		if ( get_option( self::DONE_OPTION ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_' . Item::$postType . 's' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$count = count( self::readItems() );
		if ( $count === 0 ) {
			return;
		}

		$message = sprintf(
			/* translators: %d: number of WP Inventory items */
			__( 'WP Inventory detected: import %d items into CommonsBooking and enable bookings for them?', 'commonsbooking' ),
			$count
		);
		?>
		<div class="notice notice-info">
			<p><?php echo esc_html( $message ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<?php wp_nonce_field( self::ACTION ); ?>
				<p>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Import items and enable bookings', 'commonsbooking' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Handles the import form submission, then redirects back to the item list.
	 */
	public static function handleImport(): void {
		check_admin_referer( self::ACTION );

		if ( ! current_user_can( 'manage_' . Item::$postType . 's' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to import items.', 'commonsbooking' ) );
		}

		self::import();
		update_option( self::DONE_OPTION, 1 );

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Item::$postType ) );
		exit;
	}

	/**
	 * Runs the import: ensures a default location, then creates a bookable
	 * item + timeframe for every not-yet-imported WP Inventory item.
	 *
	 * @return int Number of newly imported items.
	 */
	public static function import(): int {
		$locationId = self::ensureDefaultLocation();
		$imported   = 0;

		foreach ( self::readItems() as $wpiItem ) {
			if ( self::alreadyImported( $wpiItem['id'] ) ) {
				continue;
			}

			$itemId = self::createItem( $wpiItem );
			if ( ! $itemId ) {
				continue;
			}

			self::createBookableTimeframe( $itemId, $locationId );
			$imported++;
		}

		return $imported;
	}

	/**
	 * Reads WP Inventory items as a list of ['id' => int, 'name' => string].
	 *
	 * @return array<int, array{id:int, name:string}>
	 */
	protected static function readItems(): array {
		global $wpdb;
		$table = self::getTableName();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading a third-party plugin's own table; name is derived internally, not user input.
		$rows = $wpdb->get_results( "SELECT inventory_id, inventory_name FROM {$table}", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$items = array();
		foreach ( $rows as $row ) {
			$id = isset( $row['inventory_id'] ) ? intval( $row['inventory_id'] ) : 0;
			if ( $id <= 0 ) {
				continue;
			}
			$items[] = array(
				'id'   => $id,
				'name' => isset( $row['inventory_name'] ) ? (string) $row['inventory_name'] : '',
			);
		}

		return $items;
	}

	/**
	 * @param int $sourceId WP Inventory item id.
	 *
	 * @return bool True if a cb_item already references this source id.
	 */
	protected static function alreadyImported( int $sourceId ): bool {
		$existing = get_posts(
			array(
				'post_type'   => Item::$postType,
				'post_status' => 'any',
				'meta_key'    => self::SOURCE_ID_META,
				'meta_value'  => $sourceId,
				'fields'      => 'ids',
				'numberposts' => 1,
			)
		);

		return ! empty( $existing );
	}

	/**
	 * Creates a published cb_item for a WP Inventory item.
	 *
	 * @param array{id:int, name:string} $wpiItem
	 *
	 * @return int New item post id, or 0 on failure.
	 */
	protected static function createItem( array $wpiItem ): int {
		$title = $wpiItem['name'] !== '' ? $wpiItem['name'] : sprintf(
			/* translators: %d: WP Inventory item id */
			esc_html__( 'Item %d', 'commonsbooking' ),
			$wpiItem['id']
		);

		$itemId = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_type'   => Item::$postType,
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $itemId ) || ! $itemId ) {
			return 0;
		}

		update_post_meta( $itemId, self::SOURCE_ID_META, $wpiItem['id'] );

		return $itemId;
	}

	/**
	 * Finds the auto-created default location, creating it if needed.
	 *
	 * @return int Location post id.
	 */
	protected static function ensureDefaultLocation(): int {
		$existing = get_posts(
			array(
				'post_type'   => Location::$postType,
				'post_status' => 'any',
				'meta_key'    => self::DEFAULT_LOCATION_META,
				'meta_value'  => 1,
				'fields'      => 'ids',
				'numberposts' => 1,
			)
		);

		if ( ! empty( $existing ) ) {
			return (int) $existing[0];
		}

		$locationId = wp_insert_post(
			array(
				'post_title'  => esc_html__( 'Default location', 'commonsbooking' ),
				'post_type'   => Location::$postType,
				'post_status' => 'publish',
			)
		);

		update_post_meta( $locationId, self::DEFAULT_LOCATION_META, 1 );

		return (int) $locationId;
	}

	/**
	 * Creates an open, full-day, daily bookable timeframe binding one item to
	 * one location. Mirrors the meta set CommonsBooking itself writes.
	 *
	 * @param int $itemId
	 * @param int $locationId
	 *
	 * @return int Timeframe post id.
	 */
	protected static function createBookableTimeframe( int $itemId, int $locationId ): int {
		$timeframeId = wp_insert_post(
			array(
				'post_title'  => esc_html__( 'Bookable', 'commonsbooking' ),
				'post_type'   => Timeframe::$postType,
				'post_status' => 'publish',
			)
		);

		update_post_meta( $timeframeId, 'type', Timeframe::BOOKABLE_ID );
		update_post_meta( $timeframeId, TimeframeModel::META_ITEM_ID, $itemId );
		update_post_meta( $timeframeId, TimeframeModel::META_LOCATION_ID, $locationId );
		update_post_meta( $timeframeId, TimeframeModel::META_ITEM_SELECTION_TYPE, TimeframeModel::SELECTION_MANUAL_ID );
		update_post_meta( $timeframeId, TimeframeModel::META_LOCATION_SELECTION_TYPE, TimeframeModel::SELECTION_MANUAL_ID );
		update_post_meta( $timeframeId, 'full-day', 'on' );
		update_post_meta( $timeframeId, 'grid', 0 );
		update_post_meta( $timeframeId, TimeframeModel::META_REPETITION, 'd' );
		update_post_meta( $timeframeId, TimeframeModel::REPETITION_START, strtotime( 'today' ) );
		update_post_meta( $timeframeId, TimeframeModel::REPETITION_END, strtotime( '+5 years' ) );
		update_post_meta( $timeframeId, 'start-time', '00:00' );
		update_post_meta( $timeframeId, 'end-time', '23:59' );
		update_post_meta( $timeframeId, 'weekdays', array( '1', '2', '3', '4', '5', '6', '7' ) );
		update_post_meta( $timeframeId, TimeframeModel::META_MAX_DAYS, 3 );
		update_post_meta( $timeframeId, TimeframeModel::META_TIMEFRAME_ADVANCE_BOOKING_DAYS, 30 );
		update_post_meta( $timeframeId, TimeframeModel::META_BOOKING_START_DAY_OFFSET, 0 );
		update_post_meta( $timeframeId, TimeframeModel::META_MANUAL_SELECTION, '' );
		update_post_meta( $timeframeId, TimeframeModel::META_SHOW_BOOKING_CODES, 'off' );
		update_post_meta( $timeframeId, TimeframeModel::META_CREATE_BOOKING_CODES, 'off' );

		return (int) $timeframeId;
	}
}
