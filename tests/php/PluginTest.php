<?php

namespace CommonsBooking\Tests;

use CommonsBooking\Model\CustomPost;
use CommonsBooking\Plugin;
use CommonsBooking\Tests\Wordpress\CustomPostTypeTest;
use CommonsBooking\Wordpress\CustomPostType\CustomPostType;

class PluginTest extends CustomPostTypeTest {

	private $postIDs = [];

	/**
	 * wp_sitemaps_post_types filter must include public content CPTs and
	 * exclude admin-only CPTs that should never be indexed.
	 */
	public function testSitemapPostTypesFilterExcludesAdminCpts() {
		( new Plugin() )->init();

		// Simulate what WP core does: build the initial list from all registered CPTs
		$registered = array_filter(
			get_post_types( [], 'objects' ),
			static fn( $cpt ) => $cpt->public
		);

		$filtered = apply_filters( 'wp_sitemaps_post_types', $registered );

		// Admin-only CPTs must not be in the sitemap
		$this->assertArrayNotHasKey( 'cb_restriction', $filtered );
		$this->assertArrayNotHasKey( 'cb_map', $filtered );

		// Public content CPTs must remain
		$this->assertArrayHasKey( 'cb_item', $filtered );
		$this->assertArrayHasKey( 'cb_location', $filtered );
	}

	public function testGetCustomPostTypes() {
		$this->assertIsArray( Plugin::getCustomPostTypes() );
		// make sure, that we also have a model for each custom post type
		foreach ( Plugin::getCustomPostTypes() as $customPostType ) {
			// first, create a post of this type
			$post = wp_insert_post(
				[
					'post_type' => $customPostType,
					'post_title' => 'Test ' . $customPostType,
					'post_status' => 'publish',
				]
			);
			$this->assertIsInt( $post );
			$this->postIDs[] = $post;
			// then, try to get a model from the post. Every declared CPT should have a model
			$this->assertInstanceOf( CustomPost::class, CustomPostType::getModel( $post ) );
		}
	}

	protected function setUp(): void {
		parent::setUp();
	}

	protected function tearDown(): void {
		foreach ( $this->postIDs as $postID ) {
			wp_delete_post( $postID, true );
		}
		parent::tearDown();
	}
}
