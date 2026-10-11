<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

use MHMRentiva\Blocks\BlockRegistry;
use MHMRentiva\Core\Attribute\AllowlistRegistry;
use MHMRentiva\Core\Attribute\KeyNormalizer;

/**
 * Does a setting change what the visitor gets? (spec 2026-10-10-elementor-kopru-dilim-3a §3 G1)
 *
 * A surface is one place a user sets a value: a block.json attribute of a
 * Rentiva block, or a data control of a Rentiva Elementor widget. has_effect()
 * renders the surface with different values on top of a fixture that reaches
 * the shortcode's real markup, normalizes each render with RenderNormalizer,
 * drops the data-* attributes no stylesheet or script reads, and compares.
 * Same HTML for every value = the setting does nothing = dead.
 *
 * Ported from the measurement tools of the 2026-10-10 audit
 * (docs/superpowers/audits/2026-10-10-elementor-kopru-dilim-3/olcum/
 * block-dead-audit-v4.php and elementor-dead-audit.php): the same surfaces, the
 * same exclusions and the same first two values. Where the tools left a live
 * setting looking dead, the probe goes further (each recorded in the Task 0
 * report): every option of a select / enum is rendered, not only the first two
 * ('rand' excluded -- a random order is not a dependable effect); select options
 * come from the editor-built stack (Elementor strips them off the editor); an
 * image-size key gets real size names instead of sentinels; editor-only
 * controls (render_type 'ui') are not surfaces.
 *
 * Shared by the Lite and the Pro suites (Pro require_once's this file from the
 * Lite checkout and installs its own fixture resolver with use_fixtures()).
 */
final class SettingProbe
{
	/**
	 * Block attributes that only feed BlockRegistry's wrapper or core block supports
	 * (block-dead-audit-v4.php:21). They never reach the shortcode by design.
	 */
	public const WRAPPER_KEYS = array( 'className', 'minWidth', 'maxWidth', 'height', 'style', 'lock', 'metadata', 'anchor', 'align' );

	/**
	 * Elementor control types that carry no value (elementor-dead-audit.php:66).
	 */
	public const UI_CONTROL_TYPES = array( 'section', 'heading', 'raw_html', 'divider', 'deprecated_notice', 'alert', 'notice', 'tabs', 'tab', 'popover_toggle', 'button' );

	public const ELEMENTOR_NS = 'MHMRentiva\\Admin\\Frontend\\Widgets\\Elementor\\';

	/**
	 * The 17 Lite widgets and the tag each one renders (ElementorWidgetBase::get_mapped_shortcode_tag()).
	 */
	public const LITE_WIDGETS = array(
		'AvailabilityCalendarWidget' => 'rentiva_availability_calendar',
		'BookingFormWidget'          => 'rentiva_booking_form',
		'ContactFormWidget'          => 'rentiva_contact',
		'FeaturedVehiclesWidget'     => 'rentiva_featured_vehicles',
		'MyBookingsWidget'           => 'rentiva_my_bookings',
		'MyFavoritesWidget'          => 'rentiva_my_favorites',
		'PaymentHistoryWidget'       => 'rentiva_payment_history',
		'SearchResultsWidget'        => 'rentiva_search_results',
		'TestimonialsWidget'         => 'rentiva_testimonials',
		'UnifiedSearchWidget'        => 'rentiva_unified_search',
		'VehicleComparisonWidget'    => 'rentiva_vehicle_comparison',
		'VehicleDetailsWidget'       => 'rentiva_vehicle_details',
		'VehicleRatingWidget'        => 'rentiva_vehicle_rating_form',
		'VehiclesGridWidget'         => 'rentiva_vehicles_grid',
		'VehiclesListWidget'         => 'rentiva_vehicles_list',
		'VehicleCardWidget'          => 'rentiva_vehicles_list',
		'UserDashboardWidget'        => 'rentiva_user_dashboard',
	);

	/**
	 * @var null|callable(string,?string):array<string,mixed>
	 */
	private static $resolver = null;

	/** @var array<string,array<string,mixed>> Fixtures built since reset(), by "tag|fixture". */
	private static array $fixtures = array();

	/** @var array<string,true>|null */
	private static ?array $read_data = null;

	/** @var array<string,array<string,mixed>> Editor-built widget stacks, by short class name. */
	private static array $editor_stacks = array();

	/** @var array<string,array<string,mixed>> Block configs block_surfaces() saw, by slug. */
	private static array $block_configs = array();

	/**
	 * Installs the fixture resolver: (tag, fixture name|null) => {user, atts, pairs?,
	 * filters?, get?, reset?, unavailable?}.
	 * `atts` are canonical shortcode attributes every render of the tag carries;
	 * `pairs` overrides the probe values of a canonical key (a value that must
	 * name fixture data, e.g. a vendor slug); `filters` are hooked around each
	 * render; `get` is merged into $_GET around each render (a search request);
	 * `reset` is called before each render and after the last (a renderer cache);
	 * `unavailable` names a precondition this process can not meet. Null restores
	 * Lite's ShortcodeFixtures::setting_probe().
	 */
	public static function use_fixtures(?callable $resolver): void
	{
		self::$resolver = $resolver;
		self::reset();
	}

	/**
	 * Forget every fixture built so far. Call it at the start of each test that
	 * probes: WP_UnitTestCase rolls the fixture rows back after the test.
	 */
	public static function reset(): void
	{
		self::$fixtures = array();
	}

	/**
	 * Every block.json attribute of every Rentiva block except the wrapper keys.
	 *
	 * @param array<string,array{tag:string,base_dir?:string}>|null $blocks slug => config; null = BlockRegistry's filtered map.
	 * @return array<int,array{tag:string,block:string,attr:string}>
	 */
	public static function block_surfaces(?array $blocks = null): array
	{
		if (null === $blocks) {
			$ref = new \ReflectionMethod(BlockRegistry::class, 'get_block_config');
			$ref->setAccessible(true);
			$blocks = (array) $ref->invoke(null);
		}

		$out = array();
		foreach ($blocks as $slug => $config) {
			self::$block_configs[ (string) $slug ] = (array) $config;
			$json = self::block_json((string) $slug, $config);
			foreach (array_keys((array) ( $json['attributes'] ?? array() )) as $attr) {
				$attr = (string) $attr;
				if (in_array($attr, self::WRAPPER_KEYS, true)) {
					continue;
				}
				$out[] = array(
					'tag'   => (string) $config['tag'],
					'block' => (string) $slug,
					'attr'  => $attr,
				);
			}
		}
		return $out;
	}

	/**
	 * Every data control of every Rentiva widget: the own stack (content and style
	 * bucket), minus value-less UI controls, group-control parts and `selectors`
	 * style controls. Controls whose id maps to no schema key are included: they
	 * are dead precisely because nothing maps them (spec §3 G1, Fable I1).
	 *
	 * @param array<string,string>|null $widgets Short class name => tag; null = LITE_WIDGETS.
	 * @return array<int,array{tag:string,widget:string,control:string}>
	 */
	public static function elementor_surfaces(?array $widgets = null): array
	{
		$out = array();
		foreach ($widgets ?? self::LITE_WIDGETS as $short => $tag) {
			foreach (array_keys(self::data_controls((string) $short)) as $id) {
				$out[] = array(
					'tag'     => (string) $tag,
					'widget'  => (string) $short,
					'control' => (string) $id,
				);
			}
		}
		return $out;
	}

	/**
	 * The canonical key a surface writes (KeyNormalizer against the tag's schema).
	 *
	 * @param array<string,string> $surface A block or an Elementor surface.
	 */
	public static function key(array $surface): string
	{
		return KeyNormalizer::normalize(self::raw_name($surface), AllowlistRegistry::get_schema($surface['tag']));
	}

	/**
	 * 'block' or 'elementor'.
	 *
	 * @param array<string,string> $surface A block or an Elementor surface.
	 */
	public static function kind(array $surface): string
	{
		return isset($surface['block']) ? 'block' : 'elementor';
	}

	/**
	 * Does setting this surface to different values change the render?
	 *
	 * @param array<string,string> $surface A block or an Elementor surface.
	 * @param string|null          $fixture Named fixture (data-dependent rows); null = the tag's base fixture.
	 */
	public static function has_effect(array $surface, ?string $fixture = null): bool
	{
		return self::probe($surface, $fixture)['effect'];
	}

	/**
	 * has_effect() with its evidence: the values sent (the measurement tool's pair
	 * first, then every other option a select or enum offers), whether a repeat of
	 * the first render differed from itself (noisy: the comparison proves nothing),
	 * and the render length. Effect = at least two of the values render differently.
	 *
	 * @param array<string,string> $surface A block or an Elementor surface.
	 * @return array{effect:bool,noisy:bool,pair:list<mixed>|null,length:int,unavailable:string}
	 */
	public static function probe(array $surface, ?string $fixture = null): array
	{
		$fx     = self::fixture($surface['tag'], $fixture);
		$values = 'block' === self::kind($surface) ? self::block_values($surface, $fx) : self::elementor_values($surface, $fx);
		if (null !== $values) {
			// A random order is not an effect a visitor could rely on, and it would
			// make two renders of one value differ.
			$values = self::distinct(array_values(array_filter($values, static fn($v): bool => 'rand' !== $v)));
		}
		if (null === $values || '' !== $fx['unavailable']) {
			// One allowed value: nothing to choose, nothing the setting can change.
			// Or a fixture this environment can not build: reported, not rendered.
			return array(
				'effect'      => false,
				'noisy'       => false,
				'pair'        => $values,
				'length'      => 0,
				'unavailable' => $fx['unavailable'],
			);
		}

		wp_set_current_user($fx['user']);
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		foreach ($fx['filters'] as $f) {
			add_filter($f[0], $f[1], $f[2] ?? 10, $f[3] ?? 1);
		}
		$get_before = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- test request state, restored below.
		$_GET       = $fx['get'] + $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		try {
			$renders = array();
			foreach ($values as $value) {
				self::reset_cache($fx);
				$renders[] = self::comparable(self::render($surface, $fx, $value));
			}
			self::reset_cache($fx);
			$first = self::render($surface, $fx, $values[0]);
			$first_cmp = self::comparable($first);
		} finally {
			self::reset_cache($fx);
			$_GET = $get_before;
			foreach ($fx['filters'] as $f) {
				remove_filter($f[0], $f[1], $f[2] ?? 10);
			}
			wp_set_current_user(0);
		}

		return array(
			'effect'      => count(array_unique($renders)) > 1,
			'noisy'       => $renders[0] !== $first_cmp,
			'pair'        => $values,
			'length'      => strlen($first),
			'unavailable' => '',
		);
	}

	/**
	 * A fixture's `reset` callable (a renderer cache to empty), before each render
	 * and after the last one, so no render reads another's cached result.
	 *
	 * @param array<string,mixed> $fx Fixture.
	 */
	private static function reset_cache(array $fx): void
	{
		if (is_callable($fx['reset'])) {
			call_user_func($fx['reset']);
		}
	}

	/**
	 * A render as compared: normalized, then without the data-* attributes no
	 * stylesheet or script reads (spec §3 G1: an unstyled data-* difference is not
	 * an effect -- the vehicles-list `data-columns` trap).
	 */
	public static function comparable(string $html): string
	{
		$read = self::read_data_attributes();
		return (string) preg_replace_callback(
			'/\sdata-([a-z0-9_-]+)(?:="[^"]*"|=\'[^\']*\')?/i',
			static function (array $m) use ($read): string {
				return isset($read[ strtolower($m[1]) ]) ? $m[0] : '';
			},
			RenderNormalizer::normalize($html)
		);
	}

	/**
	 * data-* names some stylesheet or script reads: `data-foo` / `[data-foo]`
	 * anywhere in assets/css|js, `dataset.foo`, jQuery `.data('foo')`. Lite's
	 * assets, plus Pro's when Pro is loaded (its scripts read Lite's markup too).
	 *
	 * @return array<string,true>
	 */
	public static function read_data_attributes(): array
	{
		if (null !== self::$read_data) {
			return self::$read_data;
		}

		$roots = array( MHMRENTIVA_PLUGIN_DIR );
		if (defined('MHMRENTIVA_PRO_DIR')) {
			$roots[] = (string) constant('MHMRENTIVA_PRO_DIR');
		}

		$names = array();
		foreach ($roots as $root) {
			foreach (array( 'assets/css', 'assets/js' ) as $sub) {
				$dir = rtrim((string) $root, '/') . '/' . $sub;
				if (! is_dir($dir)) {
					continue;
				}
				$it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
				foreach ($it as $file) {
					$path = (string) $file;
					if (! preg_match('/\.(css|js)$/', $path)) {
						continue;
					}
					$src = (string) file_get_contents($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin asset.
					preg_match_all('/data-([a-z0-9_-]+)/i', $src, $m);
					foreach ($m[1] as $name) {
						$names[ strtolower($name) ] = true;
					}
					preg_match_all('/(?:dataset\.([A-Za-z0-9]+)|\.data\(\s*[\'"]([A-Za-z0-9_-]+)[\'"])/', $src, $m, PREG_SET_ORDER);
					foreach ($m as $hit) {
						$name                   = '' !== $hit[1] ? $hit[1] : (string) ( $hit[2] ?? '' );
						$names[ self::kebab($name) ] = true;
					}
				}
			}
		}

		self::$read_data = $names;
		return $names;
	}

	/**
	 * Own-stack data controls of a widget, by id.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function data_controls(string $short): array
	{
		$stack = self::editor_stack($short);
		$all   = (array) ( $stack['controls'] ?? array() ) + (array) ( $stack['style_controls'] ?? array() );
		$out   = array();
		foreach ($all as $id => $control) {
			$type = (string) ( $control['type'] ?? '' );
			// render_type 'ui': Elementor's own mark for an editor-only control (the
			// box-shadow group's position select); it never re-renders the widget.
			if (in_array($type, self::UI_CONTROL_TYPES, true) || isset($control['selectors']) || isset($control['groupType']) || 'ui' === ( $control['render_type'] ?? '' )) {
				continue;
			}
			$out[ (string) $id ] = (array) $control;
		}
		return $out;
	}

	/**
	 * A widget's own stack as the editor builds it, with `options` and `label`.
	 *
	 * Off the editor Elementor strips both from every control at registration
	 * (Controls_Stack::add_control() when Performance::should_optimize_controls(),
	 * 4.3.x), so a select would offer the probe no values. Build the stack once
	 * with the optimization off, then drop it so the normal (optimized) stack is
	 * rebuilt on next use and nothing else in the process sees a changed Elementor.
	 *
	 * @return array<string,mixed>
	 */
	private static function editor_stack(string $short): array
	{
		if (isset(self::$editor_stacks[ $short ])) {
			return self::$editor_stacks[ $short ];
		}

		$prop = new \ReflectionProperty(\Elementor\Core\Frontend\Performance::class, 'is_frontend');
		$prop->setAccessible(true);
		$before  = $prop->getValue();
		$widget  = WidgetFactory::make(self::widget_class($short));
		$manager = \Elementor\Plugin::$instance->controls_manager;

		$prop->setValue(null, false);
		$manager->delete_stack($widget);
		try {
			$stack = $widget->get_stack(false);
		} finally {
			$manager->delete_stack($widget);
			$prop->setValue(null, $before);
		}

		self::$editor_stacks[ $short ] = (array) $stack;
		return self::$editor_stacks[ $short ];
	}

	/**
	 * All own-stack controls of a widget (for conditions).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function all_controls(string $short): array
	{
		$stack = WidgetFactory::make(self::widget_class($short))->get_stack(false);
		return (array) ( $stack['controls'] ?? array() ) + (array) ( $stack['style_controls'] ?? array() );
	}

	public static function widget_class(string $short): string
	{
		return false !== strpos($short, '\\') ? $short : self::ELEMENTOR_NS . $short;
	}

	/**
	 * @param array<string,mixed> $config Block config (tag, optional base_dir).
	 * @return array<string,mixed>
	 */
	public static function block_json(string $slug, array $config): array
	{
		$base = isset($config['base_dir']) ? (string) $config['base_dir'] : MHMRENTIVA_PLUGIN_DIR;
		$json = wp_json_file_decode(trailingslashit($base) . 'assets/blocks/' . $slug . '/block.json', array( 'associative' => true ));
		return is_array($json) ? $json : array();
	}

	/**
	 * Directory of a block (assets/blocks/<slug>), Lite's or an add-on's.
	 */
	public static function block_json_dir(string $slug): string
	{
		$config = self::block_config($slug);
		$base   = isset($config['base_dir']) ? (string) $config['base_dir'] : MHMRENTIVA_PLUGIN_DIR;
		return trailingslashit($base) . 'assets/blocks/' . $slug;
	}

	/**
	 * @param array<string,string> $surface A block or an Elementor surface.
	 */
	private static function raw_name(array $surface): string
	{
		return 'block' === self::kind($surface) ? $surface['attr'] : $surface['control'];
	}

	/**
	 * The fixture of a tag, built once per reset().
	 *
	 * @return array<string,mixed>
	 */
	private static function fixture(string $tag, ?string $fixture): array
	{
		$cache = $tag . '|' . ( $fixture ?? '' );
		if (! isset(self::$fixtures[ $cache ])) {
			$row = null !== self::$resolver
				? (array) call_user_func(self::$resolver, $tag, $fixture)
				: ShortcodeFixtures::setting_probe($tag, $fixture);

			self::$fixtures[ $cache ] = array(
				'user'        => (int) ( $row['user'] ?? 0 ),
				'atts'        => (array) ( $row['atts'] ?? array() ),
				'pairs'       => (array) ( $row['pairs'] ?? array() ),
				'filters'     => (array) ( $row['filters'] ?? array() ),
				'get'         => (array) ( $row['get'] ?? array() ),
				'reset'       => $row['reset'] ?? null,
				'unavailable' => (string) ( $row['unavailable'] ?? '' ),
			);
		}
		return self::$fixtures[ $cache ];
	}

	/**
	 * Two different catalog vehicles for vehicle-id probe values.
	 *
	 * @return list<int>
	 */
	private static function vehicles(): array
	{
		$catalog = ShortcodeFixtures::catalog();
		return array( $catalog[1], $catalog[2] );
	}

	/**
	 * Render one surface with one value on top of the fixture.
	 *
	 * @param array<string,string>                                                                         $surface Surface.
	 * @param array<string,mixed> $fx      Fixture.
	 * @param mixed                                                                                         $value   Value under test.
	 */
	private static function render(array $surface, array $fx, $value): string
	{
		$tag    = $surface['tag'];
		$schema = AllowlistRegistry::get_schema($tag);
		$key    = self::key($surface);

		RequestSimulator::new_request();

		if ('block' === self::kind($surface)) {
			// Fixture attributes go in under the block's own attribute name: a block.json
			// default for that name (vehicleId: 0) would otherwise win over a canonical
			// key sent beside it.
			$declared = (array) ( self::block_json($surface['block'], self::block_config($surface['block']))['attributes'] ?? array() );
			$attrs    = array();
			foreach ($fx['atts'] as $k => $v) {
				$k = (string) $k;
				// A fixture key the surface itself writes would fight the probe value.
				if (KeyNormalizer::normalize($k, $schema) === $key) {
					continue;
				}
				$name = $k;
				foreach ($declared as $attr => $def) {
					if (KeyNormalizer::normalize((string) $attr, $schema) === $k) {
						$name = (string) $attr;
						// WP drops a value of the wrong type for the declared default.
						$type = (array) ( $def['type'] ?? 'string' );
						if (in_array('integer', $type, true) || in_array('number', $type, true)) {
							$v = (int) $v;
						} elseif (in_array('boolean', $type, true)) {
							$v = filter_var($v, FILTER_VALIDATE_BOOLEAN);
						}
						break;
					}
				}
				$attrs[ $name ] = $v;
			}
			$attrs[ $surface['attr'] ] = $value;

			return render_block(
				array(
					'blockName'    => 'mhm-rentiva/' . $surface['block'],
					'attrs'        => $attrs,
					'innerHTML'    => '',
					'innerContent' => array(),
					'innerBlocks'  => array(),
				)
			);
		}

		$short    = $surface['widget'];
		$controls = self::all_controls($short);
		$settings = array();
		foreach ($fx['atts'] as $k => $v) {
			foreach ($controls as $id => $control) {
				$id = (string) $id;
				if ($id === $surface['control'] || ( $id !== $k && KeyNormalizer::normalize($id, $schema) !== $k )) {
					continue;
				}
				$ctype = (string) ( $control['type'] ?? '' );
				if ('url' === $ctype) {
					$v = array( 'url' => (string) $v, 'is_external' => '', 'nofollow' => '' );
				} elseif ('switcher' === $ctype) {
					$v = filter_var($v, FILTER_VALIDATE_BOOLEAN) ? ( $control['return_value'] ?? 'yes' ) : '';
				}
				$settings[ $id ] = $v;
			}
		}
		$settings = array_merge($settings, self::condition_settings($controls[ $surface['control'] ] ?? array()));
		$settings[ $surface['control'] ] = $value;

		$class  = self::widget_class($short);
		$widget = new $class(
			array(
				'id'       => 'zzfixed1',
				'elType'   => 'widget',
				'settings' => $settings,
			),
			array()
		);

		ob_start();
		try {
			$widget->render_content();
		} finally {
			$html = (string) ob_get_clean();
		}
		return $html;
	}

	/**
	 * Settings that satisfy a control's simple `condition`, so Elementor hands the
	 * value over (elementor-dead-audit.php:122-128).
	 *
	 * @param array<string,mixed> $control Control definition.
	 * @return array<string,mixed>
	 */
	private static function condition_settings(array $control): array
	{
		$out = array();
		foreach ((array) ( $control['condition'] ?? array() ) as $k => $v) {
			$k = (string) $k;
			if ('!' === substr($k, -1)) {
				$out[ substr($k, 0, -1) ] = ( '' === $v || array() === $v ) ? 'yes' : '';
				continue;
			}
			$out[ $k ] = is_array($v) ? reset($v) : $v;
		}
		return $out;
	}

	/**
	 * Two block attribute values (block-dead-audit-v4.php:34-53).
	 *
	 * @param array<string,string>                                                                         $surface Block surface.
	 * @param array<string,mixed> $fx      Fixture.
	 * @return array{0:mixed,1:mixed}|null
	 */
	private static function block_values(array $surface, array $fx): ?array
	{
		$key      = self::key($surface);
		$attr     = $surface['attr'];
		$json     = self::block_json($surface['block'], self::block_config($surface['block']));
		$def      = (array) ( $json['attributes'][ $attr ] ?? array() );
		$schema   = AllowlistRegistry::get_schema($surface['tag']);
		$vehicles = self::vehicles();

		$type = $def['type'] ?? 'string';
		if (is_array($type)) {
			$type = $type[0];
		}
		if (isset($fx['pairs'][ $key ])) {
			return self::typed(array_map('strval', array_values($fx['pairs'][ $key ])), (string) $type);
		}
		$enum    = $def['enum'] ?? ( $schema[ $key ]['values'] ?? null );
		$options = self::editor_options($surface['block'])[ $attr ] ?? array();
		if (is_array($enum) && count($enum) >= 2) {
			$d   = $def['default'] ?? null;
			$alt = array_values(
				array_filter(
					$enum,
					static fn($v): bool => $v !== $d && strtolower((string) $v) !== strtolower((string) $d)
				)
			);
			return self::distinct(array_merge(array( $d ?? $enum[0], $alt[0] ?? $enum[1] ), self::typed($options, $type), $enum));
		}
		if (count($options) >= 2) {
			// An editor select with no enum behind it: every option the user can pick.
			return self::distinct(self::typed($options, $type));
		}
		if (preg_match('/image_?size/i', $attr)) {
			return array( 'large', 'medium', 'thumbnail' );
		}
		$stype = (string) ( $schema[ $key ]['type'] ?? '' );
		if ('boolean' === $type || 'bool' === $stype) {
			return array( true, false );
		}
		if (in_array($stype, array( 'int', 'float' ), true) && ! preg_match('/vehicle_?id/i', $attr)) {
			return 'string' === $type ? array( '1', '3' ) : array( 1, 3 );
		}
		if (in_array($type, array( 'number', 'integer' ), true)) {
			if (preg_match('/vehicle_?id/i', $attr)) {
				return array( $vehicles[0], $vehicles[1] );
			}
			return array( 1, 3 );
		}
		if (preg_match('/vehicle_?id|^ids$/i', $attr)) {
			return array( (string) $vehicles[0], (string) $vehicles[1] );
		}
		if (preg_match('/date/i', $attr)) {
			return array( '2026-11-02', '2026-12-07' );
		}
		if (preg_match('/url|redirect/i', $attr)) {
			return array( 'https://zza.example/', 'https://zzb.example/' );
		}
		if (preg_match('/email/i', $attr)) {
			return array( 'zza@example.com', 'zzb@example.com' );
		}
		return array( 'ZZSENTA', 'ZZSENTB' );
	}

	/**
	 * Config of a block slug: the one block_surfaces() saw (an add-on block carries
	 * its base_dir there), else BlockRegistry's filtered map.
	 *
	 * @return array<string,mixed>
	 */
	private static function block_config(string $slug): array
	{
		if (isset(self::$block_configs[ $slug ])) {
			return self::$block_configs[ $slug ];
		}
		$ref = new \ReflectionMethod(BlockRegistry::class, 'get_block_config');
		$ref->setAccessible(true);
		$all = (array) $ref->invoke(null);
		return (array) ( $all[ $slug ] ?? array() );
	}

	/**
	 * Two Elementor control values (elementor-dead-audit.php:73-119).
	 *
	 * @param array<string,string>                                                                         $surface Elementor surface.
	 * @param array<string,mixed> $fx      Fixture.
	 * @return array{0:mixed,1:mixed}|null
	 */
	private static function elementor_values(array $surface, array $fx): ?array
	{
		$id       = $surface['control'];
		$key      = self::key($surface);
		$controls = self::data_controls($surface['widget']);
		$c        = $controls[ $id ] ?? array();
		$ct       = (string) ( $c['type'] ?? '' );
		$schema   = AllowlistRegistry::get_schema($surface['tag']);
		$vehicles = self::vehicles();

		if (isset($fx['pairs'][ $key ])) {
			$pair = array_values($fx['pairs'][ $key ]);
			if ('url' === $ct) {
				foreach ($pair as $i => $v) {
					$pair[ $i ] = array( 'url' => (string) $v, 'is_external' => '', 'nofollow' => '' );
				}
			}
			return $pair;
		}

		$def = $c['default'] ?? '';
		switch ($ct) {
			case 'switcher':
				return array( $c['return_value'] ?? 'yes', '' );

			case 'select':
			case 'choose':
			case 'select2':
				$opts = array_map('strval', array_keys((array) ( $c['options'] ?? array() )));
				if ('vehicle_id' === $id) {
					return array( (string) $vehicles[0], (string) $vehicles[1] );
				}
				if (! empty($c['multiple'])) {
					return array( array_slice($opts, 0, 1), array_slice($opts, 1, 1) );
				}
				$opts = array_values(array_filter($opts, static fn($v): bool => '' !== $v));
				if (count($opts) < 2) {
					// No usable options on the stack: fall back to the schema's values / type.
					$sc = $schema[ $key ] ?? array();
					$st = $sc['type'] ?? '';
					if (! empty($sc['values'])) {
						$opts = array_values(array_unique(array_map('strtolower', array_filter(array_map('strval', $sc['values']), static fn($v): bool => '' !== $v))));
					} elseif ('int' === $st) {
						$opts = array( '2', '4' );
					} elseif ('bool' === $st) {
						$opts = array( '1', '0' );
					} else {
						$opts = array( 'ZZSENTA', 'ZZSENTB' );
					}
					if (in_array($id, array( 'show_time_select', 'show_dropoff_location', 'location_required', 'fields_required' ), true)) {
						$opts = array( '1', '0' );
					}
				}
				if (count($opts) < 2) {
					return null;
				}
				$alt  = array_values(array_filter($opts, static fn($v): bool => $v !== (string) ( is_scalar($def) ? $def : '' )));
				$pair = array( $opts[0] === (string) $def ? $opts[0] : (string) $def, $alt[0] );
				if ('' === $pair[0]) {
					$pair = array( $alt[0], $alt[1] ?? $opts[0] );
				}
				// Then every other option the control offers.
				return self::distinct(array_merge($pair, $opts));

			case 'number':
				$lo = isset($c['min']) && is_numeric($c['min']) ? (int) $c['min'] : 1;
				$hi = isset($c['max']) && is_numeric($c['max']) ? (int) $c['max'] : 20;
				$a  = max($lo, 1);
				$b  = min($hi, max($a + 2, 3));
				if ($a === $b) {
					$b = $a + 1;
				}
				return array( (string) $a, (string) $b );

			case 'slider':
				return array(
					array(
						'size' => 2,
						'unit' => 'px',
					),
					array(
						'size' => 4,
						'unit' => 'px',
					),
				);

			case 'url':
				return array(
					array( 'url' => 'https://zza.example/', 'is_external' => '', 'nofollow' => '' ),
					array( 'url' => 'https://zzb.example/', 'is_external' => '', 'nofollow' => '' ),
				);

			case 'date_time':
				return array( '2026-11-02', '2026-12-07' );
		}

		$sc = $schema[ $key ] ?? array();
		if (preg_match('/image_?size/i', $id) && empty($sc['values'])) {
			return array( 'large', 'medium', 'thumbnail' );
		}
		if (in_array($sc['type'] ?? '', array( 'int', 'float' ), true) && ! preg_match('/ids$|_id$/i', $id)) {
			return array( '2', '4' );
		}
		if (! empty($sc['values'])) {
			$vv = array_values(array_filter(array_map('strval', $sc['values']), static fn($v): bool => '' !== $v && $v !== (string) ( $sc['default'] ?? '' )));
			if (count($vv) >= 2) {
				return array( $vv[0], $vv[1] );
			}
		}
		if (preg_match('/email/i', $id)) {
			return array( 'zza@example.com', 'zzb@example.com' );
		}
		if (preg_match('/url|redirect/i', $id)) {
			return array( 'https://zza.example/', 'https://zzb.example/' );
		}
		if (preg_match('/ids$|_id$/i', $id)) {
			return array( (string) $vehicles[0], (string) $vehicles[1] );
		}
		if (preg_match('/limit|count|per_page|columns|max|min|number|height|width/i', $id)) {
			return array( '1', '3' );
		}
		return array( 'ZZSENTA', 'ZZSENTB' );
	}

	/**
	 * Values in order, without repeats (case-insensitive: `asc` and `ASC` are one
	 * value to every shortcode), capped so a long enum stays affordable.
	 *
	 * @param list<mixed> $values Candidate values.
	 * @return list<mixed>|null Null when fewer than two remain.
	 */
	private static function distinct(array $values): ?array
	{
		$out  = array();
		$seen = array();
		foreach ($values as $value) {
			$k = is_scalar($value) ? strtolower(var_export($value, true)) : (string) wp_json_encode($value); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- identity key.
			if (is_string($value)) {
				$k = 's:' . strtolower($value);
			} elseif (is_int($value) || is_float($value)) {
				$k = 's:' . strtolower((string) $value);
			}
			if (isset($seen[ $k ])) {
				continue;
			}
			$seen[ $k ] = true;
			$out[]      = $value;
			if (count($out) >= 10) {
				break;
			}
		}
		return count($out) >= 2 ? $out : null;
	}

	/**
	 * Editor option strings as the block attribute's declared type (WP replaces a
	 * value of the wrong type with the default before render).
	 *
	 * @param list<string> $options Option values from the editor script.
	 * @return list<mixed>
	 */
	private static function typed(array $options, string $type): array
	{
		return array_map(
			static function (string $v) use ($type) {
				if (in_array($type, array( 'integer', 'number' ), true) && is_numeric($v)) {
					return (int) $v;
				}
				if ('boolean' === $type) {
					return 'true' === $v || '1' === $v;
				}
				return $v;
			},
			$options
		);
	}

	/**
	 * The options of every SelectControl / RadioControl in a block's editor script,
	 * by the attribute its onChange writes. Static read of the literal source
	 * (the BlockEditorWriteScanner kind of parse): `el(SelectControl, { ...
	 * options: [ { value: 'x' }, ... ], onChange: ... setAttributes({ key: ... }) })`.
	 *
	 * @return array<string,list<string>> attribute => option values
	 */
	public static function editor_options(string $slug): array
	{
		$path = self::block_json_dir($slug) . '/index.js';
		if (! is_readable($path)) {
			return array();
		}
		$js  = (string) file_get_contents($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin asset.
		$out = array();
		if (! preg_match_all('/el\(\s*(?:SelectControl|RadioControl)\s*,\s*\{/', $js, $m, PREG_OFFSET_CAPTURE)) {
			return array();
		}
		foreach ($m[0] as $hit) {
			$open = $hit[1] + strlen($hit[0]) - 1;
			$body = self::balanced($js, $open);
			if (null === $body) {
				continue;
			}
			if (! preg_match('/setAttributes\(\s*\{\s*[\'"]?([A-Za-z_]\w*)[\'"]?\s*:/', $body, $km)) {
				continue;
			}
			if (! preg_match('/options\s*:\s*\[/', $body, $om, PREG_OFFSET_CAPTURE)) {
				continue;
			}
			$list = self::balanced($body, $om[0][1] + strlen($om[0][0]) - 1);
			if (null === $list) {
				continue;
			}
			preg_match_all('/value\s*:\s*([\'"])(.*?)\1/', $list, $vm);
			$out[ $km[1] ] = array_values(array_map('strval', $vm[2]));
		}
		return $out;
	}

	/**
	 * The bracketed text starting at $open (which holds `{`, `[` or `(`), or null.
	 */
	private static function balanced(string $src, int $open): ?string
	{
		$depth = 0;
		$len   = strlen($src);
		$quote = '';
		for ($i = $open; $i < $len; $i++) {
			$c = $src[ $i ];
			if ('' !== $quote) {
				if ('\\' === $c) {
					++$i;
				} elseif ($c === $quote) {
					$quote = '';
				}
				continue;
			}
			if ('"' === $c || "'" === $c || '`' === $c) {
				$quote = $c;
			} elseif ('{' === $c || '[' === $c || '(' === $c) {
				++$depth;
			} elseif ('}' === $c || ']' === $c || ')' === $c) {
				--$depth;
				if (0 === $depth) {
					return substr($src, $open, $i - $open + 1);
				}
			}
		}
		return null;
	}

	private static function kebab(string $name): string
	{
		return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', str_replace('_', '-', $name)));
	}
}
