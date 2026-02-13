<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Legacy;

use MHMRentiva\Admin\Testing\TestAdminPage;
use MHMRentiva\Admin\Utilities\Menu\Menu;
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
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	public function test_menu_shows_setup_and_about_submenus_by_default(): void {
		$this->reset_admin_menu_globals();
		Menu::add_menu();

		$submenu_slugs = $this->get_mhm_submenu_slugs();

		$this->assertContains( 'mhm-rentiva-setup', $submenu_slugs );
		$this->assertContains( 'mhm-rentiva-about', $submenu_slugs );
	}

	public function test_menu_shows_test_suite_submenu_when_registered(): void {
		$this->reset_admin_menu_globals();
		Menu::add_menu();
		TestAdminPage::add_menu_page();

		$submenu_slugs = $this->get_mhm_submenu_slugs();

		$this->assertContains( 'mhm-rentiva-tests', $submenu_slugs );
	}

	public function test_menu_keeps_core_settings_and_license_submenus(): void {
		$this->reset_admin_menu_globals();
		Menu::add_menu();

		$submenu_slugs = $this->get_mhm_submenu_slugs();

		$this->assertContains( 'mhm-rentiva-settings', $submenu_slugs );
		$this->assertContains( 'mhm-rentiva-license', $submenu_slugs );
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
}

