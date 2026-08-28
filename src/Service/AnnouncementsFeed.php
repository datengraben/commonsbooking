<?php

namespace CommonsBooking\Service;

use CommonsBooking\Settings\Settings;

/**
 * Fetches VfL member announcements from a remote RSS/Atom or JSON source,
 * caches them locally and exposes the normalized items to the views.
 *
 * Design:
 *  - The remote is fetched on WP-Cron only, never during a page render, so a
 *    slow or unreachable source can never slow down or break wp-admin.
 *  - The transient is the single source of truth the views read from.
 *  - On a failed refresh the last good cache is kept (stale-while-revalidate).
 *  - Every field coming from the remote is treated as untrusted and sanitized
 *    here, once, so the views can rely on the shape.
 */
class AnnouncementsFeed {

	public const TRANSIENT = 'commonsbooking_vfl_announcements';

	public const CRON_HOOK = 'commonsbooking_refresh_announcements';

	private const TTL = 2 * DAY_IN_SECONDS; // longer than the cron interval on purpose.

	private const MAX_ITEMS = 5;

	/**
	 * Register the background refresh. Called from Plugin::init().
	 */
	public static function initHooks(): void {
		add_action( self::CRON_HOOK, array( self::class, 'refresh' ) );

		if ( Membership::announcementsConfigured() && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'twicedaily', self::CRON_HOOK );
		}
	}

	/**
	 * Normalized announcement items from cache. Fast and offline-safe.
	 *
	 * @return array<int, array{title:string,url:string,date:int,excerpt:string,categories:array<int,string>}>
	 */
	public static function get(): array {
		$cache = get_transient( self::TRANSIENT );

		return ( is_array( $cache ) && isset( $cache['items'] ) ) ? $cache['items'] : array();
	}

	/**
	 * Fetch + parse + cache. Runs on cron. Keeps the previous cache on failure.
	 */
	public static function refresh(): void {
		if ( ! Membership::announcementsConfigured() ) {
			return;
		}

		$url   = Settings::getOption( Membership::OPTION_KEY, 'vfl_announcements_url', false );
		$items = self::fetchAndParse( (string) $url );

		if ( $items === null ) {
			// Keep the last good cache; only record that this refresh failed.
			$cache                = get_transient( self::TRANSIENT );
			$cache                = is_array( $cache ) ? $cache : array( 'items' => array() );
			$cache['last_error']  = time();
			set_transient( self::TRANSIENT, $cache, self::TTL );
			return;
		}

		set_transient(
			self::TRANSIENT,
			array(
				'items'   => $items,
				'fetched' => time(),
			),
			self::TTL
		);
	}

	/**
	 * @return array<int, array>|null Null signals a fetch/parse error.
	 */
	private static function fetchAndParse( string $url ) {
		if ( $url === '' ) {
			return null;
		}

		if ( self::looksLikeJson( $url ) ) {
			$response = wp_remote_get(
				$url,
				array(
					'timeout'    => 8,
					'user-agent' => 'CommonsBooking',
				)
			);
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
				return null;
			}
			$data = json_decode( wp_remote_retrieve_body( $response ), true );

			return is_array( $data ) ? self::normalizeJson( $data ) : null;
		}

		// RSS/Atom: reuse WordPress' bundled SimplePie.
		require_once ABSPATH . WPINC . '/feed.php';
		$feed = fetch_feed( $url );
		if ( is_wp_error( $feed ) ) {
			return null;
		}

		return self::normalizeRss( $feed->get_items( 0, self::MAX_ITEMS ) );
	}

	/**
	 * @param \SimplePie\Item[]|array $simplePieItems
	 *
	 * @return array<int, array>
	 */
	private static function normalizeRss( $simplePieItems ): array {
		$items = array();

		foreach ( $simplePieItems as $item ) {
			$categories = array();
			foreach ( (array) $item->get_categories() as $category ) {
				$label = $category->get_term() ?: $category->get_label();
				if ( $label ) {
					$categories[] = sanitize_key( $label );
				}
			}

			$items[] = array(
				'title'      => sanitize_text_field( (string) $item->get_title() ),
				'url'        => esc_url_raw( (string) $item->get_permalink() ),
				'date'       => (int) $item->get_date( 'U' ),
				'excerpt'    => wp_kses_post( (string) $item->get_description() ),
				'categories' => $categories,
			);
		}

		return $items;
	}

	/**
	 * Accepts a JSON payload shaped as { "items": [ {title,url,date,categories} ] }.
	 *
	 * @return array<int, array>
	 */
	private static function normalizeJson( array $data ): array {
		$raw   = $data['items'] ?? $data;
		$items = array();

		foreach ( array_slice( (array) $raw, 0, self::MAX_ITEMS ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$categories = array_map( 'sanitize_key', (array) ( $entry['categories'] ?? array() ) );

			$items[] = array(
				'title'      => sanitize_text_field( (string) ( $entry['title'] ?? '' ) ),
				'url'        => esc_url_raw( (string) ( $entry['url'] ?? '' ) ),
				'date'       => isset( $entry['date'] ) ? (int) strtotime( (string) $entry['date'] ) : 0,
				'excerpt'    => wp_kses_post( (string) ( $entry['excerpt'] ?? '' ) ),
				'categories' => array_values( array_filter( $categories ) ),
			);
		}

		return $items;
	}

	private static function looksLikeJson( string $url ): bool {
		return (bool) preg_match( '/\.json($|\?)/i', $url ) || str_contains( $url, '/wp-json/' );
	}
}
