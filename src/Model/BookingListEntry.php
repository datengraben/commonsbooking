<?php

namespace CommonsBooking\Model;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * One row of the booking list (@see \CommonsBooking\View\Booking::getBookingListData()), pairing
 * the typed Booking model with its rendered row data.
 *
 * Implements ArrayAccess/IteratorAggregate/Countable so that existing commonsbooking_booking_filter
 * callbacks written against the old plain assoc array (e.g. $rowData['postID'], foreach/count over
 * it) keep working unchanged against this object. Callbacks that pass the row into a function that
 * requires a literal array (array_diff_key, preg_grep, etc.) need to call toArray() first, since
 * PHP's array_* functions don't accept ArrayAccess objects.
 *
 * This object is only reduced to a plain array at the webservice boundary, via JsonSerializable,
 * when wp_json_encode() serializes the AJAX response in Booking::getTemplateData(). Everywhere else
 * it stays typed.
 */
class BookingListEntry implements ArrayAccess, IteratorAggregate, Countable, JsonSerializable {

	public Booking $booking;

	private array $rowData;

	public function __construct( Booking $booking, array $rowData ) {
		$this->booking = $booking;
		$this->rowData = $rowData;
	}

	/**
	 * Returns the row data as a plain array. Use this when passing the row into code that requires
	 * a literal array (e.g. array_diff_key(), preg_grep()).
	 *
	 * @return array
	 */
	public function toArray(): array {
		return $this->rowData;
	}

	public function offsetExists( $offset ): bool {
		return array_key_exists( $offset, $this->rowData );
	}

	public function offsetGet( $offset ): mixed {
		return $this->rowData[ $offset ] ?? null;
	}

	public function offsetSet( $offset, $value ): void {
		if ( $offset === null ) {
			$this->rowData[] = $value;
		} else {
			$this->rowData[ $offset ] = $value;
		}
	}

	public function offsetUnset( $offset ): void {
		unset( $this->rowData[ $offset ] );
	}

	public function getIterator(): Traversable {
		return new ArrayIterator( $this->rowData );
	}

	public function count(): int {
		return count( $this->rowData );
	}

	/**
	 * Called by wp_json_encode()/json_encode() at the webservice boundary. Not meant to be called
	 * directly elsewhere -- use toArray() for that.
	 *
	 * @return array
	 */
	public function jsonSerialize(): array {
		return $this->rowData;
	}
}
