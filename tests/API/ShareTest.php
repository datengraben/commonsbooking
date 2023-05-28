<?php

use CommonsBooking\API\Share;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CommonsBooking\API\Share
 */
class ShareTest extends TestCase {

	public function testShare_anyAttributes() {

		$share = new Share('myshare', 'on', 'https://cb.share/sink', 'any-key', 'cb-admin');

		$this->assertEquals('myshare', $share->getName());
		$this->assertEquals('https://cb.share/sink', $share->getPushUrl());
		$this->assertEquals('any-key', $share->getKey());
		$this->assertEquals('cb-admin', $share->getOwner());
	}

	public function testShare_isEnabled() {
		$share = new Share('myshare', 'on', 'https://cb.share/sink', 'any-key', 'cb-admin');

		$this->assertTrue($share->isEnabled());
	}


	public function testShare_isNotEnabled() {

		$share = new Share('myshare', 'off', 'https://cb.share/sink', 'any-key', 'cb-admin');

		$this->assertFalse($share->isEnabled());
	}
}
