<?php

namespace CommonsBooking\Tests\Service;

use CommonsBooking\Service\ICSImport;
use CommonsBooking\Tests\Wordpress\CustomPostTypeTest;
use CommonsBooking\Wordpress\CustomPostType\Timeframe as TimeframeCPT;
use CommonsBooking\Model\Timeframe as TimeframeModel;

class ICSImportTest extends CustomPostTypeTest {

	// Minimal valid ICS with two VEVENTs
	const SAMPLE_ICS = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:event-utc-001\r\nSUMMARY:UTC Event\r\nDTSTART:20210701T080000Z\r\nDTEND:20210701T100000Z\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nUID:event-allday-002\r\nSUMMARY:All Day Event\r\nDTSTART;VALUE=DATE:20210702\r\nDTEND;VALUE=DATE:20210703\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

	// ICS with just one event (used for cleanup tests)
	const SAMPLE_ICS_SINGLE = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:event-utc-001\r\nSUMMARY:UTC Event\r\nDTSTART:20210701T080000Z\r\nDTEND:20210701T100000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

	private int $feedId;

	// -------------------------------------------------------------------------
	// Parser unit tests
	// -------------------------------------------------------------------------

	public function testParseVEventsReturnsEvents() {
		$events = ICSImport::parseVEvents( self::SAMPLE_ICS );
		$this->assertCount( 2, $events );
	}

	public function testParseVEventsExtractsUidAndSummary() {
		$events = ICSImport::parseVEvents( self::SAMPLE_ICS );
		$this->assertEquals( 'event-utc-001', $events[0]['uid'] );
		$this->assertEquals( 'UTC Event', $events[0]['summary'] );
	}

	public function testParseDatetimeUTC() {
		$dt = ICSImport::parseDatetime( '20210701T080000Z', null, false );
		// The returned object must be in the site timezone
		$this->assertEquals( 'UTC', $dt->getTimezone()->getName() );
		$this->assertEquals( strtotime( '2021-07-01 08:00:00 UTC' ), $dt->getTimestamp() );
	}

	public function testParseDatetimeFloating() {
		$value = '20210701T120000';
		$dt    = ICSImport::parseDatetime( $value, null, false );
		// Floating time is treated as site local — timestamp should match site timezone interpretation
		$siteTz   = wp_timezone();
		$expected = new \DateTimeImmutable( '2021-07-01 12:00:00', $siteTz );
		$this->assertEquals( $expected->getTimestamp(), $dt->getTimestamp() );
	}

	public function testParseDatetimeAllDay() {
		$dt = ICSImport::parseDatetime( '20210701', null, true );
		// Should be midnight of that day in site timezone
		$siteTz   = wp_timezone();
		$expected = new \DateTimeImmutable( '2021-07-01 00:00:00', $siteTz );
		$this->assertEquals( $expected->getTimestamp(), $dt->getTimestamp() );
	}

	public function testParseDatetimeWithTZID() {
		$dt = ICSImport::parseDatetime( '20210701T100000', 'Europe/Berlin', false );
		$expected = new \DateTimeImmutable( '2021-07-01 10:00:00', new \DateTimeZone( 'Europe/Berlin' ) );
		$this->assertEquals( $expected->getTimestamp(), $dt->getTimestamp() );
	}

	public function testParseVEventsUnfoldsLongLines() {
		$folded = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:fold-test\r\nSUMMARY:This is a very long summ\r\n ary that was folded\r\nDTSTART:20210701T080000Z\r\nDTEND:20210701T100000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
		$events = ICSImport::parseVEvents( $folded );
		$this->assertCount( 1, $events );
		$this->assertEquals( 'This is a very long summary that was folded', $events[0]['summary'] );
	}

	public function testParseVEventsAllDayEndIsExclusive() {
		// DTEND:20210703 means the block covers 20210701 and 20210702 only
		$events = ICSImport::parseVEvents( self::SAMPLE_ICS );
		$allDay = $events[1];

		$siteTz    = wp_timezone();
		$startMidnight = ( new \DateTimeImmutable( '2021-07-02 00:00:00', $siteTz ) )->getTimestamp();
		// End should be one second before midnight of 20210703, i.e. end of 20210702
		$endOfDay  = ( new \DateTimeImmutable( '2021-07-02 23:59:59', $siteTz ) )->getTimestamp();

		$this->assertEquals( $startMidnight, $allDay['start'] );
		$this->assertEquals( $endOfDay, $allDay['end'] );
	}

	// -------------------------------------------------------------------------
	// Integration tests — creates real WP posts and asserts DB state
	// -------------------------------------------------------------------------

	public function testSyncFeedCreatesTimeframes() {
		// Replace fetchICS via filter so no HTTP needed
		add_filter( 'pre_http_request', [ $this, 'mockHttpResponse' ], 10, 3 );

		ICSImport::syncFeed( $this->feedId );

		remove_filter( 'pre_http_request', [ $this, 'mockHttpResponse' ] );

		// Expect 2 events × 1 item × 1 location = 2 timeframes
		$timeframes = get_posts( [
			'post_type'   => TimeframeCPT::$postType,
			'post_status' => 'publish',
			'numberposts' => -1,
			'meta_query'  => [ [ 'key' => ICSImport::SOURCE_FEED_META, 'value' => $this->feedId ] ],
		] );

		$this->assertCount( 2, $timeframes );

		// Verify the type is HOLIDAYS_ID
		foreach ( $timeframes as $tf ) {
			$this->assertEquals( TimeframeCPT::HOLIDAYS_ID, get_post_meta( $tf->ID, 'type', true ) );
		}

		// Verify last sync meta is set
		$this->assertNotEmpty( get_post_meta( $this->feedId, '_cb_ics_feed_last_sync', true ) );
		$this->assertEmpty( get_post_meta( $this->feedId, '_cb_ics_feed_last_error', true ) );
	}

	public function testSyncFeedCleansUpRemovedEvents() {
		add_filter( 'pre_http_request', [ $this, 'mockHttpResponse' ], 10, 3 );
		ICSImport::syncFeed( $this->feedId );
		remove_filter( 'pre_http_request', [ $this, 'mockHttpResponse' ] );

		// 2 timeframes created
		$timeframes = get_posts( [
			'post_type'   => TimeframeCPT::$postType,
			'numberposts' => -1,
			'meta_query'  => [ [ 'key' => ICSImport::SOURCE_FEED_META, 'value' => $this->feedId ] ],
		] );
		$this->assertCount( 2, $timeframes );

		// Second sync with only 1 event in the ICS
		add_filter( 'pre_http_request', [ $this, 'mockHttpResponseSingle' ], 10, 3 );
		ICSImport::syncFeed( $this->feedId );
		remove_filter( 'pre_http_request', [ $this, 'mockHttpResponseSingle' ] );

		$remaining = get_posts( [
			'post_type'   => TimeframeCPT::$postType,
			'numberposts' => -1,
			'meta_query'  => [ [ 'key' => ICSImport::SOURCE_FEED_META, 'value' => $this->feedId ] ],
		] );

		// Only 1 timeframe should remain (the removed event's timeframe is deleted)
		$this->assertCount( 1, $remaining );
	}

	public function testSyncFeedDoesNotDuplicateOnReSyncSameEvents() {
		add_filter( 'pre_http_request', [ $this, 'mockHttpResponse' ], 10, 3 );
		ICSImport::syncFeed( $this->feedId );
		ICSImport::syncFeed( $this->feedId ); // sync twice
		remove_filter( 'pre_http_request', [ $this, 'mockHttpResponse' ] );

		$timeframes = get_posts( [
			'post_type'   => TimeframeCPT::$postType,
			'numberposts' => -1,
			'meta_query'  => [ [ 'key' => ICSImport::SOURCE_FEED_META, 'value' => $this->feedId ] ],
		] );

		// Still only 2, not 4
		$this->assertCount( 2, $timeframes );
	}

	public function testSyncFeedErrorOnMissingUrl() {
		// Feed with no URL set
		$emptyFeedId = wp_insert_post( [
			'post_title'  => 'Empty Feed',
			'post_type'   => 'cb_ics_feed',
			'post_status' => 'publish',
		] );
		update_post_meta( $emptyFeedId, '_cb_ics_feed_item_ids', [ $this->itemId ] );
		update_post_meta( $emptyFeedId, '_cb_ics_feed_location_ids', [ $this->locationId ] );

		ICSImport::syncFeed( $emptyFeedId );

		$error = get_post_meta( $emptyFeedId, '_cb_ics_feed_last_error', true );
		$this->assertNotEmpty( $error );

		wp_delete_post( $emptyFeedId, true );
	}

	// -------------------------------------------------------------------------
	// HTTP mock helpers
	// -------------------------------------------------------------------------

	public function mockHttpResponse( $preempt, $args, $url ) {
		return [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => self::SAMPLE_ICS,
			'headers'  => [],
			'cookies'  => [],
		];
	}

	public function mockHttpResponseSingle( $preempt, $args, $url ) {
		return [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => self::SAMPLE_ICS_SINGLE,
			'headers'  => [],
			'cookies'  => [],
		];
	}

	// -------------------------------------------------------------------------
	// Setup / teardown
	// -------------------------------------------------------------------------

	protected function setUp(): void {
		parent::setUp();

		// Register the cb_ics_feed post type so wp_insert_post works in tests
		if ( ! post_type_exists( 'cb_ics_feed' ) ) {
			register_post_type( 'cb_ics_feed', [ 'public' => false, 'label' => 'ICS Feed' ] );
		}

		// Create a feed post targeting the test item + location
		$this->feedId = wp_insert_post( [
			'post_title'  => 'Test ICS Feed',
			'post_type'   => 'cb_ics_feed',
			'post_status' => 'publish',
		] );
		update_post_meta( $this->feedId, '_cb_ics_feed_url', 'https://example.com/test.ics' );
		update_post_meta( $this->feedId, '_cb_ics_feed_item_ids', [ $this->itemId ] );
		update_post_meta( $this->feedId, '_cb_ics_feed_location_ids', [ $this->locationId ] );
		update_post_meta( $this->feedId, '_cb_ics_feed_lookahead_days', 365 );
	}

	protected function tearDown(): void {
		wp_delete_post( $this->feedId, true );

		// Clean up any synced timeframes created during tests
		$synced = get_posts( [
			'post_type'   => TimeframeCPT::$postType,
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_query'  => [ [ 'key' => ICSImport::SOURCE_FEED_META ] ],
		] );
		foreach ( $synced as $id ) {
			wp_delete_post( $id, true );
		}

		parent::tearDown();
	}
}
