<?php

namespace CommonsBooking\Tests\API;

/**
 * Snapshot test for GET /commonsbooking/v1/projects
 */
class ProjectsRouteSnapshotTest extends CB_REST_Route_UnitTestCase {

	use ApiSnapshotTrait;

	protected $ENDPOINT = '/commonsbooking/v1/projects';

	public function testProjectsResponseMatchesFixture(): void {
		$request  = new \WP_REST_Request( 'GET', $this->ENDPOINT );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		$this->assertMatchesApiFixture( 'rest-projects.json', $response->get_data() );
	}
}
