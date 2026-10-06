<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Gates;

use MHMRentiva\Tests\Support\BlockEditorWriteScanner;
use WP_UnitTestCase;

/**
 * Every attribute a block editor script writes is declared in that block's
 * block.json.
 *
 * An undeclared write is dropped by the editor serializer (the setting is never
 * saved) and makes the block-renderer preview answer HTTP 400 the moment the
 * control is touched. booking-form wrote six such keys, search-results one.
 * Threat model: accidental drift, not deliberate obfuscation (see the scanner).
 */
class BlockEditorWritesRegisteredAttributesTest extends WP_UnitTestCase
{
	/** setAttributes object-literal keys across Lite's editor scripts after the slice. */
	private const LITE_LITERAL_KEYS = 194;

	private static function blocks_dir(): string
	{
		return dirname(__DIR__, 2) . '/assets/blocks';
	}

	private static function fixtures_dir(): string
	{
		return dirname(__DIR__) . '/fixtures/block-editor-scanner';
	}

	public function test_every_editor_write_is_registered(): void
	{
		$keys       = 0;
		$violations = array();
		$missing    = array();

		foreach (glob(self::blocks_dir() . '/*/index.js') as $script) {
			$slug   = basename(dirname($script));
			$result = BlockEditorWriteScanner::scan((string) file_get_contents($script), $slug);
			$keys  += count($result['literal_keys']);

			$violations = array_merge($violations, $result['violations']);
			foreach (BlockEditorWriteScanner::unregistered($result, dirname($script) . '/block.json') as $key) {
				$missing[] = $slug . ':' . $key;
			}
		}

		$this->assertSame(array(), $violations);
		$this->assertSame(array(), $missing, 'Editor writes keys block.json does not declare.');
		$this->assertSame(self::LITE_LITERAL_KEYS, $keys);
	}

	public function bad_fixture_provider(): array
	{
		$grid   = self::blocks_dir() . '/vehicles-grid/block.json';
		$vendor = self::fixtures_dir() . '/vendor-directory.block.json';

		return array(
			'second property unregistered' => array( 'multi-prop-unregistered.js', $grid ),
			'computed key'                 => array( 'computed-key.js', $grid ),
			'quoted unregistered key'      => array( 'quoted-unregistered.js', $grid ),
			'double-quoted helper key'     => array( 'helper-double-quoted.js', $vendor ),
			'mutated directory helper'     => array( 'helper-body-mutated-directory.js', $vendor ),
			'mutated profile helper'       => array( 'helper-body-mutated-profile.js', $vendor ),
			'bound setter passed to helper' => array( 'helper-bind.js', $vendor ),
			'setter aliased to a variable'  => array( 'props-setter-alias.js', $grid ),
			'setter passed to a function'   => array( 'props-setter-passed.js', $grid ),
		);
	}

	/**
	 * Negative controls: the whole gate (syntax + registration) must refuse each.
	 *
	 * @dataProvider bad_fixture_provider
	 */
	public function test_gate_rejects(string $fixture, string $block_json): void
	{
		$result = BlockEditorWriteScanner::scan((string) file_get_contents(self::fixtures_dir() . '/' . $fixture), $fixture);
		$caught = array_merge($result['violations'], BlockEditorWriteScanner::unregistered($result, $block_json));

		$this->assertNotSame(array(), $caught, "{$fixture} passed the gate.");
	}

	/** The destructuring acquisition the spec permits (rule d) is not a violation. */
	public function test_gate_accepts_destructured_setter(): void
	{
		$result = BlockEditorWriteScanner::scan((string) file_get_contents(self::fixtures_dir() . '/props-destructure.js'), 'props-destructure.js');

		$this->assertSame(array(), $result['violations']);
		$this->assertSame(array( 'className' ), $result['literal_keys']);
	}

	public function test_gate_accepts_current_pro_helper(): void
	{
		$result = BlockEditorWriteScanner::scan((string) file_get_contents(self::fixtures_dir() . '/helper-current.js'), 'helper-current.js');

		$this->assertSame(array(), $result['violations']);
		$this->assertSame(array(), BlockEditorWriteScanner::unregistered($result, self::fixtures_dir() . '/vendor-directory.block.json'));
		$this->assertSame(1, $result['exemptions']);
		$this->assertSame(array( 'show_filter_bar' ), $result['helper_keys']);
	}
}
