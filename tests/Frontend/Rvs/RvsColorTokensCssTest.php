<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Rvs;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Core\Color\AccentResolver;
use MHMRentiva\Core\Color\ColorMath;
use MHMRentiva\Core\Color\RvsColorTokens;
use MHMRentiva\Tests\Support\RvsTokenFile;
use WP_UnitTestCase;

/**
 * The resolved accent palette is printed once, as one inline rule on
 * mhm-rentiva-rvs-tokens, with the token file's own scopes and only the eight
 * literal tokens as strict hex (spec §4 Güvenlik, plan R6/R9/R10).
 */
final class RvsColorTokensCssTest extends WP_UnitTestCase
{
	private const GRAMMAR = '/^[^{}]+\{(--rvs-[a-z0-9-]+:#[0-9a-f]{6};){8}\}$/';

	/** @var array<string,mixed> */
	private array $settings = array();

	public function set_up(): void
	{
		parent::set_up();
		$this->settings = array();
		add_filter('pre_option_mhmrentiva_settings', fn () => $this->settings);
		// A theme no adapter knows, and no Elementor/theme.json colour: Automatic is the default.
		add_filter('template', static fn (): string => 'twentytwentyfive');
		add_filter('mhmrentiva_accent_sources', static fn (): array => array());
		AccentResolver::reset();
		$this->fresh_styles();
	}

	public function tear_down(): void
	{
		AccentResolver::reset();
		$this->fresh_styles();
		set_current_screen('front');
		parent::tear_down();
	}

	private function fresh_styles(): void
	{
		$GLOBALS['wp_styles'] = new \WP_Styles();
		AssetManager::register_core_styles();
	}

	private function custom(string $hex): void
	{
		$this->settings = array(
			'mhmrentiva_accent_mode'  => 'custom',
			'mhmrentiva_accent_color' => $hex,
		);
		AccentResolver::reset();
	}

	/** @return list<string> */
	private static function after(): array
	{
		$after = wp_styles()->get_data(RvsColorTokens::HANDLE, 'after');
		return is_array($after) ? array_values($after) : array();
	}

	public function test_scopes_constant_equals_token_file(): void
	{
		$css = (string) preg_replace('!/\*.*?\*/!s', '', RvsTokenFile::css());
		$this->assertSame(1, preg_match('/^\s*([^{]+)\{/', $css, $m));
		$selector = (string) preg_replace('/\s*,\s*/', ',', trim($m[1]));
		$this->assertSame(implode(',', RvsColorTokens::SCOPES), $selector);
		$this->assertStringNotContainsString(':root', $css);
		$this->assertNotContains(':root', RvsColorTokens::SCOPES);
	}

	public function test_tokens_constant_equals_the_eight_literal_tokens(): void
	{
		$eight = array( 'bg', 'accent-strong', 'accent-deep', 'accent-100', 'sel-text', 'on-accent', 'accent-text', 'accent-text-hover' );
		$this->assertSame($eight, RvsColorTokens::TOKENS);
		$decls = RvsTokenFile::declarations();
		foreach ($eight as $name) {
			$this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $decls[ '--rvs-' . $name ] ?? '', "--rvs-{$name} stays a literal hex in rvs-tokens.css");
			$this->assertSame(strtolower($decls[ '--rvs-' . $name ]), ColorMath::DEFAULT_TOKENS[ $name ], "--rvs-{$name} default");
		}
		$this->assertSame(RvsColorTokens::TOKENS, array_keys(ColorMath::DEFAULT_TOKENS));
	}

	public function test_default_accent_prints_nothing(): void
	{
		RvsColorTokens::attach();
		$this->assertSame(array(), self::after());

		$this->custom('#2271b1');
		RvsColorTokens::attach();
		$this->assertSame(array(), self::after(), 'Custom #2271b1 is the default palette: nothing inline');
	}

	public function test_custom_accent_prints_one_rule_with_eight_hex_tokens(): void
	{
		$this->custom('#0067ff');
		RvsColorTokens::attach();
		$after = self::after();
		$this->assertCount(1, $after);
		$css = $after[0];
		$this->assertMatchesRegularExpression(self::GRAMMAR, $css);
		$this->assertStringStartsWith(implode(',', RvsColorTokens::SCOPES) . '{', $css);

		$expected = '';
		foreach (ColorMath::palette('#0067ff')['tokens'] as $name => $value) {
			$expected .= '--rvs-' . $name . ':' . $value . ';';
		}
		$this->assertSame(implode(',', RvsColorTokens::SCOPES) . '{' . $expected . '}', $css);
	}

	/** @return array<string,array{0:string}> */
	public static function filter_payloads(): array
	{
		return array(
			'url injection' => array( '#fff;}body{background:url(//x)}' ),
			'style break'   => array( '</style><script>' ),
			'rule break'    => array( 'red;}' ),
		);
	}

	/** @dataProvider filter_payloads */
	public function test_injection_payloads_never_reach_css(string $payload): void
	{
		add_filter('mhmrentiva_accent_color', static fn (): string => $payload);
		RvsColorTokens::attach();
		$this->assertSame(array(), self::after(), 'A rejected filter value falls back to the default: nothing inline');
	}

	public function test_build_rejects_bad_values_and_unknown_names(): void
	{
		$this->assertSame(
			'',
			RvsColorTokens::build(
				array(
					'accent-strong' => 'red;}',
					'bg'            => '#fff',
				)
			)
		);
		$this->assertSame('', RvsColorTokens::build(array( 'x;}' => '#ffffff' )));

		$tokens       = ColorMath::palette('#cc0000')['tokens'];
		$tokens['bg'] = '#fff;}body{x';
		$tokens['x']  = '#000000';
		$css          = RvsColorTokens::build($tokens);
		$this->assertStringNotContainsString('--rvs-bg', $css);
		$this->assertStringNotContainsString('--rvs-x', $css);
		$this->assertStringNotContainsString('<', $css);
		$this->assertStringNotContainsString('url(', $css);
		$this->assertSame(1, substr_count($css, '}'));
	}

	public function test_attach_prints_once_across_hooks_and_styles_reset(): void
	{
		$this->custom('#cc0000');
		// Only this class on the two hooks: other plugins' callbacks (Elementor's
		// enqueue reads a queried post) are not under test. WP_UnitTestCase restores them.
		remove_all_actions('wp_enqueue_scripts');
		remove_all_actions('enqueue_block_assets');
		RvsColorTokens::register();
		do_action('enqueue_block_assets');
		do_action('wp_enqueue_scripts');
		do_action('wp_enqueue_scripts');
		$this->assertCount(1, array_filter(self::after(), static fn (string $c): bool => str_contains($c, '--rvs-accent-strong:#cc0000;')));

		$this->fresh_styles();
		RvsColorTokens::attach();
		$this->assertCount(1, self::after());
	}

	public function test_register_hooks_three_points(): void
	{
		RvsColorTokens::register();
		$this->assertSame(20, has_action('wp_enqueue_scripts', array( RvsColorTokens::class, 'attach' )));
		$this->assertSame(10, has_action('enqueue_block_assets', array( RvsColorTokens::class, 'attach' )));
		$this->assertSame(10, has_action('admin_enqueue_scripts', array( RvsColorTokens::class, 'attach_admin' )));

		$this->custom('#cc0000');
		set_current_screen('dashboard');
		RvsColorTokens::attach_admin();
		$this->assertSame(array(), self::after(), 'Not the settings screen: nothing');

		set_current_screen('mhm-rentiva_page_mhm-rentiva-settings');
		RvsColorTokens::attach_admin();
		$this->assertCount(1, self::after());
	}

	public function test_inline_style_prints_after_the_file(): void
	{
		$this->custom('#cc0000');
		RvsColorTokens::attach();

		ob_start();
		wp_styles()->do_items(array( 'mhm-rentiva-fonts' ));
		$fonts = (string) ob_get_clean();
		$this->assertStringNotContainsString('mhm-rentiva-rvs-tokens-inline-css', $fonts, 'Handle not enqueued: no inline style');

		ob_start();
		wp_styles()->do_items(array( RvsColorTokens::HANDLE ));
		$html = (string) ob_get_clean();
		$link  = strpos($html, 'rvs-tokens.css');
		$style = strpos($html, '<style id="mhm-rentiva-rvs-tokens-inline-css"');
		$this->assertNotFalse($link);
		$this->assertNotFalse($style);
		$this->assertLessThan($style, $link);
	}
}
