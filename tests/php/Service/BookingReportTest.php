<?php

namespace CommonsBooking\Tests\Service;

use CommonsBooking\Repository\Booking as BookingRepository;
use CommonsBooking\Service\BookingReport;
use CommonsBooking\Tests\Wordpress\CustomPostTypeTest;

class BookingReportTest extends CustomPostTypeTest {

	/**
	 * Creates a confirmed booking that starts $daysAgo days before the reference date.
	 */
	private function createBookingStartingDaysAgo( int $daysAgo, string $postStatus = 'confirmed' ): int {
		$start = strtotime( '-' . $daysAgo . ' days', strtotime( self::CURRENT_DATE ) );
		$end   = strtotime( '+2 hours', $start );

		return $this->createBooking(
			$this->locationId,
			$this->itemId,
			$start,
			$end,
			'8:00 AM',
			'10:00 AM',
			$postStatus
		);
	}

	public function testCountBookingsStartingInRange() {
		$now = strtotime( self::CURRENT_DATE );

		// Within the last 7 days.
		$this->createBookingStartingDaysAgo( 3 );
		// Within the previous 7 days (8-14 days ago).
		$this->createBookingStartingDaysAgo( 10 );
		// Between 30 and 60 days ago.
		$this->createBookingStartingDaysAgo( 40 );
		// A canceled booking within the last 7 days must NOT be counted.
		$this->createBookingStartingDaysAgo( 2, 'canceled' );

		$last7  = BookingRepository::countBookingsStartingInRange( strtotime( '-7 days', $now ) + 1, $now );
		$prev7  = BookingRepository::countBookingsStartingInRange( strtotime( '-14 days', $now ) + 1, strtotime( '-7 days', $now ) );
		$last30 = BookingRepository::countBookingsStartingInRange( strtotime( '-30 days', $now ) + 1, $now );
		$prev30 = BookingRepository::countBookingsStartingInRange( strtotime( '-60 days', $now ) + 1, strtotime( '-30 days', $now ) );

		// Only the confirmed booking 3 days ago (canceled one is excluded).
		$this->assertEquals( 1, $last7 );
		// The booking 10 days ago.
		$this->assertEquals( 1, $prev7 );
		// Bookings 3 and 10 days ago.
		$this->assertEquals( 2, $last30 );
		// The booking 40 days ago.
		$this->assertEquals( 1, $prev30 );
	}

	public function testGetReportBodyContainsCounts() {
		$now = strtotime( self::CURRENT_DATE );

		$this->createBookingStartingDaysAgo( 3 );  // last 7 days
		$this->createBookingStartingDaysAgo( 10 ); // previous 7 days

		$body = BookingReport::getReportBody( $now );

		$this->assertStringContainsString( 'Last 7 days', $body );
		$this->assertStringContainsString( 'Last 30 days', $body );
		$this->assertStringContainsString( 'Last 3 months', $body );
		// Last 7 days: 1 booking, previous 7 days: 1 booking.
		$this->assertMatchesRegularExpression( '/Last 7 days:\s+1\s+\(previous 7 days: 1\)/', $body );
		// Last 30 days: both bookings, previous 30 days: none.
		$this->assertMatchesRegularExpression( '/Last 30 days:\s+2\s+\(previous 30 days: 0\)/', $body );
	}

	public function testIsSendingDayWeekly() {
		// 2021-07-05 is a Monday.
		$this->assertTrue( BookingReport::isSendingDay( 'weekly', strtotime( '2021-07-05 12:00:00' ) ) );
		// 2021-07-06 is a Tuesday.
		$this->assertFalse( BookingReport::isSendingDay( 'weekly', strtotime( '2021-07-06 12:00:00' ) ) );
	}

	public function testIsSendingDayMonthly() {
		// First day of the month.
		$this->assertTrue( BookingReport::isSendingDay( 'monthly', strtotime( '2021-07-01 12:00:00' ) ) );
		// Not the first day of the month.
		$this->assertFalse( BookingReport::isSendingDay( 'monthly', strtotime( '2021-07-02 12:00:00' ) ) );
	}
}
