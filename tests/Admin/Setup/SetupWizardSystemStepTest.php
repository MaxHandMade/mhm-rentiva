<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Setup;

use MHMRentiva\Admin\Setup\SetupWizard;
use WP_UnitTestCase;

/**
 * The memory-limit note carries a `<code>` snippet; it was escaped whole, so
 * the admin saw the tags as text.
 *
 * @covers \MHMRentiva\Admin\Setup\SetupWizard
 */
final class SetupWizardSystemStepTest extends WP_UnitTestCase
{
	/** @return array<string, array<string, string>> label => check */
	private function checks(): array
	{
		$method = new \ReflectionMethod(SetupWizard::class, 'get_system_checks');
		$method->setAccessible(true);
		$out = array();
		foreach ($method->invoke(null) as $check) {
			$out[ (string) $check['label'] ] = $check;
		}
		return $out;
	}

	/**
	 * The wizard said PHP 7.4+ and WordPress 6.0+ were fine while the plugin
	 * required 8.1 and 6.7, then 7.0 (Fable, Lite #163). The minimums now come
	 * from the plugin header, the one place WordPress itself enforces.
	 */
	public function test_minimum_versions_follow_the_plugin_header(): void
	{
		$header = get_file_data(MHMRENTIVA_PLUGIN_FILE, array(
			'wp'  => 'Requires at least',
			'php' => 'Requires PHP',
		));
		$checks = $this->checks();

		$this->assertSame($header['php'] . '+', $checks['PHP Version']['expected']);
		$this->assertSame($header['wp'] . '+', $checks['WordPress Version']['expected']);
		$this->assertSame('7.0', $header['wp']);
		$this->assertSame('8.1', $header['php']);
	}

	private function note(string $message): string
	{
		$method = new \ReflectionMethod(SetupWizard::class, 'render_check_note');
		$method->setAccessible(true);
		ob_start();
		$method->invoke(null, $message);
		return (string) ob_get_clean();
	}

	public function test_check_note_keeps_code_tag_and_drops_others(): void
	{
		$out = $this->note("Add <code>define('WP_MEMORY_LIMIT', '256M');</code> <b>x</b>");

		$this->assertStringContainsString("<code>define('WP_MEMORY_LIMIT', '256M');</code>", $out);
		$this->assertStringNotContainsString('&lt;code&gt;', $out);
		$this->assertStringNotContainsString('<b>', $out);
	}

	public function test_check_note_drops_attributes_on_code(): void
	{
		$this->assertSame('<code>a</code>', $this->note('<code onclick="x()">a</code>'));
	}
}
