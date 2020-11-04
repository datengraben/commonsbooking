<?php
/**
 * Shortcode [cb_locations]
 * Model: location
 *
 * List all locations, with one or more associated timeframes (with location info)
 *
 * WP Post properties for locations are available as $item->property
 * location Model methods are available as $item->myMethod()
 *
 */
global $templateData;
/** @var \CommonsBooking\Model\Item $item */
$item = new \CommonsBooking\Model\Item($templateData['item']);
$noResultText = __("No article available at this item.", "commonsbooking");

?>
<div class="cb-list-header">
    <?php echo $item->thumbnail(); ?>
    <h2><?php echo $item->titleLink(); ?></h2>
</div><!-- .cb-list-header -->

<div class="cb-list-content">
    <?php echo $item->excerpt(); ?>
</div><!-- .cb-list-content -->

<?php
if (array_key_exists('data', $templateData)) {
    foreach ($templateData['data'] as $locationId => $data ) {
        $location = new \CommonsBooking\Model\Location($locationId);
        set_query_var( 'item', $item );
        set_query_var( 'location', $location );
        set_query_var( 'data', $data );
        cb_get_template_part( 'timeframe', 'withlocation' );
    }
} else { ?>
<div class="cb-status cb-availability-status"><?php echo ( $noResultText ); ?>
    <?php } // end if ($timeframes) ?>
