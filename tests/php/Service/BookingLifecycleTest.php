<?php

namespace CommonsBooking\Tests\Service;

use CommonsBooking\Model\Booking;
use CommonsBooking\Model\Timeframe;
use CommonsBooking\Service\BookingLifecycle;
use CommonsBooking\Tests\Wordpress\CustomPostTypeTest;
use CommonsBooking\Wordpress\CustomPostType\Booking as BookingPostType;
use CommonsBooking\Wordpress\CustomPostType\Timeframe as TimeframeCPT;

/**
 * Tests for the booking lifecycle action hooks.
 *
 * @covers \CommonsBooking\Service\BookingLifecycle
 */
class BookingLifecycleTest extends CustomPostTypeTest {

	/**
	 * Records callbacks fired per hook so assertions can inspect count and args.
	 *
	 * @var array<string, array<int, array>>
	 */
	private array $fired = array();

	/**
	 * Inserts a booking post the way the plugin does (type meta as part of the
	 * insert), so that the `transition_post_status` action sees a real booking.
	 */
	private function insertBooking( string $status, int $end = null ): int {
		$end = $end ?? ( time() + DAY_IN_SECONDS );

		return wp_insert_post(
			array(
				'post_title'  => 'Lifecycle booking',
				'post_type'   => BookingPostType::$postType,
				'post_status' => $status,
				'post_author' => self::USER_ID,
				'meta_input'  => array(
					'type'                        => TimeframeCPT::BOOKING_ID,
					Timeframe::META_LOCATION_ID   => $this->locationId,
					Timeframe::META_ITEM_ID       => $this->itemId,
					Timeframe::REPETITION_START   => time(),
					Timeframe::REPETITION_END     => $end,
				),
			)
		);
	}

	/**
	 * Registers a spy for a hook that records every invocation's arguments.
	 */
	private function spyOn( string $hook, int $args = 2 ): void {
		$this->fired[ $hook ] = array();
		add_action(
			$hook,
			function ( ...$received ) use ( $hook ) {
				$this->fired[ $hook ][] = $received;
			},
			10,
			$args
		);
	}

	public function testConfirmedHookFiresOnConfirmation() {
		$this->spyOn( 'commonsbooking_booking_confirmed' );
		$bookingId = $this->insertBooking( 'unconfirmed' );

		$this->assertCount( 0, $this->fired['commonsbooking_booking_confirmed'] );

		wp_update_post(
			array(
				'ID'          => $bookingId,
				'post_status' => 'confirmed',
			)
		);

		$this->assertCount( 1, $this->fired['commonsbooking_booking_confirmed'] );
		[ $id, $booking ] = $this->fired['commonsbooking_booking_confirmed'][0];
		$this->assertEquals( $bookingId, $id );
		$this->assertInstanceOf( Booking::class, $booking );
	}

	public function testConfirmedHookFiresWhenInsertedAsConfirmed() {
		$this->spyOn( 'commonsbooking_booking_confirmed' );
		$bookingId = $this->insertBooking( 'confirmed' );

		$this->assertCount( 1, $this->fired['commonsbooking_booking_confirmed'] );
		$this->assertEquals( $bookingId, $this->fired['commonsbooking_booking_confirmed'][0][0] );
	}

	public function testStatusChangedHookCarriesOldAndNewStatus() {
		$this->spyOn( 'commonsbooking_booking_status_changed', 4 );
		$bookingId = $this->insertBooking( 'unconfirmed' );

		// Reset to only assert the confirm transition below.
		$this->fired['commonsbooking_booking_status_changed'] = array();

		wp_update_post(
			array(
				'ID'          => $bookingId,
				'post_status' => 'confirmed',
			)
		);

		$this->assertCount( 1, $this->fired['commonsbooking_booking_status_changed'] );
		[ $id, $old, $new, $booking ] = $this->fired['commonsbooking_booking_status_changed'][0];
		$this->assertEquals( $bookingId, $id );
		$this->assertEquals( 'unconfirmed', $old );
		$this->assertEquals( 'confirmed', $new );
		$this->assertInstanceOf( Booking::class, $booking );
	}

	public function testCreatedHookFiresExactlyOnce() {
		$this->spyOn( 'commonsbooking_booking_created' );
		$bookingId = $this->insertBooking( 'unconfirmed' );

		$this->assertCount( 1, $this->fired['commonsbooking_booking_created'] );
		$this->assertEquals( $bookingId, $this->fired['commonsbooking_booking_created'][0][0] );

		// A later status change must not fire "created" again.
		wp_update_post(
			array(
				'ID'          => $bookingId,
				'post_status' => 'confirmed',
			)
		);

		$this->assertCount( 1, $this->fired['commonsbooking_booking_created'] );
	}

	public function testCancelledHookFiresOnCancel() {
		$this->spyOn( 'commonsbooking_booking_cancelled' );
		$bookingId = $this->insertBooking( 'confirmed' );
		$this->fired['commonsbooking_booking_cancelled'] = array();

		$booking = new Booking( $bookingId );
		$booking->cancel();

		$this->assertCount( 1, $this->fired['commonsbooking_booking_cancelled'] );
		[ $id, $cancelledBooking ] = $this->fired['commonsbooking_booking_cancelled'][0];
		$this->assertEquals( $bookingId, $id );
		$this->assertInstanceOf( Booking::class, $cancelledBooking );
	}

	public function testNoHooksForPostsWithoutBookingTypeMeta() {
		$this->spyOn( 'commonsbooking_booking_created' );
		$this->spyOn( 'commonsbooking_booking_status_changed', 4 );

		// A cb_booking post without the booking type meta is not a real booking.
		$postId = wp_insert_post(
			array(
				'post_title'  => 'Not a booking',
				'post_type'   => BookingPostType::$postType,
				'post_status' => 'confirmed',
				'post_author' => self::USER_ID,
			)
		);

		wp_update_post(
			array(
				'ID'          => $postId,
				'post_status' => 'draft',
			)
		);

		$this->assertCount( 0, $this->fired['commonsbooking_booking_created'] );
		$this->assertCount( 0, $this->fired['commonsbooking_booking_status_changed'] );
	}

	public function testCreatedMetaFlagConstantIsSet() {
		$bookingId = $this->insertBooking( 'unconfirmed' );
		$this->assertEquals( 1, get_post_meta( $bookingId, BookingLifecycle::CREATED_FIRED_META, true ) );
	}
}
