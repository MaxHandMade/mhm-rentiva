<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Color;

use MHMRentiva\Core\Color\Sources\AdapterSource;
use MHMRentiva\Core\Color\ThemeAdapters;
use WP_UnitTestCase;

/**
 * Each theme adapter returns the theme's effective colour (spec §2.1): the saved
 * value, else the theme's own default; null only when the theme has no default
 * or is in an unexpected shape, and never with a PHP warning.
 *
 * The test site runs no theme (get_template() === 'default', Task 0 probe), so
 * the template is set with the `template` filter and Astra is a shim of the
 * same shape (tests/fixtures/theme-adapters/astra-shim.php).
 */
final class ThemeAdaptersTest extends WP_UnitTestCase
{
	/** @var list<string> */
	private array $warnings = array();

	public static function set_up_before_class(): void
	{
		parent::set_up_before_class();
		require_once dirname(__DIR__, 2) . '/fixtures/theme-adapters/thembay-functions.php';
		require_once dirname(__DIR__, 2) . '/fixtures/theme-adapters/astra-shim.php';
	}

	private function use_template(string $template): void
	{
		add_filter('template', static fn (): string => $template);
	}

	/** @return array{id: string, hex: string, is_default: bool}|null */
	private function read(): ?array
	{
		return ThemeAdapters::read_active();
	}

	public function test_thembay_reads_saved_main_color(): void
	{
		$this->use_template('lasa');
		update_option('lasa_tbay_theme_options', array( 'main_color' => '#dd3333' ));
		$this->assertSame(
			array(
				'id'         => 'thembay',
				'hex'        => '#dd3333',
				'is_default' => false,
			),
			$this->read()
		);
	}

	public function test_thembay_empty_falls_to_theme_default(): void
	{
		$this->use_template('lasa');
		$this->assertSame(
			array(
				'id'         => 'thembay',
				'hex'        => '#cc0000',
				'is_default' => true,
			),
			$this->read()
		);

		remove_all_filters('template');
		$this->use_template('zota');
		update_option('zota_tbay_theme_options', array( 'main_color' => '' ));
		$this->assertSame('#0d6dd7', $this->read()['hex'] ?? null);
		$this->assertTrue($this->read()['is_default'] ?? false);
	}

	public function test_thembay_saved_default_is_default(): void
	{
		$this->use_template('lasa');
		update_option('lasa_tbay_theme_options', array( 'main_color' => '#CC0000' ));
		$this->assertSame('#cc0000', $this->read()['hex'] ?? null);
		$this->assertTrue($this->read()['is_default'] ?? false);
	}

	public function test_thembay_ruza_empty_falls_to_feb000(): void
	{
		$this->use_template('ruza');
		$this->assertSame(
			array(
				'id'         => 'thembay',
				'hex'        => '#feb000',
				'is_default' => true,
			),
			$this->read()
		);
	}

	public function test_thembay_without_default_function_and_empty_option_is_null(): void
	{
		$this->use_template('tbayfake');
		$this->assertNull($this->read());
		update_option('tbayfake_tbay_theme_options', array( 'main_color' => '#abcdef' ));
		$this->assertSame('#abcdef', $this->read()['hex'] ?? null);
		$this->assertFalse($this->read()['is_default'] ?? true);
	}

	public function test_thembay_default_function_with_required_params_is_ignored(): void
	{
		$this->use_template('tbayargs');
		$this->collect_warnings();
		$this->assertNull($this->read());
		$this->assertSame(array(), $this->stop_collecting());
	}

	public function test_avanam_detects_without_option_and_returns_default(): void
	{
		$this->use_template('avanam');
		delete_option('base_global_palette');
		$this->assertSame(
			array(
				'id'         => 'avanam',
				'hex'        => '#2b6cb0',
				'is_default' => true,
			),
			$this->read()
		);
	}

	public function test_avanam_three_active_sets(): void
	{
		$this->use_template('avanam');
		$sets = array(
			'palette'        => '#111aaa',
			'second-palette' => '#222bbb',
			'third-palette'  => '#333ccc',
		);
		foreach ($sets as $active => $hex) {
			$json = array( 'active' => $active );
			foreach ($sets as $name => $colour) {
				$json[ $name ] = array(
					array(
						'slug'  => 'palette1',
						'color' => strtoupper($colour),
					),
					array(
						'slug'  => 'palette2',
						'color' => '#000000',
					),
				);
			}
			update_option('base_global_palette', wp_json_encode($json));
			$this->assertSame($hex, $this->read()['hex'] ?? null, "active {$active}");
			$this->assertFalse($this->read()['is_default'] ?? true);
		}
	}

	public function test_avanam_wrong_slug_returns_default(): void
	{
		$this->use_template('avanam');
		update_option(
			'base_global_palette',
			wp_json_encode(
				array(
					'active'  => 'palette',
					'palette' => array(
						array(
							'slug'  => 'palette2',
							'color' => '#abcdef',
						),
					),
				)
			)
		);
		$this->assertSame('#2b6cb0', $this->read()['hex'] ?? null);
		$this->assertTrue($this->read()['is_default'] ?? false);
	}

	public function test_listeo_option_and_css_fallback(): void
	{
		$this->use_template('listeo');
		delete_option('pp_main_color');
		$this->assertSame(
			array(
				'id'         => 'listeo',
				'hex'        => '#f30c0c',
				'is_default' => true,
			),
			$this->read()
		);
		update_option('pp_main_color', '#f91942');
		$this->assertSame(
			array(
				'id'         => 'listeo',
				'hex'        => '#f91942',
				'is_default' => false,
			),
			$this->read()
		);
	}

	public function test_politic_empty_is_null(): void
	{
		$this->use_template('politic');
		$this->assertNull($this->read());
		update_option('politicem_opt', array( 'politicem_primary_color' => '' ));
		$this->assertNull($this->read());
		update_option('politicem_opt', array( 'politicem_primary_color' => '#145DA0' ));
		$this->assertSame(
			array(
				'id'         => 'politic',
				'hex'        => '#145da0',
				'is_default' => false,
			),
			$this->read()
		);
	}

	public function test_upstudy_theme_mod_and_default(): void
	{
		$this->use_template('upstudy');
		remove_theme_mod('primary_color');
		$this->assertSame(
			array(
				'id'         => 'upstudy',
				'hex'        => '#1cb098',
				'is_default' => true,
			),
			$this->read()
		);
		set_theme_mod('primary_color', '#7a3cff');
		$this->assertSame('#7a3cff', $this->read()['hex'] ?? null);
		$this->assertFalse($this->read()['is_default'] ?? true);
		remove_theme_mod('primary_color');
	}

	public function test_astra_active_palette_first_color(): void
	{
		$this->use_template('astra');
		$this->assertTrue(function_exists('astra_get_palette_colors'));
		update_option(
			'astra-color-palettes',
			array(
				'currentPalette' => 'palette_2',
				'palettes'       => array(
					'palette_1' => array( '#046bd2', '#045cb4' ),
					'palette_2' => array( '#0067FF', '#005EE9' ),
				),
			)
		);
		$this->assertSame('astra', $this->read()['id'] ?? null);
		$this->assertSame('#0067ff', $this->read()['hex'] ?? null);

		// No saved palettes: Astra's own default palette, flagged as default.
		delete_option('astra-color-palettes');
		$this->assertSame('#046bd2', $this->read()['hex'] ?? null);
		$this->assertTrue($this->read()['is_default'] ?? false);
	}

	public function test_wrong_shapes_return_null_without_warnings(): void
	{
		$this->collect_warnings();

		$this->use_template('lasa');
		update_option('lasa_tbay_theme_options', 'not-an-array');
		$this->assertSame('#cc0000', $this->read()['hex'] ?? null, 'Redux string -> theme default');
		update_option('lasa_tbay_theme_options', array( 'main_color' => array( 'x' ) ));
		$this->assertSame('#cc0000', $this->read()['hex'] ?? null, 'main_color array -> theme default');

		remove_all_filters('template');
		$this->use_template('avanam');
		update_option('base_global_palette', '{bozuk');
		$this->assertSame('#2b6cb0', $this->read()['hex'] ?? null, 'broken JSON -> default');
		update_option('base_global_palette', wp_json_encode(array( 'palette' => array() )));
		$this->assertSame('#2b6cb0', $this->read()['hex'] ?? null, 'no active key -> default');
		update_option(
			'base_global_palette',
			wp_json_encode(
				array(
					'active'  => 'palette',
					'palette' => array(),
				)
			)
		);
		$this->assertSame('#2b6cb0', $this->read()['hex'] ?? null, 'empty palette -> default');
		update_option(
			'base_global_palette',
			wp_json_encode(
				array(
					'active'  => array( 'x' ),
					'palette' => 'x',
				)
			)
		);
		$this->assertSame('#2b6cb0', $this->read()['hex'] ?? null, 'active not a string -> default');

		remove_all_filters('template');
		$this->use_template('politic');
		update_option('politicem_opt', 'string');
		$this->assertNull($this->read(), 'politic option string -> null');

		remove_all_filters('template');
		$this->use_template('astra');
		update_option('astra-color-palettes', array( 'currentPalette' => 'palette_9', 'palettes' => 'x' ));
		$this->assertNull($this->read(), 'Astra palettes not an array -> null');
		update_option(
			'astra-color-palettes',
			array(
				'currentPalette' => 'palette_9',
				'palettes'       => array( 'palette_1' => array( '#046bd2' ) ),
			)
		);
		$this->assertNull($this->read(), 'Astra active key missing -> null');

		$this->assertSame(array(), $this->stop_collecting());
	}

	public function test_unknown_theme_is_null(): void
	{
		$this->use_template('twentytwentyfive');
		$this->assertNull($this->read());
	}

	public function test_filter_adds_and_removes_adapter(): void
	{
		$this->use_template('astra');
		add_filter(
			'mhmrentiva_theme_adapters',
			static function (array $adapters): array {
				unset($adapters['astra']);
				$adapters['broken'] = array(
					'label'  => 'Broken',
					'detect' => 'not a callable function',
					'read'   => static fn (): array => array( 'hex' => '#ff0000' ),
				);
				$adapters['fake'] = array(
					'label'  => 'Fake',
					'detect' => static fn (): bool => 'astra' === get_template(),
					'read'   => static fn (): array => array(
						'hex'        => '#ABCDEF',
						'is_default' => false,
					),
				);
				return $adapters;
			}
		);
		$all = ThemeAdapters::all();
		$this->assertArrayNotHasKey('astra', $all);
		$this->assertArrayNotHasKey('broken', $all);
		$this->assertSame(
			array(
				'id'         => 'fake',
				'hex'        => '#abcdef',
				'is_default' => false,
			),
			$this->read()
		);
	}

	public function test_adapter_source_exposes_is_default_and_label(): void
	{
		$this->use_template('ruza');
		$source = new AdapterSource();
		$this->assertSame('adapter', $source->id());
		$this->assertSame('#feb000', $source->resolve());
		$this->assertTrue($source->is_default());
		$this->assertSame('thembay', $source->adapter());
		// ruza is not installed in the test site: the label falls back to the template.
		$theme = wp_get_theme('ruza');
		$this->assertSame($theme->exists() ? $theme->get('Name') : 'ruza', $source->label());

		remove_all_filters('template');
		$this->use_template('twentytwentyfive');
		$none = new AdapterSource();
		$this->assertNull($none->resolve());
		$this->assertFalse($none->is_default());
	}

	private function collect_warnings(): void
	{
		$this->warnings = array();
		set_error_handler(
			function (int $errno, string $errstr, string $file = '', int $line = 0): bool {
				$this->warnings[] = "{$errno}: {$errstr} @ {$file}:{$line}";
				return true;
			},
			E_WARNING | E_NOTICE | E_DEPRECATED | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED
		);
	}

	/** @return list<string> */
	private function stop_collecting(): array
	{
		restore_error_handler();
		return $this->warnings;
	}
}
