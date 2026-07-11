<?php

namespace CommonsBooking\Model;

/**
 * Pairs a Booking model with the row data rendered for it in the booking list view
 * (@see \CommonsBooking\View\Booking::getBookingListData()).
 *
 * Keeps a typed reference to the originating Booking so that internal consumers (e.g. iCalendar
 * generation) can use the model directly instead of re-fetching it by ID from the row data array,
 * which is only meant to be exposed as-is at the webservice boundary (the AJAX JSON response).
 */
class BookingListEntry {

	public Booking $booking;

	public array $rowData;

	public function __construct( Booking $booking, array $rowData ) {
		$this->booking = $booking;
		$this->rowData = $rowData;
	}

	/**
	 * Returns the row data as exposed at the webservice boundary (AJAX JSON response).
	 *
	 * @return array
	 */
	public function toArray(): array {
		return $this->rowData;
	}
}
