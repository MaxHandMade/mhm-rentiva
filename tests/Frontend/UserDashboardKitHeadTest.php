<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Core\AssetManager;
use WP_UnitTestCase;

/**
 * The kit's front stylesheet must be enqueued on wp_enqueue_scripts -- before
 * wp_head prints -- wherever the dashboard will render, not only at render
 * time. A render-time enqueue is printed in the footer, so on a page that
 * embeds [rentiva_user_dashboard] the cards painted unstyled first (measured
 * 2026-09-19: front.css in the body, user-dashboard.css in <head>).
 */
final class UserDashboardKitHeadTest extends WP_UnitTestCase {

	private string $handle = '';

	protected function setUp(): void {
		parent::setUp();
		$this->handle = AssetManager::kit_handle( 'front' );
		$this->assertSame( 'mhmuicore-front', $this->handle, 'Premise failed: the kit is not loaded.' );
		wp_dequeue_style( $this->handle );
	}

	protected function tearDown(): void {
		wp_dequeue_style( $this->handle );
		parent::tearDown();
	}

	private function visit_page_with( string $content ): void {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => 'somewhere-else',
				'post_content' => $content,
			)
		);
		$this->go_to( (string) get_permalink( $page_id ) );
		$this->assertTrue( is_page( $page_id ), 'Premise failed: the request did not resolve to the page.' );
		$this->assertFalse( is_page( 'panel' ), 'Premise failed: this must not be the /panel/ surface.' );
	}

	public function test_a_page_embedding_the_shortcode_enqueues_the_kit_on_wp_enqueue_scripts(): void {
		$this->visit_page_with( '<p>Intro</p>[rentiva_user_dashboard]' );

		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( wp_style_is( $this->handle, 'enqueued' ) );
	}

	public function test_a_page_embedding_the_block_enqueues_the_kit_on_wp_enqueue_scripts(): void {
		$this->visit_page_with( '<!-- wp:mhm-rentiva/user-dashboard /-->' );

		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( wp_style_is( $this->handle, 'enqueued' ) );
	}

	/**
	 * The negative control: without it the two tests above would pass just as
	 * well if the kit were enqueued on every page.
	 */
	public function test_a_page_without_the_dashboard_only_registers_the_kit(): void {
		$this->visit_page_with( '<p>Nothing to see</p>' );

		do_action( 'wp_enqueue_scripts' );

		$this->assertFalse( wp_style_is( $this->handle, 'enqueued' ) );
		// Registered, so the Elementor widget's get_style_depends() can name it.
		$this->assertTrue( wp_style_is( $this->handle, 'registered' ) );
	}

	public function test_register_kit_leaves_an_already_enqueued_kit_enqueued(): void {
		AssetManager::enqueue_kit( 'front' );

		$this->assertSame( $this->handle, AssetManager::register_kit( 'front' ) );
		$this->assertTrue( wp_style_is( $this->handle, 'enqueued' ) );
	}
}
