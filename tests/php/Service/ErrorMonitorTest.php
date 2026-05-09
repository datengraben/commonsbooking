<?php

namespace CommonsBooking\Tests\Service;

use CommonsBooking\Service\ErrorMonitor;
use CommonsBooking\Tests\BaseTestCase;

class ErrorMonitorTest extends BaseTestCase {

	protected function setUp(): void {
		parent::setUp();
		ErrorMonitor::clear();
	}

	protected function tearDown(): void {
		ErrorMonitor::clear();
		parent::tearDown();
	}

	public function testRecordStoresEntry(): void {
		ErrorMonitor::record( 'test error', ErrorMonitor::SEVERITY_ERROR );
		$this->assertSame( 1, ErrorMonitor::count() );
		$entries = ErrorMonitor::getEntries();
		$this->assertSame( 'test error', $entries[0]['message'] );
		$this->assertSame( ErrorMonitor::SEVERITY_ERROR, $entries[0]['severity'] );
	}

	public function testEntriesAreNewestFirst(): void {
		ErrorMonitor::record( 'first' );
		ErrorMonitor::record( 'second' );
		$entries = ErrorMonitor::getEntries();
		$this->assertSame( 'second', $entries[0]['message'] );
		$this->assertSame( 'first', $entries[1]['message'] );
	}

	public function testRingBufferEnforcesMaxEntries(): void {
		for ( $i = 0; $i < ErrorMonitor::MAX_ENTRIES + 5; $i++ ) {
			ErrorMonitor::record( "entry $i" );
		}
		$this->assertSame( ErrorMonitor::MAX_ENTRIES, ErrorMonitor::count() );
	}

	public function testClearRemovesAllEntries(): void {
		ErrorMonitor::record( 'keep' );
		ErrorMonitor::clear();
		$this->assertSame( 0, ErrorMonitor::count() );
		$this->assertEmpty( ErrorMonitor::getEntries() );
	}

	public function testCountBySeverity(): void {
		ErrorMonitor::record( 'e1', ErrorMonitor::SEVERITY_ERROR );
		ErrorMonitor::record( 'w1', ErrorMonitor::SEVERITY_WARNING );
		ErrorMonitor::record( 'w2', ErrorMonitor::SEVERITY_WARNING );
		$this->assertSame( 1, ErrorMonitor::countBySeverity( ErrorMonitor::SEVERITY_ERROR ) );
		$this->assertSame( 2, ErrorMonitor::countBySeverity( ErrorMonitor::SEVERITY_WARNING ) );
		$this->assertSame( 0, ErrorMonitor::countBySeverity( ErrorMonitor::SEVERITY_INFO ) );
	}

	public function testGetEntriesRespectsLimit(): void {
		for ( $i = 0; $i < 20; $i++ ) {
			ErrorMonitor::record( "entry $i" );
		}
		$this->assertCount( 5, ErrorMonitor::getEntries( 5 ) );
		$this->assertCount( 20, ErrorMonitor::getEntries( 0 ) );
	}

	public function testRecordCapturesCallerContext(): void {
		ErrorMonitor::record( 'context test' );
		$entry = ErrorMonitor::getEntries( 1 )[0];
		$this->assertArrayHasKey( 'file', $entry['context'] );
		$this->assertArrayHasKey( 'line', $entry['context'] );
		$this->assertStringContainsString( 'ErrorMonitorTest', $entry['context']['file'] );
	}

	public function testRecordIgnoresEmptyMessage(): void {
		ErrorMonitor::record( '' );
		$this->assertSame( 0, ErrorMonitor::count() );
	}

	public function testOptionIsNotAutoloaded(): void {
		ErrorMonitor::record( 'check autoload' );
		global $wpdb;
		$autoload = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
				ErrorMonitor::OPTION_KEY
			)
		);
		// WordPress 6.6+ uses 'yes'/'no', older versions use 'yes'/'no' or '1'/'0'
		$this->assertNotEquals( 'yes', $autoload );
		$this->assertNotEquals( '1', $autoload );
	}

	public function testEntryIncludesTimestamp(): void {
		$before = time();
		ErrorMonitor::record( 'ts test' );
		$after = time();
		$entry = ErrorMonitor::getEntries( 1 )[0];
		$this->assertGreaterThanOrEqual( $before, $entry['timestamp'] );
		$this->assertLessThanOrEqual( $after, $entry['timestamp'] );
	}

	public function testExtraContextIsMerged(): void {
		ErrorMonitor::record( 'ctx', ErrorMonitor::SEVERITY_INFO, [ 'type' => 'BookingDeniedException' ] );
		$entry = ErrorMonitor::getEntries( 1 )[0];
		$this->assertSame( 'BookingDeniedException', $entry['context']['type'] );
	}
}
