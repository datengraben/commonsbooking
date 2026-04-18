<?php

namespace CommonsBooking\Tests\API;

use SlopeIt\ClockMock\ClockMock;

/**
 * Snapshot test for GET /commonsbooking/v1/availability
 */
class AvailabilityRouteSnapshotTest extends CB_REST_Route_UnitTestCase {

	use ApiSnapshotTrait;

	protected $ENDPOINT = '/commonsbooking/v1/availability';

	public function setUp(): void {
		parent::setUp();
		ClockMock::freeze( new \DateTime( self::CURRENT_DATE ) );

		$this->locationId = $this->createLocation( 'Test Location', 'publish' );
		$this->itemId     = $this->createItem( 'Test Item', 'publish' );

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

	public function testAvailabilityResponseMatchesFixture(): void {
		ClockMock::freeze( new \DateTime( self::CURRENT_DATE ) );

		$request  = new \WP_REST_Request( 'GET', $this->ENDPOINT );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		$normalizationMap = [
			(string) $this->itemId     => 'ITEM_ID',
			(string) $this->locationId => 'LOCATION_ID',
		];

		$this->assertMatchesApiFixture( 'rest-availability.json', $response->get_data(), $normalizationMap );

		ClockMock::reset();
	}

	public function testSingleItemAvailabilityMatchesFixture(): void {
		ClockMock::freeze( new \DateTime( self::CURRENT_DATE ) );

		$request  = new \WP_REST_Request( 'GET', $this->ENDPOINT . '/' . $this->itemId );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		$normalizationMap = [
			(string) $this->itemId     => 'ITEM_ID',
			(string) $this->locationId => 'LOCATION_ID',
		];

		$this->assertMatchesApiFixture( 'rest-availability-single.json', $response->get_data(), $normalizationMap );

		ClockMock::reset();
	}
}
