<?php

namespace CommonsBooking\Tests\Service;

use CommonsBooking\Model\Timeframe as TimeframeModel;
use CommonsBooking\Service\WPInventoryImport;
use CommonsBooking\Tests\Wordpress\CustomPostTypeTest;
use CommonsBooking\Wordpress\CustomPostType\Item;
use CommonsBooking\Wordpress\CustomPostType\Location;
use CommonsBooking\Wordpress\CustomPostType\Timeframe;

class WPInventoryImportTest extends CustomPostTypeTest {

	private function tableName(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpinventory';
	}

	private function createWpInventoryTable(): void {
		global $wpdb;
		$table = $this->tableName();
		$wpdb->query( "CREATE TABLE IF NOT EXISTS {$table} (inventory_id INT PRIMARY KEY, inventory_name VARCHAR(255))" );
	}

	private function seed( int $id, string $name ): void {
		global $wpdb;
		$wpdb->insert( $this->tableName(), array( 'inventory_id' => $id, 'inventory_name' => $name ) );
	}

	public function testDetectsWpInventory() {
		$this->assertTrue( WPInventoryImport::isWpInventoryActive() );
	}

	public function testImportCreatesBookableItems() {
		$this->seed( 101, 'Cargo bike' );
		$this->seed( 102, 'Drill' );

		$imported = WPInventoryImport::import();
		$this->assertEquals( 2, $imported );

		// One default location was created.
		$locations = get_posts(
			array(
				'post_type'   => Location::$postType,
				'post_status' => 'any',
				'meta_key'    => WPInventoryImport::DEFAULT_LOCATION_META,
				'meta_value'  => 1,
				'fields'      => 'ids',
				'numberposts' => -1,
			)
		);
		$this->assertCount( 1, $locations );
		$locationId = (int) $locations[0];

		// One item per source row, linked back to WP Inventory.
		$items = get_posts(
			array(
				'post_type'   => Item::$postType,
				'post_status' => 'any',
				'meta_key'    => WPInventoryImport::SOURCE_ID_META,
				'fields'      => 'ids',
				'numberposts' => -1,
			)
		);
		$this->assertCount( 2, $items );

		// Each item has a bookable timeframe bound to the default location.
		foreach ( $items as $itemId ) {
			$timeframes = get_posts(
				array(
					'post_type'   => Timeframe::$postType,
					'post_status' => 'any',
					'meta_query'  => array(
						array(
							'key'   => TimeframeModel::META_ITEM_ID,
							'value' => $itemId,
						),
					),
					'numberposts' => -1,
				)
			);
			$this->assertCount( 1, $timeframes );
			$timeframeId = $timeframes[0]->ID;
			$this->assertEquals( Timeframe::BOOKABLE_ID, (int) get_post_meta( $timeframeId, 'type', true ) );
			$this->assertEquals( $locationId, (int) get_post_meta( $timeframeId, TimeframeModel::META_LOCATION_ID, true ) );
		}
	}

	public function testImportIsIdempotent() {
		$this->seed( 201, 'Trailer' );

		$this->assertEquals( 1, WPInventoryImport::import() );
		// Second run must not duplicate anything.
		$this->assertEquals( 0, WPInventoryImport::import() );

		$items = get_posts(
			array(
				'post_type'   => Item::$postType,
				'post_status' => 'any',
				'meta_key'    => WPInventoryImport::SOURCE_ID_META,
				'meta_value'  => 201,
				'fields'      => 'ids',
				'numberposts' => -1,
			)
		);
		$this->assertCount( 1, $items );
	}

	protected function setUp(): void {
		parent::setUp();
		$this->createWpInventoryTable();
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $this->tableName() );
		delete_option( WPInventoryImport::DONE_OPTION );
		parent::tearDown();
	}
}
