<?php

namespace CommonsBooking\Wordpress\Compat;

/**
 * Compatibility layer for Jetpack by Automattic.
 *
 * Hooks in this class only run when Jetpack is active, so they have zero
 * cost on sites without Jetpack.
 */
class JetpackCompat {

	/**
	 * Registers all Jetpack compatibility filters. No-op if Jetpack is not active.
	 */
	public static function init(): void {
		if ( ! defined( 'JETPACK__VERSION' ) ) {
			return;
		}

		// Prevent booking posts (personal data) from syncing to WordPress.com via Jetpack Sync.
		add_filter( 'jetpack_sync_blacklisted_post_types', array( static::class, 'excludePrivatePostTypesFromSync' ) );

		// Prevent Jetpack Photon image CDN from rewriting URLs of map marker assets served
		// from the plugin directory — Photon cannot proxy non-media-library files and would
		// produce broken image URLs.
		add_filter( 'jetpack_photon_skip_for_url', array( static::class, 'skipPhotonForMapAssets' ), 10, 2 );

		// Prevent Jetpack Image Accelerator from lazy-loading images inside CB map containers.
		// Leaflet populates these containers dynamically via JS; adding the lazy attribute
		// before render causes tiles and markers to never load.
		add_filter( 'jetpack_lazy_images_blacklisted_classes', array( static::class, 'skipLazyImagesInMapContainers' ) );
	}

	/**
	 * Exclude cb_booking and cb_restriction from Jetpack Sync so that personal booking
	 * data is not transmitted to WordPress.com infrastructure.
	 *
	 * @param string[] $post_types Post types currently blacklisted from Jetpack Sync.
	 * @return string[]
	 */
	public static function excludePrivatePostTypesFromSync( array $post_types ): array {
		$post_types[] = 'cb_booking';
		$post_types[] = 'cb_restriction';
		return $post_types;
	}

	/**
	 * Skip Jetpack Photon for URLs pointing to CB plugin asset directories.
	 * Photon only handles media-library images; trying to proxy plugin asset URLs
	 * results in broken references.
	 *
	 * @param bool   $skip      Whether Photon is already set to skip this URL.
	 * @param string $image_url The image URL being evaluated.
	 * @return bool
	 */
	public static function skipPhotonForMapAssets( bool $skip, string $image_url ): bool {
		if ( $skip ) {
			return $skip;
		}
		// CB map assets are served from the plugin directory, not the uploads directory.
		if ( strpos( $image_url, 'commonsbooking' ) !== false && strpos( $image_url, '/plugins/' ) !== false ) {
			return true;
		}
		return $skip;
	}

	/**
	 * Add CB map container classes to the Jetpack Lazy Images class blacklist so
	 * that images inside Leaflet map wrappers are never lazy-loaded by Jetpack.
	 *
	 * @param string[] $blacklisted_classes CSS classes already excluded from Jetpack lazy loading.
	 * @return string[]
	 */
	public static function skipLazyImagesInMapContainers( array $blacklisted_classes ): array {
		$blacklisted_classes[] = 'cb-map-container';
		$blacklisted_classes[] = 'leaflet-container';
		return $blacklisted_classes;
	}
}
