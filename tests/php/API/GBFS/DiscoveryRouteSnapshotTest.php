<?php

namespace CommonsBooking\Tests\API\GBFS;

use CommonsBooking\Tests\API\CB_REST_Route_UnitTestCase;
use CommonsBooking\Tests\API\ApiSnapshotTrait;

/**
 * Snapshot test for GET /commonsbooking/v1/gbfs.json
 */
class DiscoveryRouteSnapshotTest extends CB_REST_Route_UnitTestCase {

	use ApiSnapshotTrait;

	protected $ENDPOINT = '/commonsbooking/v1/gbfs.json';

	public function testDiscoveryResponseMatchesFixture(): void {
		$request  = new \WP_REST_Request( 'GET', $this->ENDPOINT );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		$this->assertMatchesApiFixture( 'rest-gbfs-discovery.json', $response->get_data() );
	}
}
