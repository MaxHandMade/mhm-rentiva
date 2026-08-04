<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Blocks\BlockRegistry;
use WP_UnitTestCase;

/**
 * T8 Görev 17 (browser-verified, surface 3): AssetManager::should_load_assets()
 * decided purely by sniffing post_content for the literal '[rentiva_' substring
 * (AssetManager.php:1300-1320, pre-fix). Gutenberg block markup
 * (`<!-- wp:mhm-rentiva/vehicles-grid /-->`) never contains that substring, so a
 * page built with the plugin's own block and NO shortcode never enqueued
 * 'mhm-rentiva-vehicle-interactions' -- the handle that binds
 * .mhm-vehicle-favorite-btn and the compare button. Favourite/compare rendered
 * with correct server-side state and a completely clean console, but did
 * nothing on click: a silent dead button, made harder to notice because the
 * block brings its own CSS `style` handle regardless (see BlockRegistry::
 * render_callback()), so the page looked visually correct.
 *
 * Fix: should_load_assets() now ALSO recognises the plugin's own registered
 * blocks anywhere in the post content, sourced from
 * BlockRegistry::get_block_names() (never a second hardcoded copy of the slug
 * list) via BlockRegistry::content_has_registered_block(). Covers nested/inner
 * blocks and one level of reusable-block/synced-pattern (core/block ref)
 * resolution; does not cover core/template-part (see that method's docblock).
 *
 * @covers \MHMRentiva\Admin\Core\AssetManager::should_load_assets
 * @covers \MHMRentiva\Admin\Core\AssetManager::enqueue_frontend_assets
 * @covers \MHMRentiva\Blocks\BlockRegistry::get_block_names
 * @covers \MHMRentiva\Blocks\BlockRegistry::content_has_registered_block
 */
final class BlockAssetGateTest extends WP_UnitTestCase {

	private const INTERACTIONS_HANDLE = 'mhm-rentiva-vehicle-interactions';

	protected function setUp(): void {
		parent::setUp();
		$this->reset_handle();

		// enqueue_frontend_assets() only enqueues the handle when it is
		// already REGISTERED (AssetManager.php:317) -- registration happens
		// on `init` via register_common_assets(). Other test classes in this
		// same process (see ToastDependencyTest::reset_handles()) deregister
		// it in their own tearDown(), so re-register unconditionally here:
		// this test's outcome must depend only on should_load_assets(), never
		// on process-wide test ordering.
		AssetManager::register_common_assets();
	}

	protected function tearDown(): void {
		$this->reset_handle();
		$GLOBALS['post'] = null;
		parent::tearDown();
	}

	/**
	 * (a) Block-only page: the exact bug. The block's own HTML comment never
	 * contains '[rentiva_', but the gate must still fire.
	 */
	public function test_block_only_page_enqueues_interactions_script(): void {
		$this->go_to_post_with_content( '<!-- wp:mhm-rentiva/vehicles-grid {"featured":true} /-->' );

		AssetManager::enqueue_frontend_assets();

		$this->assertTrue(
			wp_script_is( self::INTERACTIONS_HANDLE, 'enqueued' ),
			'A page built with only the vehicles-grid BLOCK (no shortcode anywhere) must still enqueue the interactions script -- otherwise favourite/compare buttons render but do nothing on click.'
		);
	}

	/**
	 * (b) Shortcode-only page: pre-existing behaviour, must not regress.
	 */
	public function test_shortcode_only_page_still_enqueues_interactions_script(): void {
		$this->go_to_post_with_content( '[rentiva_vehicles_grid featured="1"]' );

		AssetManager::enqueue_frontend_assets();

		$this->assertTrue(
			wp_script_is( self::INTERACTIONS_HANDLE, 'enqueued' ),
			'Regression: a shortcode-only page must keep enqueuing the interactions script exactly as before this fix.'
		);
	}

	/**
	 * (c) Neither block nor shortcode: must NOT load. This is the performance
	 * guarantee -- the fix must not turn into "load on every page".
	 */
	public function test_page_without_block_or_shortcode_does_not_enqueue(): void {
		$this->go_to_post_with_content( '<!-- wp:paragraph --><p>Just a normal page, nothing MHM Rentiva about it.</p><!-- /wp:paragraph -->' );

		AssetManager::enqueue_frontend_assets();

		$this->assertFalse(
			wp_script_is( self::INTERACTIONS_HANDLE, 'enqueued' ),
			'A page with neither a plugin block nor a plugin shortcode must NOT enqueue the interactions script -- loading it unconditionally would be a performance regression on every unrelated page on the site.'
		);
	}

	/**
	 * Sibling of (c): a page that has OTHER (non-plugin) blocks must not
	 * false-positive just because has_blocks() is true.
	 */
	public function test_unrelated_core_block_does_not_enqueue(): void {
		$this->go_to_post_with_content( '<!-- wp:group --><div class="wp-block-group"><!-- wp:heading --><h2>Hello</h2><!-- /wp:heading --></div><!-- /wp:group -->' );

		AssetManager::enqueue_frontend_assets();

		$this->assertFalse(
			wp_script_is( self::INTERACTIONS_HANDLE, 'enqueued' ),
			'Core/unrelated blocks must not trip the gate -- only this plugin\'s own mhm-rentiva/* blocks should.'
		);
	}

	/**
	 * A plugin block NESTED inside an unrelated wrapper block (e.g. Group)
	 * must still be found -- parse_blocks() nests innerBlocks recursively and
	 * the walk must follow it, not just scan the top level.
	 */
	public function test_nested_plugin_block_enqueues(): void {
		$this->go_to_post_with_content( '<!-- wp:group --><div class="wp-block-group"><!-- wp:mhm-rentiva/vehicles-grid /--></div><!-- /wp:group -->' );

		AssetManager::enqueue_frontend_assets();

		$this->assertTrue(
			wp_script_is( self::INTERACTIONS_HANDLE, 'enqueued' ),
			'A plugin block nested inside a wrapper block (e.g. Group) must still be detected.'
		);
	}

	/**
	 * Reusable block / synced pattern: the page's own post_content holds only
	 * a `core/block` ref -- the real block markup lives one level down, in the
	 * referenced wp_block post. Core's own has_block() explicitly does not
	 * resolve this (see its docblock); this plugin's gate does, one level deep.
	 */
	public function test_plugin_block_inside_reusable_block_is_detected(): void {
		$reusable_block_post = $this->factory->post->create_and_get(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:mhm-rentiva/vehicles-grid /-->',
			)
		);

		$this->go_to_post_with_content( sprintf( '<!-- wp:block {"ref":%d} /-->', $reusable_block_post->ID ) );

		AssetManager::enqueue_frontend_assets();

		$this->assertTrue(
			wp_script_is( self::INTERACTIONS_HANDLE, 'enqueued' ),
			'A plugin block placed inside a reusable block / synced pattern (core/block ref) must still be detected.'
		);
	}

	/**
	 * BlockRegistry::get_block_names() is the single source of truth the gate
	 * reads. This proves the list is real (not an empty/broken accessor) and
	 * documents the exact fully-qualified name shape callers can rely on.
	 */
	public function test_block_names_include_vehicles_grid(): void {
		$this->assertContains( 'mhm-rentiva/vehicles-grid', BlockRegistry::get_block_names() );
	}

	/**
	 * Simulates visiting a page with the given content -- through the REAL
	 * main-query path (go_to()), not a hand-spliced `global $post` assignment.
	 *
	 * This matters beyond style: should_load_assets() also short-circuits true
	 * on is_account_page(), which reads the QUERIED object, not just
	 * `global $post`. A previous test elsewhere in the suite may leave the
	 * main query pointed at the WooCommerce My Account page; splicing
	 * `global $post` alone does not reset that, so a later test here could
	 * inherit a stale "on the account page" query state and false-positive.
	 * go_to() resets the whole query (and populates `global $post` as a
	 * side effect), which is the same thing a real page view does.
	 */
	private function go_to_post_with_content( string $content ): void {
		$post_id = $this->factory->post->create( array( 'post_content' => $content ) );
		$this->go_to( (string) get_permalink( $post_id ) );
	}

	/**
	 * Resets ALL of WP_Scripts' tracking for this handle, not just the queue.
	 *
	 * wp_dequeue_script()/wp_deregister_script() alone are not enough here:
	 * WP_Dependencies also keeps a `done` list (handles already fully
	 * "printed" during THIS PHP process), and wp_script_is(..., 'enqueued')
	 * reads that too. Some other integration test elsewhere in the suite
	 * exercises a real print pipeline for 'mhm-rentiva-vehicle-interactions'
	 * and leaves some OTHER handle enqueued that lists it as a dependency --
	 * confirmed empirically by reading WP core's own class-wp-dependencies.php:
	 * the 'enqueued' case of WP_Dependencies::query() is not a direct queue
	 * membership check, it also calls recurse_deps( $this->queue, $handle ),
	 * so wp_script_is( self::INTERACTIONS_HANDLE, 'enqueued' ) reads true
	 * whenever ANY handle currently in the queue depends on it -- even if
	 * this handle itself was never directly enqueued. Five live handles
	 * declare it as a dependency (VehiclesList, MyFavorites, FeaturedVehicles,
	 * SearchResults's get_js_dependencies(), plus AccountController/
	 * AccountRenderer's direct enqueues), and an earlier, unrelated test
	 * elsewhere in the 1500+-test suite leaves one of them enqueued without
	 * cleaning up. Neither ToastDependencyTest nor AssetManagerComponentJsTest
	 * could have caught this: the former only asserts on `registered`/`deps`,
	 * never 'enqueued'; the latter asserts 'enqueued' but on three different,
	 * unrelated handles.
	 *
	 * Fixed by deriving the dependent-handle list from the LIVE registered
	 * dependency graph at reset time, not a hand-maintained copy of those five
	 * names -- the same "read the source of truth, don't hardcode a copy that
	 * can drift" principle as the production fix itself.
	 */
	private function reset_handle(): void {
		$scripts = wp_scripts();

		$dependents = array();
		foreach ( $scripts->registered as $dependent_handle => $dependency ) {
			if ( in_array( self::INTERACTIONS_HANDLE, (array) $dependency->deps, true ) ) {
				$dependents[] = $dependent_handle;
			}
		}

		foreach ( $dependents as $dependent_handle ) {
			wp_dequeue_script( $dependent_handle );
			wp_deregister_script( $dependent_handle );
		}

		wp_dequeue_script( self::INTERACTIONS_HANDLE );
		wp_deregister_script( self::INTERACTIONS_HANDLE );
	}
}
