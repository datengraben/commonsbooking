<?php

namespace CommonsBooking\Wordpress\Compat;

/**
 * Compatibility layer for popular SEO plugins.
 *
 * Registers cb_item and cb_location with Yoast SEO and Rank Math so that
 * item and location pages are included in XML sitemaps and receive proper
 * SEO meta tags. The filters only bind when the respective plugin is active.
 */
class SeoCompat {

	public static function init(): void {
		// Yoast SEO
		if ( defined( 'WPSEO_VERSION' ) ) {
			add_filter( 'wpseo_accessible_post_types', array( static::class, 'addCbPostTypesToYoast' ) );
			add_filter( 'wpseo_sitemap_post_types_query_args', array( static::class, 'allowCbPostTypesInYoastSitemap' ), 10, 2 );
		}

		// Rank Math
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			add_filter( 'rank_math/sitemap/post_type', array( static::class, 'addCbPostTypesToRankMathSitemap' ), 10, 2 );
		}
	}

	/**
	 * Register cb_item and cb_location as accessible post types with Yoast SEO
	 * so that title/meta templates and the SEO score feature work for item and
	 * location pages.
	 *
	 * @param array $post_types
	 * @return array
	 */
	public static function addCbPostTypesToYoast( array $post_types ): array {
		$post_types['cb_item']     = get_post_type_object( 'cb_item' );
		$post_types['cb_location'] = get_post_type_object( 'cb_location' );
		return array_filter( $post_types ); // remove nulls if CPTs not registered yet
	}

	/**
	 * Ensure Yoast's sitemap generator includes cb_item and cb_location posts.
	 * Without this the sitemaps only cover built-in post types plus types that
	 * have has_archive set, which CB's CPTs do not.
	 *
	 * @param array  $args      WP_Query args used by Yoast to fetch posts.
	 * @param string $post_type The post type currently being processed.
	 * @return array
	 */
	public static function allowCbPostTypesInYoastSitemap( array $args, string $post_type ): array {
		if ( in_array( $post_type, [ 'cb_item', 'cb_location' ], true ) ) {
			// Force published posts to appear regardless of archive setting.
			$args['post_status'] = 'publish';
		}
		return $args;
	}

	/**
	 * Tell Rank Math to include cb_item and cb_location in its XML sitemap.
	 *
	 * @param bool   $include
	 * @param string $post_type
	 * @return bool
	 */
	public static function addCbPostTypesToRankMathSitemap( bool $include, string $post_type ): bool {
		if ( in_array( $post_type, [ 'cb_item', 'cb_location' ], true ) ) {
			return true;
		}
		return $include;
	}
}
