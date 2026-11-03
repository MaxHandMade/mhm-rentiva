<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use MHMRentiva\Admin\Settings\Groups\FrontendSettings;
use MHMRentiva\Core\Color\AccentResolver;
use MHMRentiva\Core\Color\ColorMath;
use WP_UnitTestCase;

/**
 * The accent colour setting on the Frontend tab (spec §4, §8 PR 2 scope): two
 * keys in mhmrentiva_settings, the real sanitizer, the Colors section render
 * and the colour picker assets.
 *
 * The option is held in memory by `pre_option_mhmrentiva_settings` (the
 * SettingsReachTheirReadersTest pattern), so nothing is written to the shared
 * test database.
 *
 * @covers \MHMRentiva\Admin\Settings\Core\SettingsSanitizer
 * @covers \MHMRentiva\Admin\Settings\Groups\FrontendSettings
 */
final class FrontendColorSettingsSanitizerTest extends WP_UnitTestCase
{
	/** @var array<string, mixed> */
	private array $stored = array();

	public function set_up(): void
	{
		parent::set_up();
		$this->stored = array();
		add_filter('pre_option_mhmrentiva_settings', fn () => $this->stored);
		$GLOBALS['wp_settings_errors'] = array();
		self::reset_defaults_cache();
		AccentResolver::reset();
		// Sections and fields are registered on admin_init in production
		// (SettingsCore::init_settings_registration()); the render test needs them.
		FrontendSettings::register();
	}

	public function tear_down(): void
	{
		$GLOBALS['wp_settings_errors'] = array();
		self::reset_defaults_cache();
		AccentResolver::reset();
		parent::tear_down();
	}

	private static function reset_defaults_cache(): void
	{
		$property = ( new \ReflectionClass(SettingsCore::class) )->getProperty('defaults_cache');
		$property->setAccessible(true);
		$property->setValue(null, null);
	}

	/**
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	private function save_frontend(array $fields): array
	{
		$this->stored = SettingsSanitizer::sanitize(array( 'current_active_tab' => 'frontend' ) + $fields);
		return $this->stored;
	}

	public function test_mode_enum_defaults_to_auto(): void
	{
		$this->assertSame('auto', $this->save_frontend(array( 'mhmrentiva_accent_mode' => 'evil' ))['mhmrentiva_accent_mode']);
		$this->assertSame('auto', $this->save_frontend(array( 'mhmrentiva_accent_mode' => array( 'custom' ) ))['mhmrentiva_accent_mode']);
		$this->assertSame(
			'custom',
			$this->save_frontend(
				array(
					'mhmrentiva_accent_mode'  => 'custom',
					'mhmrentiva_accent_color' => '#cc0000',
				)
			)['mhmrentiva_accent_mode']
		);
	}

	public function test_hex_is_lowercased_and_expanded(): void
	{
		$this->assertSame('#aabbcc', $this->save_frontend(array( 'mhmrentiva_accent_color' => '#ABC' ))['mhmrentiva_accent_color']);
		$this->assertSame('#2271b1', $this->save_frontend(array( 'mhmrentiva_accent_color' => '#2271B1' ))['mhmrentiva_accent_color']);
	}

	public function test_invalid_hex_is_stored_as_empty_string_not_null(): void
	{
		foreach (array( 'red', '#12345', array(), 'red;}body{x}', null ) as $bad) {
			$out = $this->save_frontend(array( 'mhmrentiva_accent_color' => $bad ));
			$this->assertArrayHasKey('mhmrentiva_accent_color', $out);
			$this->assertIsString($out['mhmrentiva_accent_color']);
			$this->assertSame('', $out['mhmrentiva_accent_color'], var_export($bad, true));
		}
	}

	public function test_custom_with_empty_colour_becomes_auto_and_reports(): void
	{
		$out = $this->save_frontend(
			array(
				'mhmrentiva_accent_mode'  => 'custom',
				'mhmrentiva_accent_color' => '',
			)
		);
		$this->assertSame('auto', $out['mhmrentiva_accent_mode']);
		$codes = array_column(get_settings_errors('mhmrentiva_settings'), 'code');
		$this->assertContains('mhmrentiva_accent_color_empty', $codes);
	}

	public function test_other_frontend_keys_unchanged(): void
	{
		$out = $this->save_frontend(
			array(
				'mhmrentiva_vehicle_cards_per_page' => '20',
				'mhmrentiva_vehicle_default_sort'   => 'name_asc',
				'mhmrentiva_text_book_now'          => 'Rent it',
				'mhmrentiva_accent_mode'            => 'custom',
				'mhmrentiva_accent_color'           => '#0067ff',
			)
		);
		$this->assertSame(20, $out['mhmrentiva_vehicle_cards_per_page']);
		$this->assertSame('name_asc', $out['mhmrentiva_vehicle_default_sort']);
		$this->assertSame('Rent it', $out['mhmrentiva_text_book_now']);
		$this->assertSame('#0067ff', $out['mhmrentiva_accent_color']);
	}

	public function test_defaults_are_auto_and_empty(): void
	{
		$defaults = SettingsCore::get_defaults();
		$this->assertSame('auto', $defaults['mhmrentiva_accent_mode'] ?? null);
		$this->assertSame('', $defaults['mhmrentiva_accent_color'] ?? null);
	}

	private function render(): string
	{
		ob_start();
		FrontendSettings::render_settings_section();
		return (string) ob_get_clean();
	}

	public function test_colors_section_renders_fields_and_warning(): void
	{
		$this->stored = array(
			'mhmrentiva_accent_mode'  => 'custom',
			'mhmrentiva_accent_color' => '#0067ff',
		);
		add_filter(
			'gettext',
			static fn (string $translation, string $text): string => str_starts_with($text, 'This color is too light') ? '<b>' . $translation : $translation,
			10,
			2
		);
		$html = $this->render();

		$this->assertMatchesRegularExpression('/<input type="radio" name="mhmrentiva_settings\[mhmrentiva_accent_mode\]" value="auto"\s*\/?>/', $html);
		$this->assertMatchesRegularExpression('/<input type="radio" name="mhmrentiva_settings\[mhmrentiva_accent_mode\]" value="custom" checked=(["\'])checked\1/', $html);
		$this->assertMatchesRegularExpression('/<input type="text" name="mhmrentiva_settings\[mhmrentiva_accent_color\]" class="mhm-rentiva-accent-color" data-default-color="#2271b1" value="#0067ff"/', $html);
		$this->assertStringContainsString('notice notice-warning inline', $html);
		$this->assertStringContainsString(ColorMath::palette('#0067ff')['tokens']['accent-text'], $html);
		$this->assertStringContainsString('&lt;b&gt;', $html, 'The notice text is escaped');
		$this->assertStringNotContainsString('<b>This color', $html);

		$colors  = strpos($html, '<h2>Colors</h2>');
		$vehicle = strpos($html, '<h2>Vehicle Display Settings</h2>');
		$this->assertNotFalse($colors);
		$this->assertNotFalse($vehicle);
		$this->assertLessThan($vehicle, $colors, 'Colors is the first section of the tab');

		$this->stored = array(
			'mhmrentiva_accent_mode'  => 'custom',
			'mhmrentiva_accent_color' => '#cc0000',
		);
		AccentResolver::reset();
		$this->assertStringNotContainsString('notice-warning', $this->render());
	}

	public function test_render_tolerates_a_hand_written_option_row(): void
	{
		// Programmatic writes (WP-CLI, update_option) skip the form sanitizer.
		$this->stored = array(
			'mhmrentiva_accent_mode'  => array( 'custom' ),
			'mhmrentiva_accent_color' => 'red;}body{x}',
		);
		$warnings = array();
		set_error_handler(
			static function (int $errno, string $errstr) use (&$warnings): bool {
				$warnings[] = $errstr;
				return true;
			},
			E_WARNING | E_NOTICE | E_DEPRECATED
		);
		$html = $this->render();
		restore_error_handler();

		$this->assertSame(array(), $warnings);
		$this->assertMatchesRegularExpression('/value="auto" checked=(["\'])checked\1/', $html);
		$this->assertMatchesRegularExpression('/class="mhm-rentiva-accent-color" data-default-color="#2271b1" value=""/', $html);
		$this->assertStringNotContainsString('red;}', $html);
	}

	public function test_settings_screen_enqueues_colour_picker(): void
	{
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		set_current_screen('mhm-rentiva_page_mhm-rentiva-settings');
		$this->assertStringContainsString('mhm-rentiva-settings', get_current_screen()->id);

		SettingsCore::enqueue_assets();
		$this->assertTrue(wp_style_is('wp-color-picker'));
		$this->assertTrue(wp_script_is('mhm-rentiva-settings-accent-color'));
		$this->assertContains('wp-color-picker', wp_scripts()->registered['mhm-rentiva-settings-accent-color']->deps);

		set_current_screen('front');
		wp_set_current_user(0);
	}
}
