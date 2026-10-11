<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

/**
 * Gate G3 (spec 2026-10-10-elementor-kopru-dilim-3a §3): every option an editor
 * offers for an enum setting is a value the schema accepts.
 *
 * Transformers::to_enum() replaces a value outside the schema's `values` with the
 * default, silently: the user picks "Price" and gets the default sort (the
 * Gutenberg B class). Options are read from the block editor scripts (static
 * parse, SettingProbe::editor_options()) and from the Elementor controls as the
 * editor builds them. '' is the "not set" choice and always allowed.
 * SettingGates::ALIASES carries the live mappings that translate an option before the schema sees it
 * (spec G3, Fable I2): my-bookings `filterStatus` 'all' => ''
 * (assets/blocks/my-bookings/index.js:40, AllowlistRegistry).
 *
 * Shared by Lite and Pro (gate_blocks(), gate_widgets(), gate_dir() as in
 * SettingHasEffectGate).
 *
 * A mismatch on a (surface, tag, key) listed in setting-effect-expected-dead.php
 * is that row's business; the rest are listed in setting-effect-enum-mismatch.php,
 * which the tasks of the slice empty.
 */
trait EnumOptionsGate
{
	/** @return array<string,array<string,mixed>>|null */
	abstract protected function gate_blocks(): ?array;

	/** @return array<string,string>|null */
	abstract protected function gate_widgets(): ?array;

	abstract protected function gate_dir(): string;

	public function test_editor_options_are_schema_values(): void
	{
		$listed = array();
		foreach ($this->listed() as $i => $row) {
			$keys = array_keys($row);
			sort($keys);
			$this->assertSame(array( 'key', 'option', 'surface', 'tag' ), $keys, "enum-mismatch row $i has the wrong shape.");
			$listed[ $this->id($row) ] = $i;
		}
		$dead = array();
		// T10 deletes the expected-dead file once it is empty.
		$expected_file = $this->gate_dir() . '/setting-effect-expected-dead.php';
		foreach (is_readable($expected_file) ? require $expected_file : array() as $row) {
			$dead[ $row['surface'] . '|' . $row['tag'] . '|' . $row['key'] ] = true;
		}

		$measured = SettingGates::enum_mismatches($this->gate_blocks(), $this->gate_widgets());
		$failing  = array();
		foreach ($measured as $id => $where) {
			list( $surface, $tag, $key ) = explode('|', $id);
			if (! isset($listed[ $id ]) && ! isset($dead[ "$surface|$tag|$key" ])) {
				$failing[] = "  $id ($where)";
			}
		}
		foreach ($listed as $id => $i) {
			if (! isset($measured[ $id ])) {
				$failing[] = "  enum-mismatch row $i $id is not a mismatch any more; delete the row";
			}
		}

		fwrite(STDERR, sprintf("\nEnumOptionsMatchSchemaTest: option/schema mismatches = %d (listed %d, under expected-dead rows %d)\n", count($measured), count($listed), count(array_filter(array_keys($measured), static fn($id) => isset($dead[ implode('|', array_slice(explode('|', $id), 0, 3)) ])))) . implode("
", array_map(static fn($id, $where) => "  $id ($where)", array_keys($measured), $measured)) . "
"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- test diagnostics.

		$this->assertSame(array(), $failing, "Editor options outside the schema's values:\n" . implode("\n", $failing));
	}

	/**
	 * @param array<string,string> $row List row.
	 */
	protected function id(array $row): string
	{
		return $row['surface'] . '|' . $row['tag'] . '|' . $row['key'] . '|' . $row['option'];
	}

	/**
	 * @return list<array{surface:string,tag:string,key:string,option:string}>
	 */
	protected function listed(): array
	{
		return require $this->gate_dir() . '/setting-effect-enum-mismatch.php';
	}
}
