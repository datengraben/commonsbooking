<?php


namespace CommonsBooking\API\GBFS;

use CommonsBooking\Repository\Location;
use Exception;
use stdClass;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Base class which implements retrieval of basic data attributes of
 * GBFS spec.
 *
 * Note: When deriving from this class, implement \WP_REST_Controller::prepare_item_for_response,
 *       which is called in \BaseRoute::getItemData
 */
class BaseRoute extends \CommonsBooking\API\BaseRoute {

	/**
	 * Returns Rest Response with items.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_items( WP_REST_Request $request ): WP_REST_Response {
		$response                 = new stdClass();
		$response->data           = new stdClass();
		$response->data->stations = $this->getItemData( $request );
		$response->last_updated   = date( 'c' ); // ISO-8601 timestamp
		$response->ttl            = 60;
		$response->version        = '3.1-RC2';

		if ( WP_DEBUG ) {
			$this->validateData( $response );
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Returns item data array.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return array<int, mixed>
	 */
	public function getItemData( WP_REST_Request $request ): array {
		$data      = [];
		$locations = Location::get();

		foreach ( $locations as $location ) {
			try {
				$itemdata = $this->prepare_item_for_response( $location, $request );
				$data[]   = $itemdata->data;
			} catch ( Exception $exception ) {
				if ( WP_DEBUG ) {
					error_log( $exception->getMessage() );
				}
			}
		}

		return $data;
	}
}
