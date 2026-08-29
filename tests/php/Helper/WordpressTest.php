<?php

namespace CommonsBooking\Tests\Helper;

use CommonsBooking\Helper\Wordpress;
use CommonsBooking\Tests\Wordpress\CustomPostTypeTest;
use PHPUnit\Framework\TestCase;

/**
 * These are unit tests for the helper class WordPress.
 * The methods tested are mainly used to get related posts for cache invalidation.
 */
class WordpressTest extends CustomPostTypeTest {


	private int $timeframeId;
	private int $bookingId;
	private int $restrictionId;

	public function testGetRelatedPostsIdsForItem() {
		$related = Wordpress::getRelatedPostsIdsForItem( $this->itemId );
		$this->assertIsArray( $related );
		$this->assertContains( $this->bookingId, $related );
		$this->assertContains( $this->timeframeId, $related );
		// We cannot search for the restriction, because the restriction repository will filter out queries where we are not searching for both the item and the corresponding location.
		// @see \CommonsBooking\Repository\Restriction::filterPosts()
		// $this->assertContains( $this->restrictionId, $related );
		$this->assertEquals( 3, count( $related ) );

		// test it for timeframes with multiple assigned items
		$otherAssignedItemId = $this->createItem( 'other item' );
		$holidayTFid         = $this->createHolidayTimeframeForAllItemsAndLocations();
		$related             = Wordpress::getRelatedPostsIdsForItem( $otherAssignedItemId );
		$this->assertIsArray( $related );
		$this->assertEquals( 2, count( $related ) );
		$this->assertContains( $holidayTFid, $related );
		$this->assertContains( $otherAssignedItemId, $related );}

	public function testGetRelatedPostsIdsForTimeframe() {
		$related = Wordpress::getRelatedPostsIdsForTimeframe( $this->timeframeId );
		$this->assertIsArray( $related );
		$this->assertContains( $this->timeframeId, $related );
		$this->assertContains( $this->itemId, $related );
		$this->assertContains( $this->locationId, $related );
		$this->assertEquals( 3, count( $related ) );

		// test it for timeframes with multiple assigned items
		$otherAssignedItemId = $this->createItem( 'other item' );
		$holidayTFid         = $this->createHolidayTimeframeForAllItemsAndLocations();

		$related = Wordpress::getRelatedPostsIdsForTimeframe( $holidayTFid );
		$this->assertIsArray( $related );
		$this->assertContains( $holidayTFid, $related );
		$this->assertContains( $otherAssignedItemId, $related );
		$this->assertContains( $this->itemId, $related );
		$this->assertContains( $this->locationId, $related );
		$this->assertEquals( 4, count( $related ) );
	}

	public function testGetLocationAndItemIdsFromPosts() {
		// test for timeframe with single assigned item / location
		$timeframePost = get_post( $this->timeframeId );
		$related       = Wordpress::getLocationAndItemIdsFromPosts( [ $timeframePost ] );
		$this->assertIsArray( $related );
		$this->assertContains( $this->itemId, $related );
		$this->assertContains( $this->locationId, $related );
		$this->assertEquals( 2, count( $related ) );

		// test for timeframe with multiple assigned items / locations
		$secondItem                     = $this->createItem( 'second item' );
		$secondLocation                 = $this->createLocation( 'second location' );
		$secondTimeframe                = $this->createBookableTimeFrameIncludingCurrentDay( $secondLocation, $secondItem );
		$holidayForAllItemsAndLocations = $this->createHolidayTimeframeForAllItemsAndLocations();
		$holidayPost                    = get_post( $holidayForAllItemsAndLocations );
		$related                        = Wordpress::getLocationAndItemIdsFromPosts( [ $holidayPost ] );
		$this->assertIsArray( $related );
		$this->assertEqualsCanonicalizing( [ $this->itemId, $secondItem, $this->locationId, $secondLocation ], $related );

		// test reaction to non-timeframe post types
		$locationPost = get_post( $this->locationId );
		$itemPost     = get_post( $this->itemId );
		$this->assertEmpty( Wordpress::getLocationAndItemIdsFromPosts( [ $locationPost, $itemPost ] ) );
		$this->assertEqualsCanonicalizing( [ $this->itemId, $this->locationId ], Wordpress::getLocationAndItemIdsFromPosts( [ $timeframePost, $locationPost, $itemPost ] ) );
	}

	public function testGetRelatedPostsIdsForLocation() {
		$related = Wordpress::getRelatedPostsIdsForLocation( $this->locationId );
		$this->assertIsArray( $related );
		$this->assertContains( $this->locationId, $related );
		$this->assertContains( $this->timeframeId, $related );
		$this->assertContains( $this->bookingId, $related );
		// We cannot search for the restriction, because the restriction repository will filter out queries where we are not searching for both the item and the corresponding location.
		// @see \CommonsBooking\Repository\Restriction::filterPosts()
		// $this->assertContains( $this->restrictionId, $related );
		$this->assertEquals( 3, count( $related ) );
	}

	public function testGetRelatedPostsIdsForBooking() {
		$related = Wordpress::getRelatedPostsIdsForBooking( $this->bookingId );
		$this->assertIsArray( $related );
		$this->assertContains( $this->bookingId, $related );
		$this->assertContains( $this->itemId, $related );
		$this->assertContains( $this->locationId, $related );
		$this->assertContains( $this->timeframeId, $related );
		$this->assertEquals( 4, count( $related ) );
	}

	public function testGetRelatedPostsIdsForRestriction() {
		$related = Wordpress::getRelatedPostsIdsForRestriction( $this->restrictionId );
		$this->assertIsArray( $related );
		$this->assertContains( $this->itemId, $related );
		$this->assertContains( $this->locationId, $related );
		$this->assertContains( $this->timeframeId, $related );
		$this->assertContains( $this->bookingId, $related );
		$this->assertContains( $this->restrictionId, $related );
		$this->assertEquals( 5, count( $related ) );
	}

	/**
	 * Primed meta must be served from the object cache, so reading it issues no
	 * further database queries. Also guards against a regression of the N+1
	 * pattern by first showing that unprimed reads do hit the database.
	 */
	public function testPrimePostMetaCache() {
		$metaKey = \CommonsBooking\Model\Timeframe::REPETITION_START;
		$posts   = [ get_post( $this->timeframeId ), get_post( $this->bookingId ) ];

		// Control: without priming, each post triggers its own meta query.
		wp_cache_flush();
		$before = get_num_queries();
		get_post_meta( $this->timeframeId, $metaKey, true );
		get_post_meta( $this->bookingId, $metaKey, true );
		$this->assertEquals(
			2,
			get_num_queries() - $before,
			'Without priming each post should trigger its own meta query.'
		);

		// With priming, the meta reads are served from cache (zero extra queries).
		wp_cache_flush();
		Wordpress::primePostMetaCache( $posts );
		$before = get_num_queries();
		get_post_meta( $this->timeframeId, $metaKey, true );
		get_post_meta( $this->bookingId, $metaKey, true );
		$this->assertEquals(
			0,
			get_num_queries() - $before,
			'After priming, meta reads must not hit the database.'
		);
	}

	/**
	 * flattenWpdbResult() is the materialization point for the raw-SQL timeframe
	 * queries; it must leave the meta cache primed so the downstream getMeta()
	 * reads do not fall into an N+1 pattern.
	 */
	public function testFlattenWpdbResultPrimesMetaCache() {
		$metaKey = \CommonsBooking\Model\Timeframe::REPETITION_START;
		$rows    = [
			(object) [ 'ID' => $this->timeframeId ],
			(object) [ 'ID' => $this->bookingId ],
		];

		wp_cache_flush();
		$posts = Wordpress::flattenWpdbResult( $rows );

		$before = get_num_queries();
		get_post_meta( $this->timeframeId, $metaKey, true );
		get_post_meta( $this->bookingId, $metaKey, true );
		$this->assertEquals(
			0,
			get_num_queries() - $before,
			'flattenWpdbResult() should have primed the meta cache for the whole batch.'
		);
		$this->assertCount( 2, $posts );
	}

	protected function setUp(): void {
		parent::setUp();
		$this->timeframeId   = $this->createBookableTimeFrameIncludingCurrentDay();
		$this->bookingId     = $this->createConfirmedBookingStartingToday();
		$this->restrictionId = $this->createRestriction(
			'hint',
			$this->locationId,
			$this->itemId,
			strtotime( self::CURRENT_DATE ),
			strtotime( '+1 day', strtotime( self::CURRENT_DATE ) )
		);
	}

	protected function tearDown(): void {
		parent::tearDown();
	}
}
