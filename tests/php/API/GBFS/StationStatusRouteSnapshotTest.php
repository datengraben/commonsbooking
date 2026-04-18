<?php

namespace CommonsBooking\Tests\API\GBFS;

use CommonsBooking\Tests\API\CB_REST_Route_UnitTestCase;
use CommonsBooking\Tests\API\ApiSnapshotTrait;
use SlopeIt\ClockMock\ClockMock;

/**
 * Snapshot test for GET /commonsbooking/v1/station_status.json
 */
class StationStatusRouteSnapshotTest extends CB_REST_Route_UnitTestCase {

	use ApiSnapshotTrait;

	protected $ENDPOINT = '/commonsbooking/v1/station_status.json';

	public function setUp(): void {
		parent::setUp();
		ClockMock::freeze( new \DateTime( self::CURRENT_DATE ) );

		$this->locationId = $this->createLocation( 'Test Location', 'publish' );
		update_post_meta( $this->locationId, 'geo_latitude', '51.5' );
		update_post_meta( $this->locationId, 'geo_longitude', '9.0' );

		$this->itemId = $this->createItem( 'Test Item', 'publish' );

		// Timeframe covers CURRENT_DATE so the item is available right now.
		$start = ( new \DateTimeImmutable( self::CURRENT_DATE ) )->modify( '-1 day' )->getTimestamp();
		$end   = ( new \DateTimeImmutable( self::CURRENT_DATE ) )->modify( '+1 day' )->getTimestamp();

		$this->createTimeframe(
			$this->locationId,
			$this->itemId,
			$start,
			$end,
			\CommonsBooking\Wordpress\CustomPostType\Timeframe::BOOKABLE_ID,
			'on',
			'd'
		);

		ClockMock::reset();
	}

	public function tearDown(): void {
		ClockMock::reset();
		parent::tearDown();
	}

	public function testStationStatusResponseMatchesFixture(): void {
		ClockMock::freeze( new \DateTime( self::CURRENT_DATE ) );

		$request  = new \WP_REST_Request( 'GET', $this->ENDPOINT );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		$normalizationMap = [
			(string) $this->locationId => 'LOCATION_ID',
			(string) $this->itemId     => 'ITEM_ID',
		];

		$this->assertMatchesApiFixture( 'rest-gbfs-station-status.json', $response->get_data(), $normalizationMap );

		ClockMock::reset();
	}
}
