<?php

namespace CommonsBooking\Tests\Service;

use CommonsBooking\Service\GBFSDiscoveryCheck;
use WP_UnitTestCase;

class GBFSDiscoveryCheckTest extends WP_UnitTestCase {

	private function feedHost(): string {
		return strtolower( (string) wp_parse_url( GBFSDiscoveryCheck::getFeedUrl(), PHP_URL_HOST ) );
	}

	private function csvHeader(): string {
		return "Country Code,Name,Location,System ID,URL,Auto-Discovery URL,Authentication Info,Supported Versions\n";
	}

	public function testIsListedInCsvMatchesByHost() {
		$feedUrl = GBFSDiscoveryCheck::getFeedUrl();
		$body    = $this->csvHeader()
			. 'DE,Some Other System,Berlin,other,https://example-other.org,https://example-other.org/gbfs.json,,3.0' . "\n"
			. 'DE,My Commons,Town,mine,' . GBFSDiscoveryCheck::getFeedUrl() . ',' . $feedUrl . ',,3.0' . "\n";

		$this->assertTrue( GBFSDiscoveryCheck::isListedInCsv( $body, $feedUrl ) );
	}

	public function testIsListedInCsvReturnsFalseWhenAbsent() {
		$body = $this->csvHeader()
			. 'DE,Some Other System,Berlin,other,https://example-other.org,https://example-other.org/gbfs.json,,3.0' . "\n";

		$this->assertFalse( GBFSDiscoveryCheck::isListedInCsv( $body, GBFSDiscoveryCheck::getFeedUrl() ) );
	}

	public function testRefreshMarksIncluded() {
		$feedUrl = GBFSDiscoveryCheck::getFeedUrl();
		$body    = $this->csvHeader()
			. 'DE,My Commons,Town,mine,' . $feedUrl . ',' . $feedUrl . ',,3.0' . "\n";

		$filter = function () use ( $body ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => $body,
				'headers'  => array(
					'etag'          => '"v1"',
					'last-modified' => 'Wed, 01 Jan 2025 00:00:00 GMT',
				),
			);
		};
		add_filter( 'pre_http_request', $filter, 10, 0 );

		$status = GBFSDiscoveryCheck::refresh();

		remove_filter( 'pre_http_request', $filter, 10 );

		$this->assertSame( 'included', $status['sources']['systems_csv']['status'] );
		$this->assertSame( 1, $status['included_count'] );
		$this->assertSame( '1/3', $status['summary'] );
		$this->assertSame( '"v1"', $status['sources']['systems_csv']['etag'] );
	}

	public function testRefreshMarksNotIncluded() {
		$body = $this->csvHeader()
			. 'DE,Some Other System,Berlin,other,https://example-other.org,https://example-other.org/gbfs.json,,3.0' . "\n";

		$filter = function () use ( $body ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => $body,
				'headers'  => array(),
			);
		};
		add_filter( 'pre_http_request', $filter, 10, 0 );

		$status = GBFSDiscoveryCheck::refresh();

		remove_filter( 'pre_http_request', $filter, 10 );

		$this->assertSame( 'not_included', $status['sources']['systems_csv']['status'] );
		$this->assertSame( 0, $status['included_count'] );
		$this->assertSame( '0/3', $status['summary'] );
	}

	public function testNotModifiedKeepsPreviousStatus() {
		$feedUrl = GBFSDiscoveryCheck::getFeedUrl();
		$body    = $this->csvHeader()
			. 'DE,My Commons,Town,mine,' . $feedUrl . ',' . $feedUrl . ',,3.0' . "\n";

		// First run: 200 with our host present -> included, stores validators.
		$ok = function () use ( $body ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => $body,
				'headers'  => array( 'etag' => '"v1"' ),
			);
		};
		add_filter( 'pre_http_request', $ok, 10, 0 );
		GBFSDiscoveryCheck::refresh();
		remove_filter( 'pre_http_request', $ok, 10 );

		// Second run: 304 Not Modified -> previous "included" status is kept.
		$notModified = function () {
			return array(
				'response' => array( 'code' => 304 ),
				'body'     => '',
				'headers'  => array(),
			);
		};
		add_filter( 'pre_http_request', $notModified, 10, 0 );
		$status = GBFSDiscoveryCheck::refresh();
		remove_filter( 'pre_http_request', $notModified, 10 );

		$this->assertSame( 'included', $status['sources']['systems_csv']['status'] );
		$this->assertSame( '"v1"', $status['sources']['systems_csv']['etag'] );
	}
}
