<?php

namespace CommonsBooking\Service;

use CommonsBooking\Settings\Settings;

/**
 * Single decision point for "may the current user see VfL member content".
 *
 * This is intentionally the only place that knows *how* membership is
 * determined. Today that is an honor-system opt-in checkbox on the settings
 * page; if this ever becomes a verified check (token, SSO, ...) only this
 * class changes, not the call sites.
 */
class Membership {

	public const OPTION_KEY = 'commonsbooking_options_main';

	/**
	 * Whether the site has opted in to VfL member features at all.
	 */
	public static function isEnabled(): bool {
		return Settings::getOption( self::OPTION_KEY, 'is_vfl_member' ) === 'on';
	}

	/**
	 * Whether the current user should be shown VfL member content.
	 *
	 * Filterable so add-ons can tighten or extend the rule without touching
	 * the render code.
	 */
	public static function currentUserIsMember(): bool {
		$result = self::isEnabled() && is_user_logged_in();

		return (bool) apply_filters( 'commonsbooking_user_is_member', $result, get_current_user_id() );
	}

	/**
	 * Whether we are allowed to contact the remote source (opted in + URL set).
	 * Used to guard the background fetch so we never phone home unasked.
	 */
	public static function announcementsConfigured(): bool {
		$url = Settings::getOption( self::OPTION_KEY, 'vfl_announcements_url' );

		return self::isEnabled() && is_string( $url ) && $url !== '';
	}
}
