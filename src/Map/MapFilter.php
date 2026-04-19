<?php

namespace CommonsBooking\Map;

class MapFilter {

	/**
	 * @param array<int, mixed> $item_terms
	 * @param array<int, mixed> $category_groups
	 */
	protected static function check_item_terms_against_categories( array $item_terms, array $category_groups ): bool {
		$valid_groups_count = 0;

		foreach ( $category_groups as $group ) {
			foreach ( $item_terms as $term ) {
				if ( in_array( $term->term_id, $group ) ) {
					++$valid_groups_count;
					break;
				}
			}
		}

		return $valid_groups_count == count( $category_groups );
	}
}
