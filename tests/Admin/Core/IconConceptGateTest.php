<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use WP_UnitTestCase;

/**
 * bin/check-icon-concepts.php's verdicts, pinned as exit codes.
 *
 * The gate's own logic -- which bucket fails, what "measured nothing" means --
 * was pinned only by hand-run scenarios while it was being fixed (first
 * independent audit round, 2026-09-22). Every one of its bugs so far read as a
 * green run, so the verdicts are pinned here: the gate is run as a separate
 * process over a fixture directory (MHM_ICON_GATE_PATHS, which only this test
 * sets) and its exit code is asserted.
 */
final class IconConceptGateTest extends WP_UnitTestCase {

	private string $dir = '';

	public function set_up(): void {
		parent::set_up();
		$this->dir = sys_get_temp_dir() . '/mhm-icon-gate-' . uniqid( '', true );
		mkdir( $this->dir );
	}

	public function tear_down(): void {
		foreach ( (array) glob( $this->dir . '/*' ) as $file ) {
			unlink( (string) $file );
		}
		rmdir( $this->dir );
		parent::tear_down();
	}

	public function test_a_strip_of_concepts_converges(): void {
		$this->strip( array( 'bookings', 'revenue', 'pages' ) );

		$this->assertSame( 0, $this->run_gate()['code'] );
	}

	public function test_a_misspelled_concept_fails(): void {
		// Neither a concept nor a suffix with one: it would render
		// `dashicons-bookngs` and draw nothing. The first version passed this.
		$this->strip( array( 'bookings', 'bookngs' ) );

		$run = $this->run_gate();
		$this->assertSame( 1, $run['code'] );
		$this->assertStringContainsString( "UNKNOWN", $run['out'] );
	}

	public function test_a_suffix_that_has_a_concept_fails(): void {
		$this->strip( array( 'calendar-alt' ) );

		$run = $this->run_gate();
		$this->assertSame( 1, $run['code'] );
		$this->assertStringContainsString( "write 'bookings'", $run['out'] );
	}

	public function test_a_run_that_reads_nothing_is_not_a_pass(): void {
		// A directory with no anchored file: an empty gate is a broken gate.
		file_put_contents( $this->dir . '/Unrelated.jsx', "export default () => null;\n" );

		$run = $this->run_gate();
		$this->assertSame( 2, $run['code'] );
		$this->assertStringContainsString( 'MEASURE-FAILED', $run['err'] );
	}

	/** @param array<int, string> $icons */
	private function strip( array $icons ): void {
		$cards = array();
		foreach ( $icons as $icon ) {
			$cards[] = "\t\t{ label: 'x', value: '1', icon: '{$icon}' },";
		}
		file_put_contents(
			$this->dir . '/Strip.jsx',
			"import StatsGrid from 'kit';\nexport default () => (\n\t<StatsGrid cards={ [\n" . implode( "\n", $cards ) . "\n\t] } />\n);\n"
		);
	}

	/** @return array{code: int, out: string, err: string} */
	private function run_gate(): array {
		$env   = array_merge( getenv(), array( 'MHM_ICON_GATE_PATHS' => $this->dir ) );
		$proc  = proc_open(
			array( PHP_BINARY, MHMRENTIVA_PLUGIN_PATH . 'bin/check-icon-concepts.php' ),
			array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
			$pipes,
			null,
			$env
		);
		$this->assertIsResource( $proc, 'The gate could not be started.' );
		$out = (string) stream_get_contents( $pipes[1] );
		$err = (string) stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );

		return array(
			'code' => proc_close( $proc ),
			'out'  => $out,
			'err'  => $err,
		);
	}
}
