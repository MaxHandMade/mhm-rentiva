<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Growth;

use MHMRentiva\Admin\Growth\FunnelDashboard;
use MHMRentiva\Admin\Utilities\Menu\Menu;
use WP_UnitTestCase;

final class FunnelDashboardPageTest extends WP_UnitTestCase
{
	/**
	 * @var array<int|string,mixed>
	 */
	private array $menu_backup = array();

	/**
	 * @var array<int|string,mixed>
	 */
	private array $submenu_backup = array();

	private int $admin_user_id = 0;

	protected function setUp(): void
	{
		parent::setUp();
		delete_option('mhm_rentiva_upgrade_funnel_stats');

		global $menu, $submenu;
		$this->menu_backup = is_array($menu) ? $menu : array();
		$this->submenu_backup = is_array($submenu) ? $submenu : array();

		$this->admin_user_id = (int) self::factory()->user->create(array('role' => 'administrator'));
		wp_set_current_user($this->admin_user_id);
	}

	protected function tearDown(): void
	{
		delete_option('mhm_rentiva_upgrade_funnel_stats');

		global $menu, $submenu;
		$menu = $this->menu_backup;
		$submenu = $this->submenu_backup;

		wp_set_current_user(0);
		parent::tearDown();
	}

	public function test_growth_submenu_slug_is_registered_under_rentiva_menu(): void
	{
		$this->reset_admin_menu_globals();
		Menu::add_menu();
		FunnelDashboard::add_submenu_page();

		$submenu_slugs = $this->get_mhm_submenu_slugs();
		$this->assertContains('mhm-rentiva-growth-funnel', $submenu_slugs);
	}

	public function test_dashboard_renders_summary_cards_and_table_with_seeded_data(): void
	{
		$today = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
		update_option(
			'mhm_rentiva_upgrade_funnel_stats',
			array(
				$today => array(
					'license_page_view_lite' => 10,
					'upgrade_cta_click_license_page' => 2,
					'variant' => array(
						'A' => array('views' => 10, 'clicks' => 3),
						'B' => array('views' => 12, 'clicks' => 2),
					),
				),
			),
			false
		);

		ob_start();
		FunnelDashboard::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString('Upgrade Funnel', $html);
		$this->assertStringContainsString('License Page Views', $html);
		$this->assertStringContainsString('Upgrade CTA Clicks', $html);
		$this->assertStringContainsString('Conversion Rate', $html);
		$this->assertStringContainsString($today, $html);
		$this->assertStringContainsString('Variant Performance', $html);
		$this->assertStringContainsString('<td>A</td>', $html);
		$this->assertStringContainsString('<td>B</td>', $html);
		$this->assertStringContainsString('<td>10</td>', $html);
		$this->assertStringContainsString('<td>12</td>', $html);
		$this->assertMatchesRegularExpression('/30[\\.,]00%/', $html);
		$this->assertMatchesRegularExpression('/16[\\.,]67%/', $html);
	}

	public function test_dashboard_renders_empty_state_when_no_stats_exist(): void
	{
		delete_option('mhm_rentiva_upgrade_funnel_stats');

		ob_start();
		FunnelDashboard::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString('No funnel data found for the last 30 days.', $html);
		$this->assertStringContainsString('Variant Performance', $html);
		$this->assertStringContainsString('<td>A</td>', $html);
		$this->assertStringContainsString('<td>B</td>', $html);
	}

	private function reset_admin_menu_globals(): void
	{
		global $menu, $submenu;
		$menu = array();
		$submenu = array();
	}

	/**
	 * @return list<string>
	 */
	private function get_mhm_submenu_slugs(): array
	{
		global $submenu;

		if (! isset($submenu['mhm-rentiva']) || ! is_array($submenu['mhm-rentiva'])) {
			return array();
		}

		$slugs = array();
		foreach ($submenu['mhm-rentiva'] as $item) {
			if (is_array($item) && isset($item[2]) && is_string($item[2])) {
				$slugs[] = $item[2];
			}
		}

		return $slugs;
	}
}
