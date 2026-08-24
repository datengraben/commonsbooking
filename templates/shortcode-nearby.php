<?php
/**
 * Template: shortcode-nearby
 * Shortcode [cb_nearby]
 *
 * Renders a carousel of nearby locations or items. Reuses the CB list card
 * markup (thumbnail / title / excerpt) and adds a distance badge.
 *
 * Expects global $templateData with:
 *  - type    : 'items' | 'locations'
 *  - results : array of [ 'id' => int, 'distance' => float ] ordered nearest first
 *  - visible : number of cards visible at once in the carousel
 */

global $templateData;

$type    = $templateData['type'] ?? 'locations';
$results = $templateData['results'] ?? array();
$visible = (int) ( $templateData['visible'] ?? 3 );

// Configurable "nothing nearby" text, with a translatable fallback.
$noResultText = \CommonsBooking\Settings\Settings::getOption( COMMONSBOOKING_PLUGIN_SLUG . '_options_templates', 'nearby-no-results' );
if ( ! $noResultText ) {
	$noResultText = $type === 'items'
		? __( 'No bookable items nearby.', 'commonsbooking' )
		: __( 'No locations nearby.', 'commonsbooking' );
}

if ( empty( $results ) ) {
	?>
	<div class="cb-wrapper cb-shortcode-nearby template-shortcode-nearby">
		<div class="cb-list-error cb-nearby-empty"><?php echo commonsbooking_sanitizeHTML( $noResultText ); ?></div>
	</div>
	<?php
	return;
}

$modelClass = $type === 'items'
	? \CommonsBooking\Model\Item::class
	: \CommonsBooking\Model\Location::class;

$kmLabel = __( 'km', 'commonsbooking' );
?>
<div class="cb-wrapper cb-shortcode-nearby template-shortcode-nearby cb-nearby" data-cb-nearby-visible="<?php echo esc_attr( (string) max( 1, $visible ) ); ?>">
	<button type="button" class="cb-nearby-nav cb-nearby-prev" aria-label="<?php echo esc_attr__( 'Previous', 'commonsbooking' ); ?>">&#8249;</button>

	<div class="cb-nearby-viewport">
		<ul class="cb-nearby-track">
			<?php
			foreach ( $results as $result ) {
				$post = get_post( $result['id'] );
				if ( ! $post ) {
					continue;
				}
				/** @var \CommonsBooking\Model\Item|\CommonsBooking\Model\Location $model */
				$model    = new $modelClass( $post );
				$distance = (int) round( (float) $result['distance'] );
				?>
				<li class="cb-nearby-item">
					<div class="cb-list-header">
						<?php echo commonsbooking_sanitizeHTML( $model->thumbnail( 'cb_listing_medium' ) ); ?>
						<div class="cb-list-info">
							<span class="cb-nearby-distance" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: distance in kilometers */ __( 'approx. %d km away', 'commonsbooking' ), $distance ) ); ?>">
								~<?php echo esc_html( (string) $distance ); ?>&nbsp;<?php echo esc_html( $kmLabel ); ?>
							</span>
							<h3><?php echo commonsbooking_sanitizeHTML( $model->titleLink() ); ?></h3>
							<?php echo commonsbooking_sanitizeHTML( $model->excerpt() ); ?>
						</div>
					</div>
				</li>
				<?php
			}
			?>
		</ul>
	</div>

	<button type="button" class="cb-nearby-nav cb-nearby-next" aria-label="<?php echo esc_attr__( 'Next', 'commonsbooking' ); ?>">&#8250;</button>
</div>
