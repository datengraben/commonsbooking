<?php

namespace CommonsBooking\Wordpress\Shortcode;

use CommonsBooking\Service\BookingStats as BookingStatsService;

/**
 * Shortcode [cb_booking_stats] — displays a booking statistics snippet.
 *
 * Attributes:
 *   period   = day | week | month | year   (default: week)
 *   compare  = true | false                (default: true)
 *   item     = <WP post ID> | all          (default: all)
 *   location = <WP post ID> | all          (default: all)
 *   show     = count | days | both         (default: count)
 *
 * Examples:
 *   [cb_booking_stats]
 *   [cb_booking_stats period="week" compare="true"]
 *   [cb_booking_stats period="month" compare="true"]
 *   [cb_booking_stats period="year" compare="true"]
 *   [cb_booking_stats period="week" item="42"]
 *   [cb_booking_stats period="month" location="17"]
 *   [cb_booking_stats period="week" show="both"]
 *   [cb_booking_stats period="year" compare="false"]
 */
class BookingStats {

	/**
	 * Shortcode render callback.
	 *
	 * @param array|string $atts Shortcode attributes.
	 *
	 * @return string HTML output.
	 */
	public static function render( $atts ): string {
		$atts = shortcode_atts(
			[
				'period'   => 'week',
				'compare'  => 'true',
				'item'     => 'all',
				'location' => 'all',
				'show'     => 'count',
			],
			$atts,
			'cb_booking_stats'
		);

		$period    = in_array( $atts['period'], [ 'day', 'week', 'month', 'year' ], true ) ? $atts['period'] : 'week';
		$compare   = filter_var( $atts['compare'], FILTER_VALIDATE_BOOLEAN );
		$itemId    = ( $atts['item'] !== 'all' && is_numeric( $atts['item'] ) ) ? (int) $atts['item'] : null;
		$locationId = ( $atts['location'] !== 'all' && is_numeric( $atts['location'] ) ) ? (int) $atts['location'] : null;
		$show      = in_array( $atts['show'], [ 'count', 'days', 'both' ], true ) ? $atts['show'] : 'count';

		$stats = BookingStatsService::getPeriodStats( $period, $itemId, $locationId );

		return self::buildHtml( $period, $compare, $show, $stats, $itemId, $locationId );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	private static function buildHtml(
		string $period,
		bool $compare,
		string $show,
		array $stats,
		?int $itemId,
		?int $locationId
	): string {
		$count    = $stats['current']['count'];
		$days     = $stats['current']['days'];
		$diff     = $stats['diff_count'];
		$diffPct  = $stats['diff_pct'];

		$periodLabel   = self::periodLabel( $period );
		$compareLabel  = self::comparePeriodLabel( $period );

		// Build the entity context label (e.g. "for Cargo Bike Berlin").
		$contextLabel = self::entityLabel( $itemId, $locationId );

		// Main value string.
		$valueStr = self::valueString( $count, $days, $show );

		// Build the intro phrase.
		$intro = sprintf(
			/* translators: 1: period label, 2: entity context (may be empty) */
			_x( 'Bookings %1$s%2$s', 'stat shortcode intro', 'commonsbooking' ),
			$periodLabel,
			$contextLabel ? ( ' ' . $contextLabel ) : ''
		);

		$html = '<span class="cb-booking-stats">'
			. '<strong>' . esc_html( $intro ) . ':</strong> '
			. '<span class="cb-stat-value">' . esc_html( $valueStr ) . '</span>';

		if ( $compare ) {
			$html .= ' ' . self::diffBadge( $diff, $diffPct, $compareLabel );
		}

		$html .= '</span>';

		return $html;
	}

	private static function valueString( int $count, int $days, string $show ): string {
		if ( $show === 'days' ) {
			/* translators: number of booking days */
			return sprintf( _n( '%d day', '%d days', $days, 'commonsbooking' ), $days );
		}
		if ( $show === 'both' ) {
			return sprintf(
				/* translators: 1: booking count, 2: days */
				_x( '%1$d bookings / %2$d days', 'stat shortcode value', 'commonsbooking' ),
				$count,
				$days
			);
		}
		// default: count
		return (string) $count;
	}

	private static function diffBadge( int $diff, ?float $diffPct, string $compareLabel ): string {
		if ( $diff === 0 ) {
			$symbol = '=';
			$color  = '#888';
		} elseif ( $diff > 0 ) {
			$symbol = '&#9650;';
			$color  = '#67b32a';
		} else {
			$symbol = '&#9660;';
			$color  = '#c0392b';
		}

		$pctText = $diffPct !== null ? ' (' . abs( $diffPct ) . '%)' : '';

		return sprintf(
			'<span class="cb-stat-diff" style="color:%s;">(%s&nbsp;%s%s %s %s)</span>',
			esc_attr( $color ),
			$symbol,
			esc_html( (string) abs( $diff ) ),
			esc_html( $pctText ),
			esc_html__( 'vs', 'commonsbooking' ),
			esc_html( $compareLabel )
		);
	}

	private static function periodLabel( string $period ): string {
		$labels = [
			'day'   => __( 'today', 'commonsbooking' ),
			'week'  => __( 'this week', 'commonsbooking' ),
			'month' => __( 'this month', 'commonsbooking' ),
			'year'  => __( 'this year', 'commonsbooking' ),
		];
		return $labels[ $period ] ?? $period;
	}

	private static function comparePeriodLabel( string $period ): string {
		$labels = [
			'day'   => __( 'yesterday', 'commonsbooking' ),
			'week'  => __( 'last week', 'commonsbooking' ),
			'month' => __( 'last month', 'commonsbooking' ),
			'year'  => __( 'last year', 'commonsbooking' ),
		];
		return $labels[ $period ] ?? $period;
	}

	private static function entityLabel( ?int $itemId, ?int $locationId ): string {
		if ( $itemId ) {
			$post = get_post( $itemId );
			if ( $post ) {
				/* translators: item name */
				return sprintf( __( 'for %s', 'commonsbooking' ), $post->post_title );
			}
		}
		if ( $locationId ) {
			$post = get_post( $locationId );
			if ( $post ) {
				/* translators: location name */
				return sprintf( __( 'at %s', 'commonsbooking' ), $post->post_title );
			}
		}
		return '';
	}
}
