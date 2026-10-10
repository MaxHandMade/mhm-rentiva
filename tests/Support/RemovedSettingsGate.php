<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

/**
 * Gate G4 (spec 2026-10-10-elementor-kopru-dilim-3a §3): a removed setting is gone
 * from every layer, per (tag, key) -- a bare name grep is useless, because
 * orderby, limit, status, layout, title ... are live on other tags (Fable I3).
 *
 * The five layers (SettingGates::layers()): the tag's block.json, the block's
 * editor script, the tag's Elementor controls, AllowlistRegistry's list and
 * defaults for the tag, and KeyNormalizer's aliases in that schema.
 *
 * Shared by Lite and Pro (gate_blocks(), gate_widgets(), gate_dir() as in
 * SettingHasEffectGate).
 *
 * Source: setting-effect-removed.php. When a task removes a REMOVE row's key it
 * moves the row there from setting-effect-expected-dead.php, decision and all.
 */
trait RemovedSettingsGate
{
	/** @return array<string,array<string,mixed>>|null */
	abstract protected function gate_blocks(): ?array;

	/** @return array<string,string>|null */
	abstract protected function gate_widgets(): ?array;

	abstract protected function gate_dir(): string;

	public function test_removed_key_is_absent_on_every_layer(): void
	{
		$dead = array();
		// T10 deletes the expected-dead file once it is empty.
		$expected_file = $this->gate_dir() . '/setting-effect-expected-dead.php';
		foreach (is_readable($expected_file) ? require $expected_file : array() as $row) {
			$dead[ $row['tag'] . '|' . $row['key'] ] = true;
		}

		$failing = array();
		$seen    = array();
		foreach ($this->removed() as $i => $row) {
			$keys = array_keys($row);
			sort($keys);
			$this->assertSame(array( 'decision', 'key', 'surface', 'tag' ), $keys, "removed row $i has the wrong shape.");
			$this->assertSame('REMOVE', $row['decision'], "removed row $i is not a REMOVE decision; APPLY rows are deleted, not moved here.");
			$this->assertArrayNotHasKey($row['tag'] . '|' . $row['key'], $dead, "removed row $i ({$row['tag']}, {$row['key']}) is still listed as expected-dead.");

			// Both surfaces of a key go together: layers are checked per (tag, key) once.
			$id = $row['tag'] . '|' . $row['key'];
			if (isset($seen[ $id ])) {
				continue;
			}
			$seen[ $id ] = true;
			foreach (SettingGates::layers($row['tag'], $row['key'], $this->gate_blocks(), $this->gate_widgets()) as $layer => $places) {
				if (array() !== $places) {
					$failing[] = sprintf('  (%s, %s) still on %s: %s', $row['tag'], $row['key'], $layer, implode(', ', $places));
				}
			}
		}

		$this->assertSame(array(), $failing, "Removed settings still present:\n" . implode("\n", $failing));
	}

	public function test_each_layer_detector_finds_todays_keys(): void
	{
		// Positive control: a detector that finds nothing would pass every removal.
		// Each must find the live settings it guards -- one block key and one widget
		// key per tag of this suite, independent of the list files (T10 deletes one).
		$sample = array();
		foreach (SettingProbe::block_surfaces($this->gate_blocks()) as $surface) {
			$sample[ $surface['tag'] . '|block' ] ??= array( $surface['tag'], SettingProbe::key($surface) );
		}
		foreach (SettingProbe::elementor_surfaces($this->gate_widgets()) as $surface) {
			$sample[ $surface['tag'] . '|elementor' ] ??= array( $surface['tag'], SettingProbe::key($surface) );
		}
		$hits = array_fill_keys(SettingGates::LAYERS, 0);
		foreach ($sample as $pair) {
			foreach (SettingGates::layers($pair[0], $pair[1], $this->gate_blocks(), $this->gate_widgets()) as $layer => $places) {
				$hits[ $layer ] += count($places);
			}
		}
		foreach ($hits as $layer => $count) {
			if ('alias' === $layer) {
				continue;
			}
			$this->assertGreaterThan(0, $count, "The $layer detector finds none of " . count($sample) . ' live settings.');
		}

		// The alias detector, on a live alias: search results `sortBy` is an alias of `orderby`.
		$this->assertNotSame(array(), SettingGates::layers('rentiva_search_results', 'sort_by')['alias'], 'The alias detector does not see orderby <- sortBy.');
	}

	/**
	 * @return list<array{surface:string,tag:string,key:string,decision:string}>
	 */
	protected function removed(): array
	{
		return require $this->gate_dir() . '/setting-effect-removed.php';
	}
}
