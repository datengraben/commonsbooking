<?php

namespace CommonsBooking\Tests\Service;

use CommonsBooking\Service\QueryTimer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the QueryTimer service.
 *
 * Note: register_shutdown_function() is not triggered during test execution,
 * so each test that checks persistence must call QueryTimer::flushPending()
 * explicitly after calling QueryTimer::measure().
 */
class QueryTimerTest extends TestCase {

	protected function tearDown(): void {
		QueryTimer::clearSamples();
		// Reset pending buffer between tests so tests are independent
		QueryTimer::flushPending();
	}

	public function testMeasureReturnsFnResult() {
		$result = QueryTimer::measure( 'test', fn() => 42 );
		$this->assertSame( 42, $result );
	}

	public function testMeasureReturnsFnResultForNonScalar() {
		$arr    = [ 'a' => 1 ];
		$result = QueryTimer::measure( 'test', fn() => $arr );
		$this->assertSame( $arr, $result );
	}

	public function testMeasureRecordsSample() {
		QueryTimer::measure( 'my_label', fn() => null );
		QueryTimer::flushPending();

		$samples = QueryTimer::getSamples();
		$this->assertCount( 1, $samples );

		$sample = $samples[0];
		$this->assertArrayHasKey( 'label', $sample );
		$this->assertArrayHasKey( 'duration', $sample );
		$this->assertArrayHasKey( 'timestamp', $sample );
		$this->assertArrayHasKey( 'context', $sample );

		$this->assertSame( 'my_label', $sample['label'] );
		$this->assertGreaterThanOrEqual( 0.0, $sample['duration'] );
		$this->assertIsInt( $sample['timestamp'] );
	}

	public function testContextIsStoredWithSample() {
		$ctx = [ 'past_booking_flag' => true, 'item_count' => 3 ];
		QueryTimer::measure( 'ctx_test', fn() => null, $ctx );
		QueryTimer::flushPending();

		$samples = QueryTimer::getSamples();
		$this->assertSame( $ctx, $samples[0]['context'] );
	}

	public function testRingBufferTruncatesToBufferSize() {
		$bufferSize = QueryTimer::BUFFER_SIZE;
		$extra      = 5;

		for ( $i = 0; $i < $bufferSize + $extra; $i++ ) {
			QueryTimer::measure( 'loop', fn() => null );
			QueryTimer::flushPending();
		}

		$this->assertCount( $bufferSize, QueryTimer::getSamples() );
	}

	public function testClearSamples() {
		QueryTimer::measure( 'x', fn() => null );
		QueryTimer::flushPending();
		$this->assertNotEmpty( QueryTimer::getSamples() );

		QueryTimer::clearSamples();
		$this->assertEmpty( QueryTimer::getSamples() );
	}

	public function testMultipleMeasuresInOneBatch() {
		QueryTimer::measure( 'a', fn() => 1 );
		QueryTimer::measure( 'b', fn() => 2 );
		QueryTimer::measure( 'c', fn() => 3 );
		QueryTimer::flushPending();

		$samples = QueryTimer::getSamples();
		$this->assertCount( 3, $samples );
		$this->assertSame( 'a', $samples[0]['label'] );
		$this->assertSame( 'b', $samples[1]['label'] );
		$this->assertSame( 'c', $samples[2]['label'] );
	}

	public function testFlushPendingIsIdempotent() {
		QueryTimer::measure( 'x', fn() => null );
		QueryTimer::flushPending();
		QueryTimer::flushPending(); // second flush — no pending, should not duplicate
		$this->assertCount( 1, QueryTimer::getSamples() );
	}
}
