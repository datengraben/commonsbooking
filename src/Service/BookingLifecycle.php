<?php

namespace CommonsBooking\Service;

use CommonsBooking\Model\Booking;
use CommonsBooking\Wordpress\CustomPostType\Booking as BookingPostType;
use CommonsBooking\Wordpress\CustomPostType\Timeframe;
use WP_Post;

/**
 * Central dispatcher for booking lifecycle events.
 *
 * Extensions (smart-lock integrations, external calendars, audit logs, custom
 * pricing, …) need to react when a booking is created, confirmed, cancelled or
 * otherwise changes status — independent of whether the change originated from
 * the frontend calendar, the admin backend, the REST API or WP-CLI.
 *
 * To guarantee that the corresponding action hooks fire exactly once per real
 * transition, this service listens to WordPress' own `transition_post_status`
 * action, which is emitted by every `wp_insert_post()` / `wp_update_post()`
 * call regardless of the code path that triggered it.
 *
 * The one path that does not run through `wp_update_post()` is
 * {@see \CommonsBooking\Model\Booking::cancel()}, which writes the `canceled`
 * status directly to the database to preserve meta data. Cancellation is
 * therefore owned by that method, which fires `commonsbooking_booking_cancelled`
 * itself. This service intentionally does not emit a cancellation event so the
 * hook fires once for every cancellation path.
 *
 * @since 2.11.0
 */
class BookingLifecycle {

	/**
	 * Post meta flag used to make the `commonsbooking_booking_created` event
	 * fire only once over the lifetime of a booking.
	 */
	public const CREATED_FIRED_META = '_cb_lifecycle_created_fired';

	/**
	 * WordPress post statuses that do not represent a real booking state and
	 * should therefore not trigger any lifecycle event.
	 *
	 * @var string[]
	 */
	private const PLACEHOLDER_STATUSES = array( 'auto-draft', 'new', 'inherit' );

	/**
	 * Registers the lifecycle dispatcher.
	 */
	public static function initHooks(): void {
		add_action( 'transition_post_status', array( self::class, 'onTransitionPostStatus' ), 10, 3 );
	}

	/**
	 * Emits booking lifecycle action hooks based on WordPress post status
	 * transitions.
	 *
	 * Fires (for `cb_booking` posts that are actual bookings):
	 *  - `commonsbooking_booking_created`        – once, the first time the booking enters a real status.
	 *  - `commonsbooking_booking_status_changed` – on every real status transition.
	 *  - `commonsbooking_booking_confirmed`      – whenever the booking becomes `confirmed`.
	 *
	 * Cancellation is handled by {@see \CommonsBooking\Model\Booking::cancel()}.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       The post being transitioned.
	 */
	public static function onTransitionPostStatus( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof WP_Post || $post->post_type !== BookingPostType::getPostType() ) {
			return;
		}

		// No real transition, nothing to announce.
		if ( $new_status === $old_status ) {
			return;
		}

		// Ignore transitions into transient placeholder states (e.g. auto-draft).
		if ( in_array( $new_status, self::PLACEHOLDER_STATUSES, true ) ) {
			return;
		}

		// Only announce events for posts that are actual bookings. The booking
		// type meta is written as part of the insert/update, so it is available
		// by the time this action fires.
		if ( get_post_meta( $post->ID, 'type', true ) != Timeframe::BOOKING_ID ) {
			return;
		}

		$booking = new Booking( $post->ID );

		/**
		 * Fires once when a booking first enters a real status.
		 *
		 * @since 2.11.0
		 *
		 * @param int     $booking_id The booking post ID.
		 * @param Booking $booking    The booking model instance.
		 */
		if ( ! get_post_meta( $post->ID, self::CREATED_FIRED_META, true ) ) {
			update_post_meta( $post->ID, self::CREATED_FIRED_META, 1 );
			do_action( 'commonsbooking_booking_created', $post->ID, $booking );
		}

		/**
		 * Fires on every booking status transition.
		 *
		 * @since 2.11.0
		 *
		 * @param int     $booking_id The booking post ID.
		 * @param string  $old_status The previous post status.
		 * @param string  $new_status The new post status.
		 * @param Booking $booking    The booking model instance.
		 */
		do_action( 'commonsbooking_booking_status_changed', $post->ID, $old_status, $new_status, $booking );

		/**
		 * Fires when a booking is confirmed.
		 *
		 * This is the recommended hook for integrations that need to react to a
		 * booking becoming active, e.g. programming a smart lock or syncing an
		 * external calendar.
		 *
		 * @since 2.11.0
		 *
		 * @param int     $booking_id The booking post ID.
		 * @param Booking $booking    The booking model instance.
		 */
		if ( $new_status === 'confirmed' ) {
			do_action( 'commonsbooking_booking_confirmed', $post->ID, $booking );
		}
	}
}
