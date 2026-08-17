<?php

namespace CommonsBooking\Service;

use CommonsBooking\Helper\Wordpress;
use CommonsBooking\Model\Booking;
use CommonsBooking\Model\Day;
use CommonsBooking\Plugin;
use CommonsBooking\Repository\Timeframe as TimeframeRepository;
use CommonsBooking\Wordpress\CustomPostType\Item;
use CommonsBooking\Wordpress\CustomPostType\Timeframe as TimeframeCPT;
use Exception;
use WP_Post;
use WP_Query;
use WP_User;

/**
 * Builds "Book again" and "Did you try …" recommendations for a user.
 *
 * "Book again" suggests items the user has booked before that currently have a
 * free bookable slot within a given time window (today, tomorrow or the next
 * seven days). "Did you try …" suggests items that share a category (the item
 * taxonomy) with the user's previously booked items and that are free in the
 * same window.
 *
 * All availability decisions reuse the existing timeframe/booking logic via the
 * {@see Day} model, so holidays, repairs, restrictions and existing bookings are
 * respected the same way the booking calendar respects them.
 */
class BookingRecommendation {

	/**
	 * Free at some point today.
	 */
	const WINDOW_TODAY = 'today';

	/**
	 * Free at some point tomorrow.
	 */
	const WINDOW_TOMORROW = 'tomorrow';

	/**
	 * Free at some point within a rolling seven day window starting today.
	 */
	const WINDOW_WEEK = 'week';

	/**
	 * Returns the list of valid window identifiers.
	 *
	 * @return string[]
	 */
	public static function getWindows(): array {
		return [ self::WINDOW_TODAY, self::WINDOW_TOMORROW, self::WINDOW_WEEK ];
	}

	/**
	 * Returns [ startTimestamp, endTimestamp ] for the given window.
	 *
	 * The "week" window is a rolling seven days from now (not the ISO week).
	 *
	 * @param string $window
	 *
	 * @return int[]
	 */
	protected static function windowRange( string $window ): array {
		$now = time();

		switch ( $window ) {
			case self::WINDOW_TOMORROW:
				$start = (int) strtotime( 'tomorrow midnight', $now );
				$end   = (int) strtotime( 'tomorrow 23:59:59', $now );
				break;
			case self::WINDOW_WEEK:
				$start = (int) strtotime( 'midnight', $now );
				$end   = $now + ( 7 * DAY_IN_SECONDS );
				break;
			case self::WINDOW_TODAY:
			default:
				$start = (int) strtotime( 'midnight', $now );
				$end   = (int) strtotime( 'today 23:59:59', $now );
				break;
		}

		return [ $start, $end ];
	}

	/**
	 * Returns the distinct items a user has previously booked.
	 *
	 * Each entry is keyed by item id and contains the locations the user booked
	 * the item at and the most recent booking start date (used for ranking).
	 *
	 * @param WP_User $user
	 *
	 * @return array<int, array{itemId:int, locationIds:int[], lastBooked:int}>
	 * @throws Exception
	 */
	public static function getPreviousItemsForUser( WP_User $user ): array {
		$bookings = \CommonsBooking\Repository\Booking::getForUser( $user, true );

		$items = [];
		foreach ( $bookings as $booking ) {
			if ( ! ( $booking instanceof Booking ) ) {
				continue;
			}

			$itemId = $booking->getItemID();
			if ( ! $itemId ) {
				continue;
			}

			// Only keep items that still exist and are published.
			$itemPost = get_post( $itemId );
			if (
				! ( $itemPost instanceof WP_Post ) ||
				$itemPost->post_type !== Item::getPostType() ||
				$itemPost->post_status !== 'publish'
			) {
				continue;
			}

			if ( ! array_key_exists( $itemId, $items ) ) {
				$items[ $itemId ] = [
					'itemId'      => $itemId,
					'locationIds' => [],
					'lastBooked'  => 0,
				];
			}

			$locationId = $booking->getLocationID();
			if ( $locationId ) {
				$items[ $itemId ]['locationIds'][ $locationId ] = $locationId;
			}

			$startDate = $booking->getStartDate();
			if ( $startDate > $items[ $itemId ]['lastBooked'] ) {
				$items[ $itemId ]['lastBooked'] = $startDate;
			}
		}

		return $items;
	}

	/**
	 * Returns "Book again" suggestions: previously booked items that are free
	 * within the given window.
	 *
	 * @param WP_User $user
	 * @param string  $window One of the WINDOW_* constants.
	 * @param int     $limit  Maximum number of suggestions.
	 *
	 * @return array<int, array{itemId:int, locationId:?int}>
	 * @throws Exception
	 */
	public static function getBookAgainSuggestions( WP_User $user, string $window = self::WINDOW_WEEK, int $limit = 6 ): array {
		$window = self::sanitizeWindow( $window );

		$customId  = md5( __METHOD__ . $user->ID . $window . $limit );
		$cacheItem = Plugin::getCacheItem( $customId );
		if ( $cacheItem !== false ) {
			return $cacheItem;
		}

		[ $start, $end ] = self::windowRange( $window );

		$previousItems = self::getPreviousItemsForUser( $user );

		// Rank by most recently booked first.
		usort(
			$previousItems,
			function ( $a, $b ) {
				return $b['lastBooked'] <=> $a['lastBooked'];
			}
		);

		$suggestions   = [];
		$itemIdsUsed   = [];
		$locationsUsed = [];
		foreach ( $previousItems as $entry ) {
			$freeLocation = self::firstFreeLocation( $entry['itemId'], $entry['locationIds'], $start, $end );
			if ( $freeLocation === null ) {
				continue;
			}

			$suggestions[]   = [
				'itemId'     => $entry['itemId'],
				'locationId' => $freeLocation,
			];
			$itemIdsUsed[]   = $entry['itemId'];
			$locationsUsed[] = $freeLocation;

			if ( count( $suggestions ) >= $limit ) {
				break;
			}
		}

		Plugin::setCacheItem(
			$suggestions,
			Wordpress::getTags( [], $itemIdsUsed, $locationsUsed ),
			$customId,
			'midnight'
		);

		return $suggestions;
	}

	/**
	 * Returns "Did you try …" suggestions: items sharing a category with the
	 * user's previously booked items, that are free within the given window and
	 * that the user has not booked before.
	 *
	 * @param WP_User $user
	 * @param string  $window One of the WINDOW_* constants.
	 * @param int     $limit  Maximum number of suggestions.
	 *
	 * @return array<int, array{itemId:int, locationId:?int}>
	 * @throws Exception
	 */
	public static function getSimilarSuggestions( WP_User $user, string $window = self::WINDOW_WEEK, int $limit = 6 ): array {
		$window = self::sanitizeWindow( $window );

		$customId  = md5( __METHOD__ . $user->ID . $window . $limit );
		$cacheItem = Plugin::getCacheItem( $customId );
		if ( $cacheItem !== false ) {
			return $cacheItem;
		}

		[ $start, $end ] = self::windowRange( $window );

		$previousItems     = self::getPreviousItemsForUser( $user );
		$previousItemIds   = array_keys( $previousItems );
		$categoryTermIds   = self::getCategoryTermIds( $previousItemIds );

		if ( empty( $categoryTermIds ) ) {
			// Nothing to base similarity on.
			Plugin::setCacheItem( [], [ 'misc' ], $customId, 'midnight' );
			return [];
		}

		$candidateIds = self::getItemIdsByTerms( $categoryTermIds, $previousItemIds );

		$suggestions   = [];
		$itemIdsUsed   = [];
		$locationsUsed = [];
		foreach ( $candidateIds as $itemId ) {
			$freeLocation = self::firstFreeLocation( $itemId, [], $start, $end );
			if ( $freeLocation === null ) {
				continue;
			}

			$suggestions[]   = [
				'itemId'     => $itemId,
				'locationId' => $freeLocation,
			];
			$itemIdsUsed[]   = $itemId;
			$locationsUsed[] = $freeLocation;

			if ( count( $suggestions ) >= $limit ) {
				break;
			}
		}

		Plugin::setCacheItem(
			$suggestions,
			Wordpress::getTags( [], $itemIdsUsed, $locationsUsed ),
			$customId,
			'midnight'
		);

		return $suggestions;
	}

	/**
	 * Returns the first location id where the item has a free bookable slot in
	 * the given range, or null if none is found.
	 *
	 * When no candidate locations are supplied, the item's bookable locations are
	 * derived from its bookable timeframes.
	 *
	 * @param int   $itemId
	 * @param int[] $locationIds
	 * @param int   $start
	 * @param int   $end
	 *
	 * @return int|null
	 * @throws Exception
	 */
	protected static function firstFreeLocation( int $itemId, array $locationIds, int $start, int $end ): ?int {
		if ( empty( $locationIds ) ) {
			$locationIds = self::getBookableLocationIds( $itemId );
		}

		foreach ( $locationIds as $locationId ) {
			if ( self::isFreeInRange( $itemId, (int) $locationId, $start, $end ) ) {
				return (int) $locationId;
			}
		}

		return null;
	}

	/**
	 * Returns the location ids at which the item currently has bookable
	 * timeframes (visible to the current user).
	 *
	 * @param int $itemId
	 *
	 * @return int[]
	 * @throws Exception
	 */
	protected static function getBookableLocationIds( int $itemId ): array {
		$timeframes = TimeframeRepository::getBookableForCurrentUser(
			[],
			[ $itemId ],
			null,
			true
		);

		$locationIds = [];
		foreach ( $timeframes as $timeframe ) {
			$locationId = $timeframe->getLocationID();
			if ( $locationId ) {
				$locationIds[ $locationId ] = $locationId;
			}
		}

		return array_values( $locationIds );
	}

	/**
	 * Determines whether the item has at least one free bookable slot at the
	 * given location within [ $start, $end ].
	 *
	 * A slot counts as free when the highest-priority timeframe covering it is of
	 * type "bookable" (existing bookings, holidays, repairs and restrictions win
	 * over bookable timeframes in the {@see Day} grid), the slot ends in the
	 * future and the timeframe is already bookable on that day.
	 *
	 * @param int $itemId
	 * @param int $locationId
	 * @param int $start
	 * @param int $end
	 *
	 * @return bool
	 * @throws Exception
	 */
	protected static function isFreeInRange( int $itemId, int $locationId, int $start, int $end ): bool {
		$now = time();

		$dayTimestamp = (int) strtotime( 'midnight', $start );
		while ( $dayTimestamp <= $end ) {
			$dateString = date( 'Y-m-d', $dayTimestamp );
			$day        = new Day( $dateString, [ $locationId ], [ $itemId ] );

			foreach ( $day->getGrid() as $slot ) {
				if ( empty( $slot['timeframe'] ) || ! ( $slot['timeframe'] instanceof WP_Post ) ) {
					continue;
				}

				// Only directly bookable slots count as free.
				$type = get_post_meta( $slot['timeframe']->ID, 'type', true );
				if ( (int) $type !== TimeframeCPT::BOOKABLE_ID ) {
					continue;
				}

				// Slot must not lie fully in the past or after the window.
				if ( $slot['timestampend'] <= $now || $slot['timestampstart'] > $end ) {
					continue;
				}

				// Respect advance-booking rules (first bookable day of the timeframe).
				$timeframe = new \CommonsBooking\Model\Timeframe( $slot['timeframe'] );
				if ( $timeframe->getFirstBookableDay() > $dateString ) {
					continue;
				}

				return true;
			}

			$dayTimestamp = (int) strtotime( '+1 day', $dayTimestamp );
		}

		return false;
	}

	/**
	 * Collects the item-category term ids assigned to the given items.
	 *
	 * @param int[] $itemIds
	 *
	 * @return int[]
	 */
	protected static function getCategoryTermIds( array $itemIds ): array {
		$taxonomy = Item::getTaxonomyName();

		$termIds = [];
		foreach ( $itemIds as $itemId ) {
			$terms = get_the_terms( $itemId, $taxonomy );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$termIds[ $term->term_id ] = $term->term_id;
				}
			}
		}

		return array_values( $termIds );
	}

	/**
	 * Returns published item ids that carry any of the given category terms,
	 * excluding the supplied items.
	 *
	 * @param int[] $termIds
	 * @param int[] $excludeItemIds
	 *
	 * @return int[]
	 */
	protected static function getItemIdsByTerms( array $termIds, array $excludeItemIds ): array {
		if ( empty( $termIds ) ) {
			return [];
		}

		$query = new WP_Query(
			[
				'post_type'      => Item::getPostType(),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post__not_in'   => array_map( 'intval', $excludeItemIds ),
				'no_found_rows'  => true,
				'tax_query'      => [
					[
						'taxonomy' => Item::getTaxonomyName(),
						'field'    => 'term_id',
						'terms'    => array_map( 'intval', $termIds ),
					],
				],
			]
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Falls back to the rolling week window for unknown input.
	 *
	 * @param string $window
	 *
	 * @return string
	 */
	protected static function sanitizeWindow( string $window ): string {
		return in_array( $window, self::getWindows(), true ) ? $window : self::WINDOW_WEEK;
	}
}
