<?php


namespace CommonsBooking\API;

use Exception;
use RuntimeException;

use CommonsBooking\Repository\ApiShares;
use CommonsBooking\Settings\Settings;
use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Basic functionality for the different api routes.
 *
 * If you extend from this class, you need to implement the following public methods:
 *  - get_items
 *  - get_item
 *  - get_public_item_schema
 *
 * This class relies on WP rest-api.php implementations and another assumption is
 * that commonsbooking json schema files are in place.
 */
class BaseRoute extends WP_REST_Controller {

	const API_KEY_PARAM = 'apikey';

	// prefix of $id used in schemas (currently the URL of the Github repo)
	const SCHEMA_URL = 'https://github.com/wielebenwir/commons-api/blob/master/';

	// the location of the .schema.json files locally
	const SCHEMA_PATH = COMMONSBOOKING_PLUGIN_DIR . 'includes/commons-api-json-schema/';

	protected $schemaUrl;

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		$version   = '1';
		$namespace = COMMONSBOOKING_PLUGIN_SLUG . '/v' . $version;
		register_rest_route(
			$namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'args'                => array(),
					'permission_callback' => function () {
						return self::hasPermission();
					},
				),
			)
		);
		register_rest_route(
			$namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'args'                => array(
						'context' => array(
							'default' => 'view',
						),
					),
					'permission_callback' => function () {
						return self::hasPermission();
					},
				),
			)
		);

		register_rest_route(
			$namespace,
			'/' . $this->rest_base . '/schema',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_public_item_schema' ),
				'permission_callback' => function () {
					return self::hasPermission();
				},
			)
		);
	}

	/**
	 * Returns schema json for current route.
	 *
	 * @throws RuntimeException On missing schema files.
	 * @return string
	 */
	private function getSchemaJson(): string {
		$schema = file_get_contents( $this->schemaUrl ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( $schema === false ) {
			throw new RuntimeException( 'Could not retrieve schema json from ' . esc_url( $this->schemaUrl ) );
		}
		return $schema;
	}

	/**
	 * Adds schema-fields for output to current route (needed for /.../schema endpoint)
	 *
	 * @param array $schema Assoc array of schema json object.
	 * @return array
	 */
	public function add_additional_fields_schema( $schema ): array {
		$schemaArray = json_decode( $this->getSchemaJson(), true );

		return array_merge( $schema, $schemaArray );
	}

	/**
	 * Escapes JSON String for output.
	 *
	 * @param $string
	 *
	 * @return false|string
	 */
	public function escapeJsonString( $string ) {
		return substr( wp_json_encode( $string ), 1, - 1 ) ? : '';
	}

	/**
	 * Returns true if current request is allowed.
	 *
	 * @return bool
	 */
	public static function hasPermission(): bool {
		$isApiActive            = Settings::getOption( 'commonsbooking_options_api', 'api-activated' );
		$anonymousAccessAllowed = Settings::getOption( 'commonsbooking_options_api', 'apikey_not_required' );
		$apiKey                 = array_key_exists( self::API_KEY_PARAM, $_REQUEST ) ? sanitize_text_field( $_REQUEST[ self::API_KEY_PARAM ] ) : false;
		$apiShare               = ApiShares::getByKey( $apiKey );

		// Only if api is active we return something
		if ( $isApiActive ) {
			// if anonymous access is allowed, api shares are ignored
			if ( $anonymousAccessAllowed ) {
				return true;
			} else {
				// check if there is a valid api key submitted
				if ( $apiKey && $apiShare && $apiShare->isEnabled() ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Returns REST response for the given data.
	 *
	 * Response shape is verified against the JSON schema in the test suite
	 * (see the API schema-conformance tests) rather than at runtime, so the
	 * schema validator is not shipped with the plugin.
	 *
	 * @param object $data The response data.
	 * @return WP_REST_Response
	 */
	protected function respond_with_validation( $data ): WP_REST_Response {
		return new WP_REST_Response( $data, 200 );
	}
}
