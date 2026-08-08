<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

/**
 * Locale resolution in Plugin::load_textdomain().
 *
 * WordPress 7.0 removed the `plugin_locale` filter. Core's own
 * load_plugin_textdomain() no longer resolves a locale at all (it registers a
 * path with WP_Textdomain_Registry and returns), and the string `plugin_locale`
 * appears in zero files under wp-includes/ and wp-admin/ -- there is not even a
 * deprecation shim. determine_locale() is the current mechanism, and it already
 * applies the core-owned `pre_determine_locale` and `determine_locale` filters
 * itself, so overriding the locale is still possible without the plugin firing
 * an unprefixed hook name of its own.
 *
 * These tests lock both halves of that:
 *  - the plugin never fires the dropped core hook (a prefixing finding for
 *    WP.org, since nothing in core owns the name any more), and
 *  - the locale actually used to pick the .mo file is exactly what
 *    determine_locale() returns, override included.
 */
final class PluginTextdomainLocaleTest extends \WP_UnitTestCase
{
	/**
	 * Token-level scan of every shipped PHP file: a docblock or string literal
	 * mentioning the function stays legal, but any code-level reference (call,
	 * callable, alias) anywhere in the shipped tree fails. Editing a comment
	 * cannot satisfy this assertion.
	 */
	public function test_no_shipped_php_file_references_the_discouraged_plugin_textdomain_registration(): void
	{
		$targets = array(
			MHMRENTIVA_PLUGIN_DIR . 'src',
			MHMRENTIVA_PLUGIN_DIR . 'templates',
			MHMRENTIVA_PLUGIN_DIR . 'blocks',
		);
		foreach (glob(MHMRENTIVA_PLUGIN_DIR . '*.php') ?: array() as $root_file) {
			$targets[] = $root_file;
		}

		$references = array();
		foreach ($targets as $target) {
			$files = is_dir($target)
				? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS))
				: array( new \SplFileInfo($target) );

			foreach ($files as $file) {
				if (strtolower($file->getExtension()) !== 'php') {
					continue;
				}

				foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
					if (is_array($token) && T_STRING === $token[0] && 'load_plugin_textdomain' === $token[1]) {
						$references[] = substr($file->getPathname(), strlen(MHMRENTIVA_PLUGIN_DIR)) . ':' . $token[2];
					}
				}
			}
		}

		$this->assertSame(
			array(),
			$references,
			'Plugin Check flags load_plugin_textdomain() as unnecessary since WordPress 4.6; the explicit determine_locale() loader in Plugin::load_textdomain() must remain the only loader.'
		);
	}

	/**
	 * Callbacks registered by a test, removed again in tearDown.
	 *
	 * @var array<int, array{0:string, 1:callable, 2:int}>
	 */
	private array $registered = array();

	public function set_up(): void
	{
		parent::set_up();
		unload_textdomain('mhm-rentiva');
	}

	public function tear_down(): void
	{
		foreach ($this->registered as $entry) {
			remove_filter($entry[0], $entry[1], $entry[2]);
		}
		$this->registered = array();

		unload_textdomain('mhm-rentiva');
		parent::tear_down();
	}

	/**
	 * Register a filter and schedule its removal.
	 */
	private function hook(string $tag, callable $callback, int $priority = 10, int $args = 1): void
	{
		add_filter($tag, $callback, $priority, $args);
		$this->registered[] = array( $tag, $callback, $priority );
	}

	/**
	 * Invoke the method under test without running the constructor, which would
	 * register the whole plugin hook graph a second time.
	 */
	private function run_load_textdomain(): void
	{
		$plugin = ( new \ReflectionClass(\MHMRentiva\Plugin::class) )->newInstanceWithoutConstructor();
		$plugin->load_textdomain();
	}

	/**
	 * Capture the .mo path the method hands to core, if it hands one over at all.
	 */
	private function capture_loaded_mofile(): ?string
	{
		$seen = null;
		$this->hook(
			'load_textdomain',
			static function ($domain, $mofile) use (&$seen): void {
				if ($domain === 'mhm-rentiva') {
					$seen = $mofile;
				}
			},
			10,
			2
		);

		$this->run_load_textdomain();

		return $seen;
	}

	/**
	 * The hook WordPress 7.0 dropped must not be fired by this plugin.
	 */
	public function test_dropped_core_plugin_locale_filter_is_never_fired(): void
	{
		$fired = 0;
		$this->hook(
			'plugin_locale',
			static function ($locale) use (&$fired) {
				++$fired;

				return $locale;
			},
			10,
			2
		);

		$this->run_load_textdomain();

		$this->assertSame(
			0,
			$fired,
			'Plugin::load_textdomain() still applies the `plugin_locale` filter. WordPress 7.0 removed that name from core, so the plugin would be the only party firing an unprefixed global hook.'
		);
	}

	/**
	 * A locale override reaches the file lookup, and it does so through the
	 * mechanism core still ships.
	 */
	public function test_determine_locale_override_selects_the_matching_catalog(): void
	{
		$this->hook('determine_locale', static fn (): string => 'tr_TR');

		$mofile = $this->capture_loaded_mofile();

		$this->assertNotNull($mofile, 'No catalog was loaded for tr_TR, but the plugin ships languages/mhm-rentiva-tr_TR.mo.');
		$this->assertSame(
			'mhm-rentiva-tr_TR.mo',
			basename($mofile),
			'The .mo file picked does not match the locale determine_locale() returned.'
		);
		$this->assertTrue(
			is_textdomain_loaded('mhm-rentiva'),
			'The catalog path was handed to core but the text domain did not end up loaded.'
		);
	}

	/**
	 * The locale used is not a hardcoded value: it is whatever determine_locale()
	 * reports at the moment the method runs.
	 */
	public function test_locale_used_equals_determine_locale_result(): void
	{
		$this->hook('determine_locale', static fn (): string => 'tr_TR');

		$mofile = $this->capture_loaded_mofile();

		$this->assertNotNull($mofile);
		$this->assertSame(
			'mhm-rentiva-' . determine_locale() . '.mo',
			basename($mofile),
			'The locale baked into the .mo filename diverged from determine_locale().'
		);
	}

	/**
	 * Negative control: proves the filename really is locale-derived rather than
	 * the assertions above passing for an unrelated reason. de_DE ships no
	 * catalog, so the file_exists() guard must stop the load.
	 */
	public function test_locale_without_a_shipped_catalog_loads_nothing(): void
	{
		$this->hook('determine_locale', static fn (): string => 'de_DE');

		$mofile = $this->capture_loaded_mofile();

		$this->assertNull($mofile, 'A catalog was loaded for de_DE, which the plugin does not ship.');
		$this->assertFalse(is_textdomain_loaded('mhm-rentiva'));
	}
}
