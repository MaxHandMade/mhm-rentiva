<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Color;

use MHMRentiva\Core\Color\AccentResolver;
use MHMRentiva\Core\Color\ColorMath;
use MHMRentiva\Core\Color\Sources\AccentSource;
use WP_UnitTestCase;

/**
 * Automatic mode walks theme adapter > Elementor kit > theme.json > #2271b1,
 * lazily; Custom mode skips every source (spec §2, §4).
 *
 * The sources are swapped through `mhmrentiva_accent_sources` with counting
 * fakes where the order is under test; the real sources run in the fall-through
 * and theme.json cases.
 */
final class AccentResolverTest extends WP_UnitTestCase
{
	/** @var array<string,mixed> */
	private array $settings = array();

	public function set_up(): void
	{
		parent::set_up();
		$this->settings = array();
		add_filter('pre_option_mhmrentiva_settings', fn () => $this->settings);
		AccentResolver::reset();
		wp_clean_theme_json_cache();
	}

	public function tear_down(): void
	{
		AccentResolver::reset();
		wp_clean_theme_json_cache();
		parent::tear_down();
	}

	private static function fake(string $id, ?string $hex, int &$calls, string $label = 'fake'): AccentSource
	{
		return new class( $id, $hex, $calls, $label ) implements AccentSource {
			/** @var int */
			private $calls;

			public function __construct(private string $source_id, private ?string $hex, int &$calls, private string $source_label)
			{
				$this->calls = &$calls;
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
				++$this->calls;
				return $this->hex;
			}
		};
	}

	/** @param array<string,AccentSource|mixed> $sources */
	private function use_sources(array $sources): void
	{
		add_filter('mhmrentiva_accent_sources', static fn (): array => $sources);
	}

	public function test_order_adapter_beats_elementor_beats_theme_json(): void
	{
		$a = $e = $t = 0;
		$this->use_sources(
			array(
				'adapter'    => self::fake('adapter', '#111aaa', $a),
				'elementor'  => self::fake('elementor', '#222bbb', $e),
				'theme_json' => self::fake('theme_json', '#333ccc', $t),
			)
		);
		$this->assertSame('#111aaa', AccentResolver::resolve()['hex']);
		$this->assertSame('adapter', AccentResolver::resolve()['source']);

		remove_all_filters('mhmrentiva_accent_sources');
		AccentResolver::reset();
		$this->use_sources(
			array(
				'adapter'    => self::fake('adapter', null, $a),
				'elementor'  => self::fake('elementor', '#222BBB', $e, 'accent'),
				'theme_json' => self::fake('theme_json', '#333ccc', $t),
			)
		);
		$r = AccentResolver::resolve();
		$this->assertSame(array( '#222bbb', 'elementor', 'accent' ), array( $r['hex'], $r['source'], $r['label'] ));

		remove_all_filters('mhmrentiva_accent_sources');
		AccentResolver::reset();
		$this->use_sources(
			array(
				'adapter'    => self::fake('adapter', null, $a),
				'elementor'  => self::fake('elementor', null, $e),
				'theme_json' => self::fake('theme_json', '#333ccc', $t, 'primary'),
			)
		);
		$this->assertSame('theme_json', AccentResolver::resolve()['source']);
	}

	public function test_factory_kit_and_core_button_fall_through_to_default(): void
	{
		add_filter('template', static fn (): string => 'twentytwentyfive');
		if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->kits_manager)) {
			$kit_id = (int) \Elementor\Plugin::$instance->kits_manager->create_default();
			update_option('elementor_active_kit', $kit_id);
			$kit = \Elementor\Plugin::$instance->documents->get($kit_id, false);
			wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
			$kit->update_settings(
				array(
					'system_colors' => array(
						array(
							'_id'   => 'primary',
							'title' => 'Primary',
							'color' => '#6EC1E4',
						),
						array(
							'_id'   => 'accent',
							'title' => 'Accent',
							'color' => '#61CE70',
						),
					),
				)
			);
			wp_set_current_user(0);
		}
		$r = AccentResolver::resolve();
		$this->assertSame('#2271b1', $r['hex']);
		$this->assertSame('default', $r['source']);
		$this->assertSame(ColorMath::DEFAULT_TOKENS, $r['tokens']);
		$this->assertFalse($r['flags']['text_darkened']);
	}

	public function test_theme_json_palette_custom_before_theme_and_rejects_var(): void
	{
		add_filter('template', static fn (): string => 'twentytwentyfive');
		$this->use_sources(array( 'theme_json' => new \MHMRentiva\Core\Color\Sources\ThemeJsonSource() ));

		add_filter(
			'wp_theme_json_data_default',
			static fn ($data) => $data->update_with(
				array(
					'version'  => 3,
					'settings' => array(
						'color' => array(
							'palette' => array(
								array(
									'slug'  => 'primary',
									'color' => '#999999',
									'name'  => 'Core primary',
								),
							),
						),
					),
				)
			)
		);
		add_filter(
			'wp_theme_json_data_theme',
			static fn ($data) => $data->update_with(
				array(
					'version'  => 3,
					'settings' => array(
						'color' => array(
							'palette' => array(
								array(
									'slug'  => 'primary',
									'color' => 'var(--x)',
									'name'  => 'Primary',
								),
								array(
									'slug'  => 'accent',
									'color' => '#00aa00',
									'name'  => 'Accent',
								),
							),
						),
					),
				)
			)
		);
		wp_clean_theme_json_cache();
		$r = AccentResolver::resolve();
		$this->assertSame(array( '#00aa00', 'theme_json', 'accent' ), array( $r['hex'], $r['source'], $r['label'] ), 'theme primary var() rejected, theme accent used');

		add_filter(
			'wp_theme_json_data_user',
			static fn ($data) => $data->update_with(
				array(
					'version'  => 3,
					'settings' => array(
						'color' => array(
							'palette' => array(
								'custom' => array(
									array(
										'slug'  => 'accent',
										'color' => '#0D6DD7',
										'name'  => 'Accent',
									),
								),
							),
						),
					),
				)
			)
		);
		wp_clean_theme_json_cache();
		AccentResolver::reset();
		$r = AccentResolver::resolve();
		$this->assertSame(array( '#0d6dd7', 'accent' ), array( $r['hex'], $r['label'] ), 'custom origin before theme');

		// The default origin is never read: without theme and custom, no colour.
		remove_all_filters('wp_theme_json_data_theme');
		remove_all_filters('wp_theme_json_data_user');
		wp_clean_theme_json_cache();
		AccentResolver::reset();
		$this->assertSame('default', AccentResolver::resolve()['source']);
	}

	public function test_custom_mode_overrides_chain(): void
	{
		$calls          = 0;
		$this->settings = array(
			'mhmrentiva_accent_mode'  => 'custom',
			'mhmrentiva_accent_color' => '#cc0000',
		);
		$this->use_sources(array( 'adapter' => self::fake('adapter', '#111aaa', $calls) ));
		$r = AccentResolver::resolve();
		$this->assertSame(array( '#cc0000', 'custom' ), array( $r['hex'], $r['source'] ));
		$this->assertSame(0, $calls, 'Custom mode runs no source');
		$this->assertSame(ColorMath::palette('#cc0000')['tokens'], $r['tokens']);
	}

	public function test_stored_garbage_custom_color_falls_back_to_auto(): void
	{
		$calls          = 0;
		$this->settings = array(
			'mhmrentiva_accent_mode'  => 'custom',
			'mhmrentiva_accent_color' => 'red;}body{x}',
		);
		$this->use_sources(array( 'adapter' => self::fake('adapter', '#111aaa', $calls) ));
		$warnings = array();
		set_error_handler(
			static function (int $errno, string $errstr) use (&$warnings): bool {
				$warnings[] = $errstr;
				return true;
			},
			E_WARNING | E_NOTICE | E_DEPRECATED
		);
		$r = AccentResolver::resolve();
		restore_error_handler();
		$this->assertSame(array( '#111aaa', 'adapter' ), array( $r['hex'], $r['source'] ));
		$this->assertSame(1, $calls);
		$this->assertSame(array(), $warnings);
	}

	public function test_sources_filter_reorders_and_adds(): void
	{
		$a = $t = $x = 0;
		add_filter(
			'mhmrentiva_accent_sources',
			static function (array $sources) use (&$a, &$t, &$x): array {
				return array(
					'theme_json' => self::fake('theme_json', null, $t),
					'junk'       => 'not a source',
					'object'     => new \stdClass(),
					'mine'       => self::fake('mine', '#ABCDEF', $x, 'Mine'),
					'adapter'    => self::fake('adapter', '#111aaa', $a),
				);
			}
		);
		$r = AccentResolver::resolve();
		$this->assertSame(array( '#abcdef', 'mine', 'Mine' ), array( $r['hex'], $r['source'], $r['label'] ));
		$this->assertSame(array( 1, 1, 0 ), array( $t, $x, $a ));
	}

	public function test_accent_color_filter_has_last_word_and_is_normalized(): void
	{
		$calls = 0;
		$this->use_sources(array( 'adapter' => self::fake('adapter', '#111aaa', $calls) ));
		$seen = array();
		add_filter(
			'mhmrentiva_accent_color',
			static function (string $hex, string $source) use (&$seen): string {
				$seen = array( $hex, $source );
				return '#ABC';
			},
			10,
			2
		);
		$this->assertSame('#aabbcc', AccentResolver::resolve()['hex']);
		$this->assertSame('filter', AccentResolver::resolve()['source']);
		$this->assertSame(array( '#111aaa', 'adapter' ), $seen);

		remove_all_filters('mhmrentiva_accent_color');
		add_filter('mhmrentiva_accent_color', static fn (): string => '</style>');
		AccentResolver::reset();
		$r = AccentResolver::resolve();
		$this->assertSame(array( '#2271b1', 'default' ), array( $r['hex'], $r['source'] ));
	}

	public function test_memoized_until_reset(): void
	{
		$calls = 0;
		$this->use_sources(array( 'adapter' => self::fake('adapter', '#111aaa', $calls) ));
		AccentResolver::resolve();
		AccentResolver::resolve();
		$this->assertSame(1, $calls);
		AccentResolver::reset();
		AccentResolver::resolve();
		$this->assertSame(2, $calls);
	}

	public function test_lazy_sources_stop_at_first_colour(): void
	{
		$a = $e = $t = 0;
		$this->use_sources(
			array(
				'adapter'    => self::fake('adapter', '#111aaa', $a),
				'elementor'  => self::fake('elementor', '#222bbb', $e),
				'theme_json' => self::fake('theme_json', '#333ccc', $t),
			)
		);
		AccentResolver::resolve();
		$this->assertSame(array( 1, 0, 0 ), array( $a, $e, $t ));
	}

	public function test_automatic_ignores_custom_mode(): void
	{
		$calls          = 0;
		$this->settings = array(
			'mhmrentiva_accent_mode'  => 'custom',
			'mhmrentiva_accent_color' => '#cc0000',
		);
		$this->use_sources(array( 'adapter' => self::fake('adapter', '#111aaa', $calls) ));
		$auto = AccentResolver::automatic();
		$this->assertSame(array( '#111aaa', 'adapter' ), array( $auto['hex'], $auto['source'] ));
		$this->assertSame('#cc0000', AccentResolver::resolve()['hex']);
		$this->assertSame('custom', AccentResolver::resolve()['source']);
	}

	public function test_custom_resolve_still_calls_no_source(): void
	{
		$calls          = 0;
		$this->settings = array(
			'mhmrentiva_accent_mode'  => 'custom',
			'mhmrentiva_accent_color' => '#cc0000',
		);
		$this->use_sources(array( 'adapter' => self::fake('adapter', '#111aaa', $calls) ));
		AccentResolver::resolve();
		$this->assertSame(0, $calls);
	}

	public function test_reset_clears_both_memories(): void
	{
		$calls          = 0;
		$this->settings = array(
			'mhmrentiva_accent_mode'  => 'custom',
			'mhmrentiva_accent_color' => '#cc0000',
		);
		$this->use_sources(array( 'adapter' => self::fake('adapter', '#111aaa', $calls) ));
		AccentResolver::automatic();
		AccentResolver::automatic();
		AccentResolver::resolve();
		$this->assertSame(1, $calls);
		AccentResolver::reset();
		AccentResolver::automatic();
		$this->assertSame(2, $calls);
		AccentResolver::reset();
		$this->settings = array();
		AccentResolver::resolve();
		$this->assertSame(3, $calls);
		AccentResolver::resolve();
		$this->assertSame(3, $calls);
	}

	public function test_filter_that_changes_a_source_colour_is_the_source(): void
	{
		$calls = 0;
		$this->use_sources(array( 'adapter' => self::fake('adapter', '#111aaa', $calls, 'Astra') ));
		add_filter('mhmrentiva_accent_color', static fn (): string => '#222bbb');
		$r = AccentResolver::resolve();
		$this->assertSame(array( '#222bbb', 'filter', '', false ), array( $r['hex'], $r['source'], $r['label'], $r['is_default'] ));

		remove_all_filters('mhmrentiva_accent_color');
		add_filter('mhmrentiva_accent_color', static fn (string $hex): string => strtoupper($hex));
		AccentResolver::reset();
		$r = AccentResolver::resolve();
		$this->assertSame(array( '#111aaa', 'adapter', 'Astra' ), array( $r['hex'], $r['source'], $r['label'] ));
	}

	public function test_filter_that_changes_a_custom_colour_is_the_source(): void
	{
		$calls          = 0;
		$this->settings = array(
			'mhmrentiva_accent_mode'  => 'custom',
			'mhmrentiva_accent_color' => '#cc0000',
		);
		$this->use_sources(array( 'adapter' => self::fake('adapter', '#111aaa', $calls) ));
		$cb = static fn (): string => '#00aa00';
		add_filter('mhmrentiva_accent_color', $cb);
		$r = AccentResolver::resolve();
		remove_filter('mhmrentiva_accent_color', $cb);
		$this->assertSame(array( '#00aa00', 'filter', '', false ), array( $r['hex'], $r['source'], $r['label'], $r['is_default'] ));
		$this->assertSame(0, $calls, 'Custom mode runs no source');
	}
}
