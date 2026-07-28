<?php

namespace CommonsBooking\Service;

use CommonsBooking\Repository\Booking;
use CommonsBooking\Settings\Settings;

/**
 * Builds and sends a periodic, plain-text booking report by e-mail.
 *
 * The report contains the number of confirmed bookings (counted by their
 * booking start date) for a set of trailing periods, each compared against
 * the immediately preceding period of the same length:
 * - last 7 days vs. the 7 days before
 * - last 30 days vs. the 30 days before
 * - last 3 months vs. the 3 months before
 *
 * Sending is scheduled daily (see Service\Scheduler) but the report is only
 * actually sent on the configured interval (weekly on Mondays, monthly on the
 * first day of the month). The daily scheduling is used because the scheduler
 * only supports a fixed time of day for daily jobs.
 */
class BookingReport {

	const OPTION_GROUP = 'commonsbooking_options_reminder';

	/**
	 * Entry point called by the scheduler once per day.
	 *
	 * Checks whether the report is activated and whether today is a sending day
	 * for the configured interval, and sends the report if so.
	 *
	 * @return void
	 */
	public static function sendReport(): void {
		if ( Settings::getOption( self::OPTION_GROUP, 'booking-report-activate' ) != 'on' ) {
			return;
		}

		$interval = Settings::getOption( self::OPTION_GROUP, 'booking-report-interval' );
		if ( ! self::isSendingDay( $interval, time() ) ) {
			return;
		}

		$body    = self::getReportBody();
		$subject = sprintf(
			// translators: %s = site name
			__( 'CommonsBooking booking report for %s', 'commonsbooking' ),
			get_bloginfo( 'name' )
		);

		self::sendMail( $subject, $body );
	}

	/**
	 * Determines whether the report should be sent today for the given interval.
	 *
	 * @param string   $interval "weekly" or "monthly".
	 * @param int|null $now      Reference timestamp (defaults to current time). Used for testing.
	 *
	 * @return bool
	 */
	public static function isSendingDay( string $interval, ?int $now = null ): bool {
		$now ??= time();

		switch ( $interval ) {
			case 'monthly':
				// First day of the month.
				return wp_date( 'j', $now ) === '1';
			case 'weekly':
			default:
				// Monday (ISO-8601: 1 = Monday).
				return wp_date( 'N', $now ) === '1';
		}
	}

	/**
	 * Builds the plain-text body of the report.
	 *
	 * @param int|null $now Reference timestamp (defaults to current time). Used for testing.
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function getReportBody( ?int $now = null ): string {
		$now ??= time();

		$periods = array(
			array(
				'label' => __( 'Last 7 days', 'commonsbooking' ),
				'prev'  => __( 'previous 7 days', 'commonsbooking' ),
				'spec'  => '7 days',
			),
			array(
				'label' => __( 'Last 30 days', 'commonsbooking' ),
				'prev'  => __( 'previous 30 days', 'commonsbooking' ),
				'spec'  => '30 days',
			),
			array(
				'label' => __( 'Last 3 months', 'commonsbooking' ),
				'prev'  => __( 'previous 3 months', 'commonsbooking' ),
				'spec'  => '3 months',
			),
		);

		$lines   = array();
		$lines[] = sprintf(
			// translators: %1$s = site name, %2$s = date
			__( 'CommonsBooking - Booking report for %1$s (%2$s)', 'commonsbooking' ),
			get_bloginfo( 'name' ),
			wp_date( 'Y-m-d' )
		);
		$lines[] = '';
		$lines[] = __( 'Number of confirmed bookings (counted by booking start date):', 'commonsbooking' );
		$lines[] = '';

		foreach ( $periods as $period ) {
			$currentStart = strtotime( '-' . $period['spec'], $now );
			$previousStart = strtotime( '-' . $period['spec'], $currentStart );

			// Current period: (currentStart, now]. Previous period: (previousStart, currentStart].
			$current  = Booking::countBookingsStartingInRange( $currentStart + 1, $now );
			$previous = Booking::countBookingsStartingInRange( $previousStart + 1, $currentStart );

			$lines[] = sprintf(
				'  %-16s %6d   (%s: %d)',
				$period['label'] . ':',
				$current,
				$period['prev'],
				$previous
			);
		}

		$lines[] = '';

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Sends the report e-mail as plain text.
	 *
	 * @param string $subject
	 * @param string $body
	 *
	 * @return bool Whether the mail was accepted for delivery by wp_mail().
	 */
	protected static function sendMail( string $subject, string $body ): bool {
		$recipient = sanitize_email( Settings::getOption( self::OPTION_GROUP, 'booking-report-recipient' ) );
		if ( empty( $recipient ) ) {
			$recipient = get_option( 'admin_email' );
		}

		$fromName  = Settings::getOption( 'commonsbooking_options_templates', 'emailheaders_from-name' );
		$fromEmail = sanitize_email( Settings::getOption( 'commonsbooking_options_templates', 'emailheaders_from-email' ) );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( ! empty( $fromEmail ) ) {
			$headers[] = sprintf( 'From: %s <%s>', $fromName, $fromEmail );
		}

		return wp_mail( $recipient, $subject, $body, $headers );
	}
}
