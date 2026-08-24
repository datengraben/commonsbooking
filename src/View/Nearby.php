<?php

namespace CommonsBooking\View;

use CommonsBooking\Helper\GeoHelper;
use CommonsBooking\Repository\Item as ItemRepository;
use CommonsBooking\Repository\Location as LocationRepository;
use CommonsBooking\Settings\Settings;
use CommonsBooking\Wordpress\CustomPostType\Item as ItemPostType;
use CommonsBooking\Wordpress\CustomPostType\Location as LocationPostType;
use Exception;
use WP_Post;

/**
 * Handles the [cb_nearby] shortcode.
 *
 * Renders a carousel of the nearest locations or items relative to an origin
 * coordinate. The origin can be given explicitly (lat/lon), read from meta
 * fields of the current post (lat_meta/lon_meta) or inherited from the global
 * post (a location provides its own coordinates, an item the coordinates of
 * its bookable locations).
 *
 * The distance calculation and ranking live in {@see GeoHelper} so they can be
 * unit tested without the database.
 */
class Nearby extends View {

	/**
	 * Options key of the tab holding the global nearby configuration.
	 */
	public const OPTIONS_KEY = COMMONSBOOKING_PLUGIN_SLUG . '_options_templates';

	/**
	 * Fallback maximum distance (km) when neither the shortcode nor the global
	 * configuration provides one.
	 */
	public const DEFAULT_MAX_DISTANCE = 20;

	/**
	 * Fallback maximum number of result cards.
	 */
	public const DEFAULT_MAX_RESULTS = 9;

	/**
	 * Fallback number of cards shown at once in the carousel.
	 */
	public const DEFAULT_VISIBLE = 3;

	/**
	 * Allowed shortcode attributes with their defaults.
	 *
	 * @var array<string,string>
	 */
	protected static $nearbyShortCodeArgs = array(
		'type'         => 'locations',
		'max_distance' => '',
		'max_results'  => '',
		'visible'      => '',
		'post_id'      => '',
		'lat'          => '',
		'lon'          => '',
		'lat_meta'     => '',
		'lon_meta'     => '',
	);

	/**
	 * [cb_nearby] shortcode handler.
	 *
	 * @param array|string $atts
	 *
	 * @return string
	 * @throws Exception
	 */
	public static function shortcode( $atts ): string {
		$atts   = shortcode_atts( static::$nearbyShortCodeArgs, is_array( $atts ) ? $atts : array(), 'cb_nearby' );
		$config = self::resolveConfig( $atts );

		return self::render( $config );
	}

	/**
	 * Renders the nearby carousel below an item detail page when the global
	 * option is enabled. Hooked into `commonsbooking_after_item-single`.
	 *
	 * @param int $postId
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function renderOnItemSingle( $postId ): void {
		if ( ! self::boolOption( 'nearby_enable_on_item' ) ) {
			return;
		}
		$type = Settings::getOption( self::OPTIONS_KEY, 'nearby_type_on_item' ) ?: 'items';
		echo self::renderForPost( (int) $postId, $type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output is escaped in template.
	}

	/**
	 * Renders the nearby carousel below a location detail page when the global
	 * option is enabled. Hooked into `commonsbooking_after_location-single`.
	 *
	 * @param int $postId
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function renderOnLocationSingle( $postId ): void {
		if ( ! self::boolOption( 'nearby_enable_on_location' ) ) {
			return;
		}
		$type = Settings::getOption( self::OPTIONS_KEY, 'nearby_type_on_location' ) ?: 'locations';
		echo self::renderForPost( (int) $postId, $type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output is escaped in template.
	}

	/**
	 * Convenience wrapper used by the global auto-injection to render for a
	 * specific post id and type using the global configuration.
	 *
	 * @param int    $postId
	 * @param string $type
	 *
	 * @return string
	 * @throws Exception
	 */
	protected static function renderForPost( int $postId, string $type ): string {
		$config = self::resolveConfig(
			array_merge(
				static::$nearbyShortCodeArgs,
				array(
					'type'    => $type,
					'post_id' => $postId,
				)
			)
		);

		return self::render( $config );
	}

	/**
	 * Merges shortcode attributes with the global configuration, applying the
	 * precedence rules.
	 *
	 * By default a local shortcode parameter wins over the global default. When
	 * the "global overrides local" option is enabled and a global value is set,
	 * the global value wins instead.
	 *
	 * @param array<string,mixed> $atts
	 *
	 * @return array<string,mixed>
	 */
	protected static function resolveConfig( array $atts ): array {
		$overrideLocal = self::boolOption( 'nearby_override_local' );

		$maxDistance = self::pick(
			$atts['max_distance'] ?? '',
			Settings::getOption( self::OPTIONS_KEY, 'nearby_max_distance' ),
			$overrideLocal
		);
		$maxResults  = self::pick(
			$atts['max_results'] ?? '',
			Settings::getOption( self::OPTIONS_KEY, 'nearby_max_results' ),
			$overrideLocal
		);
		$visible     = self::pick(
			$atts['visible'] ?? '',
			Settings::getOption( self::OPTIONS_KEY, 'nearby_visible' ),
			$overrideLocal
		);

		$type = strtolower( trim( (string) ( $atts['type'] ?? 'locations' ) ) );
		if ( ! in_array( $type, array( 'items', 'locations' ), true ) ) {
			$type = 'locations';
		}

		$postId = ! empty( $atts['post_id'] ) ? (int) $atts['post_id'] : (int) get_the_ID();

		return array(
			'type'         => $type,
			'post_id'      => $postId,
			'max_distance' => is_numeric( $maxDistance ) ? (float) $maxDistance : (float) self::DEFAULT_MAX_DISTANCE,
			'max_results'  => is_numeric( $maxResults ) ? (int) $maxResults : self::DEFAULT_MAX_RESULTS,
			'visible'      => is_numeric( $visible ) && (int) $visible > 0 ? (int) $visible : self::DEFAULT_VISIBLE,
			'origin'       => self::resolveOrigin( $atts, $postId ),
		);
	}

	/**
	 * Applies the local-vs-global precedence for a single value.
	 *
	 * @param mixed $localValue
	 * @param mixed $globalValue
	 * @param bool  $overrideLocal
	 *
	 * @return mixed
	 */
	protected static function pick( $localValue, $globalValue, bool $overrideLocal ) {
		$globalSet = ! ( $globalValue === false || $globalValue === '' || $globalValue === null );

		if ( $overrideLocal && $globalSet ) {
			return $globalValue;
		}

		if ( ! ( $localValue === '' || $localValue === null ) ) {
			return $localValue;
		}

		return $globalSet ? $globalValue : '';
	}

	/**
	 * Resolves the origin coordinates from the shortcode attributes / post.
	 *
	 * Order of precedence:
	 *  1. explicit `lat` + `lon` attributes
	 *  2. `lat_meta` + `lon_meta` read from the given post's meta fields
	 *  3. the post's own coordinates (location) or those of its locations (item)
	 *
	 * @param array<string,mixed> $atts
	 * @param int                 $postId
	 *
	 * @return array{lat: float, lon: float}|null
	 * @throws Exception
	 */
	protected static function resolveOrigin( array $atts, int $postId ): ?array {
		// 1. explicit coordinates.
		if ( is_numeric( $atts['lat'] ?? '' ) && is_numeric( $atts['lon'] ?? '' ) ) {
			return array(
				'lat' => (float) $atts['lat'],
				'lon' => (float) $atts['lon'],
			);
		}

		// 2. coordinates read from meta fields of the current post.
		if ( ! empty( $atts['lat_meta'] ) && ! empty( $atts['lon_meta'] ) && $postId ) {
			$lat = get_post_meta( $postId, (string) $atts['lat_meta'], true );
			$lon = get_post_meta( $postId, (string) $atts['lon_meta'], true );
			if ( is_numeric( $lat ) && is_numeric( $lon ) ) {
				return array(
					'lat' => (float) $lat,
					'lon' => (float) $lon,
				);
			}
		}

		// 3. inherit from the global post.
		if ( $postId ) {
			return self::getPostCoordinates( get_post( $postId ) );
		}

		return null;
	}

	/**
	 * Returns the coordinates for a post: a location's own geo meta, or the
	 * coordinates of the first geo-referenced location bookable for an item.
	 *
	 * @param WP_Post|null $post
	 *
	 * @return array{lat: float, lon: float}|null
	 * @throws Exception
	 */
	protected static function getPostCoordinates( ?WP_Post $post ): ?array {
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		if ( $post->post_type === LocationPostType::getPostType() ) {
			return self::coordinatesFromLocationId( $post->ID );
		}

		if ( $post->post_type === ItemPostType::getPostType() ) {
			$locations = LocationRepository::getByItem( $post->ID );
			foreach ( $locations as $location ) {
				$coordinates = self::coordinatesFromLocationId( $location->ID );
				if ( $coordinates ) {
					return $coordinates;
				}
			}
		}

		return null;
	}

	/**
	 * Reads the geo coordinates of a location from its post meta.
	 *
	 * @param int $locationId
	 *
	 * @return array{lat: float, lon: float}|null
	 */
	protected static function coordinatesFromLocationId( int $locationId ): ?array {
		$lat = get_post_meta( $locationId, 'geo_latitude', true );
		$lon = get_post_meta( $locationId, 'geo_longitude', true );

		if ( ! is_numeric( $lat ) || ! is_numeric( $lon ) ) {
			return null;
		}

		return array(
			'lat' => (float) $lat,
			'lon' => (float) $lon,
		);
	}

	/**
	 * Builds the ranked result set and renders the template.
	 *
	 * @param array<string,mixed> $config
	 *
	 * @return string
	 * @throws Exception
	 */
	protected static function render( array $config ): string {
		$origin = $config['origin'];

		// Without a resolvable origin there is nothing meaningful to show; render
		// nothing rather than a misleading "nothing nearby" notice (e.g. on a
		// location detail page that has no geo coordinates yet).
		if ( ! $origin ) {
			return '';
		}

		$results = $config['type'] === 'items'
			? self::getNearbyItems( $origin, $config )
			: self::getNearbyLocations( $origin, $config );

		return self::renderTemplate( $config['type'], $results, $config['visible'] );
	}

	/**
	 * Returns the nearest locations as an ordered list of id + distance.
	 *
	 * @param array{lat: float, lon: float} $origin
	 * @param array<string,mixed>           $config
	 *
	 * @return array<int,array{id: int, distance: float}>
	 * @throws Exception
	 */
	protected static function getNearbyLocations( array $origin, array $config ): array {
		$candidates = self::getLocationCandidates();

		return GeoHelper::rankByDistance(
			$candidates,
			$origin['lat'],
			$origin['lon'],
			$config['max_distance'],
			$config['max_results'],
			array( $config['post_id'] )
		);
	}

	/**
	 * Returns the nearest items as an ordered list of id + distance.
	 *
	 * Items have no coordinates of their own, so each item inherits the distance
	 * of its nearest geo-referenced location. Locations are ranked first, then
	 * expanded to their bookable items keeping the nearest occurrence.
	 *
	 * @param array{lat: float, lon: float} $origin
	 * @param array<string,mixed>           $config
	 *
	 * @return array<int,array{id: int, distance: float}>
	 * @throws Exception
	 */
	protected static function getNearbyItems( array $origin, array $config ): array {
		$candidates = self::getLocationCandidates();

		// Rank all locations within the radius (no result cap yet).
		$rankedLocations = GeoHelper::rankByDistance(
			$candidates,
			$origin['lat'],
			$origin['lon'],
			$config['max_distance'],
			null,
			array()
		);

		$items    = array();
		$seenItem = array();
		foreach ( $rankedLocations as $location ) {
			foreach ( ItemRepository::getByLocation( $location['id'] ) as $item ) {
				if ( isset( $seenItem[ $item->ID ] ) || $item->ID === $config['post_id'] ) {
					continue;
				}
				$seenItem[ $item->ID ] = true;
				$items[]               = array(
					'id'       => $item->ID,
					'distance' => $location['distance'],
				);

				if ( count( $items ) >= $config['max_results'] ) {
					return $items;
				}
			}
		}

		return $items;
	}

	/**
	 * Returns all published, geo-referenced locations as a candidate map.
	 *
	 * @return array<int,array{lat: float, lon: float}>
	 * @throws Exception
	 */
	protected static function getLocationCandidates(): array {
		$locations  = LocationRepository::get(
			array(
				'post_status' => 'publish',
				'meta_query'  => array(
					array(
						'key'     => 'geo_latitude',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'geo_longitude',
						'compare' => 'EXISTS',
					),
				),
			)
		);
		$candidates = array();
		foreach ( $locations as $location ) {
			$lat = $location->getMeta( 'geo_latitude' );
			$lon = $location->getMeta( 'geo_longitude' );
			if ( is_numeric( $lat ) && is_numeric( $lon ) ) {
				$candidates[ $location->ID ] = array(
					'lat' => (float) $lat,
					'lon' => (float) $lon,
				);
			}
		}

		return $candidates;
	}

	/**
	 * Renders the shortcode template for the given ranked result set.
	 *
	 * @param string                                     $type
	 * @param array<int,array{id: int, distance: float}> $results
	 * @param int                                        $visible
	 *
	 * @return string
	 */
	protected static function renderTemplate( string $type, array $results, int $visible ): string {
		global $templateData;
		$templateData = array(
			'type'    => $type,
			'results' => $results,
			'visible' => $visible,
		);

		self::enqueueAssets();

		ob_start();
		commonsbooking_get_template_part( 'shortcode', 'nearby', true, false, false );

		return ob_get_clean();
	}

	/**
	 * Enqueues the carousel styles and script (registered in Plugin).
	 *
	 * @return void
	 */
	protected static function enqueueAssets(): void {
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'cb-nearby' );
			wp_enqueue_script( 'cb-nearby' );
		}
	}

	/**
	 * Reads a checkbox-style option as a boolean.
	 *
	 * @param string $fieldId
	 *
	 * @return bool
	 */
	protected static function boolOption( string $fieldId ): bool {
		$value = Settings::getOption( self::OPTIONS_KEY, $fieldId );

		return $value === 'on' || $value === '1' || $value === 1 || $value === true;
	}
}
