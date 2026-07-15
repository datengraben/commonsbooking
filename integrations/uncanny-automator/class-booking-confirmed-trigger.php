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

	/**
	 * Declares the tokens made available to later recipe actions.
	 *
	 * @param array $trigger The trigger configuration.
	 * @param array $tokens  The existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$tokens[] = array(
			'tokenId'   => 'CB_BOOKING_ID',
			'tokenName' => __( 'Booking ID', 'commonsbooking' ),
			'tokenType' => 'int',
		);
		$tokens[] = array(
			'tokenId'   => 'CB_ITEM_NAME',
			'tokenName' => __( 'Item name', 'commonsbooking' ),
			'tokenType' => 'text',
		);
		$tokens[] = array(
			'tokenId'   => 'CB_LOCATION_NAME',
			'tokenName' => __( 'Location name', 'commonsbooking' ),
			'tokenType' => 'text',
		);
		$tokens[] = array(
			'tokenId'   => 'CB_BOOKING_START',
			'tokenName' => __( 'Booking start', 'commonsbooking' ),
			'tokenType' => 'text',
		);
		$tokens[] = array(
			'tokenId'   => 'CB_BOOKING_END',
			'tokenName' => __( 'Booking end', 'commonsbooking' ),
			'tokenType' => 'text',
		);
		$tokens[] = array(
			'tokenId'   => 'CB_BOOKING_CODE',
			'tokenName' => __( 'Booking code', 'commonsbooking' ),
			'tokenType' => 'text',
		);
		$tokens[] = array(
			'tokenId'   => 'CB_BOOKING_USER_EMAIL',
			'tokenName' => __( 'Booking user email', 'commonsbooking' ),
			'tokenType' => 'email',
		);
		$tokens[] = array(
			'tokenId'   => 'CB_BOOKING_URL',
			'tokenName' => __( 'Booking URL', 'commonsbooking' ),
			'tokenType' => 'url',
		);

		return $tokens;
	}

	/**
	 * Populates the token values from the confirmed booking.
	 *
	 * @param array $trigger   The trigger configuration.
	 * @param array $hook_args The arguments passed by `commonsbooking_booking_confirmed`.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$booking_id = isset( $hook_args[0] ) ? (int) $hook_args[0] : 0;
		$booking    = isset( $hook_args[1] ) ? $hook_args[1] : null;

		if ( ! $booking instanceof \CommonsBooking\Model\Booking ) {
			$booking = $booking_id ? new \CommonsBooking\Model\Booking( $booking_id ) : null;
		}

		if ( ! $booking ) {
			return array( 'CB_BOOKING_ID' => $booking_id );
		}

		$item     = $booking->getItem();
		$location = $booking->getLocation();
		$user     = get_userdata( (int) $booking->post_author );

		// The booking code is optional and may throw if unavailable.
		$code = '';
		try {
			$code = $booking->formattedBookingCode();
		} catch ( \Exception $e ) {
			$code = '';
		}

		return array(
			'CB_BOOKING_ID'         => $booking_id,
			'CB_ITEM_NAME'          => $item ? $item->post_title : '',
			'CB_LOCATION_NAME'      => $location ? $location->post_title : '',
			'CB_BOOKING_START'      => $booking->getFormattedStartDate(),
			'CB_BOOKING_END'        => $booking->getFormattedEndDate(),
			'CB_BOOKING_CODE'       => $code,
			'CB_BOOKING_USER_EMAIL' => $user ? $user->user_email : '',
			'CB_BOOKING_URL'        => $booking->bookingLinkUrl(),
		);
	}
}
