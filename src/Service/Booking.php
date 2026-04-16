<?php

namespace CommonsBooking\Service;

use CommonsBooking\Messages\BookingReminderMessage;
use CommonsBooking\Messages\LocationBookingReminderMessage;
use CommonsBooking\Messages\Message;
use CommonsBooking\Settings\Settings;
use CommonsBooking\Wordpress\CustomPostType\Timeframe;
use WP_Query;

class Booking {

	/**
	 * Default number of booking posts to process per database batch in the migration job.
	 * Override via the 'commonsbooking_past_booking_batch_size' filter.
	 */
	const MIGRATION_BATCH_SIZE = 100;

	/**
	 * Transitions booking post statuses based on the 'past_booking' feature flag.
	 *
	 * Flag ON  (forward):  confirmed bookings whose end date has passed → 'past_booking'.
	 *                      Keeps the active confirmed pool small for faster meta queries.
	 * Flag OFF (reverse):  any existing 'past_booking' posts → 'confirmed'.
	 *                      Allows clean rollback when the feature is disabled.
	 *
	 * Processes in batches (default: 100) to limit peak memory usage on large datasets.
	 * Always re-queries page 1 — processed records change status and drop out of the
	 * result set, so the loop drains naturally without offset drift.
	 *
	 * Batch size is filterable via 'commonsbooking_past_booking_batch_size'.
	 * Uses a direct SQL UPDATE (same pattern as Model\Booking::cancel()) to avoid
	 * wp_update_post() wiping post meta.
	 */
	public static function markPastBookings(): void {
		if ( apply_filters( 'commonsbooking_enable_past_booking_status', false ) ) {
			// Forward: transition expired confirmed bookings to past_booking
			self::batchTransitionBookingStatus(
				'confirmed',
				'past_booking',
				[
					'relation' => 'AND',
					[
						'key'     => \CommonsBooking\Model\Timeframe::REPETITION_END,
						'value'   => current_time( 'timestamp' ),
						'compare' => '<',
						'type'    => 'numeric',
					],
					[
						'key'     => 'type',
						'value'   => Timeframe::BOOKING_ID,
						'compare' => '=',
					],
				]
			);
		} else {
			// Reverse: revert any past_booking posts back to confirmed
			self::batchTransitionBookingStatus(
				'past_booking',
				'confirmed',
				[
					[
						'key'     => 'type',
						'value'   => Timeframe::BOOKING_ID,
						'compare' => '=',
					],
				]
			);
		}
	}

	/**
	 * Transitions booking posts from one status to another in batches.
	 *
	 * Processes $batchSize records per loop iteration, always fetching page 1.
	 * Since updated records leave the $fromStatus pool they will not appear in
	 * subsequent queries, so the loop terminates without page-offset drift.
	 *
	 * A single UPDATE ... IN (...) per batch minimises round-trips.
	 *
	 * @param string $fromStatus Source post status to match.
	 * @param string $toStatus   Target post status to write.
	 * @param array  $metaQuery  WP_Query meta_query array for additional constraints.
	 */
	private static function batchTransitionBookingStatus(
		string $fromStatus,
		string $toStatus,
		array $metaQuery
	): void {
		global $wpdb;

		$batchSize = (int) apply_filters( 'commonsbooking_past_booking_batch_size', self::MIGRATION_BATCH_SIZE );

		do {
			$query = new WP_Query(
				[
					'post_type'      => \CommonsBooking\Wordpress\CustomPostType\Booking::$postType,
					'post_status'    => $fromStatus,
					'meta_query'     => $metaQuery,
					'posts_per_page' => $batchSize,
					'paged'          => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'cache_results'  => false,
				]
			);

			$postIds = $query->get_posts();

			if ( empty( $postIds ) ) {
				break;
			}

			// Cast to int before inlining — avoids dynamic placeholders and
			// is safe because WP_Query already returns integer post IDs.
			$intIds  = array_map( 'intval', $postIds );
			$idList  = implode( ', ', $intIds );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->posts} SET post_status = %s WHERE ID IN ({$idList}) AND post_status = %s",
					$toStatus,
					$fromStatus
				)
			);

			wp_cache_flush();

		} while ( count( $postIds ) >= $batchSize );
	}

	/**
	 * Removes all unconfirmed bookings older than 10 minutes
	 * is triggered in  Service\Scheduler initHooks()
	 *
	 * @return void
	 */
	public static function cleanupBookings() {
		$args = array(
			'post_type'   => \CommonsBooking\Wordpress\CustomPostType\Booking::$postType,
			'post_status' => 'unconfirmed',
			'meta_key'    => 'type',
			'meta_value'  => Timeframe::BOOKING_ID,
			'date_query'  => array(
				'before' => '-10 minutes',
			),
			'nopaging'    => true,
		);

		$query = new WP_Query( $args );
		if ( $query->have_posts() ) {
			foreach ( $query->get_posts() as $post ) {
				if ( $post->post_status !== 'unconfirmed' ) {
					continue;
				}
				wp_delete_post( $post->ID );
			}
		}
	}

	private static function sendMessagesForDay( int $tsDate, bool $onStartDate, Message $message ) {
		if ( $onStartDate ) {
			$bookings = \CommonsBooking\Repository\Booking::getBeginningBookingsByDate( $tsDate );
		} else {
			$bookings = \CommonsBooking\Repository\Booking::getEndingBookingsByDate( $tsDate );
		}
		if ( count( $bookings ) ) {
			foreach ( $bookings as $booking ) {
				if ( $booking->hasTotalBreakdown() ) {
					continue;
				}
				$message = new $message( $booking->getPost()->ID, $message->getAction() );
				$message->triggerMail();
			}
		}
	}

	/**
	 * Send reminder mail, x days before start of booking.
	 * is triggered in  Service\Scheduler initHooks()
	 *
	 * @throws \Exception
	 */
	public static function sendReminderMessage() {

		if ( Settings::getOption( 'commonsbooking_options_reminder', 'pre-booking-reminder-activate' ) != 'on' ) {
			return;
		}

		$message         = new BookingReminderMessage( 0, 'pre-booking-reminder' );
		$daysBeforeStart = Settings::getOption( 'commonsbooking_options_reminder', 'pre-booking-days-before' );
		self::sendMessagesForDay( strtotime( '+' . $daysBeforeStart . ' days midnight' ), true, $message );
	}

	/**
	 * Send feedback mal on same day or the day after end of booking.
	 * is triggered in  Service\Scheduler initHooks()
	 *
	 * @throws \Exception
	 */
	public static function sendFeedbackMessage() {

		if ( Settings::getOption( 'commonsbooking_options_reminder', 'post-booking-notice-activate' ) != 'on' ) {
			return;
		}

		// Yesterday at 23:59
		$endDate = strtotime( 'midnight', time() ) - 1;
		$message = new BookingReminderMessage( 0, 'post-booking-notice' );
		self::sendMessagesForDay( $endDate, false, $message );
	}

	public static function sendBookingStartLocationReminderMessage() {
		self::sendLocationBookingReminderMessage( 'start' );
	}

	public static function sendBookingEndLocationReminderMessage() {
		self::sendLocationBookingReminderMessage( 'end' );
	}

	protected static function sendLocationBookingReminderMessage( string $type ) {

		if ( Settings::getOption( 'commonsbooking_options_reminder', 'booking-' . $type . '-location-reminder-activate' ) != 'on' ) {
			return;
		}

		// current day is saved in options as 1, this is because 0 is an unset value. Subtract 1 to get the correct day
		$daysBeforeStart = (int) Settings::getOption( 'commonsbooking_options_reminder', 'booking-' . $type . '-location-reminder-day' ) - 1;
		$startDate       = strtotime( '+' . $daysBeforeStart . ' days midnight' );

		$message = new LocationBookingReminderMessage( 0, 'booking-' . $type . '-location-reminder' );
		self::sendMessagesForDay( $startDate, $type === 'start', $message );
	}
}
