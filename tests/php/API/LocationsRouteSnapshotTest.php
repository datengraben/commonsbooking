<?php

namespace CommonsBooking\Tests\API;

use SlopeIt\ClockMock\ClockMock;

/**
 * Snapshot test for GET /commonsbooking/v1/locations
 */
class LocationsRouteSnapshotTest extends CB_REST_Route_UnitTestCase {

	use ApiSnapshotTrait;

	protected $ENDPOINT = '/commonsbooking/v1/locations';

	public function setUp(): void {
		parent::setUp();

		$this->locationId = $this->createLocation( 'Test Location', 'publish' );
		update_post_meta( $this->locationId, 'geo_latitude', '51.5' );
		update_post_meta( $this->locationId, 'geo_longitude', '9.0' );
	}

	public function testLocationsResponseMatchesFixture(): void {
		$request  = new \WP_REST_Request( 'GET', $this->ENDPOINT );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		$normalizationMap = [
			(string) $this->locationId => 'LOCATION_ID',
		];

		$this->assertMatchesApiFixture( 'rest-locations.json', $response->get_data(), $normalizationMap );
	}

	public function testSingleLocationResponseMatchesFixture(): void {
		$request  = new \WP_REST_Request( 'GET', $this->ENDPOINT . '/' . $this->locationId );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		$normalizationMap = [
			(string) $this->locationId => 'LOCATION_ID',
		];

		$this->assertMatchesApiFixture( 'rest-locations-single.json', $response->get_data(), $normalizationMap );
	}
}
