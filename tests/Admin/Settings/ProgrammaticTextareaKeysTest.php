<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * A write without `current_active_tab` (the setup wizard, migrations, the
 * shortcode-page and vehicle-settings screens) goes through the programmatic
 * branch. That branch ran every scalar through sanitize_text_field(), so the
 * four settings a tab stores with sanitize_textarea_field() lost their line
 * breaks the next time any of those writers saved the whole array.
 *
 * The option is held in memory by `pre_option_mhmrentiva_settings`, so nothing
 * is written to the shared test database.
 *
 * @covers \MHMRentiva\Admin\Settings\Core\SettingsSanitizer
 */
final class ProgrammaticTextareaKeysTest extends WP_UnitTestCase
{
	private const TEXTAREA_KEYS = array(
		'mhmrentiva_booking_security_deposit_note',
		'mhmrentiva_booking_fuel_policy_note',
		'mhmrentiva_text_login_required',
		'mhmrentiva_transfer_custom_types',
	);

	/** @var array<string, mixed> */
	private array $stored = array();

	public function set_up(): void
	{
		parent::set_up();
		$this->stored = array_fill_keys(self::TEXTAREA_KEYS, '');
		$this->stored['mhmrentiva_email_from_name'] = '';
		add_filter('pre_option_mhmrentiva_settings', fn () => $this->stored);
		self::reset_defaults_cache();
	}

	public function tear_down(): void
	{
		remove_all_filters('mhmrentiva_settings_textarea_keys');
		self::reset_defaults_cache();
		parent::tear_down();
	}

	private static function reset_defaults_cache(): void
	{
		$property = ( new \ReflectionClass(SettingsCore::class) )->getProperty('defaults_cache');
		$property->setAccessible(true);
		$property->setValue(null, null);
	}

	public function test_programmatic_write_keeps_newlines_in_textarea_keys(): void
	{
		$input = array_fill_keys(self::TEXTAREA_KEYS, "Satır 1\nSatır 2");

		$out = SettingsSanitizer::sanitize($input);

		foreach (self::TEXTAREA_KEYS as $key) {
			$this->assertSame("Satır 1\nSatır 2", $out[ $key ], $key);
		}
	}

	public function test_programmatic_write_still_strips_newlines_elsewhere(): void
	{
		$out = SettingsSanitizer::sanitize(array( 'mhmrentiva_email_from_name' => "A\nB" ));

		$this->assertSame(sanitize_text_field("A\nB"), $out['mhmrentiva_email_from_name']);
	}

	public function test_programmatic_write_strips_tags_in_textarea_keys(): void
	{
		$out = SettingsSanitizer::sanitize(array( 'mhmrentiva_booking_fuel_policy_note' => "<script>x</script>Not\nİki" ));

		$this->assertSame(sanitize_textarea_field("<script>x</script>Not\nİki"), $out['mhmrentiva_booking_fuel_policy_note']);
	}

	public function test_textarea_keys_filter_adds_a_key(): void
	{
		$this->stored['foo_note'] = '';
		add_filter(
			'mhmrentiva_settings_textarea_keys',
			static fn (array $keys): array => array_merge($keys, array( 'foo_note' ))
		);

		$out = SettingsSanitizer::sanitize(array( 'foo_note' => "a\nb" ));

		$this->assertSame("a\nb", $out['foo_note']);
	}
}
