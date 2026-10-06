<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Elementor;

use MHMRentiva\Core\Attribute\AllowlistRegistry;
use MHMRentiva\Core\Attribute\KeyNormalizer;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Spec §3 T2: every schema-mapped control of every Lite widget reaches its shortcode.
 *
 * Each control under test is set alone to a probe value on a real Elementor 4.3.2
 * widget, the widget is rendered through render_content() (the editor AJAX path),
 * and the attributes the shortcode received are read from its
 * `mhmrentiva_shortcodes_{tag}_html` filter. A control whose value does not arrive
 * is dead: the user changes it and nothing happens.
 *
 * Controls under test (r6 scope rule): the widget's own stack (get_stack( false ),
 * both the content and the style bucket), not a section, no `selectors` key (a
 * style control changes CSS, never an attribute), and its id normalizes to a key
 * of the tag's schema.
 *
 * Known dead controls live in widget-contract-exceptions.php; the reverse checks
 * keep that file from going stale.
 */
final class WidgetContractTest extends WP_UnitTestCase
{
	private const NS = 'MHMRentiva\\Admin\\Frontend\\Widgets\\Elementor\\';

	/**
	 * The 17 Lite widgets and the tag each one renders.
	 */
	private const WIDGETS = array(
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
	 * Tags behind the wrapper's login gate: a guest render never reaches the shortcode.
	 */
	private const AUTH_TAGS = array( 'rentiva_my_bookings', 'rentiva_my_favorites', 'rentiva_payment_history' );

	private const REASON_PATTERN = '/^(layout-class-only|deferred-dilim-3:[a-z0-9_]+)$/';

	private const SEAM_NOT_REACHED = '<seam not reached>';

	private const ABSENT = '<absent>';

	/**
	 * Probe results for the whole process: rendering ~450 widgets once is enough
	 * for all assertions in this class.
	 *
	 * @var array{rows:list<array<string,mixed>>,excluded_style:int,excluded_style_mapped:int,excluded_style_mapped_ids:list<string>}|null
	 */
	private static ?array $probe = null;

	public function setUp(): void
	{
		parent::setUp();

		// A stub-backed Widget_Base would not reproduce Elementor 4.3.2's constructor
		// and stack contract; fail (never skip) when the real one is missing.
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; the contract test would measure an absence.');
		$this->assertSame('4.3.2', ELEMENTOR_VERSION);

		wp_set_current_user(0);
		RequestSimulator::new_request();
		// A cache hit returns before the _html filter runs, so nothing would be observed.
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false');
	}

	public function tearDown(): void
	{
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	public function test_own_stack_has_no_common_controls(): void
	{
		foreach (array_keys(self::WIDGETS) as $short) {
			$stack = WidgetFactory::make(self::NS . $short)->get_stack(false);
			foreach (array( 'controls', 'style_controls' ) as $bucket) {
				foreach (array_keys((array) ( $stack[ $bucket ] ?? array() )) as $id) {
					$id = (string) $id;
					// Elementor's shared "common" widget names its controls with a leading
					// underscore (_section_style, _margin, _title ...); third-party add-ons
					// add hfe_* and section_effects. None may leak into the own stack.
					$this->assertFalse(
						'_' === $id[0] || 0 === strpos($id, 'hfe_') || 'section_effects' === $id,
						"$short own stack carries a common control: $id"
					);
				}
			}
		}
	}

	public function test_schema_mapped_controls_reach_the_shortcode(): void
	{
		$probe      = $this->probe();
		$exceptions = $this->exception_keys();

		$dead         = array();
		$unobservable = array();
		$failing      = array();
		foreach ($probe['rows'] as $row) {
			if ($row['ok']) {
				continue;
			}
			if (self::SEAM_NOT_REACHED === $row['received']) {
				$unobservable[] = $row;
			} else {
				$dead[] = $row;
			}
			if (! isset($exceptions[ $row['widget'] . '::' . $row['control'] ])) {
				$failing[] = $this->describe($row);
			}
		}

		$report = sprintf(
			"\nWidgetContractTest: controls under test = %d; dead schema-mapped controls before exceptions = %d (P5); unobservable (no _html seam reached) = %d; excluded style controls = %d (of which schema-mapped = %d)\n",
			count($probe['rows']),
			count($dead),
			count($unobservable),
			$probe['excluded_style'],
			$probe['excluded_style_mapped']
		);
		fwrite(STDERR, $report);
		if (array() !== $failing) {
			fwrite(STDERR, implode("\n", $failing) . "\n");
		}

		$this->assertSame(array(), $failing, 'Schema-mapped widget controls that do not reach the shortcode:' . "\n" . implode("\n", $failing));
	}

	public function test_no_schema_mapped_control_carries_selectors(): void
	{
		// Ruling R7: a control with `selectors` is a style control and the bridge never
		// sends it (ElementorWidgetBase::get_bridge_settings()); the probe skips it the
		// same way. A data control that later gains a selector would therefore die in
		// the bridge and vanish from this contract without a single failing row, so the
		// overlap itself has to be zero.
		$probe = $this->probe();

		$this->assertSame(
			array(),
			$probe['excluded_style_mapped_ids'],
			"Schema-mapped controls carry `selectors`, so the bridge never sends them. Split the style part into its own control:\n" . implode("\n", $probe['excluded_style_mapped_ids'])
		);
		$this->assertSame(0, $probe['excluded_style_mapped']);
	}

	public function test_every_widget_contributes_rows(): void
	{
		// A schema or normalizer change must not silently drop a whole widget out of
		// the contract. The dashboard is a retired stub with no schema-mapped controls.
		$per_widget = array();
		foreach ($this->probe()['rows'] as $row) {
			$per_widget[ $row['widget'] ] = ( $per_widget[ $row['widget'] ] ?? 0 ) + 1;
		}
		foreach (array_keys(self::WIDGETS) as $short) {
			if ('UserDashboardWidget' === $short) {
				continue;
			}
			$this->assertGreaterThanOrEqual(1, $per_widget[ $short ] ?? 0, "$short contributes no controls under test; it dropped out of the contract.");
		}
	}

	public function test_exceptions_name_existing_controls(): void
	{
		$in_scope = array();
		foreach ($this->probe()['rows'] as $row) {
			$in_scope[ $row['widget'] . '::' . $row['control'] ] = true;
		}

		foreach ($this->exceptions() as $i => $row) {
			$this->assertSame(array( 'control', 'reason', 'widget' ), $this->sorted_keys($row), "Exception row $i has the wrong shape.");
			$this->assertArrayHasKey($row['widget'], self::WIDGETS, "Exception row $i names an unknown widget.");
			$this->assertArrayHasKey(
				$row['widget'] . '::' . $row['control'],
				$in_scope,
				"Exception row $i names {$row['widget']}::{$row['control']}, which is not a schema-mapped control under test (stale row)."
			);
			$this->assertMatchesRegularExpression(self::REASON_PATTERN, $row['reason'], "Exception row $i has a reason outside the allowed set.");
		}
	}

	public function test_working_controls_not_in_exceptions(): void
	{
		$exceptions = $this->exception_keys();
		foreach ($this->probe()['rows'] as $row) {
			if ($row['ok'] && isset($exceptions[ $row['widget'] . '::' . $row['control'] ])) {
				$this->fail("{$row['widget']}::{$row['control']} reaches the shortcode now; remove its exception row.");
			}
		}
		$this->assertTrue(true);
	}

	/**
	 * Probe every control under test once per process.
	 *
	 * @return array{rows:list<array<string,mixed>>,excluded_style:int,excluded_style_mapped:int,excluded_style_mapped_ids:list<string>}
	 */
	private function probe(): array
	{
		if (null !== self::$probe) {
			return self::$probe;
		}

		$vehicle    = ShortcodeFixtures::vehicle();
		$customer   = ShortcodeFixtures::customer();
		$rows       = array();
		$excluded   = 0;
		$mapped     = 0;
		$mapped_ids = array();

		foreach (self::WIDGETS as $short => $tag) {
			$class  = self::NS . $short;
			$schema = AllowlistRegistry::get_schema($tag);
			$stack  = WidgetFactory::make($class)->get_stack(false);
			$all    = (array) ( $stack['controls'] ?? array() ) + (array) ( $stack['style_controls'] ?? array() );

			wp_set_current_user(in_array($tag, self::AUTH_TAGS, true) ? $customer : 0);

			foreach ($all as $id => $control) {
				$id = (string) $id;
				if ('section' === ( $control['type'] ?? '' )) {
					continue;
				}
				$canonical = KeyNormalizer::normalize($id, $schema);
				if (isset($control['selectors'])) {
					++$excluded;
					if (isset($schema[ $canonical ])) {
						++$mapped;
						$mapped_ids[] = $short . '::' . $id . ' -> ' . $canonical;
					}
					continue;
				}
				if (! isset($schema[ $canonical ])) {
					continue;
				}

				$type = (string) ( $schema[ $canonical ]['type'] ?? 'string' );
				$base = $this->condition_settings($control, $all);
				$row  = array(
					'widget'    => $short,
					'control'   => $id,
					'canonical' => $canonical,
					'type'      => $type,
					'ok'        => true,
					'sent'      => null,
					'received'  => null,
				);

				foreach ($this->probe_values($canonical, $control, $schema[ $canonical ], $vehicle) as $value) {
					$received = $this->observe($class, $tag, $base + array( $id => $value ), $canonical);
					$ok       = $this->value_matches($type, $value, $received);
					if (null === $row['sent'] || ( $row['ok'] && ! $ok )) {
						$row['sent']     = $value;
						$row['received'] = $received;
					}
					if (! $ok) {
						$row['ok'] = false;
					}
				}

				$rows[] = $row;
			}
		}

		wp_set_current_user(0);

		self::$probe = array(
			'rows'                      => $rows,
			'excluded_style'            => $excluded,
			'excluded_style_mapped'     => $mapped,
			'excluded_style_mapped_ids' => $mapped_ids,
		);

		return self::$probe;
	}

	/**
	 * Probe values by schema type (spec §3 T2).
	 *
	 * bool flips the control default, then also sends the other state; enum sends
	 * the first allowed value that differs from the schema default, then one that
	 * differs from that. A single probe that happens to equal the shortcode's own
	 * default would let a dead control pass; two distinct values can not both match
	 * one default. int is 7 kept inside the control's own min/max (the editor can not
	 * save a value outside them).
	 *
	 * @param string              $canonical Canonical schema key (never the raw control id).
	 * @param array<string,mixed> $control   Control definition.
	 * @param array<string,mixed> $config    Schema entry.
	 * @return list<mixed>
	 */
	private function probe_values(string $canonical, array $control, array $config, int $vehicle): array
	{
		switch ($config['type'] ?? 'string') {
			case 'bool':
				$default = $control['default'] ?? '';
				$on      = in_array($default, array( 'yes', '1', 1, true ), true)
					|| ( isset($control['return_value']) && $default === $control['return_value'] && '' !== $default );
				return $on ? array( '', 'yes' ) : array( 'yes', '' );

			case 'enum':
				$values    = array_map('strval', (array) ( $config['values'] ?? array() ));
				$effective = (string) ( $config['default'] ?? ( $values[0] ?? '' ) );
				$first     = $this->first_other($values, $effective);
				if (null === $first) {
					return array( $effective );
				}
				$second = $this->first_other($values, $first);
				return null === $second ? array( $first ) : array( $first, $second );

			case 'int':
			case 'idlist':
				if (in_array($canonical, array( 'vehicle_id', 'ids' ), true)) {
					return array( (string) $vehicle );
				}
				$value = 7;
				if (isset($control['max']) && is_numeric($control['max'])) {
					$value = min($value, (int) $control['max']);
				}
				if (isset($control['min']) && is_numeric($control['min'])) {
					$value = max($value, (int) $control['min']);
				}
				return array( (string) $value );

			case 'url':
				return array( $this->as_control_value($control, 'https://example.test/zz') );

			default:
				return array( $this->as_control_value($control, 'zz-probe') );
		}
	}

	/**
	 * First allowed enum value that is not $not (compared case-insensitively: the
	 * schema lists `asc` and `ASC` as one value, and shortcodes normalize the case).
	 * '' is never a probe: for an enum it means "unset" and is dropped by design.
	 *
	 * @param list<string> $values Allowed values.
	 */
	private function first_other(array $values, string $not): ?string
	{
		foreach ($values as $value) {
			if ('' !== $value && 0 !== strcasecmp($value, $not)) {
				return $value;
			}
		}
		return null;
	}

	/**
	 * Elementor stores a URL control as an array; any other control as the string.
	 *
	 * @param array<string,mixed> $control Control definition.
	 * @return mixed
	 */
	private function as_control_value(array $control, string $value)
	{
		if ('url' === ( $control['type'] ?? '' )) {
			return array(
				'url'         => $value,
				'is_external' => '',
				'nofollow'    => '',
			);
		}
		return $value;
	}

	/**
	 * Settings that satisfy a control's simple `condition`, so Elementor does not
	 * hand null for it (a hidden control is dropped by design, spec §2.1/3).
	 *
	 * @param array<string,mixed>                $control Control definition.
	 * @param array<string,array<string,mixed>> $all     Own controls by id.
	 * @return array<string,mixed>
	 */
	private function condition_settings(array $control, array $all): array
	{
		$out = array();
		foreach ((array) ( $control['condition'] ?? array() ) as $key => $value) {
			$key = (string) $key;
			if ('!' === substr($key, -1)) {
				$key = substr($key, 0, -1);
				if ('' === $value || array() === $value) {
					$out[ $key ] = $this->as_control_value((array) ( $all[ $key ] ?? array() ), 'url' === ( $all[ $key ]['type'] ?? '' ) ? 'https://example.test/zz' : 'yes');
				} else {
					$out[ $key ] = '';
				}
				continue;
			}
			$out[ $key ] = is_array($value) ? reset($value) : $value;
		}
		return $out;
	}

	/**
	 * Render the widget the way the editor does and return what the shortcode got for $canonical.
	 *
	 * @param array<string,mixed> $settings Saved widget settings.
	 * @return mixed
	 */
	private function observe(string $class, string $tag, array $settings, string $canonical)
	{
		$seen   = array(
			'fired' => false,
			'atts'  => array(),
		);
		$filter = static function ($html, $atts) use (&$seen) {
			if (! $seen['fired']) {
				$seen['fired'] = true;
				$seen['atts']  = (array) $atts;
			}
			return $html;
		};

		add_filter('mhmrentiva_shortcodes_' . $tag . '_html', $filter, 10, 2);
		RequestSimulator::new_request();
		try {
			ob_start();
			WidgetFactory::make($class, $settings)->render_content();
		} finally {
			ob_end_clean();
			remove_filter('mhmrentiva_shortcodes_' . $tag . '_html', $filter, 10);
		}

		if (! $seen['fired']) {
			return self::SEAM_NOT_REACHED;
		}
		if (! array_key_exists($canonical, $seen['atts'])) {
			return self::ABSENT;
		}
		return $seen['atts'][ $canonical ];
	}

	/**
	 * Canonical comparison: bool '1'/'yes' on, '0'/'no'/'' off; int as int; enum ignoring case.
	 *
	 * @param mixed $sent     Probe value.
	 * @param mixed $received What the shortcode got.
	 */
	private function value_matches(string $type, $sent, $received): bool
	{
		if (self::SEAM_NOT_REACHED === $received || self::ABSENT === $received) {
			return false;
		}

		if (is_array($sent) && isset($sent['url'])) {
			$sent = $sent['url'];
		}

		switch ($type) {
			case 'bool':
				$on  = array( true, 1, '1', 'yes', 'on', 'true' );
				$off = array( false, 0, '0', 'no', '', 'false' );
				return in_array($received, 'yes' === $sent ? $on : $off, true);

			case 'int':
			case 'idlist':
				return is_numeric($received) && (int) $received === (int) $sent;

			case 'enum':
				return is_scalar($received) && 0 === strcasecmp((string) $received, (string) $sent);

			default:
				return is_scalar($received) && (string) $received === (string) $sent;
		}
	}

	/**
	 * @param array<string,mixed> $row Probe row.
	 */
	private function describe(array $row): string
	{
		return sprintf(
			'  %s::%s -> %s [%s] sent=%s received=%s',
			$row['widget'],
			$row['control'],
			$row['canonical'],
			$row['type'],
			$this->export($row['sent']),
			$this->export($row['received'])
		);
	}

	/**
	 * @param mixed $value Any value.
	 */
	private function export($value): string
	{
		if (is_array($value)) {
			return (string) wp_json_encode($value);
		}
		return var_export($value, true); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- test diagnostics.
	}

	/**
	 * @return list<array{widget:string,control:string,reason:string}>
	 */
	private function exceptions(): array
	{
		return require __DIR__ . '/widget-contract-exceptions.php';
	}

	/**
	 * @return array<string,true>
	 */
	private function exception_keys(): array
	{
		$out = array();
		foreach ($this->exceptions() as $row) {
			$out[ $row['widget'] . '::' . $row['control'] ] = true;
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $row Exception row.
	 * @return list<string>
	 */
	private function sorted_keys(array $row): array
	{
		$keys = array_map('strval', array_keys($row));
		sort($keys);
		return $keys;
	}
}
