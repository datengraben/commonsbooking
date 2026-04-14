<?php

namespace CommonsBooking\Service;

class MassOperations {

	public static function ajaxMigrateOrphaned() {
		check_ajax_referer( 'cb_orphaned_booking_migration', 'nonce' );

		if ( isset( $_POST['data'] ) && 'false' === $_POST['data'] ) {
			$post_data = 'false';
		} else {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array is sanitized by commonsbooking_sanitizeArrayorString() on the next line.
			$post_data = isset( $_POST['data'] ) ? (array) wp_unslash( $_POST['data'] ) : array();
			$post_data = commonsbooking_sanitizeArrayorString( $post_data );
			$post_data = array_map( 'intval', $post_data );
		}

		$success = false;
		try {
			$success = self::migrateOrphaned( $post_data );
		} catch ( \Exception $e ) {
			$errorMessage = $e->getMessage();
		}

		if ( $success ) {
			$result = array(
				'success' => true,
				'message' => __( 'All selected orphaned bookings have been migrated.', 'commonsbooking' ),
			);
		} else {
			$result = array(
				'success' => false,
				'message' => ! empty( $errorMessage ) ? $errorMessage : __( 'An error occurred while moving bookings.', 'commonsbooking' ),
			);
		}

		wp_send_json( $result );
	}

	/**
	 * Will migrate an array of booking IDs to a new location
	 * The new location is determined by the location of the previous timeframe the booking was in
	 * If no timeframe is found, the booking will be skipped
	 *
	 * @param int[] $bookingIds
	 *
	 * @return true
	 * @throws \Exception
	 */
	public static function migrateOrphaned( array $bookingIds ): bool {
		if ( empty( $bookingIds ) ) {
			throw new \Exception( __( 'No bookings to move selected.', 'commonsbooking' ) );  // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages come from internal code, not user input.
		}

		$orphanedBookings = \CommonsBooking\Repository\Booking::getOrphaned();
		// iterate over them and assign them new locations
		foreach ( $orphanedBookings as $booking ) {
			if ( ! in_array( $booking->ID, $bookingIds ) ) {
				continue;
			}
			try {
				$moveLocation = $booking->getMoveableLocation();
				if ( $moveLocation === null ) {
					// translators: %s numeric post id
					throw new \Exception( sprintf( __( 'New location not found for booking with ID %s', 'commonsbooking' ), $booking->ID ) );
				}
			} catch ( \Exception $e ) {
				// translators: %s numeric post id
				throw new \Exception( sprintf( __( 'New location not found for booking with ID %s', 'commonsbooking' ), $booking->ID ) );  // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages come from internal code, not user input.
			}
			if ( \CommonsBooking\Repository\Booking::getExistingBookings( $booking->getItemID(), $moveLocation->ID, $booking->getStartDate(), $booking->getEndDate() ) ) {
				// translators: %s numeric post id
				throw new \Exception( sprintf( __( 'There is already a booking on the new location during the timeframe of booking with ID %s.', 'commonsbooking' ), $booking->ID ) );  // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages come from internal code, not user input.
			}
			update_post_meta( $booking->ID, 'location-id', $moveLocation->ID );
		}

		return true;
	}
}
