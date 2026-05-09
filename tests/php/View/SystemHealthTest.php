<?php

namespace CommonsBooking\Tests\View;

use CommonsBooking\Service\ErrorMonitor;
use CommonsBooking\Tests\Wordpress\CustomPostTypeTest;
use CommonsBooking\View\SystemHealth;

class SystemHealthTest extends CustomPostTypeTest {

	protected function setUp(): void {
		parent::setUp();
		ErrorMonitor::clear();
	}

	protected function tearDown(): void {
		ErrorMonitor::clear();
		remove_all_filters( 'commonsbooking_health_checks' );
		parent::tearDown();
	}

	public function testGetChecksReturnsNonEmptyArray(): void {
		$checks = SystemHealth::getChecks();
		$this->assertIsArray( $checks );
		$this->assertNotEmpty( $checks );
	}

	public function testEachCheckHasRequiredKeys(): void {
		foreach ( SystemHealth::getChecks() as $check ) {
			$this->assertArrayHasKey( 'label', $check, 'Check is missing "label" key' );
			$this->assertArrayHasKey( 'status', $check, 'Check is missing "status" key' );
			$this->assertArrayHasKey( 'detail', $check, 'Check is missing "detail" key' );
			$this->assertContains(
				$check['status'],
				[ 'ok', 'warn', 'fail' ],
				'Status must be ok, warn, or fail'
			);
		}
	}

	public function testPhpVersionCheckPassesForCurrentPhp(): void {
		$checks   = SystemHealth::getChecks();
		$phpCheck = array_values(
			array_filter( $checks, fn( $c ) => $c['label'] === __( 'PHP Version', 'commonsbooking' ) )
		);
		$this->assertNotEmpty( $phpCheck, 'PHP version check not found' );
		// Tests run on a supported PHP version, so this must pass
		$this->assertSame( 'ok', $phpCheck[0]['status'] );
	}

	public function testDatabaseCheckPassesInTestEnvironment(): void {
		$checks  = SystemHealth::getChecks();
		$dbCheck = array_values(
			array_filter( $checks, fn( $c ) => $c['label'] === __( 'Database', 'commonsbooking' ) )
		);
		$this->assertNotEmpty( $dbCheck, 'Database check not found' );
		$this->assertSame( 'ok', $dbCheck[0]['status'] );
	}

	public function testRecentErrorsCheckReflectsErrorMonitorState(): void {
		// With 0 errors the check should be ok
		$checks    = SystemHealth::getChecks();
		$errChecks = array_values(
			array_filter( $checks, fn( $c ) => $c['label'] === __( 'Recent Errors', 'commonsbooking' ) )
		);
		$this->assertNotEmpty( $errChecks );
		$this->assertSame( 'ok', $errChecks[0]['status'] );

		// Record 15 errors → status should become 'fail'
		for ( $i = 0; $i < 15; $i++ ) {
			ErrorMonitor::record( "error $i" );
		}
		$checks    = SystemHealth::getChecks();
		$errChecks = array_values(
			array_filter( $checks, fn( $c ) => $c['label'] === __( 'Recent Errors', 'commonsbooking' ) )
		);
		$this->assertSame( 'fail', $errChecks[0]['status'] );
	}

	public function testCustomHealthCheckIsAddedViaFilter(): void {
		add_filter(
			'commonsbooking_health_checks',
			function ( array $checks ) {
				$checks[] = [ 'label' => 'Custom Check', 'status' => 'ok', 'detail' => 'test detail' ];
				return $checks;
			}
		);

		$checks = SystemHealth::getChecks();
		$labels = array_column( $checks, 'label' );
		$this->assertContains( 'Custom Check', $labels );
	}
}
