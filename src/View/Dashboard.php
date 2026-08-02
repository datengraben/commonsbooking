<?php

namespace CommonsBooking\View;

use CommonsBooking\Service\GBFSDiscoveryCheck;

/**
 * The dashboard that can be seen in the WordPress Backend under CommonsBooking
 */
class Dashboard extends View {

	public static function index() {
		ob_start();
		commonsbooking_sanitizeHTML( commonsbooking_get_template_part( 'dashboard', 'index' ) );
		echo ob_get_clean();
	}

	/**
	 * Renders list of bookings, which are starting today.
	 *
	 * @return string|false
	 * @throws \Exception
	 */
	public static function renderBeginningBookings() {
		$beginningBookings = \CommonsBooking\Repository\Booking::getBeginningBookingsByDate( time() );

		// filter bookings to show only allowed bookings for current user role
		if ( $beginningBookings ) {
			$beginningBookings = array_filter(
				$beginningBookings,
				function ( $beginningBooking ) {
					return commonsbooking_isCurrentUserAllowedToEdit( $beginningBooking );
				}
			);
		}

		if ( count( $beginningBookings ) > 0 ) {
			usort(
				$beginningBookings,
				function ( $a, $b ) {
					return strtotime( $a->getStartTime() ) <=> strtotime( $b->getStartTime() );
				}
			);
			$html  = '<div style="padding:5px 20px 5px 20px">';
			$html .= '<ul>';
			/** @var \CommonsBooking\Model\Booking $booking */
			foreach ( $beginningBookings as $booking ) {
				$html .= '<li>';
				$html .= '<strong>' . $booking->pickupDatetime() . ' </strong> => ' . $booking->returnDatetime() . '<br>';
				$html .= '<a href="' . $booking->bookingLinkUrl() . '" target="_blank">' . $booking->getItem()->title() . ' ' . __( 'at', 'commonsbooking' ) . ' ' . $booking->getLocation()->title() . '</a>';
				$html .= '</li>';
				$html .= '<hr style="border-top: 1px solid #bbb; border-radius: 0px; border-color:#67b32a;">';
			}
			$html .= '</ul>';
			$html .= '</div>';
			return $html;
		} else {
			return false;
		}
	}


	/**
	 * Renders list of bookings, which are ending today.
	 *
	 * @return string|false
	 * @throws \Exception
	 */
	public static function renderEndingBookings() {
		$endingBookings = \CommonsBooking\Repository\Booking::getEndingBookingsByDate( time() );

		// filter bookings to show only allowed bookings for current user role
		if ( $endingBookings ) {
			$endingBookings = array_filter(
				$endingBookings,
				function ( $endingBooking ) {
					return commonsbooking_isCurrentUserAllowedToEdit( $endingBooking );
				}
			);
		}

		if ( count( $endingBookings ) ) {
			usort(
				$endingBookings,
				function ( $a, $b ) {
					return strtotime( $a->getEndTime() ) <=> strtotime( $b->getEndTime() );
				}
			);
			// return self::renderBookingsTable( $endingBookings, false);
			$html  = '<div style="padding:5px 20px 5px 20px">';
			$html .= '<ul>';
			/** @var \CommonsBooking\Model\Booking $booking */
			foreach ( $endingBookings as $booking ) {
				$html .= '<li>';
				$html .= '<strong>' . $booking->returnDatetime() . '</strong><br>';
				$html .= '<a href="' . $booking->bookingLinkUrl() . '" target="_blank">' . $booking->getItem()->title() . ' ' . __( 'at', 'commonsbooking' ) . ' ' . $booking->getLocation()->title() . '</a>';
				$html .= '</li>';
				$html .= '<hr style="border-top: 1px solid #bbb; border-radius: 0px; border-color:#67b32a;">';
			}
			$html .= '</ul>';
			$html .= '</div>';

			return $html;
		} else {
			return false;
		}
	}

	/**
	 * Renders the GBFS directory-listing panel: whether this instance's GBFS
	 * auto-discovery feed is listed in public mobility-data directories.
	 *
	 * Handles the on-demand "Check now" reload (a nonce-protected link back to
	 * the dashboard) before rendering, and shows the cached result plus the time
	 * it was last checked.
	 *
	 * @return string
	 */
	public static function renderGBFSDiscovery(): string {
		$capability = 'manage_' . COMMONSBOOKING_PLUGIN_SLUG;

		// Handle on-demand re-check triggered by the "Check now" link.
		if (
			isset( $_GET['cb_gbfs_recheck'], $_GET['cb_gbfs_nonce'] ) &&
			current_user_can( $capability ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['cb_gbfs_nonce'] ) ), 'cb_gbfs_recheck' )
		) {
			GBFSDiscoveryCheck::refresh();
		}

		$status = GBFSDiscoveryCheck::getStatus();

		$recheckUrl = wp_nonce_url(
			add_query_arg( 'cb_gbfs_recheck', '1', admin_url( 'admin.php?page=cb-dashboard' ) ),
			'cb_gbfs_recheck',
			'cb_gbfs_nonce'
		);
		$checkButton = '<a href="' . esc_url( $recheckUrl ) . '" class="button">' . esc_html__( 'Check now', 'commonsbooking' ) . '</a>';

		$html  = '<div class="cb_welcome-panel-column" style="width:100%;">';
		$html .= '<h3>' . esc_html__( 'GBFS directory listing', 'commonsbooking' ) . '</h3>';

		if ( null === $status ) {
			$html .= '<p>' . esc_html__( 'Your GBFS feed has not been checked yet.', 'commonsbooking' ) . '</p>';
			$html .= '<p>' . $checkButton . '</p>';
			$html .= '</div>';
			return $html;
		}

		$html .= '<p>' . sprintf(
			/* translators: %s: e.g. "1/3" */
			esc_html__( 'Listed in %s directories:', 'commonsbooking' ),
			'<strong>' . esc_html( $status['summary'] ) . '</strong>'
		) . '</p>';

		$html .= '<ul>';
		foreach ( $status['sources'] as $source ) {
			$html .= '<li style="margin-bottom:6px;">' . self::renderGBFSSourceRow( $source ) . '</li>';
		}
		$html .= '</ul>';

		$lastChecked = ! empty( $status['last_checked'] )
			? (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $status['last_checked'] )
			: esc_html__( 'never', 'commonsbooking' );
		$html .= '<p><small>' . sprintf(
			/* translators: %s: date and time of last check */
			esc_html__( 'Last checked: %s', 'commonsbooking' ),
			esc_html( $lastChecked )
		) . '</small></p>';

		$html .= '<p>' . $checkButton . '</p>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Renders a single source row (icon + label + status) for the GBFS panel.
	 *
	 * @param array $source
	 *
	 * @return string
	 */
	private static function renderGBFSSourceRow( array $source ): string {
		switch ( $source['status'] ) {
			case 'included':
				$icon  = '<span class="dashicons dashicons-yes-alt" style="color:#67b32a;"></span> ';
				$state = esc_html__( 'listed', 'commonsbooking' );
				break;
			case 'not_included':
				$icon  = '<span class="dashicons dashicons-marker" style="color:#d63638;"></span> ';
				$state = esc_html__( 'not listed', 'commonsbooking' );
				break;
			case 'manual':
				$icon  = '<span class="dashicons dashicons-external"></span> ';
				$state = esc_html__( 'check manually', 'commonsbooking' );
				break;
			default:
				$icon  = '<span class="dashicons dashicons-warning" style="color:#dba617;"></span> ';
				$state = esc_html__( 'unknown', 'commonsbooking' );
				break;
		}

		$label = esc_html( $source['label'] );
		if ( ! empty( $source['link'] ) ) {
			$label = '<a href="' . esc_url( $source['link'] ) . '" target="_blank" rel="noopener">' . $label . '</a>';
		}

		return $icon . $label . ' — ' . $state;
	}
}
