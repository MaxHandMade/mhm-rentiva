<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

use MHMRentiva\Core\Attribute\AllowlistRegistry;

/**
 * Gate G1 (spec 2026-10-10-elementor-kopru-dilim-3a §3): every setting a user can
 * set -- a block.json attribute or an Elementor data control -- changes the render.
 * Shared by Lite's and Pro's SettingHasEffectTest; the using class names its
 * surfaces (gate_blocks(), gate_widgets(); null = Lite's) and the directory of
 * its list files (gate_dir()).
 *
 * Each surface is rendered with different values on its tag's fixture
 * (SettingProbe); same normalized HTML = dead. The dead surfaces known today are
 * listed in setting-effect-expected-dead.php, one row per (surface, tag, key),
 * with the decision of the 2026-10-10 decision table (APPLY / REMOVE). Each task
 * of the slice deletes its own rows; the file is empty and deleted at the end
 * (T10). Settings whose effect needs data the base fixture lacks are in
 * setting-effect-data-dependent.php with a named fixture, and are rendered with it.
 *
 * Both directions break: a dead surface outside the lists, and a listed row that
 * is no longer dead (stale) or a data-dependent row that shows no effect even
 * with its fixture. A (surface, tag, key) is dead when any of its surfaces is
 * (the vehicle card and the vehicles list widget share the list's keys).
 */
trait SettingHasEffectGate
{
	/**
	 * Block slug => config, or null for BlockRegistry's map.
	 *
	 * @return array<string,array<string,mixed>>|null
	 */
	abstract protected function gate_blocks(): ?array;

	/**
	 * Widget short name => tag, or null for SettingProbe::LITE_WIDGETS.
	 *
	 * @return array<string,string>|null
	 */
	abstract protected function gate_widgets(): ?array;

	/**
	 * Directory holding setting-effect-expected-dead.php and setting-effect-data-dependent.php.
	 */
	abstract protected function gate_dir(): string;

	/**
	 * @var array{rows:list<array<string,mixed>>,data:list<array<string,mixed>>,seconds:float}|null
	 */
	private static ?array $measured = null;

	public function test_every_setting_has_an_effect(): void
	{
		$m        = $this->measure();
		$expected = $this->groups($this->expected_dead());
		$data     = $this->groups($this->data_dependent());

		$failing = array();
		foreach ($m['rows'] as $row) {
			if ($row['effect'] || isset($expected[ $row['group'] ]) || isset($data[ $row['group'] ])) {
				continue;
			}
			$failing[] = $this->describe($row);
		}

		$dead_groups = array();
		foreach ($m['rows'] as $row) {
			if (! $row['effect']) {
				$dead_groups[ $row['group'] ] = true;
			}
		}
		fwrite(STDERR, sprintf("\nSettingHasEffectTest: surfaces = %d (block %d, elementor %d); dead surfaces = %d; dead (surface, tag, key) rows = %d; probe time = %.1fs\n", count($m['rows']), count(array_filter($m['rows'], static fn($r) => 'block' === $r['kind'])), count(array_filter($m['rows'], static fn($r) => 'elementor' === $r['kind'])), count(array_filter($m['rows'], static fn($r) => ! $r['effect'])), count($dead_groups), $m['seconds'])); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- test diagnostics.

		$this->assertSame(array(), $failing, count($failing) . " settings change nothing in the render (not in setting-effect-expected-dead.php):\n" . implode("\n", $failing));
	}

	public function test_no_render_is_noisy(): void
	{
		// A render that differs from itself makes every comparison "different": the
		// gate would call every setting live. The normalizer must absorb all noise.
		$noisy = array();
		foreach (array_merge($this->measure()['rows'], $this->measure()['data']) as $row) {
			if ($row['noisy']) {
				$noisy[] = $this->describe($row);
			}
		}
		$this->assertSame(array(), $noisy, "Renders that differ from themselves after RenderNormalizer:\n" . implode("\n", $noisy));
	}

	public function test_expected_dead_rows_are_still_dead(): void
	{
		$dead    = array();
		$present = array();
		foreach ($this->measure()['rows'] as $row) {
			$present[ $row['group'] ] = true;
			if (! $row['effect']) {
				$dead[ $row['group'] ] = true;
			}
		}

		$stale = array();
		foreach ($this->expected_dead() as $i => $row) {
			$group = $this->group_of($row);
			if (! isset($present[ $group ])) {
				$stale[] = "  row $i $group: no such surface any more (removed? delete the row and keep REMOVE rows in setting-effect-removed.php)";
			} elseif (! isset($dead[ $group ])) {
				$stale[] = "  row $i $group: has an effect now; delete the row";
			}
		}
		$this->assertSame(array(), $stale, "Stale rows in setting-effect-expected-dead.php:\n" . implode("\n", $stale));
	}

	public function test_every_data_dependent_row_has_effect_with_its_fixture(): void
	{
		$failing     = array();
		$unavailable = array();
		$seen        = array();
		foreach ($this->measure()['data'] as $row) {
			$seen[ $row['group'] ] = true;
			if ('' !== $row['unavailable']) {
				// The precondition can not be met in this process (named in the
				// fixture); say so on every run rather than pass in silence.
				$unavailable[] = $this->describe($row) . ' fixture=' . $row['fixture'] . ': ' . $row['unavailable'];
				continue;
			}
			if (! $row['effect']) {
				$failing[] = $this->describe($row) . ' fixture=' . $row['fixture'];
			}
		}
		if (array() !== $unavailable) {
			fwrite(STDERR, "
SettingHasEffectTest: data-dependent rows whose fixture this environment can not build (" . count($unavailable) . "):
" . implode("
", $unavailable) . "
"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- test diagnostics.
		}
		foreach ($this->data_dependent() as $i => $row) {
			if (! isset($seen[ $this->group_of($row) ])) {
				$failing[] = "  row $i " . $this->group_of($row) . ': no such surface';
			}
		}
		$this->assertSame(array(), $failing, "Data-dependent settings with no effect even on their fixture:\n" . implode("\n", $failing));
	}

	public function test_no_dead_surface_outside_the_expected_list(): void
	{
		// The two list files are well formed, disjoint and duplicate-free, so a dead
		// surface is exempted by exactly one row for one reason.
		$expected = $this->expected_dead();
		$data     = $this->data_dependent();
		$problems = array();

		$seen = array();
		foreach ($expected as $i => $row) {
			$keys = array_keys($row);
			sort($keys);
			if (array( 'decision', 'key', 'surface', 'tag' ) !== $keys) {
				$problems[] = "expected-dead row $i has the wrong shape";
				continue;
			}
			if (! in_array($row['surface'], array( 'block', 'elementor' ), true) || ! in_array($row['decision'], array( 'APPLY', 'REMOVE' ), true)) {
				$problems[] = "expected-dead row $i has an unknown surface or decision";
			}
			$group = $this->group_of($row);
			if (isset($seen[ $group ])) {
				$problems[] = "expected-dead row $i duplicates $group";
			}
			$seen[ $group ] = 'expected-dead';
		}
		foreach ($data as $i => $row) {
			$keys = array_keys($row);
			sort($keys);
			if (array( 'fixture', 'key', 'reason', 'surface', 'tag' ) !== $keys || '' === $row['fixture'] || '' === $row['reason']) {
				$problems[] = "data-dependent row $i has the wrong shape or an empty fixture/reason";
				continue;
			}
			$group = $this->group_of($row);
			if (isset($seen[ $group ])) {
				$problems[] = "data-dependent row $i ($group) is also listed in " . $seen[ $group ];
			}
			$seen[ $group ] = 'data-dependent';
		}

		$this->assertSame(array(), $problems, implode("\n", $problems));
	}

	public function test_every_data_control_maps_to_schema(): void
	{
		// Fable I1: a control whose id maps to no key of its tag's schema never
		// reaches the shortcode through the bridge. It may stay only when the widget
		// maps it explicitly (it has an effect: the card widget's vehicle_id) or
		// while it is a listed dead row.
		$expected = $this->groups($this->expected_dead());
		$failing  = array();
		$explicit = array();
		foreach ($this->measure()['rows'] as $row) {
			if ('elementor' !== $row['kind'] || isset(AllowlistRegistry::get_schema($row['tag'])[ $row['key'] ])) {
				continue;
			}
			if ($row['effect']) {
				$explicit[] = $row['name'];
			} elseif (! isset($expected[ $row['group'] ])) {
				$failing[] = $this->describe($row);
			}
		}
		fwrite(STDERR, "
SettingHasEffectTest: unmapped data controls with an explicit widget mapping: " . implode(', ', $explicit) . "
"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- test diagnostics.
		$this->assertSame(array(), $failing, "Elementor data controls whose key is not in the tag's schema and that change nothing:
" . implode("
", $failing));
	}

	/**
	 * Probe every surface once per process; the fixtures are rolled back with the
	 * test that built them, the results are kept.
	 *
	 * @return array{rows:list<array<string,mixed>>,data:list<array<string,mixed>>,seconds:float}
	 */
	protected function measure(): array
	{
		if (null !== self::$measured) {
			return self::$measured;
		}

		$start = microtime(true);
		SettingProbe::reset();

		$surfaces = array_merge(SettingProbe::block_surfaces($this->gate_blocks()), SettingProbe::elementor_surfaces($this->gate_widgets()));
		$rows     = array();
		foreach ($surfaces as $surface) {
			$rows[] = $this->row($surface, null);
		}

		$data = array();
		foreach ($this->data_dependent() as $listed) {
			foreach ($surfaces as $surface) {
				if (SettingProbe::kind($surface) . '|' . $surface['tag'] . '|' . SettingProbe::key($surface) === $this->group_of($listed)) {
					$data[] = $this->row($surface, $listed['fixture']);
				}
			}
		}

		self::$measured = array(
			'rows'    => $rows,
			'data'    => $data,
			'seconds' => microtime(true) - $start,
		);

		$dump = getenv('MHM_SETTING_PROBE_DUMP');
		if (is_string($dump) && '' !== $dump) {
			$lines = array();
			foreach (array_merge($rows, $data) as $row) {
				$lines[] = implode("\t", array( $row['kind'], $row['tag'], $row['key'], $row['name'], $row['effect'] ? 'effect' : 'DEAD', $row['noisy'] ? 'NOISY' : '', $row['fixture'], (string) wp_json_encode($row['pair']), (string) $row['length'] ));
			}
			file_put_contents($dump, implode("\n", $lines) . "\n"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- opt-in measurement dump.
		}

		return self::$measured;
	}

	/**
	 * @param array<string,string> $surface Surface.
	 * @return array<string,mixed>
	 */
	protected function row(array $surface, ?string $fixture): array
	{
		$probe = SettingProbe::probe($surface, $fixture);
		$kind  = SettingProbe::kind($surface);
		$key   = SettingProbe::key($surface);
		return array(
			'kind'    => $kind,
			'tag'     => $surface['tag'],
			'key'     => $key,
			'name'    => 'block' === $kind ? $surface['block'] . '/' . $surface['attr'] : $surface['widget'] . '::' . $surface['control'],
			'group'   => $kind . '|' . $surface['tag'] . '|' . $key,
			'effect'  => $probe['effect'],
			'noisy'   => $probe['noisy'],
			'pair'    => $probe['pair'],
			'length'  => $probe['length'],
			'fixture' => (string) $fixture,
			'unavailable' => $probe['unavailable'],
		);
	}

	/**
	 * @param array<string,mixed> $row Probe row.
	 */
	protected function describe(array $row): string
	{
		return sprintf('  %s %s (%s) %s values=%s', $row['kind'], $row['tag'], $row['key'], $row['name'], (string) wp_json_encode($row['pair']));
	}

	/**
	 * @param array<string,string> $row List row.
	 */
	protected function group_of(array $row): string
	{
		return $row['surface'] . '|' . $row['tag'] . '|' . $row['key'];
	}

	/**
	 * @param list<array<string,string>> $rows List rows.
	 * @return array<string,true>
	 */
	protected function groups(array $rows): array
	{
		$out = array();
		foreach ($rows as $row) {
			$out[ $this->group_of($row) ] = true;
		}
		return $out;
	}

	/**
	 * @return list<array{surface:string,tag:string,key:string,decision:string}>
	 */
	protected function expected_dead(): array
	{
		return require $this->gate_dir() . '/setting-effect-expected-dead.php';
	}

	/**
	 * @return list<array{surface:string,tag:string,key:string,fixture:string,reason:string}>
	 */
	protected function data_dependent(): array
	{
		return require $this->gate_dir() . '/setting-effect-data-dependent.php';
	}
}
