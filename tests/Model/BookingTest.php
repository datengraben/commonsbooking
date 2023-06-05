<?php

namespace CommonsBooking\Tests\Model;

use CommonsBooking\Model\Booking;
use CommonsBooking\Model\Item;
use CommonsBooking\Model\Location;
use CommonsBooking\Model\Timeframe;
use CommonsBooking\Plugin;
use CommonsBooking\Tests\Wordpress\CustomPostTypeTest;

class BookingTest extends CustomPostTypeTest {

	private Booking $testBookingTomorrow;
	private Booking $testBookingPast;
	private int $testBookingId;
	private Item  $testItem;
	private Location $testLocation;
	private Timeframe $testTimeFrame;
	/**
	 * @var int|\WP_Error
	 */
	private int $testBookingPastId;

	public function testGetBookableTimeFrame() {
		$this->assertEquals($this->testTimeFrame,$this->testBookingTomorrow->getBookableTimeFrame());
	}

	public function testGetLocation() {
		$this->assertEquals($this->testLocation,$this->testBookingTomorrow->getLocation());
	}

	public function testCancel() {
		$this->testBookingTomorrow->cancel();
		$this->testBookingPast->cancel();
		//flush cache to reflect updated post
		wp_cache_flush();
		$this->testBookingTomorrow = new Booking(get_post($this->testBookingId));
		$this->testBookingPast = new Booking(get_post($this->testBookingPastId));
		$this->assertTrue($this->testBookingTomorrow->isCancelled());
		$this->assertFalse($this->testBookingPast->isCancelled());
		parent::tearDownAllBookings();
		wp_cache_flush();
		$this->setUpTestBooking();
	}

	public function testGetItem() {
		$this->assertEquals($this->testItem,$this->testBookingTomorrow->getItem());
	}

	public function testIsPast(){
		$this->assertFalse($this->testBookingTomorrow->isPast());
		$this->assertTrue($this->testBookingPast->isPast());
	}
	
	public function testCanCancel() {
				
		// Case: Booking in the past, no one can cancel
		$this->assertFalse( $this->testBookingPast->canCancel() );
		
		// Case: Booking in the future and same author
		wp_set_current_user( self::USER_ID );
		$this->assertTrue(  $this->testBookingTomorrow->canCancel() );
		
		// Case: role can edit and != post_author => can cancel
		$userId = wp_create_user('user_who_can_edit', '');
		$userObj = get_user_by( 'id', $userId );
		$userObj->remove_role( 'subscriber' );
		$userObj->add_role( 'administrator' );
		wp_update_user( $userObj );

		wp_set_current_user( $userId );

		$this->assertTrue( $userObj->exists() );
		$this->assertTrue( Plugin::isPostCustomPostType( $this->testBookingTomorrow ) );
		$this->assertTrue( commonsbooking_isUserAdmin( $userObj ) );
		$this->assertNotSame( $userObj->ID, intval( $this->testBookingTomorrow->post_author ) );
		$this->assertTrue( commonsbooking_isUserAllowedToSee( $this->testBookingTomorrow->getPost(), $userObj ) );
		$this->assertTrue( current_user_can('administrator' ) );
		$this->assertTrue( current_user_can('edit_posts' ) );
		$this->assertTrue( current_user_can('edit_others_posts' ) );
		// TODO admin $userObj won't be able to edit post
		/*$this->assertTrue( current_user_can('edit_post', $this->testBookingId ) );
		$this->assertTrue( commonsbooking_isUserAllowedToEdit( $this->testBookingTomorrow->getPost(), $userObj ) );
		$this->assertTrue( commonsbooking_isCurrentUserAllowedToEdit( $this->testBookingId ) );
		$this->assertTrue( commonsbooking_isCurrentUserAdmin() );
		$this->assertTrue(  $this->testBookingTomorrow->canCancel() );*/
		
		// Case: role cannot edit, role != post_author, booking in the future => can't cancel
		$userObj->remove_role( 'administrator' );
		$userObj->add_role( 'subscriber' );
		$this->assertFalse(  $this->testBookingTomorrow->canCancel() );
		
	}

	protected function setUpTestBooking():void{
		$this->testBookingId       = $this->createBooking(
			$this->locationId,
			$this->itemId,
			strtotime( '+1 day', time()),
			strtotime( '+2 days', time())
		);
		$this->testBookingTomorrow = new Booking(get_post($this->testBookingId));
		$this->testBookingPastId       = $this->createBooking(
			$this->locationId,
			$this->itemId,
			strtotime('-2 days', time()),
			strtotime('-1 day', time())
		);
		$this->testBookingPast = new Booking(get_post($this->testBookingPastId));
	}

	protected function setUp() : void {
		parent::setUp();

		$this->firstTimeframeId   = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			strtotime( '-5 days',time()),
			strtotime( '+90 days', time())
		);
		$this->testItem = new Item(get_post($this->itemId));
		$this->testLocation = new Location(get_post($this->locationId));
		$this->testTimeFrame = new Timeframe(get_post($this->firstTimeframeId));
		$this->setUpTestBooking();
	}

	protected function tearDown() : void {
		parent::tearDown();
	}
}
