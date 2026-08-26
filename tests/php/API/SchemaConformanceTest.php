<?php

namespace CommonsBooking\Tests\API;

use CommonsBooking\API\BaseRoute;
use CommonsBooking\Tests\CPTCreationTrait;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Verifies that the plugin's REST API responses conform to the shipped JSON schemas.
 *
 * The schema validator (opis/json-schema) is a dev-only dependency and is no
 * longer run destructively at runtime. Conformance is asserted here in CI, on
 * every PR, instead of relying on someone browsing the API with WP_DEBUG on.
 */
class SchemaConformanceTest extends CB_REST_UnitTestCase {

	use CPTCreationTrait;

	public function setUp(): void {
		parent::setUp();

		$locationId = $this->createLocation( 'Conformance Location', 'publish' );
		$itemId     = $this->createItem( 'Conformance Item', 'publish' );

		// A bookable timeframe covering the current day so the routes return data.
		$this->createTimeframe(
			$locationId,
			$itemId,
			strtotime( '-1 day' ),
			strtotime( '+30 days' )
		);
	}

	protected function tearDown(): void {
		$this->tearDownAllPosts();
		parent::tearDown();
	}

	/**
	 * All shipped schema files must be parseable JSON.
	 */
	public function testShippedSchemaFilesAreValidJson() {
		$dirs = array(
			BaseRoute::SCHEMA_PATH,
			COMMONSBOOKING_PLUGIN_DIR . 'includes/gbfs-json-schema/',
		);

		$count = 0;
		foreach ( $dirs as $dir ) {
			foreach ( glob( $dir . '*.json' ) as $file ) {
				$this->assertNotNull(
					json_decode( (string) file_get_contents( $file ) ),
					"Schema file is not valid JSON: $file"
				);
				++$count;
			}
		}
		$this->assertGreaterThan( 0, $count, 'No schema files were found to validate.' );
	}

	public function testItemsResponseMatchesSchema() {
		$this->assertResponseMatchesSchema(
			'/commonsbooking/v1/items',
			'commons-api.items.schema.json'
		);
	}

	public function testLocationsResponseMatchesSchema() {
		$this->assertResponseMatchesSchema(
			'/commonsbooking/v1/locations',
			'commons-api.locations.schema.json'
		);
	}

	public function testProjectsResponseMatchesSchema() {
		$this->assertResponseMatchesSchema(
			'/commonsbooking/v1/projects',
			'commons-api.projects.schema.json'
		);
	}

	/**
	 * Drives a REST endpoint and asserts its response validates against the given schema.
	 *
	 * @param string $endpoint   REST route to request.
	 * @param string $schemaFile Schema filename within the commons-api schema directory.
	 */
	private function assertResponseMatchesSchema( string $endpoint, string $schemaFile ): void {
		$request  = new \WP_REST_Request( 'GET', $endpoint );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status(), "Endpoint $endpoint did not return 200." );

		// Normalise to a decoded JSON structure, i.e. exactly what the API emits over the wire.
		$data = json_decode( (string) wp_json_encode( $response->get_data() ) );

		$validator = new Validator();
		$validator->resolver()->registerPrefix( BaseRoute::SCHEMA_URL, BaseRoute::SCHEMA_PATH );

		$result = $validator->validate(
			$data,
			(string) file_get_contents( BaseRoute::SCHEMA_PATH . $schemaFile )
		);

		if ( $result->hasError() ) {
			$errors = ( new ErrorFormatter() )->formatOutput( $result->error(), 'basic' );
			$this->fail(
				"Response for $endpoint does not match schema $schemaFile:\n"
				. wp_json_encode( $errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
			);
		}

		$this->addToAssertionCount( 1 );
	}
}
