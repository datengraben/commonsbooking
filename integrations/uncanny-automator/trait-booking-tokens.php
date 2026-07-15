<?php
/**
 * Shared token definitions for CommonsBooking Automator triggers.
 *
 * All booking lifecycle triggers (confirmed, cancelled, …) expose the same set
 * of booking tokens, so the definition and hydration live here to avoid
 * duplication.
 *
 * @package CommonsBooking
 */

namespace CommonsBooking\Integrations\Automator;

defined( 'ABSPATH' ) || die( 'Thanks for visiting' );

/**
 * Provides define_tokens()/hydrate_tokens() for booking triggers.
 */
trait Booking_Tokens {

	/**
	 * Declares the booking tokens made available to later recipe actions.
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
			'tokenId'   => 'CB_BOOKING_USER_PHONE',
			'tokenName' => __( 'Booking user phone', 'commonsbooking' ),
			'tokenType' => 'text',
		);
		$tokens[] = array(
			'tokenId'   => 'CB_BOOKING_URL',
			'tokenName' => __( 'Booking URL', 'commonsbooking' ),
			'tokenType' => 'url',
		);

		return $tokens;
	}

	/**
	 * Populates the token values from the booking passed by the lifecycle hook.
	 *
	 * @param array $trigger   The trigger configuration.
	 * @param array $hook_args The arguments passed by the lifecycle action ( int $booking_id, Booking $booking ).
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

		$item      = $booking->getItem();
		$location  = $booking->getLocation();
		$author_id = (int) $booking->post_author;
		$user      = get_userdata( $author_id );

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
			'CB_BOOKING_USER_PHONE' => $author_id ? (string) get_user_meta( $author_id, 'phone', true ) : '',
			'CB_BOOKING_URL'        => $booking->bookingLinkUrl(),
		);
	}
}
