<?php

namespace CommonsBooking\Tests\Repository;

use CommonsBooking\Plugin;
use CommonsBooking\Repository\AvailabilityIndex;
use CommonsBooking\Settings\Settings;
use CommonsBooking\Tests\Wordpress\CustomPostTypeTest;
use CommonsBooking\Wordpress\CustomPostType\Timeframe as TimeframeCPT;

/**
 * Covers the optional availability index: its tables, the sync hooks, the queries and the
 * on/off lifecycle.
 */
class AvailabilityIndexTest extends CustomPostTypeTest {

	private const OPTION_KEY   = COMMONSBOOKING_PLUGIN_SLUG . '_options_advanced-options';
	private const OPTION_FIELD = 'availability_index_enabled';

	private int $secondLocationId;
	private int $secondItemId;

	// ---------------------------------------------------------------- helpers

	private function enableIndex(): void {
		Settings::updateOption( self::OPTION_KEY, self::OPTION_FIELD, 'on' );
		AvailabilityIndex::reconcile();
		$this->rebuildAll();
	}

	private function disableIndex(): void {
		Settings::updateOption( self::OPTION_KEY, self::OPTION_FIELD, '' );
		AvailabilityIndex::reconcile();
	}

	/**
	 * Runs the paginated rebuild to completion.
	 */
	private function rebuildAll(): void {
		$page   = 1;
		$guard  = 0;
		$result = false;

		while ( $result !== true && $guard++ < 500 ) {
			$result = AvailabilityIndex::rebuildFromAllTimeframes( $page );
			if ( is_int( $result ) ) {
				$page = $result;
			}
		}
	}

	/**
	 * Re-saves a post so the real sync hooks fire.
	 *
	 * CPTCreationTrait::createTimeframe() writes its meta *after* wp_insert_post(), so the
	 * hook fires before type/item/location exist and the timeframe is not indexed yet.
	 */
	private function reindexViaHook( int $postId ): void {
		wp_update_post( array( 'ID' => $postId ) );
	}

	private function tableExists( string $table ): bool {
		global $wpdb;
		$name = $wpdb->prefix . $table;

		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) );
	}

	private function indexRow( int $timeframeId ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $wpdb->prefix . AvailabilityIndex::$indexTable . ' WHERE timeframe_id = %d',
				$timeframeId
			)
		);
	}

	/**
	 * @return int[]
	 */
	private function relationIds( string $table, string $column, int $timeframeId ): array {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT $column FROM " . $wpdb->prefix . $table . ' WHERE timeframe_id = %d',
				$timeframeId
			)
		);

		$ids = array_map( 'intval', $ids );
		sort( $ids );

		return $ids;
	}

	private function locationIdsOf( int $timeframeId ): array {
		return $this->relationIds( AvailabilityIndex::$locationsTable, 'location_id', $timeframeId );
	}

	private function itemIdsOf( int $timeframeId ): array {
		return $this->relationIds( AvailabilityIndex::$itemsTable, 'item_id', $timeframeId );
	}

	/**
	 * Normalises ids from either path so they can be compared.
	 */
	private function normalise( $ids ): array {
		$ids = array_map( 'intval', (array) $ids );
		sort( $ids );

		return $ids;
	}

	private function createBookableTimeframe( $locations, $items, string $start = '+1 day', ?string $end = '+10 days' ): int {
		return $this->createTimeframe(
			$locations,
			$items,
			strtotime( $start, strtotime( self::CURRENT_DATE ) ),
			$end ? strtotime( $end, strtotime( self::CURRENT_DATE ) ) : '',
			TimeframeCPT::BOOKABLE_ID
		);
	}

	// ---------------------------------------------------------------- A. tables

	public function testInitTablesCreatesAllThreeTables() {
		$this->assertTrue( $this->tableExists( AvailabilityIndex::$indexTable ) );
		$this->assertTrue( $this->tableExists( AvailabilityIndex::$locationsTable ) );
		$this->assertTrue( $this->tableExists( AvailabilityIndex::$itemsTable ) );
	}

	public function testInitTablesIsIdempotent() {
		AvailabilityIndex::initTables();
		AvailabilityIndex::initTables();

		$this->assertTrue( $this->tableExists( AvailabilityIndex::$indexTable ) );
	}

	public function testDropTablesRemovesThem() {
		AvailabilityIndex::dropTables();

		$this->assertFalse( $this->tableExists( AvailabilityIndex::$indexTable ) );
		$this->assertFalse( $this->tableExists( AvailabilityIndex::$locationsTable ) );
		$this->assertFalse( $this->tableExists( AvailabilityIndex::$itemsTable ) );
	}

	// ---------------------------------------------------------------- B. upsert

	public function testIndexesBookableTimeframe() {
		$timeframeId = $this->createBookableTimeframe( $this->locationId, $this->itemId );
		$this->rebuildAll();

		$row = $this->indexRow( $timeframeId );

		$this->assertNotNull( $row );
		$this->assertEquals( TimeframeCPT::BOOKABLE_ID, (int) $row->type );
		$this->assertEquals( 'publish', $row->post_status );
		$this->assertEquals( array( $this->locationId ), $this->locationIdsOf( $timeframeId ) );
		$this->assertEquals( array( $this->itemId ), $this->itemIdsOf( $timeframeId ) );
	}

	public function testStoresNullEndDateForOpenEndedTimeframe() {
		$timeframeId = $this->createBookableTimeframe( $this->locationId, $this->itemId, '+1 day', null );
		$this->rebuildAll();

		$this->assertNull( $this->indexRow( $timeframeId )->end_date );
	}

	public function testKeepsOneIndexRowPerTimeframeAndOneRowPerRelation() {
		$locations   = array( $this->locationId, $this->secondLocationId );
		$items       = array( $this->itemId, $this->secondItemId );
		$timeframeId = $this->createBookableTimeframe( $locations, $items );
		$this->rebuildAll();

		global $wpdb;
		$indexRows = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $wpdb->prefix . AvailabilityIndex::$indexTable . ' WHERE timeframe_id = %d',
				$timeframeId
			)
		);

		// One index row, but a relation row per location and per item.
		$this->assertEquals( 1, $indexRows );
		$this->assertEquals( $this->normalise( $locations ), $this->locationIdsOf( $timeframeId ) );
		$this->assertEquals( $this->normalise( $items ), $this->itemIdsOf( $timeframeId ) );
	}

	public function testUpsertIsIdempotent() {
		$timeframeId = $this->createBookableTimeframe( $this->locationId, $this->itemId );

		$this->rebuildAll();
		$this->reindexViaHook( $timeframeId );
		$this->reindexViaHook( $timeframeId );

		$this->assertEquals( array( $this->locationId ), $this->locationIdsOf( $timeframeId ) );
		$this->assertEquals( array( $this->itemId ), $this->itemIdsOf( $timeframeId ) );
	}

	public function testUpsertReplacesChangedRelations() {
		$timeframeId = $this->createBookableTimeframe( $this->locationId, $this->itemId );
		$this->rebuildAll();

		update_post_meta(
			$timeframeId,
			\CommonsBooking\Model\Timeframe::META_LOCATION_ID,
			$this->secondLocationId
		);
		$this->reindexViaHook( $timeframeId );

		$this->assertEquals( array( $this->secondLocationId ), $this->locationIdsOf( $timeframeId ) );
	}

	// ---------------------------------------------------------------- C. exclusions

	public function testDoesNotIndexNonAvailabilityType() {
		$timeframeId = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			strtotime( '+1 day', strtotime( self::CURRENT_DATE ) ),
			strtotime( '+10 days', strtotime( self::CURRENT_DATE ) ),
			TimeframeCPT::BOOKING_CANCELED_ID
		);
		$this->rebuildAll();

		$this->assertNull( $this->indexRow( $timeframeId ) );
	}

	public function testDoesNotIndexTrashedTimeframe() {
		$timeframeId = $this->createBookableTimeframe( $this->locationId, $this->itemId );
		$this->rebuildAll();
		$this->assertNotNull( $this->indexRow( $timeframeId ) );

		wp_trash_post( $timeframeId );

		$this->assertNull( $this->indexRow( $timeframeId ) );
	}

	/**
	 * Canceled bookings must stay in the index: the query it replaces does not filter by
	 * post status, and callers do ask for them.
	 */
	public function testIndexesCanceledBooking() {
		$timeframeId = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			strtotime( '+1 day', strtotime( self::CURRENT_DATE ) ),
			strtotime( '+10 days', strtotime( self::CURRENT_DATE ) ),
			TimeframeCPT::BOOKING_ID,
			'on',
			'w',
			0,
			'8:00 AM',
			'12:00 PM',
			'canceled'
		);
		$this->rebuildAll();

		$row = $this->indexRow( $timeframeId );

		$this->assertNotNull( $row );
		$this->assertEquals( 'canceled', $row->post_status );
	}

	public function testDoesNotIndexTimeframeWithoutRelations() {
		$timeframeId = wp_insert_post(
			array(
				'post_title'  => 'Orphan',
				'post_type'   => TimeframeCPT::$postType,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $timeframeId, 'type', TimeframeCPT::BOOKABLE_ID );
		update_post_meta( $timeframeId, 'repetition-start', strtotime( self::CURRENT_DATE ) );
		$this->timeframeIds[] = $timeframeId;

		$this->rebuildAll();

		$this->assertNull( $this->indexRow( $timeframeId ) );
	}

	// ---------------------------------------------------------------- D. deletion

	public function testDeleteByTimeframeIdClearsAllTables() {
		$timeframeId = $this->createBookableTimeframe( $this->locationId, $this->itemId );
		$this->rebuildAll();

		AvailabilityIndex::deleteByTimeframeId( $timeframeId );

		$this->assertNull( $this->indexRow( $timeframeId ) );
		$this->assertSame( array(), $this->locationIdsOf( $timeframeId ) );
		$this->assertSame( array(), $this->itemIdsOf( $timeframeId ) );
	}

	public function testRemoveLocationOnlyDropsItsRelations() {
		$timeframeId = $this->createBookableTimeframe(
			array( $this->locationId, $this->secondLocationId ),
			$this->itemId
		);
		$this->rebuildAll();

		AvailabilityIndex::removeLocation( $this->secondLocationId );

		// The index row, the item relations and the other location survive.
		$this->assertNotNull( $this->indexRow( $timeframeId ) );
		$this->assertEquals( array( $this->locationId ), $this->locationIdsOf( $timeframeId ) );
		$this->assertEquals( array( $this->itemId ), $this->itemIdsOf( $timeframeId ) );
	}

	public function testRemoveItemOnlyDropsItsRelations() {
		$timeframeId = $this->createBookableTimeframe(
			$this->locationId,
			array( $this->itemId, $this->secondItemId )
		);
		$this->rebuildAll();

		AvailabilityIndex::removeItem( $this->secondItemId );

		$this->assertNotNull( $this->indexRow( $timeframeId ) );
		$this->assertEquals( array( $this->itemId ), $this->itemIdsOf( $timeframeId ) );
		$this->assertEquals( array( $this->locationId ), $this->locationIdsOf( $timeframeId ) );
	}

	// ---------------------------------------------------------------- E. hooks

	public function testHookIndexesTimeframeOnSave() {
		$timeframeId = $this->createBookableTimeframe( $this->locationId, $this->itemId );

		// Not indexed yet: createTimeframe() writes its meta after wp_insert_post().
		$this->assertNull( $this->indexRow( $timeframeId ) );

		$this->reindexViaHook( $timeframeId );

		$this->assertNotNull( $this->indexRow( $timeframeId ) );
	}

	public function testHookRemovesTimeframeOnHardDelete() {
		$timeframeId = $this->createBookableTimeframe( $this->locationId, $this->itemId );
		$this->rebuildAll();
		$this->assertNotNull( $this->indexRow( $timeframeId ) );

		wp_delete_post( $timeframeId, true );

		$this->assertNull( $this->indexRow( $timeframeId ) );
	}

	public function testHookDropsRelationsWhenLocationIsDeleted() {
		$timeframeId = $this->createBookableTimeframe(
			array( $this->locationId, $this->secondLocationId ),
			$this->itemId
		);
		$this->rebuildAll();

		wp_delete_post( $this->secondLocationId, true );

		$this->assertNotNull( $this->indexRow( $timeframeId ) );
		$this->assertEquals( array( $this->locationId ), $this->locationIdsOf( $timeframeId ) );
	}

	// ---------------------------------------------------------------- F. date range query

	public function testDateRangeQueryReturnsOverlappingTimeframe() {
		$timeframeId = $this->createBookableTimeframe( $this->locationId, $this->itemId, '+1 day', '+10 days' );
		$this->rebuildAll();

		$rows = AvailabilityIndex::getByLocationAndItemAndDateRange(
			$this->locationId,
			$this->itemId,
			date( 'Y-m-d', strtotime( '+2 days', strtotime( self::CURRENT_DATE ) ) ),
			date( 'Y-m-d', strtotime( '+5 days', strtotime( self::CURRENT_DATE ) ) )
		);

		$this->assertEquals( array( $timeframeId ), $this->normalise( wp_list_pluck( $rows, 'timeframe_id' ) ) );
	}

	public function testDateRangeQueryExcludesNonOverlappingTimeframe() {
		$this->createBookableTimeframe( $this->locationId, $this->itemId, '+1 day', '+5 days' );
		$this->rebuildAll();

		$rows = AvailabilityIndex::getByLocationAndItemAndDateRange(
			$this->locationId,
			$this->itemId,
			date( 'Y-m-d', strtotime( '+20 days', strtotime( self::CURRENT_DATE ) ) ),
			date( 'Y-m-d', strtotime( '+25 days', strtotime( self::CURRENT_DATE ) ) )
		);

		$this->assertSame( array(), $rows );
	}

	public function testDateRangeQueryIncludesOpenEndedTimeframe() {
		$timeframeId = $this->createBookableTimeframe( $this->locationId, $this->itemId, '+1 day', null );
		$this->rebuildAll();

		$rows = AvailabilityIndex::getByLocationAndItemAndDateRange(
			$this->locationId,
			$this->itemId,
			date( 'Y-m-d', strtotime( '+300 days', strtotime( self::CURRENT_DATE ) ) ),
			date( 'Y-m-d', strtotime( '+310 days', strtotime( self::CURRENT_DATE ) ) )
		);

		$this->assertEquals( array( $timeframeId ), $this->normalise( wp_list_pluck( $rows, 'timeframe_id' ) ) );
	}

	public function testDateRangeQueryIncludesTimeframeEndingOnWindowStart() {
		$timeframeId = $this->createBookableTimeframe( $this->locationId, $this->itemId, '+1 day', '+5 days' );
		$this->rebuildAll();

		$boundary = date( 'Y-m-d', strtotime( '+5 days', strtotime( self::CURRENT_DATE ) ) );
		$rows     = AvailabilityIndex::getByLocationAndItemAndDateRange(
			$this->locationId,
			$this->itemId,
			$boundary,
			$boundary
		);

		$this->assertEquals( array( $timeframeId ), $this->normalise( wp_list_pluck( $rows, 'timeframe_id' ) ) );
	}

	public function testDateRangeQueryDoesNotLeakAcrossLocations() {
		$this->createBookableTimeframe( $this->locationId, $this->itemId );
		$this->rebuildAll();

		$rows = AvailabilityIndex::getByLocationAndItemAndDateRange(
			$this->secondLocationId,
			$this->itemId,
			date( 'Y-m-d', strtotime( self::CURRENT_DATE ) ),
			date( 'Y-m-d', strtotime( '+30 days', strtotime( self::CURRENT_DATE ) ) )
		);

		$this->assertSame( array(), $rows );
	}

	// ---------------------------------------------------------------- G. parity

	/**
	 * Compares the index against the query it replaces. This is what makes switching the
	 * feature on safe.
	 *
	 * @dataProvider parityProvider
	 */
	public function testParityWithDefaultQuery( string $fixture ) {
		$this->{$fixture}();

		$types     = array( TimeframeCPT::BOOKABLE_ID, TimeframeCPT::HOLIDAYS_ID, TimeframeCPT::BOOKING_ID, TimeframeCPT::REPAIR_ID );
		$scenarios = array(
			'single item and location' => array( array( $this->itemId ), array( $this->locationId ) ),
			'second item and location' => array( array( $this->secondItemId ), array( $this->secondLocationId ) ),
			'all items'                => array( array(), array( $this->locationId ) ),
			'all locations'            => array( array( $this->itemId ), array() ),
			'everything'               => array( array(), array() ),
		);

		foreach ( $scenarios as $label => $args ) {
			list( $items, $locations ) = $args;

			$this->disableIndex();
			Plugin::clearCache();
			$expected = \CommonsBooking\Repository\Timeframe::getPostIdsByType( $types, $items, $locations );

			$this->enableIndex();
			Plugin::clearCache();
			$actual = AvailabilityIndex::getPostIdsByType( $types, $items, $locations );

			$this->assertNotNull( $actual, "index returned null for: $label" );
			$this->assertEquals(
				$this->normalise( $expected ),
				$this->normalise( $actual ),
				"index and default query disagree for: $label"
			);
		}
	}

	public function parityProvider(): array {
		return array(
			'single selection'  => array( 'fixtureSingleSelection' ),
			'multi selection'   => array( 'fixtureMultiSelection' ),
			'mixed types'       => array( 'fixtureMixedTypes' ),
			'canceled booking'  => array( 'fixtureCanceledBooking' ),
		);
	}

	private function fixtureSingleSelection(): void {
		$this->createBookableTimeframe( $this->locationId, $this->itemId );
		$this->createBookableTimeframe( $this->secondLocationId, $this->secondItemId );
	}

	private function fixtureMultiSelection(): void {
		$this->createBookableTimeframe(
			array( $this->locationId, $this->secondLocationId ),
			array( $this->itemId, $this->secondItemId )
		);
		$this->createBookableTimeframe( $this->locationId, $this->itemId );
	}

	private function fixtureMixedTypes(): void {
		$this->createBookableTimeframe( $this->locationId, $this->itemId );
		$this->createTimeframe(
			array( $this->locationId, $this->secondLocationId ),
			array( $this->itemId, $this->secondItemId ),
			strtotime( '+1 day', strtotime( self::CURRENT_DATE ) ),
			strtotime( '+3 days', strtotime( self::CURRENT_DATE ) ),
			TimeframeCPT::HOLIDAYS_ID
		);
		$this->createTimeframe(
			$this->secondLocationId,
			$this->secondItemId,
			strtotime( '+4 days', strtotime( self::CURRENT_DATE ) ),
			strtotime( '+6 days', strtotime( self::CURRENT_DATE ) ),
			TimeframeCPT::REPAIR_ID
		);
	}

	private function fixtureCanceledBooking(): void {
		$this->createBookableTimeframe( $this->locationId, $this->itemId );
		$this->createTimeframe(
			$this->locationId,
			$this->itemId,
			strtotime( '+2 days', strtotime( self::CURRENT_DATE ) ),
			strtotime( '+3 days', strtotime( self::CURRENT_DATE ) ),
			TimeframeCPT::BOOKING_ID,
			'on',
			'w',
			0,
			'8:00 AM',
			'12:00 PM',
			'canceled'
		);
	}

	/**
	 * The index holds only the availability types, so a query that also asks for another
	 * type must fall back instead of quietly dropping those posts.
	 *
	 * Migration\Booking::migrate() does exactly this with BOOKING_CANCELED_ID.
	 */
	public function testFallsBackForTypesTheIndexDoesNotHold() {
		$this->assertNull(
			AvailabilityIndex::getPostIdsByType(
				array( TimeframeCPT::BOOKING_ID, TimeframeCPT::BOOKING_CANCELED_ID )
			)
		);
	}

	public function testMigrationStillSeesCanceledBookingTimeframes() {
		$canceledId = $this->createTimeframe(
			$this->locationId,
			$this->itemId,
			strtotime( '+1 day', strtotime( self::CURRENT_DATE ) ),
			strtotime( '+2 days', strtotime( self::CURRENT_DATE ) ),
			TimeframeCPT::BOOKING_CANCELED_ID
		);
		$this->rebuildAll();
		Plugin::clearCache();

		$found = \CommonsBooking\Repository\Timeframe::getPostIdsByType(
			array( TimeframeCPT::BOOKING_ID, TimeframeCPT::BOOKING_CANCELED_ID )
		);

		$this->assertContains( $canceledId, $this->normalise( $found ) );
	}

	// ---------------------------------------------------------------- H. lifecycle

	public function testReturnsNullWhileSwitchedOff() {
		$this->disableIndex();

		$this->assertNull( AvailabilityIndex::getPostIdsByType( array( TimeframeCPT::BOOKABLE_ID ) ) );
	}

	public function testReconcileCreatesAndDropsTables() {
		$this->disableIndex();
		$this->assertFalse( $this->tableExists( AvailabilityIndex::$indexTable ) );
		$this->assertFalse( AvailabilityIndex::isEnabled() );

		Settings::updateOption( self::OPTION_KEY, self::OPTION_FIELD, 'on' );
		AvailabilityIndex::reconcile();

		$this->assertTrue( $this->tableExists( AvailabilityIndex::$indexTable ) );
		$this->assertTrue( AvailabilityIndex::isEnabled() );
	}

	public function testFreshlyEnabledIndexIsNotReadUntilRebuilt() {
		$this->disableIndex();

		Settings::updateOption( self::OPTION_KEY, self::OPTION_FIELD, 'on' );
		AvailabilityIndex::reconcile();

		// Tables exist but are empty, so reads must still fall back.
		$this->assertTrue( AvailabilityIndex::isEnabled() );
		$this->assertFalse( AvailabilityIndex::isReadable() );
		$this->assertNull( AvailabilityIndex::getPostIdsByType( array( TimeframeCPT::BOOKABLE_ID ) ) );

		$this->rebuildAll();

		$this->assertTrue( AvailabilityIndex::isReadable() );
	}

	/**
	 * Try it, stop trying it, try it again.
	 */
	public function testEnableDisableReEnableCycle() {
		$timeframeId = $this->createBookableTimeframe( $this->locationId, $this->itemId );
		$this->rebuildAll();
		$this->assertNotNull( $this->indexRow( $timeframeId ) );

		$this->disableIndex();
		$this->assertFalse( $this->tableExists( AvailabilityIndex::$indexTable ) );
		$this->assertNull( AvailabilityIndex::getPostIdsByType( array( TimeframeCPT::BOOKABLE_ID ) ) );

		$this->enableIndex();
		$this->assertNotNull( $this->indexRow( $timeframeId ) );
	}

	public function testRepositoryReturnsSameResultsWithIndexOnAndOff() {
		$this->fixtureMixedTypes();

		$types = array( TimeframeCPT::BOOKABLE_ID, TimeframeCPT::HOLIDAYS_ID, TimeframeCPT::REPAIR_ID );

		$this->disableIndex();
		Plugin::clearCache();
		$withoutIndex = \CommonsBooking\Repository\Timeframe::getPostIdsByType( $types );

		$this->enableIndex();
		Plugin::clearCache();
		$withIndex = \CommonsBooking\Repository\Timeframe::getPostIdsByType( $types );

		$this->assertEquals( $this->normalise( $withoutIndex ), $this->normalise( $withIndex ) );
	}

	// ---------------------------------------------------------------- I. rebuild

	public function testRebuildPopulatesFromExistingPosts() {
		$this->disableIndex();

		$timeframeId = $this->createBookableTimeframe( $this->locationId, $this->itemId );

		$this->enableIndex();

		$this->assertNotNull( $this->indexRow( $timeframeId ) );
	}

	public function testRebuildIsIdempotent() {
		$timeframeId = $this->createBookableTimeframe( $this->locationId, $this->itemId );

		$this->rebuildAll();
		$this->rebuildAll();

		$this->assertEquals( array( $this->locationId ), $this->locationIdsOf( $timeframeId ) );
		$this->assertEquals( array( $this->itemId ), $this->itemIdsOf( $timeframeId ) );
	}

	// ---------------------------------------------------------------- lifecycle

	protected function setUp(): void {
		parent::setUp();

		$this->secondLocationId = $this->createLocation( 'Testlocation 2' );
		$this->secondItemId     = $this->createItem( 'TestItem 2' );

		$this->enableIndex();
	}

	protected function tearDown(): void {
		Settings::updateOption( self::OPTION_KEY, self::OPTION_FIELD, '' );
		AvailabilityIndex::dropTables();
		delete_option( 'cb_availability_index_active' );
		delete_option( 'cb_availability_index_needs_rebuild' );

		parent::tearDown();
	}
}
