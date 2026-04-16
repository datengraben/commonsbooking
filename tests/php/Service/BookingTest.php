<?php

namespace CommonsBooking\Tests\Service;

use CommonsBooking\Service\Booking;
use CommonsBooking\Tests\Wordpress\CustomPostTypeTest;

class BookingTest extends CustomPostTypeTest {
	public function testCleanupBookings() {
		$bookingId = $this->createBooking(
			$this->locationId,
			$this->itemId,
			strtotime( 'midnight', strtotime( self::CURRENT_DATE ) ),
			strtotime( '+2 days', strtotime( self::CURRENT_DATE ) ),
			'8:00 AM',
			'12:00 PM',
			'unconfirmed'
		);
		// first, we check if the cleanup will delete our freshly created unconfirmed booking (it should not)
		Booking::cleanupBookings();
		$this->assertNotNull( get_post( $bookingId ) );

		// we make the post 11 minutes old, so that the cleanup function will delete it (the cleanup function only deletes bookings older than 10 minutes)
		wp_update_post(
			[
				'ID'        => $bookingId,
				'post_date' => date( 'Y-m-d H:i:s', strtotime( '-11 minutes' ) ),
			]
		);

		// now we run the cleanup function again
		Booking::cleanupBookings();

		// and check if the post is still there
		$this->assertNull( get_post( $bookingId ) );
	}

	public function testMarkPastBookings() {
		add_filter( 'commonsbooking_enable_past_booking_status', '__return_true' );

		// Create a confirmed booking whose end date is in the past (yesterday)
		$pastBookingId = $this->createBooking(
			$this->locationId,
			$this->itemId,
			strtotime( '-3 days' ),
			strtotime( '-1 day' ),
			'12:00 AM',
			'23:59',
			'confirmed'
		);

		// Create a confirmed booking whose end date is in the future
		$futureBookingId = $this->createBooking(
			$this->locationId,
			$this->itemId,
			strtotime( '+1 day' ),
			strtotime( '+3 days' ),
			'12:00 AM',
			'23:59',
			'confirmed'
		);

		$this->assertEquals( 'confirmed', get_post_field( 'post_status', $pastBookingId ) );
		$this->assertEquals( 'confirmed', get_post_field( 'post_status', $futureBookingId ) );

		Booking::markPastBookings();
		wp_cache_flush();

		$this->assertEquals( 'past_booking', get_post_field( 'post_status', $pastBookingId ) );
		$this->assertEquals( 'confirmed', get_post_field( 'post_status', $futureBookingId ) );

		remove_filter( 'commonsbooking_enable_past_booking_status', '__return_true' );
	}

	/**
	 * When the feature flag is OFF, markPastBookings() must revert any previously-transitioned
	 * 'past_booking' posts back to 'confirmed'.
	 */
	public function testMarkPastBookingsRevertsWhenFlagOff() {
		// Create two confirmed bookings and manually set them to past_booking via SQL
		global $wpdb;
		$bookingId1 = $this->createBooking(
			$this->locationId,
			$this->itemId,
			strtotime( '-5 days' ),
			strtotime( '-3 days' ),
			'12:00 AM',
			'23:59',
			'confirmed'
		);
		$bookingId2 = $this->createBooking(
			$this->locationId,
			$this->itemId,
			strtotime( '-4 days' ),
			strtotime( '-2 days' ),
			'12:00 AM',
			'23:59',
			'confirmed'
		);
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->posts} SET post_status = 'past_booking' WHERE ID IN (%d, %d)",
			$bookingId1, $bookingId2
		) );
		wp_cache_flush();

		$this->assertEquals( 'past_booking', get_post_field( 'post_status', $bookingId1 ) );
		$this->assertEquals( 'past_booking', get_post_field( 'post_status', $bookingId2 ) );

		// Call with flag OFF (default) — must revert both back to confirmed
		Booking::markPastBookings();
		wp_cache_flush();

		$this->assertEquals( 'confirmed', get_post_field( 'post_status', $bookingId1 ) );
		$this->assertEquals( 'confirmed', get_post_field( 'post_status', $bookingId2 ) );
	}

	/**
	 * When the batch size is smaller than the number of eligible bookings, markPastBookings()
	 * must keep looping until all batches are processed.
	 */
	public function testMarkPastBookingsBatching() {
		add_filter( 'commonsbooking_enable_past_booking_status', '__return_true' );
		// Force a batch size of 1 so three records require three separate database passes
		add_filter( 'commonsbooking_past_booking_batch_size', fn() => 1 );

		$ids = [];
		for ( $i = 1; $i <= 3; $i++ ) {
			$ids[] = $this->createBooking(
				$this->locationId,
				$this->itemId,
				strtotime( "-{$i} weeks -1 day" ),
				strtotime( "-{$i} weeks" ),
				'12:00 AM',
				'23:59',
				'confirmed'
			);
		}

		foreach ( $ids as $id ) {
			$this->assertEquals( 'confirmed', get_post_field( 'post_status', $id ) );
		}

		Booking::markPastBookings();
		wp_cache_flush();

		foreach ( $ids as $id ) {
			$this->assertEquals( 'past_booking', get_post_field( 'post_status', $id ), "Booking {$id} was not transitioned" );
		}

		remove_filter( 'commonsbooking_past_booking_batch_size', fn() => 1 );
		remove_filter( 'commonsbooking_enable_past_booking_status', '__return_true' );
	}

	protected function setUp(): void {
		parent::setUp();
		$this->firstTimeframeId = $this->createBookableTimeFrameIncludingCurrentDay();
	}

	protected function tearDown(): void {
		parent::tearDown();
		\Mockery::close();
	}
}
