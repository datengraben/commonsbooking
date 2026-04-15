<?php
/**
 * Snippet Title:     Short human-readable title
 * Description:       One sentence: what it does and why it is useful.
 * Hook/Filter:       commonsbooking_<hook_name>
 * CB Version:        2.10+
 * Tested up to:      2.10.10
 * Author:            Your name or GitHub handle
 * Author URI:        https://github.com/yourhandle  (optional)
 * License:           GPL-2.0+
 * Requires Plugins:  other-plugin-slug  (optional, remove if not needed)
 */

// Do NOT paste this file as-is. Fill the header above and replace
// the example code below with your actual implementation.

// Example: replace with your callback function and hook registration.
// Use a unique prefix for your function name to avoid conflicts.
function myplugin_cb_example_callback() {
    // your code here
}
add_action( 'commonsbooking_before_booking-single', 'myplugin_cb_example_callback' );
