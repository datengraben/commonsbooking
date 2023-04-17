<?php

namespace CommonsBooking\Helper;

use Geocoder\Exception\Exception;
use Geocoder\Location;
use Geocoder\Provider\Nominatim\Nominatim;
use Geocoder\Query\GeocodeQuery;
use Geocoder\StatefulGeocoder;
use Http\Client\Curl\Client;

/**
 * Helper for geographic informations
 */
class GeoHelper {

	/**
	 * Computes location coordinates from address string, using the Nominatim api
	 *
	 * @param string $address Adress string best parsed as "Street, PostCode City Country".
	 *
	 * @return ?Location
	 * @throws Exception
	 */
	public static function getAddressData( $address ): ?Location {
		$defaultUserAgent = 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:47.0) Gecko/20100101 Firefox/47.0';

		$client = new Client(
			null,
			null,
			array(
				CURLOPT_SSL_VERIFYHOST => 0,
				CURLOPT_SSL_VERIFYPEER => 0,
			)
		);

		$provider = Nominatim::withOpenStreetMapServer(
			$client,
			array_key_exists( 'HTTP_USER_AGENT', $_SERVER ) ? (string) filter_input( 'string', wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : $defaultUserAgent
		);
		$geoCoder = new StatefulGeocoder( $provider, 'en' );

		try {
			$addresses = $geoCoder->geocodeQuery( GeocodeQuery::create( $address ) );
			if ( ! $addresses->isEmpty() ) {
				return $addresses->first();
			}
		} catch ( \Exception $exception ) { // phpcs:ignore
			// Nothing to do in this case
		}

		return null;
	}

}
