<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Setup;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Settings\Groups\FrontendColorsSection;
use MHMRentiva\Admin\Setup\SetupWizard;
use MHMRentiva\Core\Color\AccentResolver;
use WP_UnitTestCase;

/**
 * Wizard step 4: the accent color row (the Settings > Frontend > Colors field,
 * reused), the notices after a save, and the picker/preview assets.
 *
 * @covers \MHMRentiva\Admin\Setup\SetupWizard
 * @covers \MHMRentiva\Admin\Settings\Groups\FrontendColorsSection
 */
final class SetupWizardFrontendStepTest extends WP_UnitTestCase
{
	private const HOOK = 'mhm-rentiva_page_mhm-rentiva-setup';

	/** @var mixed */
	private $saved_styles;
	/** @var mixed */
	private $saved_scripts;

	public function set_up(): void
	{
		parent::set_up();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		$this->saved_styles  = $GLOBALS['wp_styles'] ?? null;
		$this->saved_scripts = $GLOBALS['wp_scripts'] ?? null;
		$GLOBALS['wp_styles']  = null;
		$GLOBALS['wp_scripts'] = null;
		AssetManager::register_core_styles();
		AccentResolver::reset();
	}

	public function tear_down(): void
	{
		unset($_GET['page'], $_GET['step'], $_GET['saved'], $_GET['accent'], $_GET['tab']);
		delete_option('mhmrentiva_settings');
		$GLOBALS['wp_styles']  = $this->saved_styles;
		$GLOBALS['wp_scripts'] = $this->saved_scripts;
		AccentResolver::reset();
		parent::tear_down();
	}

	private function render(string $method): string
	{
		$reflection = new \ReflectionMethod(SetupWizard::class, $method);
		$reflection->setAccessible(true);
		ob_start();
		$reflection->invoke(null);
		return (string) ob_get_clean();
	}

	public function test_frontend_step_renders_accent_field_inside_the_wizard_form(): void
	{
		$html = $this->render('render_step_frontend');

		$form_start = strpos($html, 'value="mhmrentiva_setup_save_frontend"');
		$form_end   = strpos($html, '</form>');
		$mode       = strpos($html, 'name="mhmrentiva_settings[mhmrentiva_accent_mode]"');
		$color      = strpos($html, 'name="mhmrentiva_settings[mhmrentiva_accent_color]"');

		$this->assertNotFalse($form_start);
		$this->assertNotFalse($mode);
		$this->assertNotFalse($color);
		$this->assertGreaterThan($form_start, $mode);
		$this->assertLessThan($form_end, $color);
	}

	public function test_frontend_step_has_no_vehicle_card_toggles(): void
	{
		$html = $this->render('render_step_frontend');

		$this->assertStringNotContainsString('show_features', $html);
		$this->assertStringNotContainsString('show_availability', $html);
	}

	public function test_saved_notice_shows_after_redirect(): void
	{
		$this->assertSame('', trim($this->render('render_saved_notice')), 'No flag, no notice.');

		$_GET['saved'] = '1';
		$this->assertStringContainsString(__('Settings saved.', 'mhm-rentiva'), $this->render('render_saved_notice'));

		$_GET['accent'] = 'auto';
		$this->assertStringContainsString(
			__('No custom color was chosen, so the accent color stays Automatic.', 'mhm-rentiva'),
			$this->render('render_saved_notice')
		);
	}

	public function test_picker_assets_enqueue_on_wizard_frontend_step(): void
	{
		$_GET['page'] = 'mhm-rentiva-setup';
		$_GET['step'] = 'frontend';

		SetupWizard::enqueue_assets(self::HOOK);

		$this->assertTrue(wp_script_is('mhm-rentiva-settings-accent-color', 'enqueued'));
		$this->assertTrue(wp_style_is('wp-color-picker', 'enqueued'));
		foreach (FrontendColorsSection::PREVIEW_STYLES as $handle) {
			$this->assertTrue(wp_style_is($handle, 'enqueued'), $handle);
		}
		// The field's own look (swatch, preview frame) travels with the field.
		$this->assertTrue(wp_style_is('mhm-rentiva-accent-field', 'enqueued'));
	}

	public function test_picker_assets_stay_off_other_wizard_steps(): void
	{
		$_GET['page'] = 'mhm-rentiva-setup';
		$_GET['step'] = 'email';

		SetupWizard::enqueue_assets(self::HOOK);

		$this->assertFalse(wp_script_is('mhm-rentiva-settings-accent-color', 'enqueued'));
	}

	public function test_wizard_preview_wears_the_saved_accent(): void
	{
		update_option(
			'mhmrentiva_settings',
			array(
				'mhmrentiva_accent_mode'  => 'custom',
				'mhmrentiva_accent_color' => '#cc0000',
			)
		);
		AccentResolver::reset();
		$_GET['page'] = 'mhm-rentiva-setup';
		$_GET['step'] = 'frontend';

		SetupWizard::enqueue_assets(self::HOOK);

		$after = wp_styles()->get_data('mhm-rentiva-rvs-tokens', 'after');
		$this->assertIsArray($after);
		$this->assertStringContainsString('#cc0000', implode('', $after));
	}

	public function test_settings_tab_still_enqueues_picker_assets(): void
	{
		$_GET['tab'] = 'frontend';

		FrontendColorsSection::enqueue_assets();

		$this->assertTrue(wp_script_is('mhm-rentiva-settings-accent-color', 'enqueued'));
		$this->assertTrue(wp_style_is('wp-color-picker', 'enqueued'));
		$this->assertTrue(wp_style_is('mhm-rentiva-accent-field', 'enqueued'));
	}
}
