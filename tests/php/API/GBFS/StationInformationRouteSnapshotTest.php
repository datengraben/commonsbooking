<?php

namespace CommonsBooking\Tests\API\GBFS;

use CommonsBooking\Tests\API\CB_REST_Route_UnitTestCase;
use CommonsBooking\Tests\API\ApiSnapshotTrait;

/**
 * Snapshot test for GET /commonsbooking/v1/station_information.json
 */
class StationInformationRouteSnapshotTest extends CB_REST_Route_UnitTestCase {

	use ApiSnapshotTrait;

	protected $ENDPOINT = '/commonsbooking/v1/station_information.json';

	public function setUp(): void {
		parent::setUp();

		$this->locationId = $this->createLocation( 'Test Location', 'publish' );
		// Provide coordinates to avoid geocoding calls during the test.
		update_post_meta( $this->locationId, 'geo_latitude', '51.5' );
		update_post_meta( $this->locationId, 'geo_longitude', '9.0' );
	}

	public function testStationInformationResponseMatchesFixture(): void {
		$request  = new \WP_REST_Request( 'GET', $this->ENDPOINT );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		$normalizationMap = [
			(string) $this->locationId => 'LOCATION_ID',
		];

		$this->assertMatchesApiFixture( 'rest-gbfs-station-information.json', $response->get_data(), $normalizationMap );
	}
}
