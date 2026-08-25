<?php

namespace CommonsBooking\Tests\Helper;

use CommonsBooking\Helper\GeoCodeService;
use CommonsBooking\Helper\GeoHelper;
use CommonsBooking\Tests\BaseTestCase;
use CommonsBooking\Geocoder\Location;
use CommonsBooking\Geocoder\Model\AddressBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Tests wrapper impl for nominatim and provides mocking code to prevent real service calls
 */
class GeoHelperTest extends BaseTestCase {

	/**
	 * Mocks a location
	 *
	 * @return Location|null
	 */
	public static function mockedLocation(): ?Location {
		$location = new AddressBuilder( 'Mock' );
		$location->setStreetName( 'Karl-Marx-Straße' )
				->setStreetNumber( '1' )
				->setPostalCode( '12043' )
				->setLocality( 'Berlin' )
				->setCountry( 'Germany' )
				->setCoordinates( 52.4863573, 13.4247667 );

		return $location->build();
	}

	/**
	 * This can be used to get mocked locations from nominatim
	 *
	 * @param TestCase $case
	 *
	 * @return void
	 */
	public static function setUpGeoHelperMock( TestCase $case ): void {

		$sut = $case->createStub( GeoCodeService::class );
		$sut->method( 'getAddressData' )
					->willReturn( self::mockedLocation() );
		GeoHelper::setGeoCodeServiceInstance( $sut );
	}

	public function testThatGeoCoding_worksOffline() {
		$address = GeoHelper::getAddressData( 'Karl-Marx-Straße 1, 12043 Berlin' );
		$this->assertThatKarlMarxLocationIsProperlyGeoCoded( $address );
	}

	public function testThatGeoCoding_worksOnline() {
		GeoHelper::resetGeoCoder();

		$address = GeoHelper::getAddressData( 'Karl-Marx-Straße 1, 12043 Berlin' );
		$this->assertThatKarlMarxLocationIsProperlyGeoCoded( $address );
	}

	/**
	 * Exercises the real NominatimGeoCodeService with a faked WordPress HTTP
	 * response (via the pre_http_request filter) so no real network request is
	 * made. This proves the service parses a Nominatim `jsonv2` response into a
	 * Location without depending on the external service.
	 */
	public function testGeoCodingThroughWordPressHttpApi() {
		GeoHelper::resetGeoCoder();

		$place = array(
			'licence'      => 'Data © OpenStreetMap contributors',
			'osm_type'     => 'way',
			'osm_id'       => 123456,
			'lat'          => '52.4863573',
			'lon'          => '13.4247667',
			'category'     => 'highway',
			'type'         => 'residential',
			'display_name' => 'Karl-Marx-Straße 1, 12043 Berlin, Germany',
			'boundingbox'  => array( '52.4863', '52.4864', '13.4247', '13.4248' ),
			'address'      => array(
				'road'         => 'Karl-Marx-Straße',
				'house_number' => '1',
				'postcode'     => '12043',
				'city'         => 'Berlin',
				'country'      => 'Germany',
				'country_code' => 'de',
			),
		);

		$filter = function () use ( $place ) {
			return array(
				'headers'       => array( 'content-type' => 'application/json' ),
				'body'          => wp_json_encode( array( $place ) ),
				'response'      => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'       => array(),
				'http_response' => null,
			);
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );

		try {
			$address = GeoHelper::getAddressData( 'Karl-Marx-Straße 1, 12043 Berlin' );
		} finally {
			remove_filter( 'pre_http_request', $filter, 10 );
		}

		$this->assertThatKarlMarxLocationIsProperlyGeoCoded( $address );
	}
	private function assertThatKarlMarxLocationIsProperlyGeoCoded( Location $address ): void {
		$this->assertEquals( 'Karl-Marx-Straße', $address->getStreetName() );
		$this->assertEquals( '1', $address->getStreetNumber() );
		$this->assertEquals( '12043', $address->getPostalCode() );
		$this->assertEquals( 'Berlin', $address->getLocality() );
		$this->assertEquals( 'Germany', $address->getCountry() );
		// This won't check exact coords on purpose, because sometimes there are different results
		$this->assertStringStartsWith( '52.4863', '' . $address->getCoordinates()->getLatitude() );
		$this->assertStringStartsWith( '13.424', '' . $address->getCoordinates()->getLongitude() );
	}
}
