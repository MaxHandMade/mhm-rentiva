<?php
/**
 * Non-vacuity proof for bin/check-external-http.php.
 *
 * The gate has two features that deliberately make it quieter -- it ignores
 * comments, and it exempts one file -- and each is a chance to accidentally
 * make it blind. So these tests prove it still goes RED on real code, and that
 * the quieting only suppresses what it is meant to.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Carveout;

use PHPUnit\Framework\TestCase;

final class ExternalHttpGateTest extends TestCase {

	/** @var list<string> Temp dirs to clean up. */
	private array $temp_dirs = array();

	/**
	 * Build a synthetic tree, run the gate over it, return its output.
	 *
	 * @param array<string, string> $files Repo-relative path => PHP source.
	 */
	private function run_gate( array $files ): string {
		$tmp = sys_get_temp_dir() . '/exthttp_probe_' . uniqid();
		$this->temp_dirs[] = $tmp;
		mkdir( $tmp, 0777, true );

		foreach ( $files as $relative => $source ) {
			$path = $tmp . '/' . $relative;
			$dir  = dirname( $path );
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir, 0777, true );
			}
			file_put_contents( $path, $source );
		}

		$gate = dirname( __DIR__, 3 ) . '/bin/check-external-http.php';

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
	 * THE NON-VACUITY PROOF: the exact call that shipped -- the visitor's IP
	 * sent to ip-api.com over plain HTTP -- must turn the gate red.
	 */
	public function test_red_on_ip_api_call(): void {
		$out = $this->run_gate(
			array(
				'src/Probe.php' => "<?php\n\$r = wp_remote_get(\"http://ip-api.com/json/{\$ip}?fields=countryCode\");\n",
			)
		);

		$this->assertStringNotContainsString( '[OK]', $out );
		$this->assertStringContainsString( 'ip-api.com', $out );
		$this->assertStringContainsString( 'src/Probe.php:2', $out );
	}

	/** A clean tree must be green -- otherwise the gate proves nothing. */
	public function test_green_on_clean_tree(): void {
		$out = $this->run_gate(
			array(
				'src/Probe.php' => "<?php\n\$r = wp_remote_get( home_url( '/wp-json/' ) );\n",
			)
		);

		$this->assertStringContainsString( '[OK]', $out );
	}

	/**
	 * The Google Fonts leak was browser-side, in a wp_register_style() URL --
	 * not a wp_remote_get(). The PHP-egress grep would have missed it, so the
	 * gate must match hosts anywhere in code.
	 */
	public function test_red_on_cdn_asset_url(): void {
		$out = $this->run_gate(
			array(
				'src/Probe.php' => "<?php\nwp_register_style( 'x', 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans', array(), '1' );\n",
			)
		);

		$this->assertStringNotContainsString( '[OK]', $out );
		$this->assertStringContainsString( 'fonts.googleapis.com', $out );
	}

	/** templates/ is scanned too, not just src/. */
	public function test_red_on_denied_host_in_template(): void {
		$out = $this->run_gate(
			array(
				'templates/thing.php' => "<?php\necho '<img src=\"https://ipinfo.io/x.png\">';\n",
			)
		);

		$this->assertStringNotContainsString( '[OK]', $out );
		$this->assertStringContainsString( 'templates/thing.php:2', $out );
	}

	/**
	 * Comments must not trip the gate: the removal call sites carry comments
	 * that NAME ip-api.com to explain why it is gone. If prose tripped it, the
	 * gate would be permanently red and would get ignored or gutted.
	 */
	public function test_green_when_host_named_only_in_comments(): void {
		$out = $this->run_gate(
			array(
				'src/Probe.php' => "<?php\n// We deliberately no longer call http://ip-api.com/json/ here.\n/**\n * See fonts.googleapis.com -- removed, bundle locally instead.\n */\n\$x = 1;\n",
			)
		);

		$this->assertStringContainsString( '[OK]', $out );
	}

	/**
	 * Comment-stripping must not become a blanket excuse: real code on a line
	 * that ALSO has a trailing comment is still real code.
	 */
	public function test_red_on_code_with_trailing_comment(): void {
		$out = $this->run_gate(
			array(
				'src/Probe.php' => "<?php\n\$u = 'http://ip-api.com/json/'; // legacy geolocation\n",
			)
		);

		$this->assertStringNotContainsString( '[OK]', $out );
		$this->assertStringContainsString( 'src/Probe.php:2', $out );
	}

	/**
	 * The Governance.php exemption must be scoped to that exact path -- a
	 * same-named file elsewhere must not inherit it.
	 */
	public function test_exemption_is_path_scoped(): void {
		$out = $this->run_gate(
			array(
				'src/Governance.php' => "<?php\nwp_register_style( 'x', 'https://cdn.jsdelivr.net/npm/x', array(), '1' );\n",
			)
		);

		$this->assertStringNotContainsString( '[OK]', $out );
		$this->assertStringContainsString( 'src/Governance.php:2', $out );
	}

	/** The real Governance.php path stays exempt: it blocks CDNs by naming them. */
	public function test_governance_denylist_file_is_exempt(): void {
		$out = $this->run_gate(
			array(
				'src/Admin/Core/Governance.php' => "<?php\nconst FORBIDDEN_URLS = array( 'cdn.tailwindcss.com', 'unpkg.com/tailwindcss' );\n",
			)
		);

		$this->assertStringContainsString( '[OK]', $out );
	}
}
