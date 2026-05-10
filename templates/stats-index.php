<?php
/**
 * Template: Booking Statistics admin page.
 * Called via CommonsBooking\View\Stats::index().
 */

use CommonsBooking\Repository\Item as ItemRepo;
use CommonsBooking\Repository\Location as LocationRepo;
use CommonsBooking\View\Stats;

// Read filter params from GET (sanitized).
$selectedItem     = isset( $_GET['cb_stats_item'] ) ? (int) $_GET['cb_stats_item'] : 0;
$selectedLocation = isset( $_GET['cb_stats_location'] ) ? (int) $_GET['cb_stats_location'] : 0;

// When an item is selected we ignore the location filter and vice-versa.
$filterItemId     = $selectedItem ?: null;
$filterLocationId = $selectedItem ? null : ( $selectedLocation ?: null );

// Compute stat cards.
$cards = Stats::getStatCards( $filterItemId, $filterLocationId );

// Fetch items and locations for the filter dropdowns.
$items     = ItemRepo::get();
$locations = LocationRepo::get();
?>
<h1><?php echo esc_html__( 'Booking Statistics', 'commonsbooking' ); ?></h1>

<div class="wrap">

	<?php if ( isset( $_GET['recomputed'] ) && $_GET['recomputed'] === '1' ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html__( 'Statistics have been recomputed successfully.', 'commonsbooking' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Filter form -->
	<div class="cb_welcome-panel" style="margin-bottom:20px;">
		<div class="cb_welcome-panel-content">
			<h3><?php echo esc_html__( 'Filter', 'commonsbooking' ); ?></h3>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="cb-stats">
				<div style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;">
					<div>
						<label for="cb_stats_item" style="display:block;font-weight:600;margin-bottom:4px;">
							<?php echo esc_html__( 'Item', 'commonsbooking' ); ?>
						</label>
						<select name="cb_stats_item" id="cb_stats_item" style="min-width:180px;">
							<option value="0"><?php echo esc_html__( '— All items —', 'commonsbooking' ); ?></option>
							<?php foreach ( $items as $item ) : ?>
								<option value="<?php echo esc_attr( $item->ID ); ?>" <?php selected( $selectedItem, $item->ID ); ?>>
									<?php echo esc_html( $item->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div>
						<label for="cb_stats_location" style="display:block;font-weight:600;margin-bottom:4px;">
							<?php echo esc_html__( 'Location', 'commonsbooking' ); ?>
						</label>
						<select name="cb_stats_location" id="cb_stats_location" style="min-width:180px;">
							<option value="0"><?php echo esc_html__( '— All locations —', 'commonsbooking' ); ?></option>
							<?php foreach ( $locations as $location ) : ?>
								<option value="<?php echo esc_attr( $location->ID ); ?>" <?php selected( $selectedLocation, $location->ID ); ?>>
									<?php echo esc_html( $location->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div>
						<button type="submit" class="button button-primary">
							<?php echo esc_html__( 'Apply', 'commonsbooking' ); ?>
						</button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=cb-stats' ) ); ?>" class="button" style="margin-left:6px;">
							<?php echo esc_html__( 'Reset', 'commonsbooking' ); ?>
						</a>
					</div>
				</div>
			</form>
		</div>
	</div>

	<hr style="border-top:8px solid #67b32a;border-radius:5px;margin:0 0 20px;">

	<!-- Stat cards -->
	<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;">
		<?php
		$periodConfig = [
			'day'   => [
				'label'        => __( 'Today', 'commonsbooking' ),
				'compareLabel' => __( 'yesterday', 'commonsbooking' ),
			],
			'week'  => [
				'label'        => __( 'This Week', 'commonsbooking' ),
				'compareLabel' => __( 'last week', 'commonsbooking' ),
			],
			'month' => [
				'label'        => __( 'This Month', 'commonsbooking' ),
				'compareLabel' => __( 'last month', 'commonsbooking' ),
			],
			'year'  => [
				'label'        => __( 'This Year', 'commonsbooking' ),
				'compareLabel' => __( 'last year', 'commonsbooking' ),
			],
		];

		foreach ( $periodConfig as $key => $cfg ) {
			echo commonsbooking_sanitizeHTML(
				Stats::renderPeriodCard( $cfg['label'], $cfg['compareLabel'], $cards[ $key ] )
			);
		}
		?>
	</div>

	<!-- Recompute action -->
	<?php if ( commonsbooking_isCurrentUserAdmin() ) : ?>
		<div class="cb_welcome-panel" style="margin-top:24px;">
			<div class="cb_welcome-panel-content">
				<h3><?php echo esc_html__( 'Maintenance', 'commonsbooking' ); ?></h3>
				<p style="color:#666;">
					<?php echo esc_html__( 'If statistics look incorrect, you can recompute them from all existing confirmed bookings. This may take a moment on large datasets.', 'commonsbooking' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cb_recompute_stats">
					<?php wp_nonce_field( 'cb_recompute_stats' ); ?>
					<button type="submit" class="button button-secondary">
						<?php echo esc_html__( 'Recompute Statistics', 'commonsbooking' ); ?>
					</button>
				</form>
			</div>
		</div>
	<?php endif; ?>

</div>
