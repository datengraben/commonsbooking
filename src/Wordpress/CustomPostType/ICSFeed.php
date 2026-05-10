<?php

namespace CommonsBooking\Wordpress\CustomPostType;

use CommonsBooking\Service\ICSImport;

/**
 * Custom post type for external ICS calendar feed configuration.
 * Each post represents one ICS URL and the items/locations it should block.
 */
class ICSFeed extends CustomPostType {

	public static $postType = 'cb_ics_feed';

	/**
	 * @inheritDoc
	 */
	public static function getView() {
		return null;
	}

	public function __construct() {
		$this->listColumns = [
			'_cb_ics_feed_url'       => esc_html__( 'ICS URL', 'commonsbooking' ),
			'_cb_ics_feed_last_sync' => esc_html__( 'Last Sync', 'commonsbooking' ),
			'_cb_ics_feed_last_error'=> esc_html__( 'Status', 'commonsbooking' ),
		];
		$this->menuPosition = 7;
		$this->removeListDateColumn();
	}

	/**
	 * Initiates needed hooks.
	 */
	public function initHooks() {
		add_action( 'cmb2_admin_init', [ $this, 'registerMetabox' ] );
		add_action( 'save_post', [ $this, 'savePost' ], 11, 2 );
		add_action( 'admin_action_cb_ics_sync_now', [ self::class, 'handleSyncNow' ] );
		add_action( 'admin_notices', [ self::class, 'showSyncNotice' ] );
		add_filter( 'post_row_actions', [ self::class, 'addSyncNowRowAction' ], 10, 2 );
	}

	/**
	 * @inheritDoc
	 */
	public function getArgs(): array {
		$labels = [
			'name'               => esc_html__( 'ICS Calendar Feeds', 'commonsbooking' ),
			'singular_name'      => esc_html__( 'ICS Calendar Feed', 'commonsbooking' ),
			'add_new'            => esc_html__( 'Add new', 'commonsbooking' ),
			'add_new_item'       => esc_html__( 'Add new ICS Feed', 'commonsbooking' ),
			'edit_item'          => esc_html__( 'Edit ICS Feed', 'commonsbooking' ),
			'new_item'           => esc_html__( 'Add new ICS Feed', 'commonsbooking' ),
			'view_item'          => esc_html__( 'View ICS Feed', 'commonsbooking' ),
			'search_items'       => esc_html__( 'Search ICS Feeds', 'commonsbooking' ),
			'not_found'          => esc_html__( 'No ICS Feeds found', 'commonsbooking' ),
			'not_found_in_trash' => esc_html__( 'No ICS Feeds found in trash', 'commonsbooking' ),
			'all_items'          => esc_html__( 'All ICS Feeds', 'commonsbooking' ),
			'menu_name'          => esc_html__( 'ICS Calendar Sync', 'commonsbooking' ),
		];

		return [
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'menu_position'       => $this->menuPosition,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'capability_type'     => [ self::$postType, self::$postType . 's' ],
			'map_meta_cap'        => true,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'supports'            => [ 'title', 'author' ],
			'has_archive'         => false,
			'can_export'          => false,
			'show_in_rest'        => false,
		];
	}

	/**
	 * Registers the CMB2 metabox with all configuration fields.
	 */
	public function registerMetabox() {
		$cmb = new_cmb2_box( [
			'id'           => static::getPostType() . '-custom-fields',
			'title'        => esc_html__( 'ICS Feed Configuration', 'commonsbooking' ),
			'object_types' => [ static::getPostType() ],
		] );

		foreach ( $this->getCustomFields() as $field ) {
			$cmb->add_field( $field );
		}
	}

	/**
	 * Returns the CMB2 field definitions for this CPT.
	 */
	protected function getCustomFields(): array {
		$fields = [
			[
				'name' => esc_html__( 'ICS Calendar URL', 'commonsbooking' ),
				'desc' => esc_html__( 'Public URL of the ICS/iCalendar feed (must be accessible without login).', 'commonsbooking' ),
				'id'   => '_cb_ics_feed_url',
				'type' => 'text_url',
			],
			[
				'name'    => esc_html__( 'Items to block', 'commonsbooking' ),
				'desc'    => esc_html__( 'Items that become unavailable during ICS events.', 'commonsbooking' ),
				'id'      => '_cb_ics_feed_item_ids',
				'type'    => 'multicheck',
				'options' => self::sanitizeOptions( \CommonsBooking\Repository\Item::getByCurrentUser() ),
			],
			[
				'name'    => esc_html__( 'Locations to block', 'commonsbooking' ),
				'desc'    => esc_html__( 'Locations where the selected items are blocked during ICS events.', 'commonsbooking' ),
				'id'      => '_cb_ics_feed_location_ids',
				'type'    => 'multicheck',
				'options' => self::sanitizeOptions( \CommonsBooking\Repository\Location::getByCurrentUser() ),
			],
			[
				'name'    => esc_html__( 'Import events this many days ahead', 'commonsbooking' ),
				'desc'    => esc_html__( 'Only events within this window are imported. Default: 180 days.', 'commonsbooking' ),
				'id'      => '_cb_ics_feed_lookahead_days',
				'type'    => 'text_small',
				'default' => '180',
				'attributes' => [ 'type' => 'number', 'min' => '1' ],
			],
			[
				'name'           => esc_html__( 'Sync status', 'commonsbooking' ),
				'id'             => '_cb_ics_feed_status_display',
				'type'           => 'title',
				'render_row_cb'  => [ self::class, 'renderSyncStatus' ],
			],
		];

		return $fields;
	}

	/**
	 * Renders the sync status row in the metabox (read-only).
	 */
	public static function renderSyncStatus( array $fieldArgs, \CMB2_Field $field ): void {
		$postId    = $field->object_id;
		$lastSync  = get_post_meta( $postId, '_cb_ics_feed_last_sync', true );
		$lastError = get_post_meta( $postId, '_cb_ics_feed_last_error', true );

		echo '<div class="cmb-row">';
		echo '<div class="cmb-th"><label>' . esc_html__( 'Sync status', 'commonsbooking' ) . '</label></div>';
		echo '<div class="cmb-td">';

		if ( $lastSync ) {
			printf(
				'<p>' . esc_html__( 'Last successful sync: %s', 'commonsbooking' ) . '</p>',
				esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $lastSync ) )
			);
		} else {
			echo '<p>' . esc_html__( 'Not yet synced.', 'commonsbooking' ) . '</p>';
		}

		if ( ! empty( $lastError ) ) {
			echo '<p style="color:red">' . esc_html( $lastError ) . '</p>';
		}

		if ( $postId && get_post_status( $postId ) === 'publish' ) {
			$syncUrl = wp_nonce_url(
				admin_url( 'admin.php?action=cb_ics_sync_now&post_id=' . $postId ),
				'cb_ics_sync_now_' . $postId
			);
			echo '<a href="' . esc_url( $syncUrl ) . '" class="button">' . esc_html__( 'Sync now', 'commonsbooking' ) . '</a>';
		}

		echo '</div></div>';
	}

	/**
	 * Adds a "Sync now" action link in the list view row.
	 */
	public static function addSyncNowRowAction( array $actions, \WP_Post $post ): array {
		if ( $post->post_type !== self::$postType || $post->post_status !== 'publish' ) {
			return $actions;
		}

		$syncUrl = wp_nonce_url(
			admin_url( 'admin.php?action=cb_ics_sync_now&post_id=' . $post->ID ),
			'cb_ics_sync_now_' . $post->ID
		);
		$actions['cb_sync_now'] = '<a href="' . esc_url( $syncUrl ) . '">' . esc_html__( 'Sync now', 'commonsbooking' ) . '</a>';

		return $actions;
	}

	/**
	 * Handles the "Sync now" admin action.
	 */
	public static function handleSyncNow(): void {
		$postId = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
		if ( ! $postId || ! check_admin_referer( 'cb_ics_sync_now_' . $postId ) ) {
			wp_die( esc_html__( 'Invalid request.', 'commonsbooking' ) );
		}

		if ( ! current_user_can( 'manage_' . COMMONSBOOKING_PLUGIN_SLUG ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'commonsbooking' ) );
		}

		ICSImport::syncFeed( $postId );

		wp_safe_redirect(
			add_query_arg(
				[ 'post_type' => self::$postType, 'cb_ics_synced' => 1 ],
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Displays an admin notice after a manual sync.
	 */
	public static function showSyncNotice(): void {
		if (
			isset( $_GET['cb_ics_synced'] ) &&
			isset( $_GET['post_type'] ) &&
			sanitize_key( $_GET['post_type'] ) === self::$postType
		) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'ICS feed synced successfully.', 'commonsbooking' );
			echo '</p></div>';
		}
	}

	/**
	 * Modifies data in custom list columns.
	 */
	public function setCustomColumnsData( $column, $post_id ) {
		switch ( $column ) {
			case '_cb_ics_feed_url':
				$url = get_post_meta( $post_id, '_cb_ics_feed_url', true );
				echo $url ? '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $url ) . '</a>' : '—';
				break;

			case '_cb_ics_feed_last_sync':
				$ts = get_post_meta( $post_id, '_cb_ics_feed_last_sync', true );
				echo $ts ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) ) : esc_html__( 'Never', 'commonsbooking' );
				break;

			case '_cb_ics_feed_last_error':
				$err = get_post_meta( $post_id, '_cb_ics_feed_last_error', true );
				if ( empty( $err ) ) {
					$lastSync = get_post_meta( $post_id, '_cb_ics_feed_last_sync', true );
					echo $lastSync ? '<span style="color:green">&#10003; ' . esc_html__( 'OK', 'commonsbooking' ) . '</span>' : '—';
				} else {
					echo '<span style="color:red">' . esc_html( $err ) . '</span>';
				}
				break;
		}
	}

	/**
	 * Handles saving the post — currently no extra logic needed beyond CMB2.
	 */
	public function savePost( $post_id, $post ) {
		if ( $post->post_type !== self::$postType ) {
			return;
		}
		if ( $this->hasRunBefore( __METHOD__ ) ) {
			return;
		}
	}
}
