<?php

namespace CommonsBooking\Tests\API;

use SlopeIt\ClockMock\ClockMock;

/**
 * Snapshot test for GET /commonsbooking/v1/items
 *
 * On first run the fixture is generated from the live response and the test is
 * marked incomplete.  On every subsequent run the live response is compared
 * against the stored fixture so any unintentional shape change turns the test Red.
 */
class ItemsRouteSnapshotTest extends CB_REST_Route_UnitTestCase {

	use ApiSnapshotTrait;

	protected $ENDPOINT = '/commonsbooking/v1/items';

	public function setUp(): void {
		parent::setUp();
		ClockMock::freeze( new \DateTime( self::CURRENT_DATE ) );

		$this->locationId = $this->createLocation( 'Test Location', 'publish' );
		update_post_meta( $this->locationId, 'geo_latitude', '51.5' );
		update_post_meta( $this->locationId, 'geo_longitude', '9.0' );

		$this->itemId = $this->createItem( 'Test Item', 'publish' );

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
	}

	public function tearDown(): void {
		ClockMock::reset();
		parent::tearDown();
	}

	public function testItemsResponseMatchesFixture(): void {
		ClockMock::freeze( new \DateTime( self::CURRENT_DATE ) );

		$request  = new \WP_REST_Request( 'GET', $this->ENDPOINT );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		$normalizationMap = [
			(string) $this->itemId     => 'ITEM_ID',
			(string) $this->locationId => 'LOCATION_ID',
		];

		$this->assertMatchesApiFixture( 'rest-items.json', $response->get_data(), $normalizationMap );

		ClockMock::reset();
	}

	public function testSingleItemResponseMatchesFixture(): void {
		ClockMock::freeze( new \DateTime( self::CURRENT_DATE ) );

		$request  = new \WP_REST_Request( 'GET', $this->ENDPOINT . '/' . $this->itemId );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		$normalizationMap = [
			(string) $this->itemId     => 'ITEM_ID',
			(string) $this->locationId => 'LOCATION_ID',
		];

		$this->assertMatchesApiFixture( 'rest-items-single.json', $response->get_data(), $normalizationMap );

		ClockMock::reset();
	}
}
