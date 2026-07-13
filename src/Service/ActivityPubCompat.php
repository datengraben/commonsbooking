<?php

namespace CommonsBooking\Service;

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
