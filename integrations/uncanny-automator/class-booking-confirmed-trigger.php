<?php
/**
 * "A booking is confirmed" trigger for Uncanny Automator.
 *
 * Fires whenever CommonsBooking emits `commonsbooking_booking_confirmed`,
 * letting site owners build no-code recipes such as "when a booking is
 * confirmed, send an email / call a webhook / program a smart lock".
 *
 * @package CommonsBooking
 */

namespace CommonsBooking\Integrations\Automator;

defined( 'ABSPATH' ) || die( 'Thanks for visiting' );

/**
 * Anonymous trigger bound to the booking-confirmed lifecycle action.
 */
class Booking_Confirmed_Trigger extends \Uncanny_Automator\Recipe\Trigger {

	use Booking_Tokens;

	/**
	 * Configures the trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// Anonymous so the recipe runs regardless of who confirmed the booking
		// (frontend visitor, admin, or a cron job).
		$this->set_trigger_type( 'anonymous' );

		$this->set_integration( 'COMMONSBOOKING' );
		$this->set_trigger_code( 'CB_BOOKING_CONFIRMED' );
		$this->set_trigger_meta( 'CBBOOKINGCONFIRMED' );
		$this->set_sentence( esc_attr__( 'A booking is confirmed', 'commonsbooking' ) );
		$this->set_readable_sentence( esc_attr__( 'A booking is confirmed', 'commonsbooking' ) );

		// The CommonsBooking lifecycle action passes ( int $booking_id, Booking $booking ).
		$this->add_action( 'commonsbooking_booking_confirmed', 10, 2 );
	}

	/**
	 * This trigger has no configurable options; it fires for every confirmed booking.
	 *
	 * @return array
	 */
	public function options() {
		return array();
	}

	/**
	 * The trigger fires for every confirmed booking.
	 *
	 * @param array $trigger   The trigger configuration.
	 * @param array $hook_args The arguments passed by the WordPress hook.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		return true;
	}

	// define_tokens() and hydrate_tokens() are provided by the Booking_Tokens trait.
}
