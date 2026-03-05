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
	 * @return array<string,mixed>
	 */
	private function run_dark_mode_ajax(string $mode): array
	{
		wp_set_current_user($this->admin_id);

		$nonce = wp_create_nonce('mhm_dark_mode_nonce');

		$_POST['action'] = 'mhm_save_dark_mode';
		$_POST['nonce']  = $nonce;
		$_POST['mode']   = $mode;

		$_REQUEST = $_POST;

		try {
			$this->_handleAjax('mhm_save_dark_mode');
		} catch (WPAjaxDieContinueException | WPAjaxDieStopException $e) {
			// Expected in AJAX test context.
		}

		$decoded = json_decode($this->_last_response, true);
		$this->assertIsArray($decoded);

		return $decoded;
	}
}
