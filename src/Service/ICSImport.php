<?php

namespace CommonsBooking\Service;

use CommonsBooking\Model\Timeframe as TimeframeModel;
use CommonsBooking\Wordpress\CustomPostType\Timeframe as TimeframeCPT;

/**
 * Fetches external ICS calendar feeds and creates blocking timeframes for
 * items/locations so they appear unavailable during calendar events.
 */
class ICSImport {

	const SOURCE_FEED_META  = '_cb_ics_source_feed_id';
	const SOURCE_UID_META   = '_cb_ics_source_uid';

	/**
	 * Called by the cron job — syncs all published ICS feed posts.
	 */
	public static function syncAllFeeds(): void {
		$feeds = get_posts( [
			'post_type'   => 'cb_ics_feed',
			'post_status' => 'publish',
			'numberposts' => -1,
		] );

		foreach ( $feeds as $feed ) {
			self::syncFeed( $feed->ID );
		}
	}

	/**
	 * Syncs a single ICS feed post.
	 */
	public static function syncFeed( int $feedPostId ): void {
		$url = get_post_meta( $feedPostId, '_cb_ics_feed_url', true );
		if ( empty( $url ) ) {
			update_post_meta( $feedPostId, '_cb_ics_feed_last_error', __( 'No ICS URL configured.', 'commonsbooking' ) );
			return;
		}

		$icsContent = self::fetchICS( $url );
		if ( $icsContent === false ) {
			// error already stored inside fetchICS
			return;
		}

		$events = self::parseVEvents( $icsContent );

		$itemIds     = (array) get_post_meta( $feedPostId, '_cb_ics_feed_item_ids', true );
		$locationIds = (array) get_post_meta( $feedPostId, '_cb_ics_feed_location_ids', true );
		$lookahead   = (int) get_post_meta( $feedPostId, '_cb_ics_feed_lookahead_days', true );
		if ( $lookahead <= 0 ) {
			$lookahead = 180;
		}

		$itemIds     = array_filter( array_map( 'intval', $itemIds ) );
		$locationIds = array_filter( array_map( 'intval', $locationIds ) );

		if ( empty( $itemIds ) || empty( $locationIds ) ) {
			update_post_meta( $feedPostId, '_cb_ics_feed_last_error', __( 'No items or locations configured.', 'commonsbooking' ) );
			return;
		}

		$horizon = time() + ( $lookahead * DAY_IN_SECONDS );
		$now     = strtotime( 'today midnight' );

		// Collect UIDs from the parsed feed (within the lookahead window)
		$currentUids = [];
		foreach ( $events as $event ) {
			if ( $event['end'] < $now || $event['start'] > $horizon ) {
				continue;
			}
			$currentUids[] = $event['uid'];
		}

		// Load existing synced timeframes for this feed
		$existingPosts = get_posts( [
			'post_type'   => TimeframeCPT::$postType,
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_query'  => [ [ 'key' => self::SOURCE_FEED_META, 'value' => $feedPostId ] ],
		] );

		$existingByUid = [];
		foreach ( $existingPosts as $postId ) {
			$uid = get_post_meta( $postId, self::SOURCE_UID_META, true );
			if ( $uid ) {
				$existingByUid[ $uid ][] = $postId;
			}
		}

		// Delete timeframes for events that are no longer in the feed
		self::cleanupRemovedEvents( $existingByUid, $currentUids );

		// Create or update timeframes for each event × each item+location pair
		foreach ( $events as $event ) {
			if ( $event['end'] < $now || $event['start'] > $horizon ) {
				continue;
			}

			foreach ( $itemIds as $itemId ) {
				foreach ( $locationIds as $locationId ) {
					$pairUid = $event['uid'] . ':' . $itemId . ':' . $locationId;

					if ( isset( $existingByUid[ $pairUid ] ) ) {
						// Update the first match (there should only ever be one)
						self::updateBlockingTimeframe( $existingByUid[ $pairUid ][0], $event );
					} else {
						self::createBlockingTimeframe( $feedPostId, $event, $itemId, $locationId );
					}
				}
			}
		}

		update_post_meta( $feedPostId, '_cb_ics_feed_last_sync', time() );
		update_post_meta( $feedPostId, '_cb_ics_feed_last_error', '' );
	}

	/**
	 * Fetches the raw ICS content from the given URL.
	 * Returns false and stores an error on failure.
	 */
	private static function fetchICS( string $url ): string|false {
		$response = wp_safe_remote_get( $url, [
			'timeout'    => 15,
			'user-agent' => 'CommonsBooking ICS Sync/' . COMMONSBOOKING_VERSION,
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return false;
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Parses VEVENT blocks from raw ICS content.
	 * Returns an array of ['uid', 'start', 'end', 'summary'] arrays.
	 * Timestamps are Unix timestamps in the WP site's local timezone.
	 */
	public static function parseVEvents( string $icsContent ): array {
		// Unfold long lines (RFC 5545 line folding: CRLF + whitespace)
		$icsContent = preg_replace( '/\r\n[ \t]/', '', $icsContent );
		$icsContent = preg_replace( '/\n[ \t]/', '', $icsContent );

		$events = [];

		// Split on VEVENT blocks
		if ( ! preg_match_all( '/BEGIN:VEVENT(.+?)END:VEVENT/s', $icsContent, $matches ) ) {
			return $events;
		}

		foreach ( $matches[1] as $block ) {
			$uid     = self::extractField( $block, 'UID' );
			$summary = self::extractField( $block, 'SUMMARY' ) ?: '';
			$summary = self::unescapeICSText( $summary );

			// DTSTART and DTEND may carry TZID or VALUE=DATE parameters
			$dtStartRaw = self::extractFieldWithParams( $block, 'DTSTART' );
			$dtEndRaw   = self::extractFieldWithParams( $block, 'DTEND' );

			if ( ! $dtStartRaw || ! $dtEndRaw || ! $uid ) {
				continue;
			}

			try {
				$start = self::parseDatetime( $dtStartRaw['value'], $dtStartRaw['tzid'], $dtStartRaw['allDay'] );
				$end   = self::parseDatetime( $dtEndRaw['value'], $dtEndRaw['tzid'], $dtEndRaw['allDay'] );
			} catch ( \Exception $e ) {
				continue;
			}

			// For all-day events, DTEND in ICS is exclusive (the day after) — subtract one second
			if ( $dtEndRaw['allDay'] ) {
				$end = $end->modify( '-1 second' );
			}

			$events[] = [
				'uid'     => $uid,
				'start'   => $start->getTimestamp(),
				'end'     => $end->getTimestamp(),
				'summary' => $summary,
			];
		}

		return $events;
	}

	/**
	 * Extracts a simple field value from a VEVENT block.
	 */
	private static function extractField( string $block, string $fieldName ): ?string {
		if ( preg_match( '/^' . preg_quote( $fieldName, '/' ) . '[;:][^\r\n]*/m', $block, $m ) ) {
			// Strip the field name + parameters up to the colon
			$line = $m[0];
			$pos  = strpos( $line, ':' );
			if ( $pos !== false ) {
				return trim( substr( $line, $pos + 1 ) );
			}
		}
		return null;
	}

	/**
	 * Extracts a date/time field along with TZID and VALUE=DATE parameters.
	 * Returns ['value' => string, 'tzid' => string|null, 'allDay' => bool].
	 */
	private static function extractFieldWithParams( string $block, string $fieldName ): ?array {
		if ( ! preg_match( '/^' . preg_quote( $fieldName, '/' ) . '([^:]*):([^\r\n]*)/m', $block, $m ) ) {
			return null;
		}

		$params = $m[1]; // e.g. ";TZID=Europe/Berlin" or ";VALUE=DATE"
		$value  = trim( $m[2] );
		$tzid   = null;
		$allDay = false;

		if ( preg_match( '/TZID=([^;]+)/', $params, $tzMatch ) ) {
			$tzid = trim( $tzMatch[1] );
		}
		if ( str_contains( $params, 'VALUE=DATE' ) ) {
			$allDay = true;
		}
		// Date-only values (8 chars, no T) are also all-day
		if ( strlen( $value ) === 8 && ! str_contains( $value, 'T' ) ) {
			$allDay = true;
		}

		return [ 'value' => $value, 'tzid' => $tzid, 'allDay' => $allDay ];
	}

	/**
	 * Parses an ICS datetime string into a DateTimeImmutable in the WP site timezone.
	 *
	 * Handles:
	 * - UTC:      20240601T120000Z
	 * - Named TZ: 20240601T120000 + TZID=Europe/Berlin
	 * - Floating: 20240601T120000 (treated as site-local)
	 * - All-day:  20240601
	 */
	public static function parseDatetime( string $value, ?string $tzid, bool $allDay = false ): \DateTimeImmutable {
		$siteTz = wp_timezone();

		if ( $allDay ) {
			// YYYYMMDD — interpret as midnight in site timezone
			$dt = \DateTimeImmutable::createFromFormat( 'Ymd', $value, $siteTz );
			if ( ! $dt ) {
				throw new \InvalidArgumentException( "Cannot parse all-day date: $value" );
			}
			return $dt->setTime( 0, 0, 0 );
		}

		// Strip trailing Z to check for UTC marker
		$isUtc = str_ends_with( $value, 'Z' );
		$value = rtrim( $value, 'Z' );

		if ( $isUtc ) {
			$dt = \DateTimeImmutable::createFromFormat( 'Ymd\THis', $value, new \DateTimeZone( 'UTC' ) );
			if ( ! $dt ) {
				throw new \InvalidArgumentException( "Cannot parse UTC datetime: $value" );
			}
			return $dt->setTimezone( $siteTz );
		}

		if ( $tzid ) {
			try {
				$tz = new \DateTimeZone( $tzid );
			} catch ( \Exception $e ) {
				$tz = $siteTz;
			}
			$dt = \DateTimeImmutable::createFromFormat( 'Ymd\THis', $value, $tz );
			if ( ! $dt ) {
				throw new \InvalidArgumentException( "Cannot parse datetime with tzid=$tzid: $value" );
			}
			return $dt->setTimezone( $siteTz );
		}

		// Floating time — treat as site local
		$dt = \DateTimeImmutable::createFromFormat( 'Ymd\THis', $value, $siteTz );
		if ( ! $dt ) {
			throw new \InvalidArgumentException( "Cannot parse floating datetime: $value" );
		}
		return $dt;
	}

	/**
	 * Creates a blocking HOLIDAYS_ID timeframe for the given event/item/location.
	 */
	private static function createBlockingTimeframe( int $feedPostId, array $event, int $itemId, int $locationId ): int {
		$title = sanitize_text_field( $event['summary'] ) ?: __( 'ICS Block', 'commonsbooking' );

		$postId = wp_insert_post( [
			'post_title'  => $title,
			'post_type'   => TimeframeCPT::$postType,
			'post_status' => 'publish',
			'post_author' => self::getAdminUserId(),
		] );

		if ( is_wp_error( $postId ) || ! $postId ) {
			return 0;
		}

		// Use start-of-day for start, end-of-day for end (matching full-day timeframe convention)
		$startDay = strtotime( 'midnight', $event['start'] );
		$endDay   = strtotime( 'midnight', $event['end'] ) + 86399; // +23h 59m 59s matches sanitizeRepetitionEndDate()

		update_post_meta( $postId, 'type', TimeframeCPT::HOLIDAYS_ID );
		update_post_meta( $postId, TimeframeModel::REPETITION_START, $startDay );
		update_post_meta( $postId, TimeframeModel::REPETITION_END, $endDay );
		update_post_meta( $postId, TimeframeModel::META_REPETITION, 'norep' );
		update_post_meta( $postId, 'full-day', 'on' );
		update_post_meta( $postId, 'start-time', '00:00' );
		update_post_meta( $postId, 'end-time', '23:59' );
		update_post_meta( $postId, 'grid', 0 );
		update_post_meta( $postId, TimeframeModel::META_ITEM_ID, $itemId );
		update_post_meta( $postId, TimeframeModel::META_LOCATION_ID, $locationId );
		update_post_meta( $postId, TimeframeModel::META_ITEM_SELECTION_TYPE, TimeframeModel::SELECTION_MANUAL_ID );
		update_post_meta( $postId, TimeframeModel::META_LOCATION_SELECTION_TYPE, TimeframeModel::SELECTION_MANUAL_ID );

		// ICS tracking — UID includes item+location so each pair is independently tracked
		update_post_meta( $postId, self::SOURCE_FEED_META, $feedPostId );
		update_post_meta( $postId, self::SOURCE_UID_META, $event['uid'] . ':' . $itemId . ':' . $locationId );

		return $postId;
	}

	/**
	 * Updates start/end dates of an existing synced timeframe.
	 */
	private static function updateBlockingTimeframe( int $timeframeId, array $event ): void {
		$startDay = strtotime( 'midnight', $event['start'] );
		$endDay   = strtotime( 'midnight', $event['end'] ) + 86399;

		update_post_meta( $timeframeId, TimeframeModel::REPETITION_START, $startDay );
		update_post_meta( $timeframeId, TimeframeModel::REPETITION_END, $endDay );

		$title = sanitize_text_field( $event['summary'] );
		if ( $title ) {
			wp_update_post( [ 'ID' => $timeframeId, 'post_title' => $title ] );
		}
	}

	/**
	 * Deletes synced timeframes whose UIDs are no longer present in the feed.
	 *
	 * @param array $existingByUid  Map of uid => [post_id, ...]
	 * @param array $currentUids    UIDs still present in the feed (base UIDs without item/location suffix)
	 */
	private static function cleanupRemovedEvents( array $existingByUid, array $currentUids ): void {
		// currentUids are base UIDs; existingByUid keys are pair UIDs (uid:itemId:locationId)
		foreach ( $existingByUid as $pairUid => $postIds ) {
			// Extract base UID by stripping :itemId:locationId suffix
			$baseUid = preg_replace( '/:\d+:\d+$/', '', $pairUid );
			if ( ! in_array( $baseUid, $currentUids, true ) ) {
				foreach ( $postIds as $postId ) {
					wp_delete_post( $postId, true );
				}
			}
		}
	}

	/**
	 * Unescapes ICS text field escape sequences.
	 */
	private static function unescapeICSText( string $text ): string {
		return str_replace( [ '\\,', '\\;', '\\n', '\\N', '\\\\' ], [ ',', ';', "\n", "\n", '\\' ], $text );
	}

	/**
	 * Returns the ID of the first administrator user, used as post_author for generated timeframes.
	 */
	public static function getAdminUserId(): int {
		$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
		return ! empty( $admins ) ? (int) $admins[0] : 1;
	}
}
