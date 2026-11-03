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
	public function test_check_note_keeps_code_tag_and_drops_others(): void
	{
		$method = new \ReflectionMethod(SetupWizard::class, 'render_check_note');
		$method->setAccessible(true);

		$out = (string) $method->invoke(null, "Add <code>define('WP_MEMORY_LIMIT', '256M');</code> <b>x</b>");

		$this->assertStringContainsString("<code>define('WP_MEMORY_LIMIT', '256M');</code>", $out);
		$this->assertStringNotContainsString('&lt;code&gt;', $out);
		$this->assertStringNotContainsString('<b>', $out);
	}

	public function test_check_note_drops_attributes_on_code(): void
	{
		$method = new \ReflectionMethod(SetupWizard::class, 'render_check_note');
		$method->setAccessible(true);

		$out = (string) $method->invoke(null, '<code onclick="x()">a</code>');

		$this->assertSame('<code>a</code>', $out);
	}
}
