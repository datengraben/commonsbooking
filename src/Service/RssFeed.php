<?php

namespace CommonsBooking\Service;

use CommonsBooking\Settings\Settings;
use WP_Query;

/**
 * Provides RSS 2.0 feeds for CommonsBooking custom post types.
 *
 * Users can subscribe to feeds for Items, Locations, and Timeframes
 * to track new posts and edits. The feed is enabled/disabled via the
 * advanced options settings page (rss_feed_enabled).
 *
 * URL format:  /?commonsbooking_rss=1&commonsbooking_rss_type=cb_item
 *
 * Usage:
 *   RssFeed::initRewrite()  — call from Plugin::init() to register hooks.
 *   RssFeed::getFeedUrl($postType)  — returns the subscription URL.
 */
class RssFeed {

	/** Query var that triggers feed output. */
	public const URL_SLUG = COMMONSBOOKING_PLUGIN_SLUG . '_rss';

	/** Query var that selects the post type. */
	public const QUERY_TYPE = COMMONSBOOKING_PLUGIN_SLUG . '_rss_type';

	/** Maximum number of items per feed. */
	public const ITEMS_PER_FEED = 20;

	/**
	 * Post types exposed via RSS (public-facing only; bookings are private).
	 *
	 * @var string[]
	 */
	private const SUPPORTED_POST_TYPES = [
		'cb_item',
		'cb_location',
		'cb_timeframe',
	];

	/**
	 * Human-readable labels used as the feed channel title.
	 * Kept intentionally plain-text (no i18n at definition time).
	 *
	 * @var string[]
	 */
	private const POST_TYPE_LABELS = [
		'cb_item'      => 'Items',
		'cb_location'  => 'Locations',
		'cb_timeframe' => 'Timeframes',
	];

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Registers rewrite rules and query vars for the RSS feed.
	 * Call from Plugin::init() — only hooks up when the setting is enabled.
	 */
	public static function initRewrite(): void {
		if ( ! self::isFeedEnabled() ) {
			return;
		}

		add_action( 'wp_loaded', static function () {
			add_rewrite_rule(
				self::URL_SLUG . '/([^/]+)/?$',
				'index.php?' . self::URL_SLUG . '=1&' . self::QUERY_TYPE . '=$matches[1]',
				'top'
			);
		} );

		add_filter( 'query_vars', static function ( array $vars ): array {
			$vars[] = self::URL_SLUG;
			$vars[] = self::QUERY_TYPE;
			return $vars;
		} );

		add_action( 'parse_request', static function ( \WP $wp ): void {
			if ( empty( $wp->query_vars[ self::URL_SLUG ] ) ) {
				return;
			}
			$postType = isset( $wp->query_vars[ self::QUERY_TYPE ] )
				? sanitize_key( $wp->query_vars[ self::QUERY_TYPE ] )
				: '';

			self::outputFeed( $postType );
		} );
	}

	/**
	 * Returns whether the RSS feed feature is enabled in settings.
	 */
	public static function isFeedEnabled(): bool {
		return Settings::getOption(
			COMMONSBOOKING_PLUGIN_SLUG . '_options_advanced-options',
			'rss_feed_enabled'
		) === 'on';
	}

	/**
	 * Returns the subscription URL for a given post type.
	 *
	 * @param string $postType  One of the SUPPORTED_POST_TYPES values.
	 * @return string           Absolute URL.
	 */
	public static function getFeedUrl( string $postType ): string {
		return add_query_arg(
			[
				self::URL_SLUG   => '1',
				self::QUERY_TYPE => $postType,
			],
			trailingslashit( get_site_url() )
		);
	}

	/**
	 * Returns the list of post types that have RSS feeds.
	 *
	 * @return string[]
	 */
	public static function getSupportedPostTypes(): array {
		return self::SUPPORTED_POST_TYPES;
	}

	/**
	 * Returns true when $postType has a feed.
	 */
	public static function isValidPostType( string $postType ): bool {
		return in_array( $postType, self::SUPPORTED_POST_TYPES, true );
	}

	// -------------------------------------------------------------------------
	// Feed rendering
	// -------------------------------------------------------------------------

	/**
	 * Sends RSS 2.0 feed headers and body for the requested post type.
	 * Terminates script execution afterwards.
	 *
	 * @param string $postType
	 */
	public static function outputFeed( string $postType ): void {
		if ( ! self::isValidPostType( $postType ) ) {
			status_header( 404 );
			wp_die( esc_html__( 'RSS feed not found for this post type.', 'commonsbooking' ), 404 );
		}

		$posts = self::fetchPosts( $postType );
		$xml   = self::renderFeedXml( $posts, $postType );

		header( 'Content-Type: application/rss+xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $xml;
		exit;
	}

	/**
	 * Renders and returns an RSS 2.0 XML string.
	 *
	 * Separated from outputFeed() to make it directly testable without HTTP.
	 *
	 * @param \WP_Post[]|\stdClass[] $posts     Array of WP_Post objects.
	 * @param string                 $postType  Post type slug (used for channel metadata).
	 * @return string                           Well-formed RSS 2.0 XML.
	 */
	public static function renderFeedXml( array $posts, string $postType ): string {
		$siteUrl     = get_site_url() ?: 'http://localhost';
		$siteTitle   = get_bloginfo( 'name' ) ?: 'CommonsBooking';
		$label       = self::POST_TYPE_LABELS[ $postType ] ?? $postType;
		$feedUrl     = self::getFeedUrl( $postType );
		$lastBuild   = gmdate( 'r' );

		$channelTitle = $siteTitle . ' — ' . $label;
		$channelDesc  = sprintf( 'RSS feed for %s', $label );

		$dom  = new \DOMDocument( '1.0', 'UTF-8' );
		$dom->formatOutput = true;

		// <rss version="2.0">
		$rss = $dom->createElement( 'rss' );
		$rss->setAttribute( 'version', '2.0' );
		$rss->setAttribute( 'xmlns:atom', 'http://www.w3.org/2005/Atom' );
		$dom->appendChild( $rss );

		// <channel>
		$channel = $dom->createElement( 'channel' );
		$rss->appendChild( $channel );

		self::appendTextNode( $dom, $channel, 'title', $channelTitle );
		self::appendTextNode( $dom, $channel, 'link', $siteUrl );
		self::appendTextNode( $dom, $channel, 'description', $channelDesc );
		self::appendTextNode( $dom, $channel, 'language', get_bloginfo( 'language' ) ?: 'en' );
		self::appendTextNode( $dom, $channel, 'lastBuildDate', $lastBuild );

		// <atom:link rel="self">
		$atomLink = $dom->createElement( 'atom:link' );
		$atomLink->setAttribute( 'href', $feedUrl );
		$atomLink->setAttribute( 'rel', 'self' );
		$atomLink->setAttribute( 'type', 'application/rss+xml' );
		$channel->appendChild( $atomLink );

		// <item> for each post
		foreach ( $posts as $post ) {
			$channel->appendChild( self::buildItemNode( $dom, $post ) );
		}

		return $dom->saveXML();
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Fetches the most recent published posts for a given post type.
	 *
	 * @param  string      $postType
	 * @return \WP_Post[]
	 */
	private static function fetchPosts( string $postType ): array {
		$query = new WP_Query( [
			'post_type'      => $postType,
			'post_status'    => 'publish',
			'posts_per_page' => self::ITEMS_PER_FEED,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		] );

		return $query->posts ?: [];
	}

	/**
	 * Builds a <item> DOMElement for a single post.
	 *
	 * @param  \DOMDocument          $dom
	 * @param  \WP_Post|\stdClass    $post
	 * @return \DOMElement
	 */
	private static function buildItemNode( \DOMDocument $dom, $post ): \DOMElement {
		$item = $dom->createElement( 'item' );

		$title   = isset( $post->post_title ) ? $post->post_title : '';
		$link    = get_permalink( $post->ID );
		$pubDate = isset( $post->post_date_gmt )
			? gmdate( 'r', strtotime( $post->post_date_gmt ) )
			: gmdate( 'r' );
		$guid    = $link ?: ( get_site_url() . '/?p=' . $post->ID );
		$content = isset( $post->post_content ) ? $post->post_content : '';
		$excerpt = isset( $post->post_excerpt ) && $post->post_excerpt !== ''
			? $post->post_excerpt
			: wp_trim_words( $content, 55 );

		self::appendTextNode( $dom, $item, 'title', $title );
		self::appendTextNode( $dom, $item, 'link', $link ?: '' );
		self::appendTextNode( $dom, $item, 'pubDate', $pubDate );

		$guidEl = $dom->createElement( 'guid' );
		$guidEl->setAttribute( 'isPermaLink', 'true' );
		$guidEl->appendChild( $dom->createTextNode( $guid ) );
		$item->appendChild( $guidEl );

		// Description wrapped in CDATA so HTML is preserved safely
		$desc = $dom->createElement( 'description' );
		$desc->appendChild( $dom->createCDATASection( $excerpt ) );
		$item->appendChild( $desc );

		return $item;
	}

	/**
	 * Creates and appends a text-content element to a parent node.
	 *
	 * @param \DOMDocument $dom
	 * @param \DOMElement  $parent
	 * @param string       $tagName
	 * @param string       $value
	 */
	private static function appendTextNode(
		\DOMDocument $dom,
		\DOMElement $parent,
		string $tagName,
		string $value
	): void {
		$el = $dom->createElement( $tagName );
		$el->appendChild( $dom->createTextNode( $value ) );
		$parent->appendChild( $el );
	}
}
