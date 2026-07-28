<?php

namespace CommonsBooking\Helper;

use CommonsBooking\Settings\Settings;

/**
 * Central place for feature-flag style checks.
 *
 * Wraps individual plugin settings behind named boolean methods so callers do
 * not need to know which option group and key back a given feature. This keeps
 * the option keys in a single location and makes the intent at the call site
 * explicit (e.g. `Feature::isApiEnabled()` instead of comparing a raw option
 * value to the string `'on'`).
 */
class Feature {

	/**
	 * Whether the CommonsBooking API is globally enabled in the settings.
	 *
	 * @return bool
	 */
	public static function isApiEnabled(): bool {
		return Settings::getOption( 'commonsbooking_options_api', 'api-activated' ) === 'on';
	}

	/**
	 * Whether the API may be accessed without a valid API key.
	 *
	 * @return bool
	 */
	public static function isApiAnonymousAccessAllowed(): bool {
		return Settings::getOption( 'commonsbooking_options_api', 'apikey_not_required' ) === 'on';
	}
}
