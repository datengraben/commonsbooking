<?php

namespace CommonsBooking\Tests\View;

use CommonsBooking\Tests\Wordpress\CustomPostTypeTest;
use CommonsBooking\View\Nearby;
use ReflectionMethod;

class NearbyTest extends CustomPostTypeTest {

	/** @var int Cologne cathedral area (origin). */
	private const ORIGIN_LAT = 50.9413035;
	private const ORIGIN_LON = 6.9581379978318;

	private int $originLocationId;
	private int $nearLocationId;
	private int $farLocationId;
	private int $nearItemId;

	/**
	 * Calls a protected/private static method of the Nearby class.
	 *
	 * @param string $method
	 * @param array  $args
	 *
	 * @return mixed
	 */
	private function invokeStatic( string $method, array $args ) {
		$reflection = new ReflectionMethod( Nearby::class, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( null, $args );
	}

	public function testPick_localWinsByDefault() {
		$this->assertEquals( '5', $this->invokeStatic( 'pick', array( '5', '10', false ) ) );
	}

	public function testPick_fallsBackToGlobalWhenLocalEmpty() {
		$this->assertEquals( '10', $this->invokeStatic( 'pick', array( '', '10', false ) ) );
	}

	public function testPick_globalOverridesLocalWhenEnabled() {
		$this->assertEquals( '10', $this->invokeStatic( 'pick', array( '5', '10', true ) ) );
	}

	public function testPick_globalOverrideFallsBackToLocalWhenGlobalEmpty() {
		$this->assertEquals( '5', $this->invokeStatic( 'pick', array( '5', '', true ) ) );
	}

	public function testCoordinatesFromLocationId_readsGeoMeta() {
		$coordinates = $this->invokeStatic( 'coordinatesFromLocationId', array( $this->originLocationId ) );
		$this->assertEqualsWithDelta( self::ORIGIN_LAT, $coordinates['lat'], 0.0001 );
		$this->assertEqualsWithDelta( self::ORIGIN_LON, $coordinates['lon'], 0.0001 );
	}

	public function testResolveOrigin_explicitLatLonWins() {
		$origin = $this->invokeStatic( 'resolveOrigin', array( array( 'lat' => '48.0', 'lon' => '11.0' ), 0 ) );
		$this->assertEquals( 48.0, $origin['lat'] );
		$this->assertEquals( 11.0, $origin['lon'] );
	}

	public function testResolveOrigin_readsFromMetaFields() {
		$postId = $this->createLocation( 'Meta origin' );
		update_post_meta( $postId, 'custom_lat', '48.1372' );
		update_post_meta( $postId, 'custom_lon', '11.5755' );

		$origin = $this->invokeStatic(
			'resolveOrigin',
			array( array( 'lat_meta' => 'custom_lat', 'lon_meta' => 'custom_lon' ), $postId )
		);
		$this->assertEqualsWithDelta( 48.1372, $origin['lat'], 0.0001 );
		$this->assertEqualsWithDelta( 11.5755, $origin['lon'], 0.0001 );
	}

	public function testResolveOrigin_inheritsFromLocationPost() {
		$origin = $this->invokeStatic( 'resolveOrigin', array( array(), $this->originLocationId ) );
		$this->assertEqualsWithDelta( self::ORIGIN_LAT, $origin['lat'], 0.0001 );
	}

	public function testGetLocationCandidates_returnsOnlyGeoReferencedLocations() {
		$candidates = $this->invokeStatic( 'getLocationCandidates', array() );
		$this->assertArrayHasKey( $this->originLocationId, $candidates );
		$this->assertArrayHasKey( $this->nearLocationId, $candidates );
		$this->assertArrayHasKey( $this->farLocationId, $candidates );
	}

	public function testGetNearbyLocations_filtersByDistanceAndExcludesOrigin() {
		$origin = array( 'lat' => self::ORIGIN_LAT, 'lon' => self::ORIGIN_LON );
		$config = array( 'max_distance' => 50.0, 'max_results' => 9, 'post_id' => $this->originLocationId );

		$results = $this->invokeStatic( 'getNearbyLocations', array( $origin, $config ) );
		$ids     = array_column( $results, 'id' );

		$this->assertContains( $this->nearLocationId, $ids );
		$this->assertNotContains( $this->farLocationId, $ids, 'Berlin is outside the 50km radius' );
		$this->assertNotContains( $this->originLocationId, $ids, 'Origin location must be excluded' );
	}

	public function testGetNearbyItems_mapsLocationsToItems() {
		$origin = array( 'lat' => self::ORIGIN_LAT, 'lon' => self::ORIGIN_LON );
		$config = array( 'max_distance' => 50.0, 'max_results' => 9, 'post_id' => 0 );

		$results = $this->invokeStatic( 'getNearbyItems', array( $origin, $config ) );
		$ids     = array_column( $results, 'id' );

		$this->assertContains( $this->nearItemId, $ids );
	}

	public function testShortcode_rendersEmptyMessageWhenNothingNearby() {
		// Reference point in the Gulf of Guinea - no locations anywhere near it.
		$output = Nearby::shortcode(
			array(
				'type'         => 'locations',
				'lat'          => '5.0',
				'lon'          => '5.0',
				'max_distance' => '5',
			)
		);
		$this->assertStringContainsString( 'cb-nearby-empty', $output );
	}

	public function testShortcode_rendersCarouselWithDistanceBadge() {
		$output = Nearby::shortcode(
			array(
				'type'         => 'locations',
				'lat'          => (string) self::ORIGIN_LAT,
				'lon'          => (string) self::ORIGIN_LON,
				'max_distance' => '50',
				'post_id'      => (string) $this->originLocationId,
			)
		);
		$this->assertStringContainsString( 'cb-nearby-track', $output );
		$this->assertStringContainsString( 'cb-nearby-distance', $output );
		$this->assertStringContainsString( 'Near location', $output );
	}

	protected function setUp(): void {
		parent::setUp();

		// Origin: Cologne.
		$this->originLocationId = $this->createLocation( 'Origin location' );
		update_post_meta( $this->originLocationId, 'geo_latitude', self::ORIGIN_LAT );
		update_post_meta( $this->originLocationId, 'geo_longitude', self::ORIGIN_LON );

		// Near: ~1 km from origin.
		$this->nearLocationId = $this->createLocation( 'Near location' );
		update_post_meta( $this->nearLocationId, 'geo_latitude', 50.9375 );
		update_post_meta( $this->nearLocationId, 'geo_longitude', 6.9700 );

		// Far: Berlin, ~477 km from origin.
		$this->farLocationId = $this->createLocation( 'Far location' );
		update_post_meta( $this->farLocationId, 'geo_latitude', 52.5162746 );
		update_post_meta( $this->farLocationId, 'geo_longitude', 13.3777041 );

		// An item bookable at the near location.
		$this->nearItemId = $this->createItem( 'Near item' );
		$this->createBookableTimeFrameIncludingCurrentDay( $this->nearLocationId, $this->nearItemId );
	}
}
