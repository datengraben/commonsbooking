<?php

namespace CommonsBooking\Tests\Service;

use CommonsBooking\Service\RssFeed;
use CommonsBooking\Settings\Settings;
use CommonsBooking\Tests\Wordpress\CustomPostTypeTest;
use CommonsBooking\Wordpress\CustomPostType\Item;
use CommonsBooking\Wordpress\CustomPostType\Location;

/**
 * Tests for the RssFeed service.
 *
 * Tests verify RSS 2.0 compliance, post type validation, feed URL generation,
 * and XML structure for all supported custom post types.
 */
class RssFeedTest extends CustomPostTypeTest {

	// -------------------------------------------------------------------------
	// Post type validation
	// -------------------------------------------------------------------------

	public function testIsValidPostTypeReturnsTrueForCbItem() {
		$this->assertTrue( RssFeed::isValidPostType( 'cb_item' ) );
	}

	public function testIsValidPostTypeReturnsTrueForCbLocation() {
		$this->assertTrue( RssFeed::isValidPostType( 'cb_location' ) );
	}

	public function testIsValidPostTypeReturnsTrueForCbTimeframe() {
		$this->assertTrue( RssFeed::isValidPostType( 'cb_timeframe' ) );
	}

	public function testIsValidPostTypeReturnsFalseForUnknownType() {
		$this->assertFalse( RssFeed::isValidPostType( 'post' ) );
	}

	public function testIsValidPostTypeReturnsFalseForEmptyString() {
		$this->assertFalse( RssFeed::isValidPostType( '' ) );
	}

	public function testIsValidPostTypeReturnsFalseForCbBooking() {
		// Bookings are private – they must not be exposed via public RSS.
		$this->assertFalse( RssFeed::isValidPostType( 'cb_booking' ) );
	}

	// -------------------------------------------------------------------------
	// Supported post types list
	// -------------------------------------------------------------------------

	public function testSupportedPostTypesContainsAllPublicCpts() {
		$supported = RssFeed::getSupportedPostTypes();
		$this->assertContains( 'cb_item', $supported );
		$this->assertContains( 'cb_location', $supported );
		$this->assertContains( 'cb_timeframe', $supported );
	}

	public function testSupportedPostTypesDoesNotContainPrivateCpts() {
		$supported = RssFeed::getSupportedPostTypes();
		$this->assertNotContains( 'cb_booking', $supported );
		$this->assertNotContains( 'cb_restriction', $supported );
	}

	// -------------------------------------------------------------------------
	// Feed URL generation
	// -------------------------------------------------------------------------

	public function testGetFeedUrlReturnsString() {
		$url = RssFeed::getFeedUrl( 'cb_item' );
		$this->assertIsString( $url );
	}

	public function testGetFeedUrlContainsRssSlug() {
		$url = RssFeed::getFeedUrl( 'cb_item' );
		$this->assertStringContainsString( RssFeed::URL_SLUG, $url );
	}

	public function testGetFeedUrlContainsPostType() {
		$url = RssFeed::getFeedUrl( 'cb_location' );
		$this->assertStringContainsString( 'cb_location', $url );
	}

	public function testGetFeedUrlDiffersPerPostType() {
		$itemUrl    = RssFeed::getFeedUrl( 'cb_item' );
		$locationUrl = RssFeed::getFeedUrl( 'cb_location' );
		$this->assertNotEquals( $itemUrl, $locationUrl );
	}

	// -------------------------------------------------------------------------
	// RSS XML structure
	// -------------------------------------------------------------------------

	public function testRenderFeedXmlReturnsNonEmptyString() {
		$xml = RssFeed::renderFeedXml( [], 'cb_item' );
		$this->assertIsString( $xml );
		$this->assertNotEmpty( $xml );
	}

	public function testRenderFeedXmlIsWellFormedXml() {
		$xml = RssFeed::renderFeedXml( [], 'cb_item' );
		$doc = new \DOMDocument();
		$loaded = @$doc->loadXML( $xml );
		$this->assertTrue( $loaded, 'renderFeedXml must return well-formed XML.' );
	}

	public function testRenderFeedXmlHasRss2Root() {
		$xml = RssFeed::renderFeedXml( [], 'cb_item' );
		$doc = new \DOMDocument();
		$doc->loadXML( $xml );
		$root = $doc->documentElement;
		$this->assertEquals( 'rss', $root->nodeName );
		$this->assertEquals( '2.0', $root->getAttribute( 'version' ) );
	}

	public function testRenderFeedXmlHasChannel() {
		$xml = RssFeed::renderFeedXml( [], 'cb_item' );
		$doc = new \DOMDocument();
		$doc->loadXML( $xml );
		$channels = $doc->getElementsByTagName( 'channel' );
		$this->assertEquals( 1, $channels->length );
	}

	public function testRenderFeedXmlChannelHasTitle() {
		$xml = RssFeed::renderFeedXml( [], 'cb_item' );
		$doc = new \DOMDocument();
		$doc->loadXML( $xml );
		$channel = $doc->getElementsByTagName( 'channel' )->item( 0 );
		$titles  = $channel->getElementsByTagName( 'title' );
		$this->assertGreaterThanOrEqual( 1, $titles->length );
		$this->assertNotEmpty( $titles->item( 0 )->textContent );
	}

	public function testRenderFeedXmlChannelHasLink() {
		$xml = RssFeed::renderFeedXml( [], 'cb_item' );
		$doc = new \DOMDocument();
		$doc->loadXML( $xml );
		$channel = $doc->getElementsByTagName( 'channel' )->item( 0 );
		$links   = $channel->getElementsByTagName( 'link' );
		$this->assertGreaterThanOrEqual( 1, $links->length );
	}

	public function testRenderFeedXmlChannelHasDescription() {
		$xml = RssFeed::renderFeedXml( [], 'cb_item' );
		$doc = new \DOMDocument();
		$doc->loadXML( $xml );
		$channel      = $doc->getElementsByTagName( 'channel' )->item( 0 );
		$descriptions = $channel->getElementsByTagName( 'description' );
		$this->assertGreaterThanOrEqual( 1, $descriptions->length );
	}

	public function testRenderFeedXmlChannelHasLastBuildDate() {
		$xml = RssFeed::renderFeedXml( [], 'cb_item' );
		$doc = new \DOMDocument();
		$doc->loadXML( $xml );
		$channel = $doc->getElementsByTagName( 'channel' )->item( 0 );
		$dates   = $channel->getElementsByTagName( 'lastBuildDate' );
		$this->assertEquals( 1, $dates->length );
	}

	// -------------------------------------------------------------------------
	// RSS items from posts
	// -------------------------------------------------------------------------

	public function testRenderFeedXmlContainsPostAsItem() {
		$posts = [ get_post( $this->itemId ) ];
		$xml   = RssFeed::renderFeedXml( $posts, 'cb_item' );
		$doc   = new \DOMDocument();
		$doc->loadXML( $xml );
		$items = $doc->getElementsByTagName( 'item' );
		$this->assertEquals( 1, $items->length );
	}

	public function testRenderFeedXmlItemHasTitle() {
		$posts = [ get_post( $this->itemId ) ];
		$xml   = RssFeed::renderFeedXml( $posts, 'cb_item' );
		$doc   = new \DOMDocument();
		$doc->loadXML( $xml );
		$item  = $doc->getElementsByTagName( 'item' )->item( 0 );
		$title = $item->getElementsByTagName( 'title' )->item( 0 );
		$this->assertNotNull( $title );
		$this->assertNotEmpty( $title->textContent );
	}

	public function testRenderFeedXmlItemHasLink() {
		$posts = [ get_post( $this->itemId ) ];
		$xml   = RssFeed::renderFeedXml( $posts, 'cb_item' );
		$doc   = new \DOMDocument();
		$doc->loadXML( $xml );
		$item = $doc->getElementsByTagName( 'item' )->item( 0 );
		$link = $item->getElementsByTagName( 'link' )->item( 0 );
		$this->assertNotNull( $link );
		$this->assertNotEmpty( $link->textContent );
	}

	public function testRenderFeedXmlItemHasPubDate() {
		$posts = [ get_post( $this->itemId ) ];
		$xml   = RssFeed::renderFeedXml( $posts, 'cb_item' );
		$doc   = new \DOMDocument();
		$doc->loadXML( $xml );
		$item    = $doc->getElementsByTagName( 'item' )->item( 0 );
		$pubDate = $item->getElementsByTagName( 'pubDate' )->item( 0 );
		$this->assertNotNull( $pubDate );
		$this->assertNotEmpty( $pubDate->textContent );
	}

	public function testRenderFeedXmlItemHasGuid() {
		$posts = [ get_post( $this->itemId ) ];
		$xml   = RssFeed::renderFeedXml( $posts, 'cb_item' );
		$doc   = new \DOMDocument();
		$doc->loadXML( $xml );
		$item = $doc->getElementsByTagName( 'item' )->item( 0 );
		$guid = $item->getElementsByTagName( 'guid' )->item( 0 );
		$this->assertNotNull( $guid );
		$this->assertNotEmpty( $guid->textContent );
	}

	public function testRenderFeedXmlItemHasDescription() {
		$posts = [ get_post( $this->itemId ) ];
		$xml   = RssFeed::renderFeedXml( $posts, 'cb_item' );
		$doc   = new \DOMDocument();
		$doc->loadXML( $xml );
		$item        = $doc->getElementsByTagName( 'item' )->item( 0 );
		$description = $item->getElementsByTagName( 'description' )->item( 0 );
		$this->assertNotNull( $description );
	}

	public function testRenderFeedXmlEmptyPostsHasNoItems() {
		$xml = RssFeed::renderFeedXml( [], 'cb_item' );
		$doc = new \DOMDocument();
		$doc->loadXML( $xml );
		$items = $doc->getElementsByTagName( 'item' );
		$this->assertEquals( 0, $items->length );
	}

	public function testRenderFeedXmlCorrectItemCountForMultiplePosts() {
		$posts = [
			get_post( $this->itemId ),
			get_post( $this->locationId ),
		];
		$xml  = RssFeed::renderFeedXml( $posts, 'cb_item' );
		$doc  = new \DOMDocument();
		$doc->loadXML( $xml );
		$items = $doc->getElementsByTagName( 'item' );
		$this->assertEquals( 2, $items->length );
	}

	// -------------------------------------------------------------------------
	// Channel title reflects post type label
	// -------------------------------------------------------------------------

	public function testChannelTitleDiffersPerPostType() {
		$xmlItem     = RssFeed::renderFeedXml( [], 'cb_item' );
		$xmlLocation = RssFeed::renderFeedXml( [], 'cb_location' );

		$docItem     = new \DOMDocument();
		$docLocation = new \DOMDocument();
		$docItem->loadXML( $xmlItem );
		$docLocation->loadXML( $xmlLocation );

		$titleItem     = $docItem->getElementsByTagName( 'channel' )->item( 0 )
		                         ->getElementsByTagName( 'title' )->item( 0 )->textContent;
		$titleLocation = $docLocation->getElementsByTagName( 'channel' )->item( 0 )
		                              ->getElementsByTagName( 'title' )->item( 0 )->textContent;

		$this->assertNotEquals( $titleItem, $titleLocation );
	}

	// -------------------------------------------------------------------------
	// Settings gate
	// -------------------------------------------------------------------------

	public function testIsFeedEnabledReturnsFalseWhenSettingOff() {
		Settings::updateOption(
			COMMONSBOOKING_PLUGIN_SLUG . '_options_advanced-options',
			'rss_feed_enabled',
			'off'
		);
		$this->assertFalse( RssFeed::isFeedEnabled() );
	}

	public function testIsFeedEnabledReturnsTrueWhenSettingOn() {
		Settings::updateOption(
			COMMONSBOOKING_PLUGIN_SLUG . '_options_advanced-options',
			'rss_feed_enabled',
			'on'
		);
		$this->assertTrue( RssFeed::isFeedEnabled() );
	}
}
