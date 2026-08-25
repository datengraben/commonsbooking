<?php

namespace CommonsBooking\Helper;

use CommonsBooking\Geocoder\Location;
use CommonsBooking\Geocoder\Model\AddressBuilder;

/**
 * Implementation of geocoding web service calls against OpenStreetMap Nominatim.
 *
 * Requests go through the WordPress HTTP API ({@see wp_remote_get()}) directly,
 * so geocoding respects the site's proxy configuration and the pre_http_request
 * filter, needs no curl extension, and requires no third-party HTTP client.
 * Helps to properly mock/unit-test 3rd-party components.
 */
class NominatimGeoCodeService implements GeoCodeService {

	/**
	 * OpenStreetMap Nominatim search endpoint.
	 */
	private const SEARCH_ENDPOINT = 'https://nominatim.openstreetmap.org/search';

	/**
	 * NOTE: This uses the english locale since we mainly use the coordinates of the returned address objects.
	 *
	 * @param string $addressString
	 *
	 * @return ?Location
	 */
	public function getAddressData( $addressString ): ?Location {
		$url = self::SEARCH_ENDPOINT . '?' . http_build_query(
			array(
				'q'               => $addressString,
				'format'          => 'jsonv2',
				'addressdetails'  => 1,
				'limit'           => 1,
				'accept-language' => 'en',
			)
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 15,
				'sslverify' => true,
				'headers'   => array(
					// The Nominatim usage policy requires an identifying User-Agent.
					'User-Agent' => 'CommonsBooking v.' . COMMONSBOOKING_VERSION . ' Contact: mail@commonsbooking.org',
					'Referer'    => get_site_url(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'CommonsBooking geocoding request failed: ' . $response->get_error_message() );

			return null;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$places = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! is_array( $places ) || array() === $places ) {
			return null;
		}

		return $this->buildLocation( $places[0] );
	}

	/**
	 * Builds a geocoder Location value object from a single Nominatim `jsonv2` result.
	 *
	 * @param mixed $place A decoded Nominatim place object.
	 *
	 * @return Location|null Null when the result carries no coordinates.
	 */
	private function buildLocation( $place ): ?Location {
		if ( ! ( $place instanceof \stdClass ) || ! isset( $place->lat, $place->lon ) ) {
			return null;
		}

		$builder = new AddressBuilder( 'nominatim' );
		$builder->setCoordinates( (float) $place->lat, (float) $place->lon );

		$address = $place->address ?? null;
		if ( $address instanceof \stdClass ) {
			$builder->setStreetName( $address->road ?? $address->pedestrian ?? null );
			$builder->setStreetNumber( $address->house_number ?? null );
			$builder->setPostalCode( $address->postcode ?? null );
			$builder->setLocality( $address->city ?? $address->town ?? $address->village ?? $address->hamlet ?? null );
			$builder->setCountry( $address->country ?? null );
			$builder->setCountryCode( isset( $address->country_code ) ? strtoupper( $address->country_code ) : null );
		}

		return $builder->build();
	}
}
