<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Licensing;

use MHMRentiva\Admin\Licensing\LicenseAdmin;
use MHMRentiva\Admin\Licensing\LicenseManager;
use MHMRentiva\Admin\Licensing\UpgradeFunnelTelemetry;
use WP_UnitTestCase;

final class UpgradeCtaSurfaceTest extends WP_UnitTestCase
{
	private int $admin_user_id;
	private int $subscriber_user_id;

	protected function setUp(): void
	{
		parent::setUp();

		$this->admin_user_id = self::factory()->user->create(array('role' => 'administrator'));
		$this->subscriber_user_id = self::factory()->user->create(array('role' => 'subscriber'));

		wp_set_current_user($this->admin_user_id);
		update_option(LicenseManager::OPTION, array(), false);
		update_option('mhm_rentiva_disable_dev_mode', 1, false);
		delete_option('mhm_rentiva_upgrade_funnel_stats');
		UpgradeFunnelTelemetry::reset_request_guard_for_tests();
	}

	protected function tearDown(): void
	{
		delete_option('mhm_rentiva_upgrade_funnel_stats');
		wp_set_current_user(0);
		UpgradeFunnelTelemetry::reset_request_guard_for_tests();
		parent::tearDown();
	}

	public function test_register_adds_admin_post_tracking_hook(): void
	{
		LicenseAdmin::register();

		$this->assertNotFalse(
			has_action('admin_post_mhm_rentiva_track_upgrade_cta', array(LicenseAdmin::class, 'handle_track_upgrade_cta')),
			'LicenseAdmin must register admin_post_mhm_rentiva_track_upgrade_cta hook.'
		);

		$this->assertFalse(
			has_action('admin_post_nopriv_mhm_rentiva_track_upgrade_cta', array(LicenseAdmin::class, 'handle_track_upgrade_cta')),
			'Public nopriv upgrade tracking endpoint must not be registered.'
		);
	}

	public function test_lite_license_page_renders_tracked_upgrade_cta_url(): void
	{
		$admin = new LicenseAdmin();
		ob_start();
		$admin->render_page();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString('action=mhm_rentiva_track_upgrade_cta', $html);
		$this->assertStringContainsString('event=upgrade_cta_click_license_page', $html);
	}

	public function test_valid_nonce_and_capability_tracks_event(): void
	{
		$this->call_tracking_handler_for_tests(
			$this->admin_user_id,
			array(
				'event' => 'upgrade_cta_click_license_page',
				'_wpnonce' => wp_create_nonce('mhm_rentiva_track_upgrade_cta'),
			)
		);

		$this->assertSame(1, $this->get_event_count('upgrade_cta_click_license_page'));
	}

	public function test_invalid_nonce_does_not_track(): void
	{
		$this->call_tracking_handler_for_tests(
			$this->admin_user_id,
			array(
				'event' => 'upgrade_cta_click_license_page',
				'_wpnonce' => 'invalid-nonce',
			)
		);

		$this->assertSame(0, $this->get_event_count('upgrade_cta_click_license_page'));
	}

	public function test_invalid_event_is_ignored(): void
	{
		$this->call_tracking_handler_for_tests(
			$this->admin_user_id,
			array(
				'event' => 'evil_custom_event',
				'_wpnonce' => wp_create_nonce('mhm_rentiva_track_upgrade_cta'),
			)
		);

		$this->assertSame(0, $this->get_event_count('evil_custom_event'));
	}

	public function test_same_request_double_call_tracks_once(): void
	{
		$request = array(
			'event' => 'upgrade_cta_click_license_page',
			'_wpnonce' => wp_create_nonce('mhm_rentiva_track_upgrade_cta'),
		);

		$this->call_tracking_handler_for_tests($this->admin_user_id, $request);
		$this->call_tracking_handler_for_tests($this->admin_user_id, $request);

		$this->assertSame(1, $this->get_event_count('upgrade_cta_click_license_page'));
	}

	private function call_tracking_handler_for_tests(int $user_id, array $request): void
	{
		wp_set_current_user($user_id);

		if (! method_exists(LicenseAdmin::class, 'process_upgrade_cta_tracking_for_tests')) {
			$this->fail('LicenseAdmin::process_upgrade_cta_tracking_for_tests() is required for deterministic endpoint testing.');
		}

		LicenseAdmin::process_upgrade_cta_tracking_for_tests($request);
	}

	private function get_event_count(string $event): int
	{
		$stats = get_option('mhm_rentiva_upgrade_funnel_stats', array());
		$today = gmdate('Y-m-d');

		return (int) ($stats[$today][$event] ?? 0);
	}
}
