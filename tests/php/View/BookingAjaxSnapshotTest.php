<?php

namespace CommonsBooking\Tests\View;

use CommonsBooking\Settings\Settings;
use CommonsBooking\Tests\API\ApiSnapshotTrait;
use CommonsBooking\Tests\Wordpress\CustomPostType_AJAX_Test;
use CommonsBooking\Tests\Wordpress\CustomPostTypeTest;
use CommonsBooking\Wordpress\CustomPostType\Timeframe;
use SlopeIt\ClockMock\ClockMock;

/**
 * Snapshot tests for booking-form AJAX endpoints.
 *
 * Covers:
 *   - cb_get_bookable_location  → View\Booking::getLocationForItem_AJAX()
 *   - cb_get_booking_code       → View\Booking::getBookingCode_AJAX()
 */
class BookingAjaxSnapshotTest extends CustomPostType_AJAX_Test {

	use ApiSnapshotTrait;

	protected static string $fixtureDir = __DIR__ . '/../API/Fixtures';

	protected $hooks = [
		'cb_get_bookable_location' => [
			\CommonsBooking\View\Booking::class,
			'getLocationForItem_AJAX',
		],
		'cb_get_booking_code' => [
			\CommonsBooking\View\Booking::class,
			'getBookingCode_AJAX',
		],
	];

	private array $bookingCodes = [ 'alpha', 'bravo', 'charlie', 'delta', 'echo', 'foxtrot' ];

	public function set_up(): void {
		parent::set_up();

		$codesString = implode( ',', $this->bookingCodes );
		Settings::updateOption( 'commonsbooking_options_bookingcodes', 'bookingcodes', $codesString );
		\CommonsBooking\Repository\BookingCodes::initBookingCodesTable();

		ClockMock::freeze( new \DateTime( CustomPostTypeTest::CURRENT_DATE ) );

		$this->createTimeframe();

		// Trigger booking-code generation for the frozen date.
		$timeframeCPT = new Timeframe();
		$timeframeCPT->savePost( $this->timeframeID, get_post( $this->timeframeID ) );

		ClockMock::reset();
	}

	public function tear_down(): void {
		ClockMock::reset();
		$this->tearDownBookingCodesTable();
		parent::tear_down();
	}

	public function testGetBookableLocationResponseMatchesFixture(): void {
		ClockMock::freeze( new \DateTime( CustomPostTypeTest::CURRENT_DATE ) );

		$_POST['data'] = [ 'itemID' => $this->itemID ];
		$response      = $this->runHook( 'cb_get_bookable_location' );

		$normalizationMap = [
			(string) $this->itemID     => 'ITEM_ID',
			(string) $this->locationID => 'LOCATION_ID',
		];

		$this->assertMatchesApiFixture( 'ajax-get-bookable-location.json', $response, $normalizationMap );

		ClockMock::reset();
	}

	public function testGetBookingCodeResponseMatchesFixture(): void {
		ClockMock::freeze( new \DateTime( CustomPostTypeTest::CURRENT_DATE ) );

		$_POST['data'] = [
			'itemID'     => $this->itemID,
			'locationID' => $this->locationID,
			'startDate'  => date( 'm/d/Y', strtotime( CustomPostTypeTest::CURRENT_DATE ) ),
		];
		$response = $this->runHook( 'cb_get_booking_code' );

		$normalizationMap = [
			(string) $this->itemID     => 'ITEM_ID',
			(string) $this->locationID => 'LOCATION_ID',
		];

		// The booking code itself is one of the known codes — normalize it so
		// the fixture doesn't depend on which code was assigned.
		foreach ( $this->bookingCodes as $code ) {
			$normalizationMap[ $code ] = 'BOOKING_CODE';
		}

		$this->assertMatchesApiFixture( 'ajax-get-booking-code.json', $response, $normalizationMap );

		ClockMock::reset();
	}

	private function tearDownBookingCodesTable(): void {
		global $wpdb;
		$table = $wpdb->prefix . \CommonsBooking\Repository\BookingCodes::$tablename;
		$wpdb->query( "DROP TABLE IF EXISTS $table" );
	}
}
