<?php 
/** 
* Single item with either list of timeframes or booking calendar
* 
* Original post content is preserved, contents of this file are attached.
* 
* WP Post properties for item are available as $item->property
* Item Model methods are available as $item->myMethod()
* 
* Model: Item
*/

$timeframes 	= $item->getBookableTimeframes();
$noResultText = __("This item is currently not available.", "commonsbooking");
$location_selected = isset($_GET['location']);

?>
<?php 
  if ( $timeframes ) { 
    if ( $location_selected ) { // location selected + has timeframes
        //set_query_var( 'templateData', $templateData); 
        include __DIR__ . '/timeframe-calendar.php'; //@TODO: This is not ideal, should use same system as other templates 
    } elseif ( ! $location_selected  ) {  // no location selected+ has timeframes
      foreach ($timeframes as $timeframe ) { 
        set_query_var( 'timeframe', $timeframe );
        cb_get_template_part( 'timeframe', 'withlocation' ); // file: timeframe-widthlocation.php
      } 
    } // $location_selected 
  } else {
    ?>
		<div class="cb-status cb-availability-status cb-no-residency"><?php echo ( $noResultText ); ?>
<?php } // end if ($timeframes) ?>
