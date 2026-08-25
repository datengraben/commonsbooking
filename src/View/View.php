<?php


namespace CommonsBooking\View;

use CommonsBooking\Model\Timeframe;
use CommonsBooking\Settings\Settings;
use Exception;

/**
 * Serves as abstraction for view rendering of different models and custom post types.
 *
 * Important design decision/assumption: Because there can be multiple timeframes with different configurations,
 * which make rendering the presentation of them (e.g. in calendar view) more complicated. We just stick to the first
 * timeframe when rendering a view.
 */
abstract class View {

	/**
	 * List of allowed query params for shortcodes.
	 * All other query params will be ignored.
	 *
	 * @var string[]
	 */
	protected static $allowedShortCodeArgs = array(
		'p'              => '', // post id
		// Author: https://developer.wordpress.org/reference/classes/wp_query/#author-parameters
		'author'         => '',
		'author_name'    => '',
		// Category: https://developer.wordpress.org/reference/classes/wp_query/#category-parameters
		'cat'            => '',
		'category_name'  => '',
		'category_slug'  => '',
		// Tag: https://developer.wordpress.org/reference/classes/wp_query/#tag-parameters
		'tag'            => '',
		'tag_id'         => '',
		// Status https://developer.wordpress.org/reference/classes/wp_query/#status-parameters
		'post_status'    => '',
		// Pagination: https://developer.wordpress.org/reference/classes/wp_query/#pagination-parameters
		'posts_per_page' => '',
		'nopaging'       => '',
		'offset'         => '',
		// Order: https://developer.wordpress.org/reference/classes/wp_query/#order-orderby-parameters
		'order'          => '',
		'orderby'        => '',
	);

	/**
	 * Will generate the shortcode view for only one given item or location.
	 * This includes the availability timeframe, see assumptions in class docstring for more details.
	 *
	 * @param \CommonsBooking\Model\Item|\CommonsBooking\Model\Location $cpt location or item model object to retrieve timeframe data from.
	 * @param string                                                    $type 'Item' or 'Location'.
	 * @return array
	 * @throws Exception
	 */
	public static function getShortcodeData( $cpt, string $type ): array {
		$cptData    = [];
		$timeframes = $cpt->getBookableTimeframes( true );

		// sort by start date, to get latest possible booking date by first timeframe
		usort(
			$timeframes,
			function ( $a, $b ) {
				return $a->getStartDate() <=> $b->getStartDate();
			}
		);
		$latestPossibleBookingDate = false;

		/** @var Timeframe $timeframe */
		foreach ( $timeframes as $timeframe ) {
			if ( ! $timeframe->getStartDate() ) {
				continue;
			}

			// We only fetch the latest possible booking date from the first timeframe.
			// This is ok, because the timeframes are sorted by their start date.
			if ( ! $latestPossibleBookingDate ) {
				$latestPossibleBookingDate = $timeframe->getLatestPossibleBookingDateTimestamp();
			}

			// If start date is after latest possible booking date, we leave range out
			$endOfStartDay = strtotime( '+1 day midnight', $timeframe->getStartDate() ) - 1;
			if ( $endOfStartDay > $latestPossibleBookingDate ) {
				continue;
			}

			$item = $timeframe->{'get' . $type}();

			// We need only published items
			if ( ! $item || $item->post_status !== 'publish' ) {
				continue;
			}

			// Init Ranges array for new item in array
			if ( ! array_key_exists( $item->ID, $cptData ) ) {
				$cptData[ $item->ID ] = [
					'ranges' => [
						[
							'start_date' => $timeframe->getStartDate(),
							'end_date'   => $timeframe->getEndDate(),
						],

					],
				];
			} else {
				$addRange           = true;
				$timeframeStartDate = $timeframe->getStartDate();
				$timeframeEndDate   = $timeframe->getEndDate();

				foreach ( $cptData[ $item->ID ]['ranges'] as $key => $range ) {
					// Check if Timeframe overlaps or differs max. 1 day with existing one.
					$overlaps =
						// Startdate is in range
						(
							$timeframeStartDate >= ( $range['start_date'] - 86400 ) &&
							$timeframeStartDate <= ( $range['end_date'] + 86400 )
						) ||
						// Enddate is in range
						(
							$timeframeEndDate >= ( $range['start_date'] - 86400 ) &&
							$timeframeEndDate <= ( $range['end_date'] + 86400 )
						) ||
						// Range and Timeframe have no enddate -> must overlap
						(
							$range['end_date'] == false && $timeframeEndDate == false
						);

					// If timeframe overlaps, check if we need to extend existing one.
					if ( $overlaps ) {
						$addRange = false;

						if (
							$range['start_date'] > $timeframeStartDate
						) {
							$cptData[ $item->ID ]['ranges'][ $key ]['start_date'] = $timeframeStartDate;
						}

						if (
							! $range['end_date'] ||
							$range['end_date'] < $timeframeStartDate
						) {
							$cptData[ $item->ID ]['ranges'][ $key ]['end_date'] = $timeframeEndDate;
						}
					}
				}

				// Only add new range if it's not starting after a repeating timeframe without an enddate
				if ( $addRange ) {
					$cptData[ $item->ID ]['ranges'][] = [
						'start_date' => $timeframeStartDate,
						'end_date'   => $timeframeEndDate,
					];
				}
			}

			// Remove duplicate ranges
			$cptData[ $item->ID ]['ranges'] = array_unique( $cptData[ $item->ID ]['ranges'], SORT_REGULAR );

			// sort ranges by starting date
			usort(
				$cptData[ $item->ID ]['ranges'],
				function ( $a, $b ) {
					return $a['start_date'] <=> $b['start_date'];
				}
			);
		}

		return $cptData;
	}

	/**
	 * Transient key under which the compiled color scheme CSS is cached.
	 */
	public const COLOR_CSS_TRANSIENT = 'commonsbooking_colorscheme_css';

	/**
	 * Returns the user defined color scheme as CSS.
	 *
	 * The color scheme only changes when the template options are saved
	 * (see OptionsTab::savePostOptions(), which drops the cache), so the compiled result is
	 * cached in a transient instead of being recompiled on every front-end request. The cache
	 * is bypassed while WP_DEBUG is on, matching the asset cache-busting in commonsbooking_public().
	 *
	 * @return string|false
	 */
	public static function getColorCSS() {
		if ( ! WP_DEBUG ) {
			$cached = get_transient( self::COLOR_CSS_TRANSIENT );
			if ( $cached !== false ) {
				// An empty string is the cached sentinel for "no custom color scheme".
				return $cached === '' ? false : $cached;
			}
		}

		$css = self::compileColorCSS();

		if ( ! WP_DEBUG ) {
			set_transient( self::COLOR_CSS_TRANSIENT, $css === false ? '' : $css );
		}

		return $css;
	}

	/**
	 * Builds the user defined color scheme as a `:root` block of CSS custom properties.
	 *
	 * The base stylesheet already consumes these values through var(--commonsbooking-color-*),
	 * so the color scheme is nothing more than a set of custom-property overrides. It is emitted
	 * directly in PHP instead of running the SCSSPHP compiler at runtime. Only the properties the
	 * user controls are written; the fixed values (font sizes, spacers, radius, …) stay in the
	 * built public.css. The derived properties (error, success, gray-background) mirror the aliases
	 * in assets/global/sass/partials/_variables.scss, which remains the source of truth for the build.
	 *
	 * @return string|false
	 */
	private static function compileColorCSS() {
		// Template color options mapped to the CSS custom properties they define.
		$optionProperties = [
			'colorscheme_primarycolor'          => [ '--commonsbooking-color-primary' ],
			'colorscheme_secondarycolor'        => [ '--commonsbooking-color-secondary' ],
			'colorscheme_buttoncolor'           => [ '--commonsbooking-color-buttons' ],
			'colorscheme_acceptcolor'           => [ '--commonsbooking-color-accept', '--commonsbooking-color-success' ],
			'colorscheme_cancelcolor'           => [ '--commonsbooking-color-cancel', '--commonsbooking-color-error' ],
			'colorscheme_holidaycolor'          => [ '--commonsbooking-color-holiday' ],
			'colorscheme_greyedoutcolor'        => [ '--commonsbooking-color-greyedout' ],
			'colorscheme_backgroundcolor'       => [ '--commonsbooking-color-bg', '--commonsbooking-color-gray-background' ],
			'colorscheme_noticebackgroundcolor' => [ '--commonsbooking-color-noticebg' ],
			'colorscheme_lighttext'             => [ '--commonsbooking-textcolor-light' ],
			'colorscheme_darktext'              => [ '--commonsbooking-textcolor-dark' ],
		];

		$declarations = [];
		foreach ( $optionProperties as $option => $properties ) {
			$value = self::sanitizeCssColor( Settings::getOption( 'commonsbooking_options_templates', $option ) );
			if ( $value === null ) {
				return false; // do not return CSS unless every color is set to a valid value
			}
			foreach ( $properties as $property ) {
				$declarations[] = "\t$property: $value;";
			}
		}

		return ":root {\n" . implode( "\n", $declarations ) . "\n}\n";
	}

	/**
	 * Validates a user supplied color for safe inline CSS output.
	 *
	 * Accepts hex colors (as produced by the CMB2 color picker, with optional alpha) and the
	 * rgb()/rgba()/hsl()/hsla() and named-color forms. Returns null for empty or unrecognized
	 * values, both so the caller can bail out when a color is unset and to prevent CSS injection
	 * through a stored option value.
	 *
	 * @param mixed $value
	 *
	 * @return string|null
	 */
	private static function sanitizeCssColor( $value ) {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = trim( $value );
		if ( $value === '' ) {
			return null;
		}

		// Hex: #rgb, #rgba, #rrggbb, #rrggbbaa
		if ( preg_match( '/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value ) ) {
			return $value;
		}

		// Functional notation with only numeric/percentage components.
		if ( preg_match( '/^(?:rgb|rgba|hsl|hsla)\(\s*[0-9.,%\s\/]+\)$/i', $value ) ) {
			return $value;
		}

		// Plain CSS named color keyword.
		if ( preg_match( '/^[a-zA-Z]+$/', $value ) ) {
			return $value;
		}

		return null;
	}
}
