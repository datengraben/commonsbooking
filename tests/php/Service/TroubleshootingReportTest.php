<?php

namespace CommonsBooking\Tests\Service;

use CommonsBooking\Service\ErrorMonitor;
use CommonsBooking\Service\TroubleshootingReport;
use CommonsBooking\Tests\Wordpress\CustomPostTypeTest;

class TroubleshootingReportTest extends CustomPostTypeTest {

	protected function setUp(): void {
		parent::setUp();
		ErrorMonitor::clear();
	}

	protected function tearDown(): void {
		ErrorMonitor::clear();
		remove_all_filters( 'commonsbooking_troubleshooting_report' );
		parent::tearDown();
	}

	public function testGenerateReturnsAllTopLevelKeys(): void {
		$report = TroubleshootingReport::generate();
		foreach ( [ 'generated_at', 'system', 'health', 'error_log', 'stats', 'cron' ] as $key ) {
			$this->assertArrayHasKey( $key, $report, "Missing key: $key" );
		}
	}

	public function testGeneratedAtIsValidIso8601(): void {
		$ts = TroubleshootingReport::generate()['generated_at'];
		$dt = \DateTime::createFromFormat( 'Y-m-d\TH:i:sP', $ts );
		$this->assertNotFalse( $dt, "generated_at is not valid ISO 8601: $ts" );
	}

	public function testSystemInfoContainsVersions(): void {
		$sys = TroubleshootingReport::generate()['system'];
		$this->assertSame( COMMONSBOOKING_VERSION, $sys['plugin_version'] );
		$this->assertSame( PHP_VERSION, $sys['php_version'] );
		$this->assertIsBool( $sys['wp_debug'] );
		$this->assertIsBool( $sys['wp_debug_log'] );
		$this->assertIsBool( $sys['wp_multisite'] );
		$this->assertIsArray( $sys['active_plugins'] );
		$this->assertIsArray( $sys['php_extensions'] );
	}

	public function testStatsContainsExpectedKeys(): void {
		$stats = TroubleshootingReport::generate()['stats'];
		foreach ( [ 'items', 'locations', 'timeframes', 'restrictions', 'bookings', 'orphaned_bookings' ] as $key ) {
			$this->assertArrayHasKey( $key, $stats, "stats missing key: $key" );
		}
	}

	public function testBookingStatsHaveStatusBreakdown(): void {
		$bookings = TroubleshootingReport::generate()['stats']['bookings'];
		$this->assertArrayHasKey( 'confirmed', $bookings );
		$this->assertArrayHasKey( 'unconfirmed', $bookings );
		$this->assertArrayHasKey( 'canceled', $bookings );
		foreach ( $bookings as $count ) {
			$this->assertIsInt( $count );
			$this->assertGreaterThanOrEqual( 0, $count );
		}
	}

	public function testCronSectionContainsKnownHooks(): void {
		$cron = TroubleshootingReport::generate()['cron'];
		$this->assertArrayHasKey( COMMONSBOOKING_PLUGIN_SLUG . '_cleanup', $cron );
		$first = reset( $cron );
		$this->assertArrayHasKey( 'next_run', $first );
		$this->assertArrayHasKey( 'next_run_diff', $first );
	}

	public function testErrorLogReflectsCurrentMonitorState(): void {
		ErrorMonitor::record( 'report export test' );
		$log = TroubleshootingReport::generate()['error_log'];
		$this->assertCount( 1, $log );
		$this->assertSame( 'report export test', $log[0]['message'] );
	}

	public function testHealthSectionIsNonEmptyWithValidStructure(): void {
		$health = TroubleshootingReport::generate()['health'];
		$this->assertNotEmpty( $health );
		foreach ( $health as $check ) {
			$this->assertArrayHasKey( 'label', $check );
			$this->assertArrayHasKey( 'status', $check );
			$this->assertContains( $check['status'], [ 'ok', 'warn', 'fail' ] );
		}
	}

	public function testCustomSectionCanBeAddedViaFilter(): void {
		add_filter(
			'commonsbooking_troubleshooting_report',
			function ( array $report ) {
				$report['custom'] = [ 'added_by_filter' => true ];
				return $report;
			}
		);

		$report = TroubleshootingReport::generate();
		$this->assertArrayHasKey( 'custom', $report );
		$this->assertTrue( $report['custom']['added_by_filter'] );
	}

	public function testStatsCountsAreIntegers(): void {
		$stats = TroubleshootingReport::generate()['stats'];
		$this->assertIsInt( $stats['items'] );
		$this->assertIsInt( $stats['locations'] );
		$this->assertIsInt( $stats['timeframes'] );
		$this->assertIsInt( $stats['restrictions'] );
		$this->assertIsInt( $stats['orphaned_bookings'] );
	}
}
