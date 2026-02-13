<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Legacy;

use MHMRentiva\Admin\Utilities\Menu\Menu;
use MHMRentiva\Plugin;
use WP_UnitTestCase;

final class LegacyFeatureFlagTest extends WP_UnitTestCase {

	/**
	 * @var array<int|string,mixed>
	 */
	private array $menu_backup = array();

	/**
	 * @var array<int|string,mixed>
	 */
	private array $submenu_backup = array();
	private int $admin_user_id = 0;

	public function setUp(): void {
		parent::setUp();

		global $menu, $submenu;
		$this->menu_backup    = is_array( $menu ) ? $menu : array();
		$this->submenu_backup = is_array( $submenu ) ? $submenu : array();
		$this->admin_user_id  = (int) $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_user_id );
	}

	public function tearDown(): void {
		global $menu, $submenu;
		$menu    = $this->menu_backup;
		$submenu = $this->submenu_backup;

		remove_all_filters( 'mhm_rentiva_legacy_feature_enabled' );
		remove_all_filters( 'mhm_rentiva_legacy_setup_wizard_enabled' );
		remove_all_filters( 'mhm_rentiva_legacy_about_page_enabled' );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	public function test_menu_hides_legacy_submenus_when_global_legacy_flag_is_disabled(): void {
		add_filter(
			'mhm_rentiva_legacy_feature_enabled',
			static function ( bool $enabled, string $feature ): bool {
				return false;
			},
			10,
			2
		);

		$this->reset_admin_menu_globals();
		Menu::add_menu();

		$submenu_slugs = $this->get_mhm_submenu_slugs();

		$this->assertNotContains( 'mhm-rentiva-setup', $submenu_slugs );
		$this->assertNotContains( 'mhm-rentiva-about', $submenu_slugs );
	}

	public function test_menu_shows_legacy_submenus_when_feature_flags_are_enabled(): void {
		$this->reset_admin_menu_globals();
		Menu::add_menu();

		$submenu_slugs = $this->get_mhm_submenu_slugs();

		$this->assertContains( 'mhm-rentiva-setup', $submenu_slugs );
		$this->assertContains( 'mhm-rentiva-about', $submenu_slugs );
	}

	public function test_plugin_feature_specific_filter_can_override_global_flag(): void {
		add_filter(
			'mhm_rentiva_legacy_feature_enabled',
			static function ( bool $enabled, string $feature ): bool {
				return false;
			},
			10,
			2
		);
		add_filter(
			'mhm_rentiva_legacy_setup_wizard_enabled',
			static function ( bool $enabled ): bool {
				return true;
			}
		);

		$this->assertTrue( $this->is_legacy_feature_enabled( 'setup_wizard' ) );
		$this->assertFalse( $this->is_legacy_feature_enabled( 'about_page' ) );
	}

	private function reset_admin_menu_globals(): void {
		global $menu, $submenu;
		$menu    = array();
		$submenu = array();
	}

	/**
	 * @return list<string>
	 */
	private function get_mhm_submenu_slugs(): array {
		global $submenu;

		if ( ! isset( $submenu['mhm-rentiva'] ) || ! is_array( $submenu['mhm-rentiva'] ) ) {
			return array();
		}

		$slugs = array();
		foreach ( $submenu['mhm-rentiva'] as $item ) {
			if ( is_array( $item ) && isset( $item[2] ) && is_string( $item[2] ) ) {
				$slugs[] = $item[2];
			}
		}

		return $slugs;
	}

	private function is_legacy_feature_enabled( string $feature ): bool {
		$reflection = new \ReflectionClass( Plugin::class );
		$plugin     = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'is_legacy_feature_enabled' );
		$method->setAccessible( true );

		return (bool) $method->invoke( $plugin, $feature );
	}
}
