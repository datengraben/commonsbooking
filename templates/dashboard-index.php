<h1>Dashboard</h1>
<!-- based on WordPress Dashboard -->
<div class="wrap">
	<div id="cb_welcome-panel" class="cb_welcome-panel">
		<div class="cb_welcome-panel-content">
			<h2><?php

			echo esc_html__( 'Welcome to CommonsBooking', 'commonsbooking' );?>.</h2>
			<div class="cb_welcome-panel-column-container">
				<div class="cb_welcome-panel-column">
					<img src="<?php echo plugin_dir_url( __DIR__ ) . 'assets/global/cb-ci/logo.png'; ?>" style="width:200px">
				</div><!-- .cb_welcome-panel-column -->
				<div class="cb_welcome-panel-column">
				<p></p>
				</div><!-- .cb_welcome-panel-column -->
				<div class="cb_welcome-panel-column cb_welcome-panel-last">
					<h3><?php echo esc_html__( 'Support', 'commonsbooking' ); ?></h3>
					<ul>
						<li><a href="https://commonsbooking.org/documentation" target="_blank"><?php echo esc_html__( 'Documentation & Tutorials', 'commonsbooking' ); ?></a></li>
						<?php
						$support_body  = "\r\n\r\n-----------\r\n\r\n";
						$support_body .= 'Installations-URL: ' . home_url() . "\r\n\r\n";
						$support_body .= 'WP-Version: ' . get_bloginfo( 'version' ) . "\r\n";
						$support_body .= 'PHP-Version: ' . phpversion() . "\r\n";
						$support_body .= 'CB-Version: ' . COMMONSBOOKING_VERSION . "\r\n";
						$support_body .= 'Theme: ' . wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ) . "\r\n";
						$support_body .= 'Locale: ' . get_locale() . "\r\n";
						$support_body .= 'WP_DEBUG: ' . ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'enabled' : 'disabled' ) . "\r\n";
						$support_body .= 'PHP-Memory-Limit: ' . ini_get( 'memory_limit' ) . "\r\n";
						$support_body .= 'Permalink-Structure: ' . ( get_option( 'permalink_structure' ) ?: '(default/plain)' ) . "\r\n";

						if ( is_multisite() ) {
							$support_body .= 'Multisite: yes' . "\r\n";
						}
						if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
							$support_body .= 'WP-Cron: disabled' . "\r\n";
						}

						// Check for known incompatible plugins (see FAQ)
						if ( ! function_exists( 'is_plugin_active' ) ) {
							include_once ABSPATH . 'wp-admin/includes/plugin.php';
						}
						$known_problematic_plugins = [
							'wp-maintenance-mode/wp-maintenance-mode.php'         => 'Lightstart (wp-maintenance-mode)',
							'all-in-one-event-calendar/all-in-one-event-calendar.php' => 'All-in-One Event Calendar',
							'redis-cache/redis-cache.php'                         => 'Redis Object Cache',
							'ultimate-member/ultimate-member.php'                 => 'Ultimate Member',
							'autoptimize/autoptimize.php'                         => 'Autoptimize',
						];
						$active_problematic = [];
						foreach ( $known_problematic_plugins as $plugin_file => $plugin_name ) {
							if ( is_plugin_active( $plugin_file ) ) {
								$active_problematic[] = $plugin_name;
							}
						}
						// Also check for the incompatible GridBulletin theme
						if ( 'gridbulletin' === wp_get_theme()->get_template() ) {
							$active_problematic[] = 'GridBulletin (active theme)';
						}
						if ( $active_problematic ) {
							$support_body .= "\r\nActive known-problematic plugins/themes:\r\n";
							foreach ( $active_problematic as $name ) {
								$support_body .= '  - ' . $name . "\r\n";
							}
						}

						$support_href  = 'mailto:mail@commonsbooking.org'
							. '?subject=' . rawurlencode( 'Support Request - CommonsBooking' )
							. '&body=' . rawurlencode( $support_body );
						?>
						<li><a href="<?php echo esc_attr( $support_href ); ?>" target="_blank"><?php echo esc_html__( 'Support E-Mail', 'commonsbooking' ); ?></a></li>
						<li><a href="https://commonsbooking.org/contact/" target="_blank"><?php echo __( 'Contact & Newsletter', 'commonsbooking' ); ?></a></li>
					</ul>
				<p>			<?php echo esc_html__( 'CommonsBooking Version', 'commonsbooking' ) . ' ' . commonsbooking_sanitizeHTML( COMMONSBOOKING_VERSION . ' ' . COMMONSBOOKING_VERSION_COMMENT ); ?></p>
				</div><!-- .cb_welcome-panel-column -->
			</div><!-- .cb_welcome-panel-column-container -->
			<div style="clear:both;">
			<hr style="border-top: 8px solid #bbb; border-radius: 5px; border-color:#67b32a;">
			</div>
			<div class="cb_welcome-panel-column-container" style="margin-top: 10px;">
				<div class="cb_welcome-panel-column">
					<h3 style="padding-bottom:20px"><?php echo esc_html__( 'Setup and manage Items, Locations and Timeframes', 'commonsbooking' ); ?></h3>
					<ul>
						<li><a href="edit.php?post_type=cb_item"><span class="dashicons dashicons-carrot"></span> <?php echo esc_html__( 'Items', 'commonsbooking' ); ?></a>
						</li>
						<li><a href="edit.php?post_type=cb_location"><span class="dashicons dashicons-store"></span> <?php echo esc_html__( 'Locations', 'commonsbooking' ); ?></a>
						</li>
						<li><a href="edit.php?post_type=cb_timeframe"><span class="dashicons dashicons-calendar-alt"></span> <?php echo esc_html__( 'Timeframes', 'commonsbooking' ); ?></a>
						</li>
					</ul>

				</div><!-- .cb_welcome-panel-column -->
				<div class="cb_welcome-panel-column">
					<h3 style="padding-bottom:20px"><?php echo esc_html__( 'See Bookings & manage restrictions', 'commonsbooking' ); ?></h3>
					<ul>
						<li><a href="edit.php?post_type=cb_booking"><span class="dashicons dashicons-list-view"></span> <?php echo esc_html__( 'Bookings', 'commonsbooking' ); ?></a>
						</li>
						<li><a href="edit.php?post_type=cb_restriction"><span class="dashicons dashicons-warning"></span> <?php echo esc_html__( 'Restrictions', 'commonsbooking' ); ?></a>
						</li>
					</ul>
				</div><!-- .cb_welcome-panel-column -->
				<div class="cb_welcome-panel-column cb_welcome-panel-last">
					<h3 style="padding-bottom:20px"><?php echo esc_html__( 'Configuration', 'commonsbooking' ); ?></h3>
					<ul>
					<?php if ( commonsbooking_isCurrentUserAdmin() ) { ?>
							<li><a href="edit.php?post_type=cb_map"><span class="dashicons dashicons-location-alt"></span> <?php echo esc_html__( 'Maps', 'commonsbooking' ); ?></a>
							</li>
							<li><a href="options-general.php?page=commonsbooking_options"><span class="dashicons dashicons-admin-settings"></span> <?php echo esc_html__( 'Settings', 'commonsbooking' ); ?></a>
							</li>
						<?php } ?>
					</ul>
				</div><!-- .cb_welcome-panel-column -->
			</div><!-- .cb_welcome-panel-column-container -->
		</div> <!-- .cb_welcome-panel-content -->
	</div> <!-- .cb_welcome-panel -->
	<div id="cb_welcome-panel" class="cb_welcome-panel">
		<div class="cb_welcome-panel-content">
			<div class="cb_welcome-panel-column-container">
				<div class="cb_welcome-panel-column" style="width: 50%;">
					<h3><?php echo esc_html__( "Today's pickups", 'commonsbooking' ); ?></h3>
					<?php
					// Display list of bookings with pickup date = today
					$BeginningBookings = CommonsBooking\View\Dashboard::renderBeginningBookings();
					if ( $BeginningBookings ) {
						echo commonsbooking_sanitizeHTML( $BeginningBookings );
					} else {
						echo esc_html__( 'No pickups today', 'commonsbooking' );
					}

					?>
				</div>
				<div class="cb_welcome-panel-column" style="width: 50%">
					<h3><?php echo esc_html__( "Today's returns", 'commonsbooking' ); ?></h3>
					<?php
					// Display list of bookings with return date = today
					$BeginningBookings = CommonsBooking\View\Dashboard::renderEndingBookings();
					if ( $BeginningBookings ) {
						echo commonsbooking_sanitizeHTML( $BeginningBookings );
					} else {
						echo esc_html__( 'No returns today', 'commonsbooking' );
					}

					?>
				</div>
			</div>

		</div>
	</div>
</div>