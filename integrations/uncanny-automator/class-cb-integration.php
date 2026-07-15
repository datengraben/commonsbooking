<?php
/**
 * CommonsBooking integration definition for Uncanny Automator.
 *
 * @package CommonsBooking
 */

namespace CommonsBooking\Integrations\Automator;

defined( 'ABSPATH' ) || die( 'Thanks for visiting' );

/**
 * Registers CommonsBooking as an integration in Uncanny Automator.
 */
class CB_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Sets up the integration (name and icon shown in the Automator UI).
	 *
	 * @return void
	 */
	protected function setup() {
		$this->set_integration( 'COMMONSBOOKING' );
		$this->set_name( 'CommonsBooking' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'icon.svg' );
	}
}
