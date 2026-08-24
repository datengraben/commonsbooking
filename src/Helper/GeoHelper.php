<?php

namespace CommonsBooking\Helper;

use CommonsBooking\Geocoder\Location;

/**
 * Wrapper for calling the geoCoder service.
 * Defaults to implementation of {@see NominatimGeoCodeService}.
 */
class GeoHelper {

	/**
	 * @var GeoCodeService Singleton instance
	 */
	private static GeoCodeService $geoCodeService;

	/**
	 * @see NominatimGeoCodeService::getAddressData() for default values and more information
	 * @param string $addressString
	 *
	 * @return Location|null
	 */
	public static function getAddressData( $addressString ): ?Location {
		if ( ! isset( self::$geoCodeService ) ) {
			self::resetGeoCoder();
		}
		return self::$geoCodeService->getAddressData( $addressString );
	}

	/**
	 * Configure the service implementation in use
	 *
	 * @param GeoCodeService $instance
	 *
	 * @return void
	 */
	public static function setGeoCodeServiceInstance( GeoCodeService $instance ): void {
		self::$geoCodeService = $instance;
	}

	public static function resetGeoCoder(): void {
		self::setGeoCodeServiceInstance( new NominatimGeoCodeService() );
	}

	/**
	 * Mean earth radius in kilometers as used by the haversine formula.
	 */
	private const EARTH_RADIUS_KM = 6371.0;

	/**
	 * Calculates the great-circle distance between two points on the earth
	 * using the haversine formula.
	 *
	 * This is a pure function (no WordPress or geocoder dependencies) so it can
	 * be unit tested in isolation and reused wherever a straight-line distance
	 * between two coordinates is needed (e.g. the [cb_nearby] shortcode).
	 *
	 * @param float $lat1 Latitude of the first point in decimal degrees.
	 * @param float $lon1 Longitude of the first point in decimal degrees.
	 * @param float $lat2 Latitude of the second point in decimal degrees.
	 * @param float $lon2 Longitude of the second point in decimal degrees.
	 *
	 * @return float Distance in kilometers.
	 */
	public static function getDistanceInKm( float $lat1, float $lon1, float $lat2, float $lon2 ): float {
		$lat1Rad  = deg2rad( $lat1 );
		$lat2Rad  = deg2rad( $lat2 );
		$deltaLat = deg2rad( $lat2 - $lat1 );
		$deltaLon = deg2rad( $lon2 - $lon1 );

		$a = sin( $deltaLat / 2 ) ** 2 +
			cos( $lat1Rad ) * cos( $lat2Rad ) *
			sin( $deltaLon / 2 ) ** 2;

		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

		return self::EARTH_RADIUS_KM * $c;
	}

	/**
	 * Ranks a set of geo-referenced candidates by their distance from an origin.
	 *
	 * Pure function so the [cb_nearby] shortcode ranking can be unit tested
	 * without touching the database. Candidates that are missing coordinates,
	 * that lie outside the (optional) maximum distance, or whose id is in the
	 * exclude list are dropped. The remaining candidates are sorted by ascending
	 * distance and optionally capped.
	 *
	 * @param array<int|string,array{lat: float|string, lon: float|string}> $candidates Map of post id => coordinates.
	 * @param float                                                         $originLat  Origin latitude in decimal degrees.
	 * @param float                                                         $originLon  Origin longitude in decimal degrees.
	 * @param float|null                                                    $maxDistanceKm Maximum distance in km, or null for no limit.
	 * @param int|null                                                      $maxResults    Maximum number of results, or null for no limit.
	 * @param array<int|string>                                             $excludeIds    Candidate ids to exclude (e.g. the origin post).
	 *
	 * @return array<int,array{id: int|string, distance: float}> Ordered list, nearest first.
	 */
	public static function rankByDistance(
		array $candidates,
		float $originLat,
		float $originLon,
		?float $maxDistanceKm = null,
		?int $maxResults = null,
		array $excludeIds = array()
	): array {
		$ranked = array();

		foreach ( $candidates as $id => $coordinates ) {
			if ( in_array( $id, $excludeIds ) ) {
				continue;
			}

			if ( ! isset( $coordinates['lat'], $coordinates['lon'] ) ) {
				continue;
			}

			$lat = (float) $coordinates['lat'];
			$lon = (float) $coordinates['lon'];

			// Skip candidates without usable coordinates.
			if ( 0.0 === $lat && 0.0 === $lon ) {
				continue;
			}

			$distance = self::getDistanceInKm( $originLat, $originLon, $lat, $lon );

			if ( null !== $maxDistanceKm && $distance > $maxDistanceKm ) {
				continue;
			}

			$ranked[] = array(
				'id'       => $id,
				'distance' => $distance,
			);
		}

		usort(
			$ranked,
			function ( $a, $b ) {
				return $a['distance'] <=> $b['distance'];
			}
		);

		if ( null !== $maxResults && $maxResults >= 0 ) {
			$ranked = array_slice( $ranked, 0, $maxResults );
		}

		return $ranked;
	}
}
