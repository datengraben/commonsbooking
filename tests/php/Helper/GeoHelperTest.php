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
	public function testGetDistanceInKm_returnsZeroForIdenticalCoords() {
		$this->assertEqualsWithDelta(
			0.0,
			GeoHelper::getDistanceInKm( 50.9413035, 6.9581379978318, 50.9413035, 6.9581379978318 ),
			0.0001
		);
	}

	public function testGetDistanceInKm_matchesKnownCityPair() {
		// Cologne Cathedral <-> Berlin, great-circle distance is ~477 km.
		$distance = GeoHelper::getDistanceInKm( 50.9413035, 6.9581379978318, 52.5162746, 13.3777041 );
		$this->assertGreaterThan( 470, $distance );
		$this->assertLessThan( 485, $distance );
	}

	public function testGetDistanceInKm_isSymmetric() {
		$forward  = GeoHelper::getDistanceInKm( 50.9413035, 6.9581379978318, 52.5162746, 13.3777041 );
		$backward = GeoHelper::getDistanceInKm( 52.5162746, 13.3777041, 50.9413035, 6.9581379978318 );
		$this->assertEqualsWithDelta( $forward, $backward, 0.0001 );
	}

	public function testRankByDistance_filtersSortsAndCaps() {
		$candidates = array(
			10 => array( 'lat' => 50.9413035, 'lon' => 6.9581379978318 ), // origin itself (0 km)
			11 => array( 'lat' => 50.9375, 'lon' => 6.9700 ),            // ~1 km
			12 => array( 'lat' => 52.5162746, 'lon' => 13.3777041 ),      // ~477 km (out of range)
			13 => array( 'lat' => 50.9500, 'lon' => 6.9600 ),            // ~1 km
		);

		$ranked = GeoHelper::rankByDistance( $candidates, 50.9413035, 6.9581379978318, 50.0, 2, array( 10 ) );

		// Origin (10) excluded, far one (12) filtered by radius, capped to 2 results, nearest first.
		$this->assertCount( 2, $ranked );
		$ids = array_column( $ranked, 'id' );
		$this->assertNotContains( 10, $ids );
		$this->assertNotContains( 12, $ids );
		$this->assertLessThanOrEqual( $ranked[1]['distance'], $ranked[0]['distance'] );
	}

	public function testRankByDistance_withoutMaxDistanceKeepsAll() {
		$candidates = array(
			11 => array( 'lat' => 50.9375, 'lon' => 6.9700 ),
			12 => array( 'lat' => 52.5162746, 'lon' => 13.3777041 ),
		);

		$ranked = GeoHelper::rankByDistance( $candidates, 50.9413035, 6.9581379978318, null, null, array() );
		$this->assertCount( 2, $ranked );
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
