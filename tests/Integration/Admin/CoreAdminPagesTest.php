<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\About\About;
use MHMRentiva\Admin\Setup\SetupWizard;
use MHMRentiva\Admin\Testing\TestAdminPage;
use MHMRentiva\Admin\Utilities\Menu\Menu;
use WP_UnitTestCase;

final class CoreAdminPagesTest extends WP_UnitTestCase {

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

	public function test_core_admin_submenus_require_manage_options_capability(): void {
		$this->reset_admin_menu_globals();
		Menu::add_menu();
		TestAdminPage::add_menu_page();

		$setup_item   = $this->get_mhm_submenu_item_by_slug( 'mhm-rentiva-setup' );
		$about_item   = $this->get_mhm_submenu_item_by_slug( 'mhm-rentiva-about' );
		$tests_item   = $this->get_mhm_submenu_item_by_slug( 'mhm-rentiva-tests' );
		$license_item = $this->get_mhm_submenu_item_by_slug( 'mhm-rentiva-license' );

		$this->assertIsArray( $setup_item );
		$this->assertIsArray( $about_item );
		$this->assertIsArray( $tests_item );
		$this->assertIsArray( $license_item );

		$this->assertSame( 'manage_options', $setup_item[1] );
		$this->assertSame( 'manage_options', $about_item[1] );
		$this->assertSame( 'manage_options', $tests_item[1] );
		$this->assertSame( 'manage_options', $license_item[1] );
	}

	public function test_setup_about_and_test_suite_callback_classes_are_available(): void {
		$this->assertTrue( class_exists( SetupWizard::class ) );
		$this->assertTrue( method_exists( SetupWizard::class, 'render_page' ) );

		$this->assertTrue( class_exists( About::class ) );
		$this->assertTrue( method_exists( About::class, 'render_page' ) );

		$this->assertTrue( class_exists( TestAdminPage::class ) );
		$this->assertTrue( method_exists( TestAdminPage::class, 'render_page' ) );
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

	/**
	 * @return array<int,mixed>|null
	 */
	private function get_mhm_submenu_item_by_slug( string $slug ): ?array {
		global $submenu;

		if ( ! isset( $submenu['mhm-rentiva'] ) || ! is_array( $submenu['mhm-rentiva'] ) ) {
			return null;
		}

		foreach ( $submenu['mhm-rentiva'] as $item ) {
			if ( is_array( $item ) && isset( $item[2] ) && $item[2] === $slug ) {
				return $item;
			}
		}

		return null;
	}
}
