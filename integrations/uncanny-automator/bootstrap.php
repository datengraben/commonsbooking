<?php
/**
 * Uncanny Automator integration for CommonsBooking.
 *
 * This is an optional compatibility layer: it only loads when the Uncanny
 * Automator plugin is active. It is intentionally kept out of the PSR-4
 * autoloaded `src/` tree so that classes extending Automator base classes are
 * never loaded unless Automator is present.
 *
 * @package CommonsBooking
 */

defined( 'ABSPATH' ) || die( 'Thanks for visiting' );

// Register on Automator's own loader hook. This action only fires when the
// Uncanny Automator plugin is active, so there is zero overhead otherwise.
add_action( 'automator_add_integration', 'commonsbooking_load_automator_integration' );

/**
 * Loads the CommonsBooking Automator integration and its triggers.
 *
 * @return void
 */
function commonsbooking_load_automator_integration() {
	// If this class does not exist, Uncanny Automator is not active or is outdated.
	if ( ! class_exists( '\Uncanny_Automator\Integration' ) ) {
		return;
	}

	require_once __DIR__ . '/class-cb-integration.php';
	new \CommonsBooking\Integrations\Automator\CB_Integration();

	// Shared token trait must be loaded before the triggers that use it.
	require_once __DIR__ . '/trait-booking-tokens.php';

	require_once __DIR__ . '/class-booking-confirmed-trigger.php';
	new \CommonsBooking\Integrations\Automator\Booking_Confirmed_Trigger();

	require_once __DIR__ . '/class-booking-cancelled-trigger.php';
	new \CommonsBooking\Integrations\Automator\Booking_Cancelled_Trigger();
}
