<?php
/**
 * Snippet Title:    Show 3 months in the mobile booking calendar
 * Description:      The mobile calendar defaults to 1 month. This snippet
 *                   increases it to 3 months, giving mobile users a wider
 *                   booking window without switching to the desktop view.
 *                   Adjust the return value to any positive integer.
 * Hook/Filter:      commonsbooking_mobile_calendar_month_count
 * CB Version:       2.10.5+
 * Tested up to:     2.10.10
 * Author:           CommonsBooking contributors
 * License:          GPL-2.0+
 */

add_filter( 'commonsbooking_mobile_calendar_month_count', fn (): int => 3 );
