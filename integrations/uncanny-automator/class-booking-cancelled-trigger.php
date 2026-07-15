<?php
/**
 * "A booking is cancelled" trigger for Uncanny Automator.
 *
 * Fires whenever CommonsBooking emits `commonsbooking_booking_cancelled`, so
 * site owners can route cancellations to an SMS (Twilio), Slack, Telegram or
 * other messaging gateway — e.g. alert the volunteer team that an item has
 * just freed up, or notify a waitlist.
 *
 * @package CommonsBooking
 */

namespace CommonsBooking\Integrations\Automator;

defined( 'ABSPATH' ) || die( 'Thanks for visiting' );

/**
 * Anonymous trigger bound to the booking-cancelled lifecycle action.
 */
class Booking_Cancelled_Trigger extends \Uncanny_Automator\Recipe\Trigger {

	use Booking_Tokens;

	/**
	 * Configures the trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// Anonymous so the recipe runs regardless of who cancelled the booking
		// (the booking owner, an admin, or a cron job).
		$this->set_trigger_type( 'anonymous' );

		$this->set_integration( 'COMMONSBOOKING' );
		$this->set_trigger_code( 'CB_BOOKING_CANCELLED' );
		$this->set_trigger_meta( 'CBBOOKINGCANCELLED' );
		$this->set_sentence( esc_attr__( 'A booking is cancelled', 'commonsbooking' ) );
		$this->set_readable_sentence( esc_attr__( 'A booking is cancelled', 'commonsbooking' ) );

		// The CommonsBooking lifecycle action passes ( int $booking_id, Booking $booking ).
		$this->add_action( 'commonsbooking_booking_cancelled', 10, 2 );
	}

	/**
	 * This trigger has no configurable options; it fires for every cancelled booking.
	 *
	 * @return array
	 */
	public function options() {
		return array();
	}

	/**
	 * The trigger fires for every cancelled booking.
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
