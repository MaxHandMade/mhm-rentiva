<?php
/**
 * Non-vacuity + precision proof for bin/check-unguarded-pro-refs.php.
 *
 * A gate that can never go red is worse than no gate: it converts "we have not
 * checked" into "we have checked". So the first duty of these tests is to prove
 * the checker actually FAILS on a genuinely unguarded reference to an
 * allowlisted Pro class, and only then that it stays quiet on the guard shapes
 * this codebase really uses.
 *
 * Every case runs against a synthetic temp tree via MHM_GUARD_ROOT, never the
 * real repo -- otherwise the result would silently depend on which Pro classes
 * happen to be present today.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Carveout;

use PHPUnit\Framework\TestCase;

final class UnguardedProRefsGateTest extends TestCase {

	/** The Pro FQN used as the allowlisted seam throughout these probes. */
	private const PRO_FQN = 'MHMRentiva\\Admin\\Reports\\Reports';

	/** @var list<string> Temp dirs to clean up. */
	private array $temp_dirs = array();

	/**
	 * Build a synthetic tree, run the checker over it, return its output.
	 *
	 * @param array<string, string> $files Repo-relative path => PHP source.
	 */
	private function run_gate( array $files ): string {
		$tmp = sys_get_temp_dir() . '/unguarded_probe_' . uniqid();
		$this->temp_dirs[] = $tmp;

		mkdir( $tmp . '/bin', 0777, true );
		file_put_contents( $tmp . '/bin/seam-classes.txt', self::PRO_FQN . "\n" );

		foreach ( $files as $relative => $source ) {
			$path = $tmp . '/' . $relative;
			$dir  = dirname( $path );
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir, 0777, true );
			}
			file_put_contents( $path, $source );
		}

		$gate = dirname( __DIR__, 3 ) . '/bin/check-unguarded-pro-refs.php';

		// The checker resolves its root from its OWN location, independent of
		// CWD; MHM_GUARD_ROOT is the only thing that redirects it at the
		// synthetic tree. putenv() propagates it to the child process.
		putenv( 'MHM_GUARD_ROOT=' . $tmp );
		$out = (string) shell_exec( 'php ' . escapeshellarg( $gate ) . ' 2>&1' );
		putenv( 'MHM_GUARD_ROOT' );

		return $out;
	}

	protected function tearDown(): void {
		foreach ( $this->temp_dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			$files = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::CHILD_FIRST
			);
			foreach ( $files as $file ) {
				$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
			}
			rmdir( $dir );
		}
		$this->temp_dirs = array();
		parent::tearDown();
	}

	/**
	 * THE NON-VACUITY PROOF: an unguarded call to an absent Pro class -- the
	 * exact defect that shipped (Reports::get_currency_symbol() in a template) --
	 * must turn the gate red.
	 */
	public function test_red_on_unguarded_reference(): void {
		$out = $this->run_gate(
			array(
				'src/Probe.php' => "<?php\nnamespace MHMRentiva\\Admin\\Foo;\n\$x = \\MHMRentiva\\Admin\\Reports\\Reports::get_currency_symbol();\n",
			)
		);

		$this->assertStringNotContainsString( '[OK]', $out );
		$this->assertStringContainsString( 'Unguarded references', $out );
		$this->assertStringContainsString( self::PRO_FQN, $out );
		$this->assertStringContainsString( 'src/Probe.php:3', $out );
	}

	/** The same reference, once guarded, must turn the gate green. */
	public function test_green_when_guarded(): void {
		$out = $this->run_gate(
			array(
				'src/Probe.php' => "<?php\nnamespace MHMRentiva\\Admin\\Foo;\nif ( class_exists( '\\\\MHMRentiva\\\\Admin\\\\Reports\\\\Reports' ) ) {\n\t\$x = \\MHMRentiva\\Admin\\Reports\\Reports::get_currency_symbol();\n}\n",
			)
		);

		$this->assertStringContainsString( '[OK]', $out );
	}

	/**
	 * templates/ is the blind spot that mattered: PHPStan never scans it, and
	 * that is where the real fatal shipped.
	 */
	public function test_red_on_unguarded_reference_in_template(): void {
		$out = $this->run_gate(
			array(
				'templates/thing.php' => "<?php\necho \\MHMRentiva\\Admin\\Reports\\Reports::get_currency_symbol();\n",
			)
		);

		$this->assertStringNotContainsString( '[OK]', $out );
		$this->assertStringContainsString( 'templates/thing.php:2', $out );
	}

	/**
	 * The codebase's most common guard shape: bail out early when the Pro class
	 * is absent, then use it freely for the rest of the function.
	 */
	public function test_green_on_negative_early_return_guard(): void {
		$out = $this->run_gate(
			array(
				'src/Probe.php' => "<?php\nnamespace MHMRentiva\\Admin\\Foo;\nclass Probe {\n\tpublic function run() {\n\t\tif ( ! class_exists( '\\\\MHMRentiva\\\\Admin\\\\Reports\\\\Reports' ) ) {\n\t\t\treturn '-';\n\t\t}\n\t\treturn \\MHMRentiva\\Admin\\Reports\\Reports::get_currency_symbol();\n\t}\n}\n",
			)
		);

		$this->assertStringContainsString( '[OK]', $out );
	}

	/** The is_class_available() wrapper is a guard too (src/Plugin.php). */
	public function test_green_on_is_class_available_wrapper_guard(): void {
		$out = $this->run_gate(
			array(
				'src/Probe.php' => "<?php\nnamespace MHMRentiva;\nclass Probe {\n\tpublic function run() {\n\t\tif ( \$this->is_class_available( 'MHMRentiva\\\\Admin\\\\Reports\\\\Reports' ) ) {\n\t\t\t\\MHMRentiva\\Admin\\Reports\\Reports::boot();\n\t\t}\n\t}\n}\n",
			)
		);

		$this->assertStringContainsString( '[OK]', $out );
	}

	/**
	 * An `else` branch is the class-is-ABSENT branch. A reference there is a
	 * real fatal and must not inherit the guard.
	 */
	public function test_red_on_reference_in_else_branch(): void {
		$out = $this->run_gate(
			array(
				'src/Probe.php' => "<?php\nnamespace MHMRentiva\\Admin\\Foo;\nif ( class_exists( '\\\\MHMRentiva\\\\Admin\\\\Reports\\\\Reports' ) ) {\n\techo 'ok';\n} else {\n\t\\MHMRentiva\\Admin\\Reports\\Reports::boot();\n}\n",
			)
		);

		$this->assertStringNotContainsString( '[OK]', $out );
		$this->assertStringContainsString( 'src/Probe.php:6', $out );
	}

	/**
	 * A short name that resolves through a `use` import is still a real
	 * reference -- this is a shape a fully-qualified-only scan would miss.
	 */
	public function test_red_on_unguarded_imported_short_name(): void {
		$out = $this->run_gate(
			array(
				'src/Probe.php' => "<?php\nnamespace MHMRentiva\\Admin\\Foo;\nuse MHMRentiva\\Admin\\Reports\\Reports;\n\$x = Reports::get_currency_symbol();\n",
			)
		);

		$this->assertStringNotContainsString( '[OK]', $out );
		$this->assertStringContainsString( 'src/Probe.php:4', $out );
	}

	/** Aliased imports must resolve through the alias. */
	public function test_red_on_unguarded_aliased_import(): void {
		$out = $this->run_gate(
			array(
				'src/Probe.php' => "<?php\nnamespace MHMRentiva\\Admin\\Foo;\nuse MHMRentiva\\Admin\\Reports\\Reports as Rep;\nnew Rep();\n",
			)
		);

		$this->assertStringNotContainsString( '[OK]', $out );
		$this->assertStringContainsString( 'src/Probe.php:4', $out );
	}

	/** Group-use imports must resolve too. */
	public function test_red_on_unguarded_group_use_import(): void {
		$out = $this->run_gate(
			array(
				'src/Probe.php' => "<?php\nnamespace MHMRentiva\\Admin\\Foo;\nuse MHMRentiva\\Admin\\Reports\\{ Reports };\nReports::boot();\n",
			)
		);

		$this->assertStringNotContainsString( '[OK]', $out );
		$this->assertStringContainsString( 'src/Probe.php:4', $out );
	}

	/**
	 * THE PRECISION PROOF -- the failure mode that sank the earlier naive scan.
	 * An unrelated class that merely SHARES the short name `Reports`, in a
	 * different namespace, must not be reported. This is why the checker
	 * resolves before matching instead of grepping short names.
	 */
	public function test_green_on_unrelated_class_sharing_short_name(): void {
		$out = $this->run_gate(
			array(
				'src/Probe.php' => "<?php\nnamespace MHMRentiva\\Admin\\Lite;\nReports::boot();\nnew Export();\n\$m = Message::find( 1 );\n",
			)
		);

		$this->assertStringContainsString( '[OK]', $out );
	}

	/**
	 * A bare `use` import autoloads nothing. Importing a Pro class and only
	 * touching it behind a guard is correct and must stay green.
	 */
	public function test_green_on_import_without_reference(): void {
		$out = $this->run_gate(
			array(
				'src/Probe.php' => "<?php\nnamespace MHMRentiva\\Admin\\Foo;\nuse MHMRentiva\\Admin\\Reports\\Reports;\necho 'nothing else';\n",
			)
		);

		$this->assertStringContainsString( '[OK]', $out );
	}

	/**
	 * Type positions fatal just as hard as calls: a parameter type hint on an
	 * absent class fatals when the method is invoked.
	 */
	public function test_red_on_unguarded_type_hint_and_extends(): void {
		$out = $this->run_gate(
			array(
				'src/Probe.php'  => "<?php\nnamespace MHMRentiva\\Admin\\Foo;\nuse MHMRentiva\\Admin\\Reports\\Reports;\nclass Probe {\n\tpublic function run( Reports \$r ) {}\n}\n",
				'src/Probe2.php' => "<?php\nnamespace MHMRentiva\\Admin\\Foo;\nclass Probe2 extends \\MHMRentiva\\Admin\\Reports\\Reports {}\n",
			)
		);

		$this->assertStringNotContainsString( '[OK]', $out );
		$this->assertStringContainsString( 'src/Probe.php:5', $out );
		$this->assertStringContainsString( 'src/Probe2.php:3', $out );
	}
}
