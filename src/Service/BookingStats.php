<?php

namespace CommonsBooking\Service;

use CommonsBooking\Model\Booking;
use CommonsBooking\Repository\BookingStats as BookingStatsRepo;
use CommonsBooking\Wordpress\CustomPostType\Booking as BookingCPT;

/**
 * High-level booking statistics service.
 *
 * Resolves "entity type / entity id" from optional item/location IDs,
 * computes period date ranges, and delegates reads to BookingStatsRepo.
 * Also handles the transition_post_status hook to keep the stats table in sync.
 */
class BookingStats {

	// -------------------------------------------------------------------------
	// Lifecycle hook (called from Plugin::init via transition_post_status)
	// -------------------------------------------------------------------------

	public static function handleStatusTransition( string $newStatus, string $oldStatus, \WP_Post $post ): void {
		if ( $post->post_type !== BookingCPT::$postType ) {
			return;
		}
		if ( $newStatus === $oldStatus ) {
			return;
		}

		try {
			$booking = new Booking( $post->ID );
		} catch ( \Exception $e ) {
			return;
		}

		if ( $newStatus === 'confirmed' ) {
			BookingStatsRepo::recordBooking( $booking );
		} elseif ( $oldStatus === 'confirmed' ) {
			BookingStatsRepo::removeBooking( $booking );
		}
	}

	// -------------------------------------------------------------------------
	// Period stats API
	// -------------------------------------------------------------------------

	/**
	 * @return array{current: array{count: int, days: int}, previous: array{count: int, days: int}, diff_count: int, diff_pct: float|null}
	 */
	public static function getDayStats( ?int $itemId = null, ?int $locationId = null ): array {
		[ $entityType, $entityId ] = self::resolveEntity( $itemId, $locationId );

		[ $curFrom, $curTo ]  = self::currentDay();
		[ $prevFrom, $prevTo ] = self::previousDay();

		return self::buildResult( $curFrom, $curTo, $prevFrom, $prevTo, $entityType, $entityId );
	}

	/**
	 * @return array{current: array{count: int, days: int}, previous: array{count: int, days: int}, diff_count: int, diff_pct: float|null}
	 */
	public static function getWeekStats( ?int $itemId = null, ?int $locationId = null ): array {
		[ $entityType, $entityId ] = self::resolveEntity( $itemId, $locationId );

		[ $curFrom, $curTo ]  = self::currentWeek();
		[ $prevFrom, $prevTo ] = self::previousWeek();

		return self::buildResult( $curFrom, $curTo, $prevFrom, $prevTo, $entityType, $entityId );
	}

	/**
	 * @return array{current: array{count: int, days: int}, previous: array{count: int, days: int}, diff_count: int, diff_pct: float|null}
	 */
	public static function getMonthStats( ?int $itemId = null, ?int $locationId = null ): array {
		[ $entityType, $entityId ] = self::resolveEntity( $itemId, $locationId );

		[ $curFrom, $curTo ]  = self::currentMonth();
		[ $prevFrom, $prevTo ] = self::previousMonth();

		return self::buildResult( $curFrom, $curTo, $prevFrom, $prevTo, $entityType, $entityId );
	}

	/**
	 * @return array{current: array{count: int, days: int}, previous: array{count: int, days: int}, diff_count: int, diff_pct: float|null}
	 */
	public static function getYearStats( ?int $itemId = null, ?int $locationId = null ): array {
		[ $entityType, $entityId ] = self::resolveEntity( $itemId, $locationId );

		[ $curFrom, $curTo ]  = self::currentYear();
		[ $prevFrom, $prevTo ] = self::previousYear();

		return self::buildResult( $curFrom, $curTo, $prevFrom, $prevTo, $entityType, $entityId );
	}

	/**
	 * Generic period stats lookup – used by the shortcode.
	 *
	 * @param string   $period      'day'|'week'|'month'|'year'
	 * @param int|null $itemId
	 * @param int|null $locationId
	 *
	 * @return array{current: array{count: int, days: int}, previous: array{count: int, days: int}, diff_count: int, diff_pct: float|null}
	 */
	public static function getPeriodStats( string $period, ?int $itemId = null, ?int $locationId = null ): array {
		switch ( $period ) {
			case 'day':
				return self::getDayStats( $itemId, $locationId );
			case 'month':
				return self::getMonthStats( $itemId, $locationId );
			case 'year':
				return self::getYearStats( $itemId, $locationId );
			default:
				return self::getWeekStats( $itemId, $locationId );
		}
	}

	// -------------------------------------------------------------------------
	// Private helpers – entity resolution
	// -------------------------------------------------------------------------

	/** @return array{string, int} */
	private static function resolveEntity( ?int $itemId, ?int $locationId ): array {
		if ( $itemId ) {
			return [ 'item', $itemId ];
		}
		if ( $locationId ) {
			return [ 'location', $locationId ];
		}
		return [ 'all', 0 ];
	}

	// -------------------------------------------------------------------------
	// Private helpers – period date ranges
	// -------------------------------------------------------------------------

	/** @return array{\DateTimeImmutable, \DateTimeImmutable} */
	private static function currentDay(): array {
		$today = new \DateTimeImmutable( 'today' );
		return [ $today, $today ];
	}

	/** @return array{\DateTimeImmutable, \DateTimeImmutable} */
	private static function previousDay(): array {
		$yesterday = new \DateTimeImmutable( 'yesterday' );
		return [ $yesterday, $yesterday ];
	}

	/** @return array{\DateTimeImmutable, \DateTimeImmutable} */
	private static function currentWeek(): array {
		$from = new \DateTimeImmutable( 'monday this week' );
		$to   = new \DateTimeImmutable( 'sunday this week' );
		return [ $from, $to ];
	}

	/** @return array{\DateTimeImmutable, \DateTimeImmutable} */
	private static function previousWeek(): array {
		$from = new \DateTimeImmutable( 'monday last week' );
		$to   = new \DateTimeImmutable( 'sunday last week' );
		return [ $from, $to ];
	}

	/** @return array{\DateTimeImmutable, \DateTimeImmutable} */
	private static function currentMonth(): array {
		$from = new \DateTimeImmutable( 'first day of this month' );
		$to   = new \DateTimeImmutable( 'last day of this month' );
		return [ $from, $to ];
	}

	/** @return array{\DateTimeImmutable, \DateTimeImmutable} */
	private static function previousMonth(): array {
		$from = new \DateTimeImmutable( 'first day of last month' );
		$to   = new \DateTimeImmutable( 'last day of last month' );
		return [ $from, $to ];
	}

	/** @return array{\DateTimeImmutable, \DateTimeImmutable} */
	private static function currentYear(): array {
		$year = (int) date( 'Y' );
		$from = new \DateTimeImmutable( "$year-01-01" );
		$to   = new \DateTimeImmutable( "$year-12-31" );
		return [ $from, $to ];
	}

	/** @return array{\DateTimeImmutable, \DateTimeImmutable} */
	private static function previousYear(): array {
		$year = (int) date( 'Y' ) - 1;
		$from = new \DateTimeImmutable( "$year-01-01" );
		$to   = new \DateTimeImmutable( "$year-12-31" );
		return [ $from, $to ];
	}

	// -------------------------------------------------------------------------
	// Private helpers – result building
	// -------------------------------------------------------------------------

	/** @return array{current: array{count: int, days: int}, previous: array{count: int, days: int}, diff_count: int, diff_pct: float|null} */
	private static function buildResult(
		\DateTimeImmutable $curFrom,
		\DateTimeImmutable $curTo,
		\DateTimeImmutable $prevFrom,
		\DateTimeImmutable $prevTo,
		string $entityType,
		int $entityId
	): array {
		$current  = BookingStatsRepo::getAggregated( $curFrom, $curTo, $entityType, $entityId );
		$previous = BookingStatsRepo::getAggregated( $prevFrom, $prevTo, $entityType, $entityId );

		$diffCount = $current['count'] - $previous['count'];
		$diffPct   = $previous['count'] > 0
			? round( ( $diffCount / $previous['count'] ) * 100, 1 )
			: null;

		return [
			'current'    => $current,
			'previous'   => $previous,
			'diff_count' => $diffCount,
			'diff_pct'   => $diffPct,
		];
	}
}
