<?php
/**
 * Template: shortcode-items_table
 * Shortcode [cb_items_table]
 * Model: Calendar
 *
 * Shows an availability table for items
 */


global $templateData;

echo $templateData['data']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template data contains pre-rendered HTML from the Calendar model; HTML escaping would corrupt the table output.
?>

<div id="cb-table-footnote">
	<?php
	commonsbooking_get_template_part( 'calendar', 'key' ); // file: calendar-key.php
	?>
</div>
