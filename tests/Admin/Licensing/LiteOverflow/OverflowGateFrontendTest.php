<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Licensing\LiteOverflow;

use MHMRentiva\Admin\Licensing\LiteOverflow\OverflowGate;
use MHMRentiva\Admin\Licensing\LiteOverflow\OverflowRegistry;
use WP_UnitTestCase;

final class OverflowGateFrontendTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		OverflowGate::register();
	}

	protected function tearDown(): void {
		delete_option( 'mhm_rentiva_lite_overflow_hidden' );
		parent::tearDown();
	}

	public function test_public_query_excludes_hidden_vehicle(): void {
		$keep = self::factory()->post->create( array( 'post_type' => 'vehicle', 'post_status' => 'publish' ) );
		$hide = self::factory()->post->create( array( 'post_type' => 'vehicle', 'post_status' => 'publish' ) );
		OverflowRegistry::set( 'vehicle', array( $hide ) );

		$q = new \WP_Query(
			array( 'post_type' => 'vehicle', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1 )
		);

		$this->assertContains( $keep, $q->posts );
		$this->assertNotContains( $hide, $q->posts );
	}

	public function test_admin_query_still_includes_hidden_vehicle(): void {
		$hide = self::factory()->post->create( array( 'post_type' => 'vehicle', 'post_status' => 'publish' ) );
		OverflowRegistry::set( 'vehicle', array( $hide ) );

		set_current_screen( 'edit-vehicle' ); // is_admin() === true
		$q = new \WP_Query(
			array( 'post_type' => 'vehicle', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1 )
		);
		set_current_screen( 'front' );

		$this->assertContains( $hide, $q->posts );
	}

	public function test_frontend_ajax_query_excludes_hidden_vehicle(): void {
		$keep = self::factory()->post->create( array( 'post_type' => 'vehicle', 'post_status' => 'publish' ) );
		$hide = self::factory()->post->create( array( 'post_type' => 'vehicle', 'post_status' => 'publish' ) );
		OverflowRegistry::set( 'vehicle', array( $hide ) );

		// Simulate admin-ajax.php: is_admin() true AND wp_doing_ajax() true.
		set_current_screen( 'edit-vehicle' );
		add_filter( 'wp_doing_ajax', '__return_true' );

		$q = new \WP_Query(
			array( 'post_type' => 'vehicle', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1 )
		);

		remove_filter( 'wp_doing_ajax', '__return_true' );
		set_current_screen( 'front' );

		$this->assertContains( $keep, $q->posts );
		$this->assertNotContains( $hide, $q->posts, 'front-end AJAX must still exclude hidden vehicles' );
	}
}
