<?php

namespace CommonsBooking\Tests\View;

use CommonsBooking\Tests\API\ApiSnapshotTrait;
use CommonsBooking\Tests\Wordpress\CustomPostType_AJAX_Test;
use CommonsBooking\Tests\Wordpress\CustomPostTypeTest;
use SlopeIt\ClockMock\ClockMock;

/**
 * Snapshot test for the cb_calendar_data AJAX endpoint.
 *
 * Handler: CommonsBooking\View\Calendar::getCalendarData()
 * No nonce required (nopriv handler).
 */
class CalendarDataSnapshotTest extends CustomPostType_AJAX_Test {

	use ApiSnapshotTrait;

	// Fixture files live alongside the other API fixtures.
	protected static string $fixtureDir = __DIR__ . '/../API/Fixtures';

	protected $hooks = [
		'cb_calendar_data' => [
			\CommonsBooking\View\Calendar::class,
			'getCalendarData',
		],
	];

	public function set_up(): void {
		parent::set_up();
		ClockMock::freeze( new \DateTime( CustomPostTypeTest::CURRENT_DATE ) );
		$this->createTimeframe();
		ClockMock::reset();
	}

	public function tear_down(): void {
		ClockMock::reset();
		parent::tear_down();
	}

	public function testCalendarDataResponseMatchesFixture(): void {
		ClockMock::freeze( new \DateTime( CustomPostTypeTest::CURRENT_DATE ) );

		$_POST['item']     = $this->itemID;
		$_POST['location'] = $this->locationID;
		$_POST['sd']       = CustomPostTypeTest::CURRENT_DATE;
		$_POST['ed']       = date( 'Y-m-d', strtotime( '+2 weeks', strtotime( CustomPostTypeTest::CURRENT_DATE ) ) );

		$response = $this->runHook( 'cb_calendar_data' );

		$normalizationMap = [
			(string) $this->itemID     => 'ITEM_ID',
			(string) $this->locationID => 'LOCATION_ID',
		];

		$this->assertMatchesApiFixture( 'ajax-calendar-data.json', $response, $normalizationMap );

		ClockMock::reset();
	}
}
