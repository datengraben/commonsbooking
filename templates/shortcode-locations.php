<?php
/**
 * Shortcode [cb_locations]
 * Model: location
 *
 * List all locations, with one or more associated timeframes (with item info)
 *
 * WP Post properties for locations are available as $location->property
 * location Model methods are available as $location->myMethod()
 *
 */
global $templateData;
$location = new \CommonsBooking\Model\Location($templateData['location']);

// the location without items message is shown if there are currently no available items at this location. Can be defined via plugin options -> message templates
$noResultText = \CommonsBooking\Settings\Settings::getOption( COMMONSBOOKING_PLUGIN_SLUG . '_options_templates', 'location-without-items' );

?>
<div class="cb-list-header">
	<?php echo $location->thumbnail(); ?>
	<h2><?php echo $location->titleLink(); ?></h2>
</div><!-- .cb-list-header -->

<div class="cb-list-content">
	<?php echo $location->excerpt(); ?>
</div><!-- .cb-list-content -->

<?php
if (array_key_exists('data', $templateData) && count($templateData['data'])) {
    foreach ($templateData['data'] as $itemId => $data ) {
        $item = new \CommonsBooking\Model\Item($itemId);
        set_query_var( 'item', $item );
        set_query_var( 'location', $location );
        set_query_var( 'data', $data );
        commonsbooking_get_template_part( 'timeframe', 'withitem' ); // file: timeframe-withlocation.php
    }
} else { ?>
    <div class="cb-status cb-availability-status"><?php echo ( $noResultText ); ?></div>
<?php } // end if ($timeframes) ?>
