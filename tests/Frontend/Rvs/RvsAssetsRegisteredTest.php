<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Rvs;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Tests\Support\RequestSimulator;
use WP_UnitTestCase;

/**
 * Aşama 2 PR 1: the `rvs-` style chain (fonts -> tokens -> shell -> primitives),
 * the bundled Source Serif 4 with its licence, and the rules the spec pins in
 * the stylesheets themselves.
 */
final class RvsAssetsRegisteredTest extends WP_UnitTestCase
{
	private const ROOT = MHMRENTIVA_PLUGIN_DIR;

	public function setUp(): void
	{
		parent::setUp();
		RequestSimulator::new_request();
		AssetManager::register_core_styles();
	}

	public function tearDown(): void
	{
		RequestSimulator::new_request();
		parent::tearDown();
	}

	private static function css(string $path): string
	{
		return (string) file_get_contents(self::ROOT . $path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads a shipped asset.
	}

	public function test_rvs_chain_is_registered_in_order(): void
	{
		$styles = wp_styles();
		$this->assertSame(array(), $styles->registered['mhm-rentiva-rvs-fonts']->deps);
		$this->assertSame(array( 'mhm-rentiva-rvs-fonts' ), $styles->registered['mhm-rentiva-rvs-tokens']->deps);
		$this->assertSame(array( 'mhm-rentiva-rvs-tokens' ), $styles->registered['mhm-rentiva-rvs-shell']->deps);
		$this->assertSame(array( 'mhm-rentiva-rvs-shell' ), $styles->registered['mhm-rentiva-rvs-primitives']->deps);
	}

	public function test_registering_does_not_enqueue(): void
	{
		$this->assertFalse(wp_style_is('mhm-rentiva-rvs-primitives', 'enqueued'));
	}

	public function test_enqueue_rvs_styles_loads_the_whole_chain(): void
	{
		AssetManager::enqueue_rvs_styles();

		wp_styles()->all_deps(wp_styles()->queue);
		foreach (array( 'mhm-rentiva-rvs-fonts', 'mhm-rentiva-rvs-tokens', 'mhm-rentiva-rvs-shell', 'mhm-rentiva-rvs-primitives' ) as $handle) {
			$this->assertContains($handle, wp_styles()->to_do, $handle);
		}
	}

	public function test_every_registered_file_exists(): void
	{
		foreach (array( 'mhm-rentiva-rvs-fonts', 'mhm-rentiva-rvs-tokens', 'mhm-rentiva-rvs-shell', 'mhm-rentiva-rvs-primitives' ) as $handle) {
			$src  = (string) wp_styles()->registered[ $handle ]->src;
			$path = str_replace(MHMRENTIVA_PLUGIN_URL, '', $src);
			$this->assertFileExists(self::ROOT . $path, $handle);
		}
	}

	public function test_font_css_references_only_bundled_files(): void
	{
		// The header comment names the upstream URLs; only rules matter here.
		$css = (string) preg_replace('!/\*.*?\*/!s', '', self::css('assets/vendor/fonts/source-serif-4.css'));

		$this->assertStringNotContainsString('http', $css);
		$this->assertStringNotContainsString('//fonts.', $css);
		preg_match_all('/url\("([^"]+)"\)/', $css, $m);
		$this->assertCount(6, $m[1]);
		foreach ($m[1] as $file) {
			$this->assertFileExists(self::ROOT . 'assets/vendor/fonts/' . $file);
		}
	}

	public function test_license_ships_with_font(): void
	{
		$license = self::css('assets/vendor/fonts/source-serif-4/LICENSE-Source-Serif-4.txt');

		$this->assertStringContainsString('SIL OPEN FONT LICENSE Version 1.1', $license);
		$this->assertStringContainsString('Adobe', $license);
	}

	public function test_tokens_declare_spec_values_scoped_to_the_shell(): void
	{
		$css = self::css('assets/css/rvs/rvs-tokens.css');

		$this->assertStringNotContainsString(':root', preg_replace('!/\*.*?\*/!s', '', $css));
		foreach (
			array(
				'--rvs-bg: #f3f2f2',
				'--rvs-text: #201e1d',
				'--rvs-accent: #0088b0',
				'--rvs-accent-strong: #006786',
				'--rvs-alert: #aa0b56',
				'--rvs-n-700: #605d5d',
				'--rvs-s1: 5px',
				'--rvs-s12: 60px',
				'--rvs-r: 2px',
				'--rvs-font: "Source Serif 4", Georgia, serif',
			) as $declaration
		) {
			$this->assertStringContainsString($declaration, $css);
		}
	}

	public function test_primary_button_never_white_on_accent(): void
	{
		$css = self::css('assets/css/rvs/rvs-primitives.css');

		$this->assertMatchesRegularExpression('/\.rvs-btn--primary\s*\{[^}]*background:\s*var\(--rvs-accent-strong\)/', $css);
		$this->assertDoesNotMatchRegularExpression('/background:\s*var\(--rvs-accent\);[^}]*color:\s*#fff/', $css);
	}

	public function test_interactive_minimums(): void
	{
		$css = self::css('assets/css/rvs/rvs-primitives.css');

		$this->assertMatchesRegularExpression('/\.rvs-shell \.rvs-btn \{[^}]*min-width: 44px;[^}]*min-height: 44px;/', $css);
		$this->assertMatchesRegularExpression('/\.rvs-shell \.rvs-input \{[^}]*min-height: 48px;/', $css);
		$this->assertMatchesRegularExpression('/\.rvs-shell \.rvs-check \{[^}]*min-height: 44px;/', $css);
		$this->assertMatchesRegularExpression('/\.rvs-shell \.rvs-chip \{[^}]*min-height: 44px;/', $css);
	}

	public function test_calendar_style_sits_on_primitives(): void
	{
		$this->assertSame(array( 'mhm-rentiva-rvs-primitives' ), wp_styles()->registered['mhm-rentiva-rvs-calendar']->deps);

		AssetManager::enqueue_rvs_calendar_styles();
		wp_styles()->all_deps(wp_styles()->queue);
		foreach (array( 'mhm-rentiva-rvs-tokens', 'mhm-rentiva-rvs-primitives', 'mhm-rentiva-rvs-calendar' ) as $handle) {
			$this->assertContains($handle, wp_styles()->to_do, $handle);
		}
	}
}
