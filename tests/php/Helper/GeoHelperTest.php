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

	/**
	 * Integration test that hits the real Nominatim service.
	 *
	 * Tagged `external-http` so it is skipped by default (the group is excluded
	 * in phpunit.xml.dist) and only runs on explicit opt-in, by requesting the
	 * external-http group on the phpunit command line. The mocked default
	 * coverage lives in {@see self::testThatGeoCoding_worksOffline()}.
	 *
	 * @group external-http
	 */
	public function testThatGeoCoding_worksOnline() {
		GeoHelper::resetGeoCoder();

		$address = GeoHelper::getAddressData( 'Karl-Marx-Straße 1, 12043 Berlin' );
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
