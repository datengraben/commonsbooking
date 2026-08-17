<?php

namespace CommonsBooking\View;

use CommonsBooking\Service\BookingRecommendation;
use Exception;

/**
 * Frontend rendering for the "Book again" / "Did you try …" recommendations.
 *
 * Registered as the [cb_book_again] shortcode. The shortcode only outputs
 * something for logged-in users and stays silent when there is nothing to
 * suggest.
 */
class BookAgain extends View {

	/**
	 * Default shortcode attributes.
	 *
	 * @var array
	 */
	public static $allowedShortCodeArgs = [
		'window'     => BookingRecommendation::WINDOW_WEEK,
		'max'        => 6,
		'book-again' => 'yes',
		'similar'    => 'yes',
	];

	/**
	 * Renders the [cb_book_again] shortcode.
	 *
	 * @param array|string $atts
	 *
	 * @return string
	 * @throws Exception
	 */
	public static function shortcode( $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$args = shortcode_atts(
			static::$allowedShortCodeArgs,
			$atts,
			'cb_book_again'
		);

		$user   = wp_get_current_user();
		$window = in_array( $args['window'], BookingRecommendation::getWindows(), true )
			? $args['window']
			: BookingRecommendation::WINDOW_WEEK;
		$max    = max( 1, intval( $args['max'] ) );

		$bookAgain = ( $args['book-again'] !== 'no' )
			? BookingRecommendation::getBookAgainSuggestions( $user, $window, $max )
			: [];

		$similar = ( $args['similar'] !== 'no' )
			? BookingRecommendation::getSimilarSuggestions( $user, $window, $max )
			: [];

		if ( empty( $bookAgain ) && empty( $similar ) ) {
			return '';
		}

		global $templateData;
		$templateData = [
			'bookAgain' => $bookAgain,
			'similar'   => $similar,
			'window'    => $window,
		];

		ob_start();
		commonsbooking_get_template_part( 'shortcode', 'book-again', true, false, false );

		return (string) ob_get_clean();
	}

	/**
	 * Returns a human readable label for the free-availability window.
	 *
	 * @param string $window
	 *
	 * @return string
	 */
	public static function getWindowLabel( string $window ): string {
		switch ( $window ) {
			case BookingRecommendation::WINDOW_TODAY:
				return __( 'free today', 'commonsbooking' );
			case BookingRecommendation::WINDOW_TOMORROW:
				return __( 'free tomorrow', 'commonsbooking' );
			case BookingRecommendation::WINDOW_WEEK:
			default:
				return __( 'free within the next 7 days', 'commonsbooking' );
		}
	}
}
