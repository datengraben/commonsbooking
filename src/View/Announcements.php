<?php

namespace CommonsBooking\View;

use CommonsBooking\Service\AnnouncementsFeed;
use CommonsBooking\Service\Membership;
use CommonsBooking\Settings\Settings;

/**
 * Renders VfL member announcements: as a list (WordPress dashboard widget and
 * the CommonsBooking dashboard) and, for items in the configured "banner"
 * category, as a prominent admin notice across wp-admin.
 *
 * All visibility is gated through Membership so there is a single rule.
 */
class Announcements {

	/**
	 * Category slug that promotes an item to a banner. Configurable in settings.
	 */
	public static function bannerCategory(): string {
		return sanitize_key(
			(string) Settings::getOption( Membership::OPTION_KEY, 'vfl_announcements_banner_category' )
		);
	}

	/**
	 * All cached items, empty when the current user is not a member.
	 */
	public static function items(): array {
		if ( ! Membership::currentUserIsMember() ) {
			return array();
		}

		return AnnouncementsFeed::get();
	}

	/**
	 * Items flagged for banner display (in the configured banner category).
	 */
	public static function bannerItems(): array {
		$category = self::bannerCategory();
		if ( $category === '' ) {
			return array();
		}

		return array_filter(
			self::items(),
			static function ( $item ) use ( $category ) {
				return in_array( $category, $item['categories'], true );
			}
		);
	}

	/**
	 * Register the WordPress dashboard widget. Hooked on wp_dashboard_setup.
	 */
	public static function registerDashboardWidget(): void {
		if ( ! Membership::currentUserIsMember() ) {
			return;
		}

		wp_add_dashboard_widget(
			'cb_vfl_announcements',
			esc_html__( 'VfL announcements', 'commonsbooking' ),
			array( self::class, 'renderWidget' )
		);
	}

	/**
	 * Callback that prints the dashboard widget content.
	 */
	public static function renderWidget(): void {
		echo commonsbooking_sanitizeHTML( self::renderList() );
	}

	/**
	 * Prominent banner shown on all admin pages for banner-category items.
	 * Hooked on admin_notices.
	 */
	public static function renderAdminBanners(): void {
		foreach ( self::bannerItems() as $item ) {
			printf(
				'<div class="notice notice-warning cb-announcement-banner"><p><strong>%s</strong>%s %s</p></div>',
				esc_html( $item['title'] ),
				$item['excerpt'] !== '' ? '<br>' . commonsbooking_sanitizeHTML( $item['excerpt'] ) : '',
				$item['url'] !== '' ? sprintf(
					'<br><a href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url( $item['url'] ),
					esc_html__( 'Read more', 'commonsbooking' )
				) : ''
			);
		}
	}

	/**
	 * HTML list of the announcements, banner items first and marked.
	 */
	public static function renderList(): string {
		$items = self::items();
		if ( ! $items ) {
			return '<p>' . esc_html__( 'No announcements at the moment.', 'commonsbooking' ) . '</p>';
		}

		$bannerCategory = self::bannerCategory();

		// Banner-flagged items first.
		usort(
			$items,
			static function ( $a, $b ) use ( $bannerCategory ) {
				$aBanner = $bannerCategory !== '' && in_array( $bannerCategory, $a['categories'], true );
				$bBanner = $bannerCategory !== '' && in_array( $bannerCategory, $b['categories'], true );
				if ( $aBanner !== $bBanner ) {
					return $aBanner ? -1 : 1;
				}
				return $b['date'] <=> $a['date'];
			}
		);

		$html = '<ul class="cb-announcements">';
		foreach ( $items as $item ) {
			$isBanner = $bannerCategory !== '' && in_array( $bannerCategory, $item['categories'], true );
			$title    = esc_html( $item['title'] );
			if ( $item['url'] !== '' ) {
				$title = sprintf(
					'<a href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url( $item['url'] ),
					$title
				);
			}

			$html .= sprintf(
				'<li style="margin-bottom:8px;%s">%s%s%s</li>',
				$isBanner ? 'padding:6px 10px;border-left:4px solid #d63638;background:#fcf0f1;' : '',
				self::renderCategoryLabels( $item['categories'] ),
				$title,
				$item['date'] ? '<br><small>' . esc_html( date_i18n( get_option( 'date_format' ), $item['date'] ) ) . '</small>' : ''
			);
		}
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Small colored labels for an item's categories.
	 */
	private static function renderCategoryLabels( array $categories ): string {
		if ( ! $categories ) {
			return '';
		}

		$labels = '';
		foreach ( $categories as $category ) {
			$labels .= sprintf(
				'<span class="cb-announcement-label" style="display:inline-block;font-size:11px;text-transform:uppercase;background:#67b32a;color:#fff;border-radius:3px;padding:1px 6px;margin-right:5px;">%s</span>',
				esc_html( $category )
			);
		}

		return $labels;
	}
}
