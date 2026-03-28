<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Utilities\Dashboard\DashboardPage;
use WP_Scripts;
use WP_UnitTestCase;

final class DashboardScriptDepsTest extends WP_UnitTestCase
{
	private int $admin_user_id = 0;

	public function setUp(): void
	{
		parent::setUp();

		$this->admin_user_id = (int) $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($this->admin_user_id);
		set_current_screen('toplevel_page_mhm-rentiva');
		$this->reset_dashboard_handles();
	}

	public function tearDown(): void
	{
		$this->reset_dashboard_handles();
		wp_set_current_user(0);
		set_current_screen('front');
		parent::tearDown();
	}

	public function test_asset_manager_dashboard_script_includes_sortable_dependency(): void
	{
		AssetManager::enqueue_admin_assets();

		$scripts = wp_scripts();
		$this->assertInstanceOf(WP_Scripts::class, $scripts);
		$this->assertArrayHasKey('mhm-dashboard', $scripts->registered);
		$this->assertContains(
			'jquery-ui-sortable',
			$scripts->registered['mhm-dashboard']->deps,
			'mhm-dashboard must include jquery-ui-sortable dependency on dashboard screen.'
		);
	}

	public function test_dashboard_page_enqueue_keeps_sortable_dependency_when_handle_exists(): void
	{
		AssetManager::enqueue_admin_assets();
		DashboardPage::enqueue_scripts('toplevel_page_mhm-rentiva');

		$scripts = wp_scripts();
		$this->assertInstanceOf(WP_Scripts::class, $scripts);
		$this->assertArrayHasKey('mhm-dashboard', $scripts->registered);
		$this->assertContains(
			'jquery-ui-sortable',
			$scripts->registered['mhm-dashboard']->deps,
			'mhm-dashboard dependency list must keep sortable even when registered from multiple places.'
		);
	}

	private function reset_dashboard_handles(): void
	{
		wp_dequeue_script('mhm-dashboard');
		wp_deregister_script('mhm-dashboard');
		wp_dequeue_script('chart-js');
		wp_deregister_script('chart-js');
	}
}
