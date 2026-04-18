<?php

namespace CommonsBooking\Tests\API;

/**
 * Golden-master snapshot assertions for API tests.
 *
 * Workflow:
 *   1. First test run: no fixture file exists → response is captured and saved.
 *      The test is marked as incomplete ("fixture generated").
 *   2. Subsequent runs: live response is normalized and compared against the
 *      saved fixture. Any shape change turns the test Red.
 *   3. To regenerate a fixture, delete the corresponding file and re-run.
 *
 * Normalization map (caller-supplied):
 *   [(string)$postId => 'POST_PLACEHOLDER', ...]
 *
 * Auto-normalizations applied to every response string value:
 *   - ISO-8601 timestamps  → "TIMESTAMP"
 *   - get_rest_url() value → "REST_URL/"
 *   - get_bloginfo('url')  → "SITE_URL"
 */
trait ApiSnapshotTrait {

	/**
	 * Directory that holds fixture files.
	 * Override in a test class if the trait is used outside tests/php/API/.
	 */
	protected static string $fixtureDir = __DIR__ . '/Fixtures';

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Load and JSON-decode a fixture file.
	 *
	 * @param string $name Filename inside $fixtureDir (e.g. 'rest-items.json')
	 */
	public function loadFixture( string $name ): mixed {
		$path = static::$fixtureDir . '/' . $name;
		$this->assertFileExists( $path, "Fixture file not found: $path" );
		$contents = file_get_contents( $path );
		$decoded  = json_decode( $contents, true );
		$this->assertNotNull( $decoded, "Fixture '$name' contains invalid JSON." );
		return $decoded;
	}

	/**
	 * Recursively replace dynamic values in $data with stable placeholders.
	 *
	 * @param mixed $data            Any PHP value (array, object, string, scalar)
	 * @param array $normalizationMap Additional replacements: [original => placeholder]
	 */
	public function normalizeForComparison( mixed $data, array $normalizationMap = [] ): mixed {
		if ( is_array( $data ) ) {
			$result = [];
			foreach ( $data as $key => $value ) {
				$result[ $key ] = $this->normalizeForComparison( $value, $normalizationMap );
			}
			return $result;
		}

		if ( is_object( $data ) ) {
			$result = [];
			foreach ( get_object_vars( $data ) as $key => $value ) {
				$result[ $key ] = $this->normalizeForComparison( $value, $normalizationMap );
			}
			return $result;
		}

		if ( is_string( $data ) ) {
			// Caller-supplied dynamic IDs / URLs.
			foreach ( $normalizationMap as $original => $placeholder ) {
				$data = str_replace( (string) $original, (string) $placeholder, $data );
			}

			// ISO-8601 timestamps (e.g. last_updated, last_reported, availability slots).
			$data = preg_replace(
				'/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+\-]\d{2}:\d{2}/',
				'TIMESTAMP',
				$data
			);

			// REST namespace URL prefix.
			$restUrl = rtrim( get_rest_url(), '/' ) . '/';
			if ( $restUrl !== '/' ) {
				$data = str_replace( $restUrl, 'REST_URL/', $data );
			}

			// Site URL.
			$siteUrl = get_bloginfo( 'url' );
			if ( $siteUrl ) {
				$data = str_replace( $siteUrl, 'SITE_URL', $data );
			}

			return $data;
		}

		return $data;
	}

	/**
	 * Assert live response matches stored fixture.
	 *
	 * On first call (no fixture file): saves the normalized response as the
	 * fixture and marks the test as incomplete so the developer knows to review
	 * and commit the generated file.
	 *
	 * @param string $fixtureName    Filename inside $fixtureDir (e.g. 'rest-items.json')
	 * @param mixed  $responseData   Raw API response (object, array, or scalar)
	 * @param array  $normalizationMap Dynamic-value replacements: [original => placeholder]
	 */
	public function assertMatchesApiFixture(
		string $fixtureName,
		mixed $responseData,
		array $normalizationMap = []
	): void {
		$path   = static::$fixtureDir . '/' . $fixtureName;
		$actual = $this->normalizeForComparison( $responseData, $normalizationMap );

		if ( ! file_exists( $path ) ) {
			$this->generateFixture( $path, $actual );
			$this->markTestIncomplete(
				"Fixture '$fixtureName' was generated. Review it and re-run the tests."
			);
			return;
		}

		$expected = $this->loadFixture( $fixtureName );
		$this->assertEquals(
			$expected,
			$actual,
			"API response does not match fixture '$fixtureName'.\n" .
			"If the change is intentional, delete the fixture file and re-run to regenerate."
		);
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	private function generateFixture( string $path, mixed $data ): void {
		$dir = dirname( $path );
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}
		file_put_contents(
			$path,
			json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);
	}
}
