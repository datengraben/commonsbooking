<?php

namespace CommonsBooking\View;

use CMB2_Field;
use CommonsBooking\Repository\AvailabilityIndex as AvailabilityIndexRepository;

/**
 * Renders the rebuild control for the optional availability index on the settings page.
 */
class AvailabilityIndex {

	/**
	 * Renders a button that fills the index, page by page, through the repository's own
	 * AJAX endpoint. Only shown while the feature is switched on.
	 *
	 * @param array      $field_args
	 * @param CMB2_Field $field
	 *
	 * @return bool|void
	 */
	public static function renderRebuildButton( array $field_args, CMB2_Field $field ) {
		if ( ! AvailabilityIndexRepository::isEnabled() ) {
			return false;
		}

		$action = AvailabilityIndexRepository::AJAX_ACTION;
		$nonce  = wp_create_nonce( $action );
		?>
		<div class="cmb-row cmb-type-text" id="cb-availability-index-rebuild">
			<a id="cb-rebuild-index" class="button button-secondary" href="#">
				<?php echo esc_html__( 'Rebuild index', 'commonsbooking' ); ?>
			</a>
			<span id="cb-rebuild-index-status" style="margin-left: 10px;"></span>
		</div>
		<script>
			jQuery( function ( $ ) {
				var button = $( '#cb-rebuild-index' ),
					status = $( '#cb-rebuild-index-status' );

				function runPage( page ) {
					$.post( ajaxurl, {
						action: <?php echo wp_json_encode( $action ); ?>,
						nonce: <?php echo wp_json_encode( $nonce ); ?>,
						page: page
					} ).done( function ( response ) {
						if ( ! response || ! response.success ) {
							status.text( ( response && response.data && response.data.message ) || <?php echo wp_json_encode( __( 'Rebuild failed.', 'commonsbooking' ) ); ?> );
							button.removeClass( 'disabled' );
							return;
						}

						if ( response.data.done ) {
							status.text( <?php echo wp_json_encode( __( 'Index rebuilt.', 'commonsbooking' ) ); ?> );
							button.removeClass( 'disabled' );
							return;
						}

						status.text( <?php echo wp_json_encode( __( 'Rebuilding, page', 'commonsbooking' ) ); ?> + ' ' + response.data.page + ' ...' );
						runPage( response.data.page );
					} ).fail( function () {
						status.text( <?php echo wp_json_encode( __( 'Rebuild failed.', 'commonsbooking' ) ); ?> );
						button.removeClass( 'disabled' );
					} );
				}

				button.on( 'click', function ( event ) {
					event.preventDefault();

					if ( button.hasClass( 'disabled' ) ) {
						return;
					}

					button.addClass( 'disabled' );
					status.text( <?php echo wp_json_encode( __( 'Rebuilding ...', 'commonsbooking' ) ); ?> );
					runPage( 1 );
				} );
			} );
		</script>
		<?php
	}
}
