<?php
/**
 * Template: Plugin comparison page
 *
 * Shown in the WordPress admin under CommonsBooking > Plugin Comparison.
 */
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Plugin Comparison', 'commonsbooking' ); ?></h1>
	<p><?php echo esc_html__( 'CommonsBooking is purpose-built for community sharing of items and resources. The table below compares key features with other popular WordPress booking plugins.', 'commonsbooking' ); ?></p>

	<style>
		#cb-comparison-table {
			border-collapse: collapse;
			margin-top: 1.5em;
			font-size: 14px;
		}
		#cb-comparison-table th,
		#cb-comparison-table td {
			padding: 10px 20px;
			text-align: center;
			border: 1px solid #c3c4c7;
			vertical-align: middle;
		}
		#cb-comparison-table th:first-child,
		#cb-comparison-table td:first-child {
			text-align: left;
			font-weight: 600;
			white-space: nowrap;
		}
		#cb-comparison-table thead th {
			background: #f0f0f1;
			font-weight: 700;
		}
		#cb-comparison-table thead th.cb-highlight {
			background: #2c7a2c;
			color: #fff;
		}
		#cb-comparison-table tbody tr:nth-child(even) td {
			background: #f9f9f9;
		}
		#cb-comparison-table .cb-yes {
			color: #2c7a2c;
			font-size: 18px;
			font-weight: 700;
		}
		#cb-comparison-table .cb-no {
			color: #a00;
			font-size: 18px;
		}
		#cb-comparison-table .cb-partial {
			color: #996600;
			font-size: 13px;
		}
		.cb-comparison-note {
			margin-top: 1em;
			color: #646970;
			font-size: 12px;
		}
	</style>

	<table id="cb-comparison-table" class="widefat">
		<thead>
			<tr>
				<th><?php echo esc_html__( 'Feature', 'commonsbooking' ); ?></th>
				<th class="cb-highlight">CommonsBooking</th>
				<th>Bookly</th>
				<th>Amelia</th>
				<th>WooCommerce<br>Bookings</th>
				<th>Booking<br>Calendar</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<?php echo esc_html__( 'Booking Calendar', 'commonsbooking' ); ?>
					<br><small><?php echo esc_html__( 'Interactive calendar showing item availability', 'commonsbooking' ); ?></small>
				</td>
				<td><span class="cb-yes" title="<?php esc_attr_e( 'Yes', 'commonsbooking' ); ?>">&#10003;</span></td>
				<td><span class="cb-yes" title="<?php esc_attr_e( 'Yes', 'commonsbooking' ); ?>">&#10003;</span></td>
				<td><span class="cb-yes" title="<?php esc_attr_e( 'Yes', 'commonsbooking' ); ?>">&#10003;</span></td>
				<td><span class="cb-yes" title="<?php esc_attr_e( 'Yes', 'commonsbooking' ); ?>">&#10003;</span></td>
				<td><span class="cb-yes" title="<?php esc_attr_e( 'Yes', 'commonsbooking' ); ?>">&#10003;</span></td>
			</tr>
			<tr>
				<td>
					<?php echo esc_html__( 'Map / Location Display', 'commonsbooking' ); ?>
					<br><small><?php echo esc_html__( 'Interactive map showing items and locations', 'commonsbooking' ); ?></small>
				</td>
				<td><span class="cb-yes" title="<?php esc_attr_e( 'Yes', 'commonsbooking' ); ?>">&#10003;</span></td>
				<td><span class="cb-no" title="<?php esc_attr_e( 'No', 'commonsbooking' ); ?>">&#10007;</span></td>
				<td><span class="cb-no" title="<?php esc_attr_e( 'No', 'commonsbooking' ); ?>">&#10007;</span></td>
				<td><span class="cb-no" title="<?php esc_attr_e( 'No', 'commonsbooking' ); ?>">&#10007;</span></td>
				<td><span class="cb-no" title="<?php esc_attr_e( 'No', 'commonsbooking' ); ?>">&#10007;</span></td>
			</tr>
			<tr>
				<td>
					<?php echo esc_html__( 'iCal Export', 'commonsbooking' ); ?>
					<br><small><?php echo esc_html__( 'Subscribe to bookings via iCalendar feed', 'commonsbooking' ); ?></small>
				</td>
				<td><span class="cb-yes" title="<?php esc_attr_e( 'Yes', 'commonsbooking' ); ?>">&#10003;</span></td>
				<td><span class="cb-no" title="<?php esc_attr_e( 'No', 'commonsbooking' ); ?>">&#10007;</span></td>
				<td><span class="cb-no" title="<?php esc_attr_e( 'No', 'commonsbooking' ); ?>">&#10007;</span></td>
				<td><span class="cb-no" title="<?php esc_attr_e( 'No', 'commonsbooking' ); ?>">&#10007;</span></td>
				<td><span class="cb-yes" title="<?php esc_attr_e( 'Yes', 'commonsbooking' ); ?>">&#10003;</span></td>
			</tr>
			<tr>
				<td>
					<?php echo esc_html__( 'GBFS (General Bikeshare Feed Spec)', 'commonsbooking' ); ?>
					<br><small><?php echo esc_html__( 'Open API standard for shared mobility data', 'commonsbooking' ); ?></small>
				</td>
				<td><span class="cb-yes" title="<?php esc_attr_e( 'Yes', 'commonsbooking' ); ?>">&#10003;</span></td>
				<td><span class="cb-no" title="<?php esc_attr_e( 'No', 'commonsbooking' ); ?>">&#10007;</span></td>
				<td><span class="cb-no" title="<?php esc_attr_e( 'No', 'commonsbooking' ); ?>">&#10007;</span></td>
				<td><span class="cb-no" title="<?php esc_attr_e( 'No', 'commonsbooking' ); ?>">&#10007;</span></td>
				<td><span class="cb-no" title="<?php esc_attr_e( 'No', 'commonsbooking' ); ?>">&#10007;</span></td>
			</tr>
		</tbody>
	</table>

	<p class="cb-comparison-note">
		<?php echo esc_html__( 'Feature information is based on publicly available plugin documentation. Competitor features may vary by version or plan.', 'commonsbooking' ); ?>
	</p>
</div>
