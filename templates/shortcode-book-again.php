<?php
/**
 * Template: shortcode-book-again
 * Shortcode [cb_book_again]
 *
 * Renders two lists of item suggestions for the current user:
 *  - "Book again": items the user has booked before that are free in the window.
 *  - "Did you try …": items sharing a category with the user's items, free too.
 *
 * Provided via global $templateData:
 *  - bookAgain: array of [ 'itemId' => int, 'locationId' => ?int ]
 *  - similar:   array of [ 'itemId' => int, 'locationId' => ?int ]
 *  - window:    one of the BookingRecommendation::WINDOW_* constants
 */

use CommonsBooking\Model\Item;
use CommonsBooking\View\BookAgain;
use CommonsBooking\Wordpress\CustomPostType\Item as ItemCPT;

global $templateData;

$window      = $templateData['window'] ?? '';
$windowLabel = BookAgain::getWindowLabel( $window );

/**
 * Renders a single suggestion card.
 *
 * @param array  $suggestion  [ 'itemId' => int, 'locationId' => ?int ]
 * @param string $windowLabel Availability label, e.g. "free today".
 *
 * @return void
 */
$renderCard = function ( array $suggestion, string $windowLabel ): void {
	$itemPost = get_post( $suggestion['itemId'] );
	if ( ! $itemPost ) {
		return;
	}
	$item = new Item( $itemPost );

	// Deep link to the item page (with the free location preselected if known).
	$bookUrl = get_the_permalink( $item->ID );
	if ( ! empty( $suggestion['locationId'] ) ) {
		$bookUrl = add_query_arg( 'cb-location', (int) $suggestion['locationId'], $bookUrl );
	}

	$categoryList = get_the_term_list(
		$item->ID,
		ItemCPT::getTaxonomyName(),
		'',
		', '
	);
	?>
	<div class="cb-book-again-item cb-list-header">
		<?php echo commonsbooking_sanitizeHTML( $item->thumbnail( 'cb_listing_medium' ) ); ?>
		<div class="cb-list-info">
			<h3><?php echo commonsbooking_sanitizeHTML( $item->titleLink() ); ?></h3>
			<?php if ( $categoryList && ! is_wp_error( $categoryList ) ) { ?>
				<div class="cb-book-again-categories"><?php echo commonsbooking_sanitizeHTML( $categoryList ); ?></div>
			<?php } ?>
			<div class="cb-status cb-availability-status cb-status-available cb-notice-small">
				<?php echo esc_html( $windowLabel ); ?>
			</div>
			<a class="cb-button cb-book-again-button" href="<?php echo esc_url( $bookUrl ); ?>">
				<?php echo esc_html__( 'Book again', 'commonsbooking' ); ?>
			</a>
		</div>
	</div><!-- .cb-book-again-item -->
	<?php
};

?>
<div class="cb-wrapper cb-book-again template-shortcode-book-again">

	<?php if ( ! empty( $templateData['bookAgain'] ) ) { ?>
		<div class="cb-book-again-section cb-book-again-previous">
			<h2><?php echo esc_html__( 'Book again', 'commonsbooking' ); ?></h2>
			<div class="cb-book-again-list">
				<?php
				foreach ( $templateData['bookAgain'] as $suggestion ) {
					$renderCard( $suggestion, $windowLabel );
				}
				?>
			</div>
		</div>
	<?php } ?>

	<?php if ( ! empty( $templateData['similar'] ) ) { ?>
		<div class="cb-book-again-section cb-book-again-similar">
			<h2><?php echo esc_html__( 'Did you try …', 'commonsbooking' ); ?></h2>
			<div class="cb-book-again-list">
				<?php
				foreach ( $templateData['similar'] as $suggestion ) {
					$renderCard( $suggestion, $windowLabel );
				}
				?>
			</div>
		</div>
	<?php } ?>

</div><!-- .cb-book-again -->
