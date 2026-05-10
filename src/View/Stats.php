<?php

namespace CommonsBooking\View;

use CommonsBooking\Repository\BookingStats as BookingStatsRepo;
use CommonsBooking\Service\BookingStats as BookingStatsService;

/**
 * Admin view for booking statistics.
 * Renders the stats page at CommonsBooking → Statistics.
 */
class Stats extends View {

	public static function index(): void {
		ob_start();
		commonsbooking_sanitizeHTML( commonsbooking_get_template_part( 'stats', 'index' ) );
		echo ob_get_clean();
	}

	/**
	 * Handle the "Recompute Statistics" admin POST action.
	 */
	public static function handleRecompute(): void {
		if ( ! current_user_can( 'manage_' . COMMONSBOOKING_PLUGIN_SLUG ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'commonsbooking' ) );
		}
		check_admin_referer( 'cb_recompute_stats' );

		BookingStatsRepo::recomputeAll();

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => 'cb-stats', 'recomputed' => '1' ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Build all four stat card data sets, optionally filtered by item or location.
	 *
	 * Called from the template.
	 *
	 * @return array{day: array, week: array, month: array, year: array}
	 */
	public static function getStatCards( ?int $itemId, ?int $locationId ): array {
		return [
			'day'   => BookingStatsService::getDayStats( $itemId, $locationId ),
			'week'  => BookingStatsService::getWeekStats( $itemId, $locationId ),
			'month' => BookingStatsService::getMonthStats( $itemId, $locationId ),
			'year'  => BookingStatsService::getYearStats( $itemId, $locationId ),
		];
	}

	/**
	 * Render one period stat card as an HTML string.
	 *
	 * @param string $label  Human-readable period label (e.g. "This Week").
	 * @param string $compareLabel  Label for the comparison period (e.g. "last week").
	 * @param array  $stats  Result from BookingStatsService::get*Stats().
	 *
	 * @return string
	 */
	public static function renderPeriodCard( string $label, string $compareLabel, array $stats ): string {
		$current    = $stats['current']['count'];
		$diffCount  = $stats['diff_count'];
		$diffPct    = $stats['diff_pct'];
		$days       = $stats['current']['days'];

		$diffHtml = self::formatDiff( $diffCount, $diffPct, $compareLabel );

		$html  = '<div class="cb-stat-card" style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:16px 20px;min-width:160px;">';
		$html .= '<div class="cb-stat-label" style="font-size:12px;color:#666;text-transform:uppercase;letter-spacing:.05em;">' . esc_html( $label ) . '</div>';
		$html .= '<div class="cb-stat-value" style="font-size:32px;font-weight:700;margin:4px 0;">' . esc_html( (string) $current ) . '</div>';
		$html .= '<div class="cb-stat-sub" style="font-size:12px;color:#888;">' . esc_html__( 'bookings', 'commonsbooking' );
		if ( $days > 0 ) {
			$html .= ' &nbsp;·&nbsp; ' . esc_html( (string) $days ) . ' ' . esc_html__( 'days', 'commonsbooking' );
		}
		$html .= '</div>';
		$html .= '<div class="cb-stat-diff" style="margin-top:8px;font-size:13px;">' . $diffHtml . '</div>';
		$html .= '</div>';

		return $html;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	private static function formatDiff( int $diffCount, ?float $diffPct, string $compareLabel ): string {
		if ( $diffCount === 0 ) {
			$arrow = '=';
			$color = '#888';
		} elseif ( $diffCount > 0 ) {
			$arrow = '&#9650;';
			$color = '#67b32a';
		} else {
			$arrow = '&#9660;';
			$color = '#c0392b';
		}

		$pctText = $diffPct !== null ? ' (' . abs( $diffPct ) . '%)' : '';

		return sprintf(
			'<span style="color:%s">%s %s%s</span> <span style="color:#999">%s %s</span>',
			$color,
			$arrow,
			abs( $diffCount ),
			esc_html( $pctText ),
			esc_html__( 'vs', 'commonsbooking' ),
			esc_html( $compareLabel )
		);
	}
}
