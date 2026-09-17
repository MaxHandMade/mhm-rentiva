<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\AssetManager;
use WP_UnitTestCase;

final class KitAssetsTest extends WP_UnitTestCase {

	public function test_enqueue_kit_registers_the_package_handle_for_the_admin_surface(): void {
		$handle = AssetManager::enqueue_kit( 'admin' );

		$this->assertSame( 'mhmuicore-admin', $handle );
		$this->assertTrue( wp_style_is( 'mhmuicore-admin', 'enqueued' ) );
	}

	public function test_enqueue_kit_uses_this_plugins_own_copy_as_the_fallback_root(): void {
		AssetManager::enqueue_kit( 'front' );
		$src = wp_styles()->registered['mhmuicore-front']->src ?? '';

		$this->assertStringContainsString( 'react/front.css', $src );
	}

	public function test_version_literal_matches_the_installed_package(): void {
		$plugin = (string) file_get_contents( MHMRENTIVA_PLUGIN_PATH . 'mhm-rentiva.php' );
		preg_match( "/mhmuicore_register\(\s*'([^']+)'/", $plugin, $m );

		$this->assertSame( MHMUICORE_VERSION, $m[1] ?? '' );
	}
}
