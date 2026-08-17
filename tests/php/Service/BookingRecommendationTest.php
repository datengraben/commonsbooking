<?php

namespace CommonsBooking\Tests\Service;

use CommonsBooking\Service\BookingRecommendation;
use CommonsBooking\Tests\Wordpress\CustomPostTypeTest;
use CommonsBooking\Wordpress\CustomPostType\Item;
use SlopeIt\ClockMock\ClockMock;

class BookingRecommendationTest extends CustomPostTypeTest {

	/**
	 * Bookable timeframe spanning the whole test window (-30 .. +30 days).
	 *
	 * @var int
	 */
	private $bookableTimeframeId;

	public function testGetBookAgainSuggestionsReturnsPreviouslyBookedFreeItem() {
		// The user has a past booking for the item, and the item is free from today on.
		$suggestions = BookingRecommendation::getBookAgainSuggestions(
			get_user_by( 'id', self::USER_ID ),
			BookingRecommendation::WINDOW_WEEK
		);

		$itemIds = array_column( $suggestions, 'itemId' );
		$this->assertContains( $this->itemId, $itemIds );
	}

	public function testGetBookAgainSuggestionsEmptyWithoutHistory() {
		// A fresh subscriber without any bookings gets no "book again" suggestions.
		$this->createSubscriber();
		$subscriber = get_user_by( 'id', $this->subscriberId );

		$suggestions = BookingRecommendation::getBookAgainSuggestions(
			$subscriber,
			BookingRecommendation::WINDOW_WEEK
		);

		$this->assertEmpty( $suggestions );
	}

	public function testGetBookAgainSuggestionsExcludesFullyBlockedItem() {
		// Block the whole rolling week with a holiday -> item is not free anymore.
		$this->createTimeframe(
			$this->locationId,
			$this->itemId,
			strtotime( '-1 day', strtotime( self::CURRENT_DATE ) ),
			strtotime( '+8 days', strtotime( self::CURRENT_DATE ) ),
			\CommonsBooking\Wordpress\CustomPostType\Timeframe::HOLIDAYS_ID
		);

		$suggestions = BookingRecommendation::getBookAgainSuggestions(
			get_user_by( 'id', self::USER_ID ),
			BookingRecommendation::WINDOW_WEEK
		);

		$itemIds = array_column( $suggestions, 'itemId' );
		$this->assertNotContains( $this->itemId, $itemIds );
	}

	public function testGetSimilarSuggestionsReturnsSameCategoryItem() {
		// Create a sibling item in the same category, bookable and never booked.
		$term = wp_create_term( 'Recommendation Test Category', Item::getTaxonomyName() );
		wp_set_post_terms( $this->itemId, [ $term['term_id'] ], Item::getTaxonomyName() );

		$siblingItemId = $this->createItem( 'SiblingItem' );
		wp_set_post_terms( $siblingItemId, [ $term['term_id'] ], Item::getTaxonomyName() );
		$this->createTimeframe(
			$this->locationId,
			$siblingItemId,
			strtotime( '-30 days', strtotime( self::CURRENT_DATE ) ),
			strtotime( '+30 days', strtotime( self::CURRENT_DATE ) )
		);

		$suggestions = BookingRecommendation::getSimilarSuggestions(
			get_user_by( 'id', self::USER_ID ),
			BookingRecommendation::WINDOW_WEEK
		);

		$itemIds = array_column( $suggestions, 'itemId' );
		// The sibling is suggested, the already-booked item is not.
		$this->assertContains( $siblingItemId, $itemIds );
		$this->assertNotContains( $this->itemId, $itemIds );
	}

	public function testGetSimilarSuggestionsEmptyWithoutCategories() {
		// The booked item has no category assigned -> nothing to base similarity on.
		$suggestions = BookingRecommendation::getSimilarSuggestions(
			get_user_by( 'id', self::USER_ID ),
			BookingRecommendation::WINDOW_WEEK
		);

		$this->assertEmpty( $suggestions );
	}

	protected function setUp(): void {
		parent::setUp();

		ClockMock::freeze( new \DateTime( self::CURRENT_DATE ) );
		wp_set_current_user( self::USER_ID );

		// Item is bookable across the whole test window.
		$this->bookableTimeframeId = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			strtotime( '-30 days', strtotime( self::CURRENT_DATE ) ),
			strtotime( '+30 days', strtotime( self::CURRENT_DATE ) )
		);

		// The user booked the item in the past (does not block the current window).
		$this->createBooking(
			$this->locationId,
			$this->itemId,
			strtotime( '-10 days', strtotime( self::CURRENT_DATE ) ),
			strtotime( '-9 days', strtotime( self::CURRENT_DATE ) ),
			'12:00 AM',
			'23:59',
			'confirmed',
			self::USER_ID
		);
	}

	protected function tearDown(): void {
		ClockMock::reset();
		parent::tearDown();
	}
}
