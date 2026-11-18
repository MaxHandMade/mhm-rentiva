<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\AssetManager;
use WP_UnitTestCase;

/**
 * Pro 6.4.x's transfer add-on modal reads its currency symbol from the
 * add-on object Lite 6.3.x printed on every front-end page. Lite 6.4.0 no
 * longer prints it; until Pro carries its own, Lite hands that one field to
 * the modal's script when the script is on the page.
 *
 * @covers \MHMRentiva\Admin\Core\AssetManager::legacy_addons_object
 */
final class AssetManagerLegacyAddonsObjectTest extends WP_UnitTestCase
{
	private const HANDLE = 'rentiva-transfer-addon-modal';

	public function setUp(): void
	{
		parent::setUp();
		$GLOBALS['wp_scripts'] = null;
	}

	public function tearDown(): void
	{
		$GLOBALS['wp_scripts'] = null;
		parent::tearDown();
	}

	private function before(): string
	{
		$before = wp_scripts()->get_data(self::HANDLE, 'before');

		return is_array($before) ? implode("\n", $before) : '';
	}

	public function test_the_modal_script_gets_the_currency_symbol(): void
	{
		wp_enqueue_script(self::HANDLE, 'https://example.test/modal.js', array(), '1', true);

		AssetManager::legacy_addons_object();

		$this->assertStringContainsString('window.mhmRentivaAddons = window.mhmRentivaAddons ||', $this->before());
		$this->assertStringContainsString('"currencySymbol"', $this->before());
	}

	public function test_nothing_is_added_without_the_modal_script(): void
	{
		wp_register_script(self::HANDLE, 'https://example.test/modal.js', array(), '1', true);

		AssetManager::legacy_addons_object();

		$this->assertSame('', $this->before());
	}

	public function test_it_runs_before_the_footer_scripts_print(): void
	{
		AssetManager::init();

		$this->assertSame(1, has_action('wp_print_footer_scripts', array( AssetManager::class, 'legacy_addons_object' )));
	}
}
