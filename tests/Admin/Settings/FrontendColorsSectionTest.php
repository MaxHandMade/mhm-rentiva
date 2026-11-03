<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Groups\FrontendColorsSection;
use MHMRentiva\Admin\Settings\Groups\FrontendSettings;
use MHMRentiva\Core\Color\AccentResolver;
use MHMRentiva\Core\Color\RvsColorTokens;
use MHMRentiva\Core\Color\Sources\AccentSource;
use WP_UnitTestCase;

/**
 * Settings > Frontend > Colors (spec §8, PR 3 plan R6-R12): the source line,
 * the two readability notices, the page cache note, the inert .rvs-shell
 * preview and the preview assets. Everything here is server output: the
 * section is complete without JavaScript.
 *
 * The option is held in memory by `pre_option_mhmrentiva_settings`; the
 * automatic chain's sources are fakes through `mhmrentiva_accent_sources`.
 *
 * @covers \MHMRentiva\Admin\Settings\Groups\FrontendColorsSection
 */
final class FrontendColorsSectionTest extends WP_UnitTestCase
{
	/** @var array<string, mixed> */
	private array $stored = array();

	/** @var mixed Saved $GLOBALS['wp_styles'] while a test swaps the registry. */
	private $saved_styles = null;

	/** @var mixed Saved $GLOBALS['wp_scripts'] while a test swaps the registry. */
	private $saved_scripts = null;

	private bool $swapped_assets = false;

	public function set_up(): void
	{
		parent::set_up();
		$this->stored = array();
		add_filter('pre_option_mhmrentiva_settings', fn () => $this->stored);
		self::reset_defaults_cache();
		AccentResolver::reset();
		FrontendSettings::register();
	}

	public function tear_down(): void
	{
		unset($_GET['tab']);
		if ($this->swapped_assets) {
			$GLOBALS['wp_styles']  = $this->saved_styles;
			$GLOBALS['wp_scripts'] = $this->saved_scripts;
			$this->swapped_assets  = false;
		}
		remove_all_filters('mhmrentiva_accent_color');
		set_current_screen('front');
		wp_set_current_user(0);
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

	private static function fake(string $id, ?string $hex, string $label): AccentSource
	{
		return new class( $id, $hex, $label ) implements AccentSource {
			public function __construct(private string $source_id, private ?string $hex, private string $source_label)
			{
			}

			public function id(): string
			{
				return $this->source_id;
			}

			public function label(): string
			{
				return $this->source_label;
			}

			public function resolve(): ?string
			{
				return $this->hex;
			}
		};
	}

	private function use_source(string $id, ?string $hex, string $label): void
	{
		$source = self::fake($id, $hex, $label);
		add_filter('mhmrentiva_accent_sources', static fn (): array => array( $id => $source ));
	}

	/** @param array<string, mixed> $settings */
	private function render(array $settings): string
	{
		$this->stored = $settings;
		AccentResolver::reset();
		ob_start();
		FrontendColorsSection::render_fields();
		return (string) ob_get_clean();
	}

	/** @return array<string, array{0: array{source:string,label:string,is_default:bool}, 1: string}> */
	public static function source_cases(): array
	{
		return array(
			'adapter setting'    => array( array( 'source' => 'adapter', 'label' => 'Astra', 'is_default' => false ), 'theme setting (Astra)' ),
			'adapter default'    => array( array( 'source' => 'adapter', 'label' => 'Zota', 'is_default' => true ), 'theme default (Zota)' ),
			'elementor accent'   => array( array( 'source' => 'elementor', 'label' => 'accent', 'is_default' => false ), 'Elementor global color (Accent)' ),
			'elementor primary'  => array( array( 'source' => 'elementor', 'label' => 'primary', 'is_default' => false ), 'Elementor global color (Primary)' ),
			'theme.json button'  => array( array( 'source' => 'theme_json', 'label' => 'button', 'is_default' => false ), "the theme's button color" ),
			'theme.json palette' => array( array( 'source' => 'theme_json', 'label' => 'primary', 'is_default' => false ), 'theme color palette (theme.json: primary)' ),
			'default'            => array( array( 'source' => 'default', 'label' => '', 'is_default' => false ), 'Rentiva default' ),
			'custom'             => array( array( 'source' => 'custom', 'label' => '', 'is_default' => false ), 'Custom color' ),
			'filter'             => array( array( 'source' => 'filter', 'label' => '', 'is_default' => false ), 'developer filter (mhmrentiva_accent_color)' ),
			'third party label'  => array( array( 'source' => 'mine', 'label' => 'Mine', 'is_default' => false ), 'Mine' ),
			'third party id'     => array( array( 'source' => 'mine', 'label' => '', 'is_default' => false ), 'mine' ),
		);
	}

	/**
	 * @dataProvider source_cases
	 * @param array{source:string,label:string,is_default:bool} $resolved
	 */
	public function test_source_label_sentences(array $resolved, string $expected): void
	{
		$this->assertSame($expected, FrontendColorsSection::source_label($resolved));
	}

	public function test_automatic_mode_shows_resolved_colour_and_source(): void
	{
		$this->use_source('adapter', '#0067ff', 'Astra');
		$html = $this->render(array( 'mhmrentiva_accent_mode' => 'auto' ));

		$this->assertStringContainsString('<span class="mhm-rentiva-accent-source-text">Source: theme setting (Astra)</span>', $html);
		$this->assertStringContainsString('data-template="Source: %s"', $html);
		$this->assertStringContainsString('style="background-color:#0067ff"', $html);
		$this->assertMatchesRegularExpression('/<code class="mhm-rentiva-accent-hex">#0067ff<\/code>/', $html);
		$this->assertStringContainsString('data-auto-hex="#0067ff"', $html);
		$this->assertStringContainsString('data-auto-source="theme setting (Astra)"', $html);
		$this->assertStringContainsString('data-custom-source="Custom color"', $html);
	}

	public function test_custom_saved_still_carries_the_automatic_data(): void
	{
		$this->use_source('adapter', '#0067ff', 'Astra');
		$html = $this->render(
			array(
				'mhmrentiva_accent_mode'  => 'custom',
				'mhmrentiva_accent_color' => '#cc0000',
			)
		);

		$this->assertStringContainsString('<span class="mhm-rentiva-accent-source-text">Source: Custom color</span>', $html);
		$this->assertStringContainsString('<code class="mhm-rentiva-accent-hex">#cc0000</code>', $html);
		$this->assertStringContainsString('style="background-color:#cc0000"', $html);
		$this->assertStringContainsString('data-auto-hex="#0067ff"', $html);
		$this->assertStringContainsString('data-auto-source="theme setting (Astra)"', $html);
	}

	public function test_filter_overriding_the_custom_colour_is_handed_to_the_preview(): void
	{
		add_filter('mhmrentiva_accent_color', static fn (): string => '#000000');
		$html = $this->render(
			array(
				'mhmrentiva_accent_mode'  => 'custom',
				'mhmrentiva_accent_color' => '#ffee58',
			)
		);

		$this->assertStringContainsString('data-custom-filter-hex="#000000"', $html);
		$this->assertStringContainsString('data-custom-filter-source="developer filter (mhmrentiva_accent_color)"', $html);
	}

	public function test_no_filter_means_no_custom_filter_attributes(): void
	{
		$html = $this->render(
			array(
				'mhmrentiva_accent_mode'  => 'custom',
				'mhmrentiva_accent_color' => '#ffee58',
			)
		);

		$this->assertStringNotContainsString('data-custom-filter-hex', $html);
		$this->assertStringNotContainsString('data-custom-filter-source', $html);
	}

	public function test_filter_returning_the_same_colour_means_no_custom_filter_attributes(): void
	{
		add_filter('mhmrentiva_accent_color', static fn ( $hex ) => $hex);
		$html = $this->render(
			array(
				'mhmrentiva_accent_mode'  => 'custom',
				'mhmrentiva_accent_color' => '#ffee58',
			)
		);

		$this->assertStringNotContainsString('data-custom-filter-hex', $html);
		$this->assertStringNotContainsString('data-custom-filter-source', $html);
	}

	/** @return array{text: string, fill: string} The two notice opening tags. */
	private static function notice_tags(string $html): array
	{
		preg_match('/<p class="notice notice-warning inline mhm-rentiva-accent-notice--text"[^>]*>/', $html, $text);
		preg_match('/<p class="notice notice-warning inline mhm-rentiva-accent-notice--fill"[^>]*>/', $html, $fill);
		return array(
			'text' => $text[0] ?? '',
			'fill' => $fill[0] ?? '',
		);
	}

	public function test_notices_follow_the_flags(): void
	{
		$custom = static fn (string $hex): array => array(
			'mhmrentiva_accent_mode'  => 'custom',
			'mhmrentiva_accent_color' => $hex,
		);

		$html = $this->render($custom('#0067ff'));
		$tags = self::notice_tags($html);
		$this->assertNotSame('', $tags['text']);
		$this->assertNotSame('', $tags['fill']);
		$this->assertStringNotContainsString(' hidden', $tags['text']);
		$this->assertStringContainsString(' hidden', $tags['fill']);
		$this->assertStringContainsString('#0052cc', $html);
		$this->assertMatchesRegularExpression('/data-template="[^"]*%s[^"]*"/', $tags['text']);
		$this->assertStringContainsString('This light color is hard to tell from a white background.', $html);

		$tags = self::notice_tags($this->render($custom('#ffee58')));
		$this->assertStringNotContainsString(' hidden', $tags['text']);
		$this->assertStringNotContainsString(' hidden', $tags['fill']);

		$tags = self::notice_tags($this->render($custom('#cc0000')));
		$this->assertStringContainsString(' hidden', $tags['text']);
		$this->assertStringContainsString(' hidden', $tags['fill']);
	}

	public function test_preview_markup_is_inert_and_complete(): void
	{
		$html = $this->render(array( 'mhmrentiva_accent_mode' => 'auto' ));

		$this->assertMatchesRegularExpression('/<div class="rvs-shell mhm-rentiva-accent-preview" inert aria-hidden="true">/', $html);
		$preview = substr($html, (int) strpos($html, 'mhm-rentiva-accent-preview"'));
		$this->assertStringContainsString('rvs-btn--primary', $preview);
		$this->assertStringContainsString('<a ', $preview);
		$this->assertStringContainsString('rvs-cal__day is-selected', $preview);
		$this->assertStringContainsString('rvs-cal__day is-today', $preview);
		$this->assertStringContainsString('rvs-status--confirmed', $preview);
		$this->assertStringContainsString('<p class="mhm-rentiva-accent-preview-title"><strong>Preview</strong></p>', $html);
	}

	public function test_accessible_names(): void
	{
		$html = $this->render(array( 'mhmrentiva_accent_mode' => 'auto' ));

		$this->assertStringContainsString('<legend class="screen-reader-text">Accent color mode</legend>', $html);
		$this->assertStringContainsString('<label for="mhm-rentiva-accent-color"', $html);
		$this->assertStringContainsString('id="mhm-rentiva-accent-color"', $html);
		$this->assertSame(2, substr_count($html, 'aria-live="polite"'));
		$this->assertMatchesRegularExpression('/<span class="mhm-rentiva-accent-swatch" aria-hidden="true"/', $html);
	}

	public function test_source_line_escapes_a_third_party_label(): void
	{
		$this->use_source('mine', '#0067ff', '<b>X</b>');
		$html = $this->render(array( 'mhmrentiva_accent_mode' => 'auto' ));

		$this->assertStringContainsString('Source: &lt;b&gt;X&lt;/b&gt;</span>', $html);
		$this->assertStringContainsString('data-auto-source="&lt;b&gt;X&lt;/b&gt;"', $html);
		$this->assertStringNotContainsString('<b>X', $html);
	}

	public function test_section_is_complete_without_javascript(): void
	{
		// Server output only: no script runs in this test, so whatever the
		// screen needs without JavaScript must already be in the markup.
		$this->use_source('adapter', '#0067ff', 'Astra');
		$html = $this->render(array( 'mhmrentiva_accent_mode' => 'auto' ));

		$this->assertStringContainsString('Source: theme setting (Astra)', $html);
		$tags = self::notice_tags($html);
		$this->assertStringNotContainsString(' hidden', $tags['text'], 'The right notice is visible without JS');
		$this->assertStringContainsString('#0052cc', $html);
		$this->assertStringContainsString('<p class="description">If you use a page cache plugin, clear it after changing this.</p>', $html);
		$this->assertStringContainsString('rvs-shell mhm-rentiva-accent-preview', $html);
		$this->assertMatchesRegularExpression('/value="auto"\s+checked=(["\'])checked\1/', $html);
		$this->assertStringContainsString('name="mhmrentiva_settings[mhmrentiva_accent_color]"', $html);
	}

	public function test_preview_assets_only_on_the_frontend_tab(): void
	{
		$this->use_source('adapter', '#0067ff', 'Astra');
		$this->stored = array( 'mhmrentiva_accent_mode' => 'auto' );
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		set_current_screen('mhm-rentiva_page_mhm-rentiva-settings');

		// tear_down() puts these back, even when an assertion below fails.
		$this->saved_styles  = $GLOBALS['wp_styles'] ?? null;
		$this->saved_scripts = $GLOBALS['wp_scripts'] ?? null;
		$this->swapped_assets = true;

		$handles = array_merge(FrontendColorsSection::PREVIEW_STYLES, array( 'mhm-rentiva-rvs-tokens' ));
		$scripts = array( 'mhm-rentiva-accent-palette', 'mhm-rentiva-settings-accent-color' );

		foreach (array( 'general', 'frontend' ) as $tab) {
			$GLOBALS['wp_styles']  = null;
			$GLOBALS['wp_scripts'] = null;
			AssetManager::register_core_styles();
			AccentResolver::reset();
			$_GET['tab'] = $tab;

			SettingsCore::enqueue_assets();
			RvsColorTokens::attach_admin();

			$frontend = 'frontend' === $tab;
			foreach ($handles as $handle) {
				$this->assertSame($frontend, wp_style_is($handle), $tab . ': ' . $handle);
			}
			foreach ($scripts as $handle) {
				$this->assertSame($frontend, wp_script_is($handle), $tab . ': ' . $handle);
			}
			$this->assertSame($frontend, wp_style_is('wp-color-picker'), $tab . ': wp-color-picker');
		}

		$after = wp_styles()->get_data('mhm-rentiva-rvs-tokens', 'after');
		$this->assertIsArray($after);
		$this->assertStringContainsString('--rvs-accent-strong:#0067ff', implode('', $after));
		$this->assertSame(
			array( 'jquery', 'wp-color-picker', 'mhm-rentiva-accent-palette' ),
			wp_scripts()->registered['mhm-rentiva-settings-accent-color']->deps
		);
		$this->assertSame(array(), wp_scripts()->registered['mhm-rentiva-accent-palette']->deps);
	}
}
