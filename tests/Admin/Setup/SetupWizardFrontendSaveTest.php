<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Setup;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Setup\SetupWizard;
use WP_UnitTestCase;

/**
 * Step 4 of the setup wizard saves through admin-post.php. The handler writes
 * the whole `mhmrentiva_settings` array, so the registered option sanitizer
 * runs its programmatic branch (no `current_active_tab`).
 *
 * @covers \MHMRentiva\Admin\Setup\SetupWizard
 */
final class SetupWizardFrontendSaveTest extends WP_UnitTestCase
{
	public function set_up(): void
	{
		parent::set_up();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		if (! has_filter('sanitize_option_mhmrentiva_settings')) {
			SettingsCore::init_settings_registration();
		}
		$this->assertNotFalse(has_filter('sanitize_option_mhmrentiva_settings'), 'The option sanitizer must be registered, as on admin_init.');
		$GLOBALS['wp_settings_errors'] = array();
		delete_option('mhmrentiva_settings');
		delete_option('mhmrentiva_setup_frontend_saved');
		delete_option('mhmrentiva_setup_completed');
		$_POST    = array();
		$_REQUEST = array( '_wpnonce' => wp_create_nonce('mhmrentiva_setup_frontend') );
		add_filter(
			'wp_redirect',
			static function ($location) {
				throw new \RuntimeException('redirected:' . $location);
			}
		);
	}

	public function tear_down(): void
	{
		remove_all_filters('wp_redirect');
		remove_all_filters('mhmrentiva_woocommerce_is_active');
		$_POST    = array();
		$_REQUEST = array();
		$GLOBALS['wp_settings_errors'] = array();
		delete_option('mhmrentiva_settings');
		delete_option('mhmrentiva_setup_frontend_saved');
		delete_option('mhmrentiva_setup_completed');
		parent::tear_down();
	}

	/**
	 * Run the handler; return the redirect location.
	 *
	 * @param array<string, mixed> $post
	 */
	private function save(array $post): string
	{
		$_POST = $post + array(
			'default_days' => '1',
			'min_days'     => '1',
			'max_days'     => '30',
		);
		try {
			SetupWizard::handle_save_frontend();
		} catch (\RuntimeException $e) {
			$this->assertStringStartsWith('redirected:', $e->getMessage());
			return substr($e->getMessage(), strlen('redirected:'));
		}
		$this->fail('Expected the handler to redirect.');
	}

	/** @return array<string, mixed> */
	private function stored(): array
	{
		return (array) get_option('mhmrentiva_settings', array());
	}

	private function seed(array $values): void
	{
		update_option('mhmrentiva_settings', $values);
	}

	private function woocommerce(bool $active): void
	{
		remove_all_filters('mhmrentiva_woocommerce_is_active');
		add_filter('mhmrentiva_woocommerce_is_active', static fn () => $active);
	}

	public function test_custom_accent_is_saved_through_the_accent_sanitizer(): void
	{
		$this->save(
			array(
				'mhmrentiva_settings' => array(
					'mhmrentiva_accent_mode'  => 'custom',
					'mhmrentiva_accent_color' => '#CC0000',
				),
			)
		);

		$this->assertSame('custom', $this->stored()['mhmrentiva_accent_mode']);
		$this->assertSame('#cc0000', $this->stored()['mhmrentiva_accent_color']);
	}

	public function test_custom_without_color_falls_back_and_flags_redirect(): void
	{
		$location = $this->save(
			array(
				'mhmrentiva_settings' => array(
					'mhmrentiva_accent_mode'  => 'custom',
					'mhmrentiva_accent_color' => '',
				),
			)
		);

		$this->assertSame('auto', $this->stored()['mhmrentiva_accent_mode']);
		$this->assertSame('', $this->stored()['mhmrentiva_accent_color']);
		$this->assertStringContainsString('mhmrentiva_accent=auto', $location);
	}

	public function test_forged_accent_input_is_cleaned(): void
	{
		$this->seed(
			array(
				'mhmrentiva_accent_mode'  => 'custom',
				'mhmrentiva_accent_color' => '#123456',
			)
		);

		$this->save(
			array(
				'mhmrentiva_settings' => array(
					'mhmrentiva_accent_mode'  => 'evil',
					'mhmrentiva_accent_color' => 'red',
					'foo'                     => 'bar',
				),
			)
		);

		$this->assertSame('auto', $this->stored()['mhmrentiva_accent_mode']);
		$this->assertSame('', $this->stored()['mhmrentiva_accent_color']);
		$this->assertArrayNotHasKey('foo', $this->stored());
	}

	public function test_missing_accent_post_leaves_saved_accent_untouched(): void
	{
		$this->seed(
			array(
				'mhmrentiva_accent_mode'  => 'custom',
				'mhmrentiva_accent_color' => '#123456',
			)
		);

		$this->save(array());

		$this->assertSame('custom', $this->stored()['mhmrentiva_accent_mode']);
		$this->assertSame('#123456', $this->stored()['mhmrentiva_accent_color']);
	}

	public function test_wizard_save_keeps_textarea_newlines(): void
	{
		$this->seed(array( 'mhmrentiva_booking_fuel_policy_note' => "A\nB" ));

		$this->save(array());

		$this->assertSame("A\nB", $this->stored()['mhmrentiva_booking_fuel_policy_note']);
	}

	public function test_woocommerce_active_save_does_not_touch_rentiva_currency(): void
	{
		$this->woocommerce(true);
		$this->seed(array( 'mhmrentiva_currency' => 'EUR' ));

		$this->save(array());

		$this->assertSame('EUR', $this->stored()['mhmrentiva_currency']);
	}

	public function test_woocommerce_inactive_save_writes_posted_currency(): void
	{
		$this->woocommerce(false);
		$this->seed(array( 'mhmrentiva_currency' => 'EUR' ));

		$this->save(array( 'currency' => 'TRY' ));

		$this->assertSame('TRY', $this->stored()['mhmrentiva_currency']);
	}

	public function test_save_no_longer_writes_vehicle_card_toggles(): void
	{
		$this->seed(array( 'mhmrentiva_vehicle_show_features' => '1' ));

		$this->save(array());

		$this->assertSame('1', $this->stored()['mhmrentiva_vehicle_show_features']);
	}

	public function test_save_marks_frontend_step_saved(): void
	{
		$this->save(array());

		$this->assertSame('1', get_option('mhmrentiva_setup_frontend_saved'));
	}

	public function test_frontend_step_completion_reads_saved_flag_or_completed_wizard(): void
	{
		$method = new \ReflectionMethod(SetupWizard::class, 'is_step_completed');
		$method->setAccessible(true);

		$this->assertFalse($method->invoke(null, 'frontend'));

		update_option('mhmrentiva_setup_frontend_saved', '1');
		$this->assertTrue($method->invoke(null, 'frontend'));

		delete_option('mhmrentiva_setup_frontend_saved');
		update_option('mhmrentiva_setup_completed', '1');
		$this->assertTrue($method->invoke(null, 'frontend'));
	}
}
