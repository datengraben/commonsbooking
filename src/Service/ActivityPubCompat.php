<?php

namespace CommonsBooking\Service;

use CommonsBooking\Messages\AdminMessage;
use CommonsBooking\Wordpress\CustomPostType\Item;
use CommonsBooking\Wordpress\CustomPostType\Location;

/**
 * Compatibility layer for the ActivityPub plugin (https://wordpress.org/plugins/activitypub/).
 *
 * All hooks here are no-ops unless the ActivityPub plugin is active, so this class is always
 * safe to initialize.
 */
class ActivityPubCompat {

	/**
	 * True while the ActivityPub plugin is generating the federated representation of a post.
	 *
	 * @var bool
	 */
	private static bool $isGeneratingActivityPubContent = false;

	/**
	 * Registers the compatibility hooks. Does nothing if the ActivityPub plugin isn't active.
	 */
	public static function initHooks() {
		if ( ! class_exists( '\Activitypub\Activitypub' ) ) {
			return;
		}

		// Item and Location append their booking widget (calendar, booking form) to `the_content`
		// for frontend rendering. That markup is meaningless outside the CommonsBooking frontend
		// and must not leak into federated Notes/Articles, so we flag AP content generation and
		// let Item::getTemplate() / Location::getTemplate() skip themselves for its duration.
		add_action( 'activitypub_before_get_content', array( self::class, 'startContentGeneration' ) );
		add_filter( 'activitypub_the_content', array( self::class, 'stopContentGeneration' ), 5 );

		// Point admins at the ActivityPub plugin's own "Post Types" setting, since Items and
		// Locations are eligible (public + REST-enabled) but not enabled for federation by default.
		add_action( 'admin_notices', array( self::class, 'maybeShowSettingsNotice' ) );
	}

	/**
	 * Shows a one-line pointer to the ActivityPub post-type settings on the CommonsBooking
	 * dashboard, as long as Items/Locations aren't already enabled for federation.
	 */
	public static function maybeShowSettingsNotice() {
		global $plugin_page;

		if ( 'cb-dashboard' !== $plugin_page ) {
			return;
		}

		$supportedPostTypes = (array) get_option( 'activitypub_support_post_types', array() );
		$cbPostTypes         = array( Item::$postType, Location::$postType );

		// Already enabled for both post types, nothing to point out.
		if ( ! array_diff( $cbPostTypes, $supportedPostTypes ) ) {
			return;
		}

		$settingsUrl = admin_url( 'options-general.php?page=activitypub' );

		$message = sprintf(
			// translators: %1$s and %2$s are opening/closing link tags to the ActivityPub settings page.
			__( 'The ActivityPub plugin is active. To let people follow your items and locations from Mastodon and other Fediverse apps, enable "Items" and "Locations" under %1$sSettings → ActivityPub → Post Types%2$s.', 'commonsbooking' ),
			'<a href="' . esc_url( $settingsUrl ) . '">',
			'</a>'
		);

		new AdminMessage( commonsbooking_sanitizeHTML( $message ) );
	}

	/**
	 * Fired on `activitypub_before_get_content`.
	 */
	public static function startContentGeneration() {
		self::$isGeneratingActivityPubContent = true;
	}

	/**
	 * Fired on `activitypub_the_content`, which runs once content generation is complete.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public static function stopContentGeneration( string $content ): string {
		self::$isGeneratingActivityPubContent = false;

		return $content;
	}

	/**
	 * Whether the ActivityPub plugin is currently building the federated representation of a post.
	 *
	 * @return bool
	 */
	public static function isGeneratingActivityPubContent(): bool {
		return self::$isGeneratingActivityPubContent;
	}
}
