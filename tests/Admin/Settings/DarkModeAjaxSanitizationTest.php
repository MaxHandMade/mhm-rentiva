<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;
use WPAjaxDieStopException;

final class DarkModeAjaxSanitizationTest extends WP_Ajax_UnitTestCase
{
	private int $admin_id;

	public function setUp(): void
	{
		parent::setUp();
		$this->admin_id = $this->factory->user->create(array('role' => 'administrator'));
		SettingsCore::register();

		$_POST = array();
		$_REQUEST = array();
	}

	public function tearDown(): void
	{
		delete_option('mhmrentiva_dark_mode');
		delete_option('mhmrentiva_settings');
		parent::tearDown();
	}

	public function test_ajax_accepts_legacy_on_value_and_normalizes_to_dark(): void
	{
		$response = $this->run_dark_mode_ajax('on');

		$this->assertTrue($response['success'] ?? false);
		$this->assertSame('dark', get_option('mhmrentiva_dark_mode', 'auto'));

		$settings = (array) get_option('mhmrentiva_settings', array());
		$this->assertSame('dark', $settings['mhmrentiva_dark_mode'] ?? null);
	}

	public function test_ajax_falls_back_to_auto_for_invalid_value(): void
	{
		update_option('mhmrentiva_dark_mode', 'auto');
		update_option(
			'mhmrentiva_settings',
			array(
				'mhmrentiva_dark_mode' => 'auto',
			)
		);

		$response = $this->run_dark_mode_ajax('<script>alert(1)</script>');

		$this->assertTrue($response['success'] ?? false);
		$this->assertSame('auto', get_option('mhmrentiva_dark_mode', 'light'));

		$settings = (array) get_option('mhmrentiva_settings', array());
		$this->assertSame('auto', $settings['mhmrentiva_dark_mode'] ?? null);
	}

	/**
	 * Regression test: toggling dark mode via the quick AJAX switcher must
	 * only touch mhmrentiva_dark_mode. It previously routed through
	 * SettingsSanitizer::sanitize() with 'current_active_tab' => 'general',
	 * which re-ran the entire General/Site-Info sanitizer on an input array
	 * that only contained the dark mode key — silently blanking
	 * contact_phone/contact_hours/support_email and resetting brand_name
	 * to get_bloginfo('name') on every toggle.
	 */
	public function test_ajax_does_not_clobber_unrelated_site_info_fields(): void
	{
		update_option(
			'mhmrentiva_settings',
			array(
				'mhmrentiva_dark_mode'     => 'auto',
				'mhmrentiva_brand_name'    => 'Custom Brand',
				'mhmrentiva_contact_phone' => '+90 555 123 45 67',
				'mhmrentiva_contact_hours' => '09:00 - 18:00',
				'mhmrentiva_support_email' => 'support@example.com',
			)
		);

		$response = $this->run_dark_mode_ajax('dark');

		$this->assertTrue($response['success'] ?? false);

		$settings = (array) get_option('mhmrentiva_settings', array());
		$this->assertSame('dark', $settings['mhmrentiva_dark_mode'] ?? null);
		$this->assertSame('Custom Brand', $settings['mhmrentiva_brand_name'] ?? null);
		$this->assertSame('+90 555 123 45 67', $settings['mhmrentiva_contact_phone'] ?? null);
		$this->assertSame('09:00 - 18:00', $settings['mhmrentiva_contact_hours'] ?? null);
		$this->assertSame('support@example.com', $settings['mhmrentiva_support_email'] ?? null);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function run_dark_mode_ajax(string $mode): array
	{
		wp_set_current_user($this->admin_id);

		$nonce = wp_create_nonce('mhmrentiva_dark_mode_nonce');

		$_POST['action'] = 'mhmrentiva_save_dark_mode';
		$_POST['nonce']  = $nonce;
		$_POST['mode']   = $mode;

		$_REQUEST = $_POST;

		try {
			$this->_handleAjax('mhmrentiva_save_dark_mode');
		} catch (WPAjaxDieContinueException | WPAjaxDieStopException $e) {
			// Expected in AJAX test context.
		}

		$decoded = json_decode($this->_last_response, true);
		$this->assertIsArray($decoded);

		return $decoded;
	}
}
