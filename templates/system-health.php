<?php
/**
 * Template: System Health page
 * Rendered by CommonsBooking\View\SystemHealth::index()
 */

use CommonsBooking\Service\ErrorMonitor;
use CommonsBooking\View\SystemHealth;

$checks  = SystemHealth::getChecks();
$entries = ErrorMonitor::getEntries( 50 );
$count   = ErrorMonitor::count();

$statusIcon = [
	'ok'   => '<span style="color:#46b450;font-weight:bold;">&#x2714; OK</span>',
	'warn' => '<span style="color:#ffb900;font-weight:bold;">&#x26a0; WARN</span>',
	'fail' => '<span style="color:#dc3232;font-weight:bold;">&#x2718; FAIL</span>',
];

$severityStyle = [
	ErrorMonitor::SEVERITY_ERROR   => 'color:#dc3232;font-weight:bold;',
	ErrorMonitor::SEVERITY_WARNING => 'color:#ffb900;font-weight:bold;',
	ErrorMonitor::SEVERITY_INFO    => 'color:#0073aa;font-weight:bold;',
];
?>
<div class="wrap">
	<h1><?php esc_html_e( 'CommonsBooking — System Health', 'commonsbooking' ); ?></h1>

	<?php if ( isset( $_GET['cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Error log cleared.', 'commonsbooking' ); ?></p>
		</div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper" id="cb-health-tabs">
		<a href="#cb-system-status" class="nav-tab nav-tab-active" data-tab="cb-system-status">
			<?php esc_html_e( 'System Status', 'commonsbooking' ); ?>
		</a>
		<a href="#cb-error-log" class="nav-tab" data-tab="cb-error-log">
			<?php
			printf(
				/* translators: %d: number of errors in log */
				esc_html__( 'Error Log (%d)', 'commonsbooking' ),
				(int) $count
			);
			?>
		</a>
	</nav>

	<!-- System Status Tab -->
	<div id="cb-system-status" class="cb-tab-panel" style="margin-top:1em;">
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" style="width:220px;"><?php esc_html_e( 'Check', 'commonsbooking' ); ?></th>
					<th scope="col" style="width:100px;"><?php esc_html_e( 'Status', 'commonsbooking' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Details', 'commonsbooking' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $checks as $check ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $check['label'] ); ?></strong></td>
						<td><?php echo $statusIcon[ $check['status'] ] ?? esc_html( $check['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td><?php echo esc_html( $check['detail'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<!-- Error Log Tab -->
	<div id="cb-error-log" class="cb-tab-panel" style="display:none;margin-top:1em;">
		<form method="post" action="" style="margin-bottom:1em;">
			<?php wp_nonce_field( SystemHealth::NONCE_ACTION, SystemHealth::NONCE_FIELD ); ?>
			<input type="hidden" name="cb_clear_error_log" value="1">
			<button type="submit" class="button button-secondary"
				onclick="return confirm('<?php esc_attr_e( 'Clear all recorded errors?', 'commonsbooking' ); ?>')">
				<?php esc_html_e( 'Clear Error Log', 'commonsbooking' ); ?>
			</button>
		</form>

		<?php if ( empty( $entries ) ) : ?>
			<p><?php esc_html_e( 'No errors recorded.', 'commonsbooking' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped" style="font-family:monospace;font-size:12px;">
				<thead>
					<tr>
						<th scope="col" style="width:160px;"><?php esc_html_e( 'Time', 'commonsbooking' ); ?></th>
						<th scope="col" style="width:80px;"><?php esc_html_e( 'Severity', 'commonsbooking' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Message', 'commonsbooking' ); ?></th>
						<th scope="col" style="width:160px;"><?php esc_html_e( 'Location', 'commonsbooking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $entries as $entry ) :
						$sev     = $entry['severity'] ?? ErrorMonitor::SEVERITY_ERROR;
						$file    = basename( $entry['context']['file'] ?? '' );
						$line    = $entry['context']['line'] ?? '';
						$style   = $severityStyle[ $sev ] ?? '';
					?>
						<tr>
							<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $entry['timestamp'] ) ); ?></td>
							<td><span style="<?php echo esc_attr( $style ); ?>"><?php echo esc_html( strtoupper( $sev ) ); ?></span></td>
							<td style="word-break:break-word;"><?php echo esc_html( $entry['message'] ); ?></td>
							<td><small><?php echo esc_html( $file . ( $line ? ':' . $line : '' ) ); ?></small></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<script>
(function () {
	var tabs   = document.querySelectorAll('#cb-health-tabs .nav-tab');
	var panels = document.querySelectorAll('.cb-tab-panel');

	function showTab(targetId) {
		panels.forEach(function (p) { p.style.display = 'none'; });
		tabs.forEach(function (t) { t.classList.remove('nav-tab-active'); });
		var panel = document.getElementById(targetId);
		if (panel) { panel.style.display = ''; }
		tabs.forEach(function (t) {
			if (t.getAttribute('data-tab') === targetId) { t.classList.add('nav-tab-active'); }
		});
	}

	tabs.forEach(function (tab) {
		tab.addEventListener('click', function (e) {
			e.preventDefault();
			showTab(tab.getAttribute('data-tab'));
		});
	});

	// Restore tab from URL hash if present
	if (window.location.hash && document.getElementById(window.location.hash.substring(1))) {
		showTab(window.location.hash.substring(1));
	}
})();
</script>
