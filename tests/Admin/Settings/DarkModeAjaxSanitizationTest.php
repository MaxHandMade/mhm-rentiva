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
		delete_option('mhm_rentiva_dark_mode');
		delete_option('mhm_rentiva_settings');
		parent::tearDown();
	}

	public function test_ajax_accepts_legacy_on_value_and_normalizes_to_dark(): void
	{
		$response = $this->run_dark_mode_ajax('on');

		$this->assertTrue($response['success'] ?? false);
		$this->assertSame('dark', get_option('mhm_rentiva_dark_mode', 'auto'));

		$settings = (array) get_option('mhm_rentiva_settings', array());
		$this->assertSame('dark', $settings['mhm_rentiva_dark_mode'] ?? null);
	}

	public function test_ajax_falls_back_to_auto_for_invalid_value(): void
	{
		update_option('mhm_rentiva_dark_mode', 'auto');
		update_option(
			'mhm_rentiva_settings',
			array(
				'mhm_rentiva_dark_mode' => 'auto',
			)
		);

		$response = $this->run_dark_mode_ajax('<script>alert(1)</script>');

		$this->assertTrue($response['success'] ?? false);
		$this->assertSame('auto', get_option('mhm_rentiva_dark_mode', 'light'));

		$settings = (array) get_option('mhm_rentiva_settings', array());
		$this->assertSame('auto', $settings['mhm_rentiva_dark_mode'] ?? null);
	}

	/**
	 * Regression test: toggling dark mode via the quick AJAX switcher must
	 * only touch mhm_rentiva_dark_mode. It previously routed through
	 * SettingsSanitizer::sanitize() with 'current_active_tab' => 'general',
	 * which re-ran the entire General/Site-Info sanitizer on an input array
	 * that only contained the dark mode key — silently blanking
	 * contact_phone/contact_hours/support_email and resetting brand_name
	 * to get_bloginfo('name') on every toggle.
	 */
	public function test_ajax_does_not_clobber_unrelated_site_info_fields(): void
	{
		update_option(
			'mhm_rentiva_settings',
			array(
				'mhm_rentiva_dark_mode'     => 'auto',
				'mhm_rentiva_brand_name'    => 'Custom Brand',
				'mhm_rentiva_contact_phone' => '+90 555 123 45 67',
				'mhm_rentiva_contact_hours' => '09:00 - 18:00',
				'mhm_rentiva_support_email' => 'support@example.com',
			)
		);

		$response = $this->run_dark_mode_ajax('dark');

		$this->assertTrue($response['success'] ?? false);

		$settings = (array) get_option('mhm_rentiva_settings', array());
		$this->assertSame('dark', $settings['mhm_rentiva_dark_mode'] ?? null);
		$this->assertSame('Custom Brand', $settings['mhm_rentiva_brand_name'] ?? null);
		$this->assertSame('+90 555 123 45 67', $settings['mhm_rentiva_contact_phone'] ?? null);
		$this->assertSame('09:00 - 18:00', $settings['mhm_rentiva_contact_hours'] ?? null);
		$this->assertSame('support@example.com', $settings['mhm_rentiva_support_email'] ?? null);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function run_dark_mode_ajax(string $mode): array
	{
		wp_set_current_user($this->admin_id);

		$nonce = wp_create_nonce('mhm_dark_mode_nonce');

		$_POST['action'] = 'mhm_rentiva_save_dark_mode';
		$_POST['nonce']  = $nonce;
		$_POST['mode']   = $mode;

		$_REQUEST = $_POST;

		try {
			$this->_handleAjax('mhm_rentiva_save_dark_mode');
		} catch (WPAjaxDieContinueException | WPAjaxDieStopException $e) {
			// Expected in AJAX test context.
		}

		$decoded = json_decode($this->_last_response, true);
		$this->assertIsArray($decoded);

		return $decoded;
	}
}
