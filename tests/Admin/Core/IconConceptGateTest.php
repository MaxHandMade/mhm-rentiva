<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use WP_UnitTestCase;

/**
 * bin/check-icon-concepts.php's verdicts, pinned as exit codes.
 *
 * The gate's own logic -- which bucket fails, what "measured nothing" means --
 * was pinned only by hand-run scenarios while it was being fixed, and every one
 * of its bugs so far read as a green run. So it is run here as a separate
 * process over a fixture directory (MHM_ICON_GATE_PATHS) with, where needed, a
 * test-supplied accepted list (MHM_ICON_GATE_ACCEPTED): this tree's own list is
 * empty, and without the second seam its per-file count could not be reached
 * at all. Only this test sets either variable, and the gate announces both.
 */
final class IconConceptGateTest extends WP_UnitTestCase {

	private string $dir = '';

	public function set_up(): void {
		parent::set_up();
		$this->dir = sys_get_temp_dir() . '/mhm-icon-gate-' . uniqid( '', true );
		mkdir( $this->dir );
	}

	public function tear_down(): void {
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $this->dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $files as $file ) {
			$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
		}
		rmdir( $this->dir );
		parent::tear_down();
	}

	public function test_a_strip_of_concepts_converges(): void {
		$this->strip( 'Strip.jsx', array( 'bookings', 'revenue', 'pages' ) );

		$run = $this->run_gate();
		$this->assertSame( 0, $run['code'] );
		$this->assertStringContainsString( 'MHM_ICON_GATE_PATHS is set', $run['err'], 'A fixture run must say it is one.' );
	}

	public function test_a_misspelled_concept_fails(): void {
		// Neither a concept nor a suffix with one: it would render
		// `dashicons-bookngs` and draw nothing. The first version passed this.
		$this->strip( 'Strip.jsx', array( 'bookings', 'bookngs' ) );

		$run = $this->run_gate();
		$this->assertSame( 1, $run['code'] );
		$this->assertStringContainsString( 'UNKNOWN', $run['out'] );
	}

	public function test_a_suffix_that_has_a_concept_fails(): void {
		$this->strip( 'Strip.jsx', array( 'calendar-alt' ) );

		$run = $this->run_gate();
		$this->assertSame( 1, $run['code'] );
		$this->assertStringContainsString( 'RAW', $run['out'] );
		$this->assertStringContainsString( "'bookings'", $run['out'], 'The advice must name the concept for that suffix.' );
	}

	public function test_an_accepted_value_where_it_is_accepted_converges(): void {
		$this->strip( 'customers/StatsCards.jsx', array( 'groups' ) );
		$this->strip( 'dashboard/StatsCards.jsx', array( 'bookings' ) );

		$run = $this->run_gate( array( 'groups' => array( array( 'customers/StatsCards.jsx' => 1 ), 'test' ) ) );
		$this->assertSame( 0, $run['code'] );
		$this->assertStringContainsString( 'MHM_ICON_GATE_ACCEPTED is set', $run['err'] );
	}

	public function test_an_accepted_value_moved_to_a_same_named_file_fails(): void {
		// The tree really has two StatsCards.jsx. Keyed by basename, this move
		// passed (fourth independent audit round, 2026-09-22).
		$this->strip( 'customers/StatsCards.jsx', array( 'bookings' ) );
		$this->strip( 'dashboard/StatsCards.jsx', array( 'groups' ) );

		$run = $this->run_gate( array( 'groups' => array( array( 'customers/StatsCards.jsx' => 1 ), 'test' ) ) );
		$this->assertSame( 1, $run['code'] );
		$this->assertStringContainsString( 'COUNT', $run['out'] );
	}

	public function test_an_accepted_value_nothing_writes_fails(): void {
		$this->strip( 'Strip.jsx', array( 'bookings' ) );

		$run = $this->run_gate( array( 'groups' => array( array( 'Strip.jsx' => 1 ), 'test' ) ) );
		$this->assertSame( 1, $run['code'] );
		$this->assertStringContainsString( 'STALE', $run['out'] );
	}

	public function test_a_run_that_reads_nothing_is_not_a_pass(): void {
		// A directory with no anchored file: an empty gate is a broken gate.
		file_put_contents( $this->dir . '/Unrelated.jsx', "export default () => null;\n" );

		$run = $this->run_gate();
		$this->assertSame( 2, $run['code'] );
		$this->assertStringContainsString( 'MEASURE-FAILED', $run['err'] );
	}

	/** @param array<int, string> $icons */
	private function strip( string $relative, array $icons ): void {
		$path = $this->dir . '/' . $relative;
		if ( ! is_dir( dirname( $path ) ) ) {
			mkdir( dirname( $path ), 0777, true );
		}
		$cards = array();
		foreach ( $icons as $icon ) {
			$cards[] = "\t\t{ label: 'x', value: '1', icon: '{$icon}' },";
		}
		file_put_contents(
			$path,
			"import StatsGrid from 'kit';\nexport default () => (\n\t<StatsGrid cards={ [\n" . implode( "\n", $cards ) . "\n\t] } />\n);\n"
		);
	}

	/**
	 * @param array<string, mixed>|null $accepted Test-supplied accepted list.
	 * @return array{code: int, out: string, err: string}
	 */
	private function run_gate( ?array $accepted = null ): array {
		$env = array_merge( getenv(), array( 'MHM_ICON_GATE_PATHS' => $this->dir ) );
		if ( null !== $accepted ) {
			$env['MHM_ICON_GATE_ACCEPTED'] = (string) wp_json_encode( $accepted );
		}
		$proc = proc_open(
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
