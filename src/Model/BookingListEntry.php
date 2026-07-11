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
 * The known/fixed row fields (see KNOWN_FIELD_DEFAULTS) are real typed properties, so internal code
 * can use e.g. $entry->postID directly. commonsbooking_booking_filter callbacks are free to add
 * arbitrary extra keys though (that's part of the hook's contract), so anything outside the known
 * schema is kept in an untyped overflow bag instead of being rejected.
 *
 * Implements ArrayAccess/IteratorAggregate/Countable so that existing filter callbacks written
 * against the old plain assoc array (e.g. $rowData['postID'], foreach/count over it) keep working
 * unchanged against this object. Callbacks that pass the row into a function that requires a literal
 * array (array_diff_key, preg_grep, etc.) need to call toArray() first, since PHP's array_*
 * functions don't accept ArrayAccess objects.
 *
 * This object is only reduced to a plain array at the webservice boundary, via JsonSerializable,
 * when wp_json_encode() serializes the AJAX response in Booking::getTemplateData(). Everywhere else
 * it stays typed.
 */
class BookingListEntry implements ArrayAccess, IteratorAggregate, Countable, JsonSerializable {

	public Booking $booking;

	public int $postID;

	public int $startDate;

	/**
	 * @var int|false Mirrors \CommonsBooking\Model\Booking::getEndDate(), which can return false.
	 */
	public $endDate;

	public string $startDateFormatted;

	public string $endDateFormatted;

	public string $item;

	public string $location;

	public string $locationAddr;

	public mixed $locationLat;

	public mixed $locationLong;

	public string $bookingDate;

	public string $user;

	public string $status;

	public mixed $fullDay;

	public string $calendarLink;

	public array $content;

	public ?array $bookingCode;

	public string $actions;

	/**
	 * Overflow bag for keys outside the known schema above -- e.g. custom fields added by a
	 * commonsbooking_booking_filter callback. Kept untyped on purpose, since the hook explicitly
	 * allows arbitrary extension.
	 *
	 * @var array
	 */
	private array $extra = [];

	/**
	 * Default values for the known/fixed row fields, also used as the authoritative list of which
	 * keys are known (as opposed to landing in the untyped overflow bag).
	 */
	private const KNOWN_FIELD_DEFAULTS = [
		'postID'             => 0,
		'startDate'          => 0,
		'endDate'            => 0,
		'startDateFormatted' => '',
		'endDateFormatted'   => '',
		'item'               => '',
		'location'           => '',
		'locationAddr'       => '',
		'locationLat'        => 0,
		'locationLong'       => 0,
		'bookingDate'        => '',
		'user'               => '',
		'status'             => '',
		'fullDay'            => null,
		'calendarLink'       => '',
		'content'            => [],
		'bookingCode'        => null,
		'actions'            => '',
	];

	public function __construct( Booking $booking, array $rowData ) {
		$this->booking = $booking;
		foreach ( self::KNOWN_FIELD_DEFAULTS as $field => $default ) {
			$this->$field = $default;
		}
		foreach ( $rowData as $key => $value ) {
			$this->offsetSet( $key, $value );
		}
	}

	/**
	 * Returns the row data as a plain array. Use this when passing the row into code that requires
	 * a literal array (e.g. array_diff_key(), preg_grep()).
	 *
	 * NOTE: 'bookingCode' is omitted entirely (not included as null) when a booking has no code,
	 * matching the original conditional array_key_exists()/isset() behavior for that key.
	 *
	 * @return array
	 */
	public function toArray(): array {
		$data = [];
		foreach ( array_keys( self::KNOWN_FIELD_DEFAULTS ) as $field ) {
			if ( $field === 'bookingCode' && $this->bookingCode === null ) {
				continue;
			}
			$data[ $field ] = $this->$field;
		}

		return array_merge( $data, $this->extra );
	}

	public function offsetExists( $offset ): bool {
		if ( array_key_exists( $offset, self::KNOWN_FIELD_DEFAULTS ) ) {
			return $this->$offset !== null;
		}

		return array_key_exists( $offset, $this->extra );
	}

	public function offsetGet( $offset ): mixed {
		if ( array_key_exists( $offset, self::KNOWN_FIELD_DEFAULTS ) ) {
			return $this->$offset;
		}

		return $this->extra[ $offset ] ?? null;
	}

	public function offsetSet( $offset, $value ): void {
		if ( $offset === null ) {
			$this->extra[] = $value;
			return;
		}

		if ( array_key_exists( $offset, self::KNOWN_FIELD_DEFAULTS ) ) {
			$this->$offset = $value;
			return;
		}

		$this->extra[ $offset ] = $value;
	}

	public function offsetUnset( $offset ): void {
		if ( array_key_exists( $offset, self::KNOWN_FIELD_DEFAULTS ) ) {
			// Known fields are real typed properties and structurally always present; "unsetting"
			// one resets it to its schema default rather than truly removing it.
			$this->$offset = self::KNOWN_FIELD_DEFAULTS[ $offset ];
			return;
		}

		unset( $this->extra[ $offset ] );
	}

	public function getIterator(): Traversable {
		return new ArrayIterator( $this->toArray() );
	}

	public function count(): int {
		return count( $this->toArray() );
	}

	/**
	 * Called by wp_json_encode()/json_encode() at the webservice boundary. Not meant to be called
	 * directly elsewhere -- use toArray() for that.
	 *
	 * @return array
	 */
	public function jsonSerialize(): array {
		return $this->toArray();
	}
}
