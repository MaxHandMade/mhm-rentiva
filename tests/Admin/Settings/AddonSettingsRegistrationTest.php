<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Addons\AddonSettings;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

final class AddonSettingsRegistrationTest extends WP_UnitTestCase
{
	public function test_addon_option_is_registered_in_central_settings_lifecycle(): void
	{
		SettingsCore::init_settings_registration();

		$registered = function_exists('get_registered_settings')
			? get_registered_settings()
			: ( $GLOBALS['wp_registered_settings'] ?? array() );

		$this->assertIsArray($registered);
		$this->assertArrayHasKey('mhm_rentiva_addon_settings', $registered);
		$this->assertSame(
			array(SettingsSanitizer::class, 'sanitize_addon_settings_option'),
			$registered['mhm_rentiva_addon_settings']['sanitize_callback'] ?? null
		);
	}

	public function test_addon_option_sanitizer_normalizes_expected_fields(): void
	{
		$input = array(
			'system_enabled' => 'on',
			'show_prices' => 'yes',
			'allow_multiple' => 0,
			'display_order' => '<script>invalid</script>',
			'extra' => 'should_be_removed',
		);

		$sanitized = SettingsSanitizer::sanitize_addon_settings_option($input);

		$this->assertSame('1', $sanitized['system_enabled']);
		$this->assertSame('1', $sanitized['show_prices']);
		$this->assertSame('0', $sanitized['allow_multiple']);
		$this->assertSame(AddonSettings::defaults()['display_order'], $sanitized['display_order']);
		$this->assertArrayNotHasKey('extra', $sanitized);
	}
}
