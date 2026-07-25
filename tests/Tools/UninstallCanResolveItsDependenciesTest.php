<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Tools;

use PHPUnit\Framework\TestCase;

/**
 * `uninstall.php` runs with the plugin NOT loaded.
 *
 * WordPress includes it directly when a plugin is deleted; the main plugin file
 * never executes, so the PSR-4 autoloader registered in `mhm-rentiva.php` does
 * not exist. Anything `Uninstaller` reaches for has to be resolvable from what
 * `uninstall.php` itself sets up, or deletion fatals partway through and every
 * later step -- table drops, cron clearing, taxonomy cleanup -- silently never
 * runs, leaving exactly the residue the user asked to be removed.
 *
 * This is not hypothetical: moving the backup directory added
 * `DatabaseCleaner::backup_dirs()` calls to `Uninstaller`, while `uninstall.php`
 * required only `Uninstaller.php`. Nothing caught it -- the class resolves fine
 * in wp-admin, where the plugin is loaded, so every test and every manual check
 * passed while the real uninstall path was broken.
 *
 * The guard is structural on purpose: asserting that a specific class is
 * required would pass again the moment a new dependency appears.
 */
final class UninstallCanResolveItsDependenciesTest extends TestCase
{
	private function root(): string
	{
		return dirname( __DIR__, 2 );
	}

	/**
	 * Every MHMRentiva class named inside Uninstaller.php.
	 *
	 * @return list<string>
	 */
	private function dependencies(): array
	{
		$src = (string) file_get_contents( $this->root() . '/src/Admin/Utilities/Uninstall/Uninstaller.php' );

		preg_match_all( '/\\\\?(MHMRentiva(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+)\s*::/', $src, $m );

		$found = array_unique( $m[1] );
		sort( $found );

		return array_values(
			array_filter(
				$found,
				static fn( string $class ): bool => 'MHMRentiva\Admin\Utilities\Uninstall\Uninstaller' !== $class
			)
		);
	}

	/**
	 * Guards the scan: if the regex stopped matching, the assertion below would
	 * pass while checking nothing.
	 */
	public function test_the_scan_finds_the_dependencies(): void
	{
		$this->assertNotEmpty(
			$this->dependencies(),
			'Uninstaller names no other MHMRentiva class -- either that changed, or the scan is broken.'
		);
	}

	public function test_uninstall_registers_an_autoloader_for_the_plugin_namespace(): void
	{
		$uninstall = (string) file_get_contents( $this->root() . '/uninstall.php' );

		$this->assertStringContainsString(
			'spl_autoload_register',
			$uninstall,
			sprintf(
				"uninstall.php loads Uninstaller but registers no autoloader, so these classes it calls cannot resolve:\n  %s",
				implode( "\n  ", $this->dependencies() )
			)
		);

		$this->assertStringContainsString(
			'MHMRentiva',
			$uninstall,
			'The autoloader in uninstall.php does not mention the plugin namespace.'
		);
	}

	/**
	 * And the autoloader has to actually resolve them: every class Uninstaller
	 * names must exist at the PSR-4 path under src/.
	 */
	public function test_every_dependency_resolves_to_a_file_under_src(): void
	{
		$missing = array();

		foreach ( $this->dependencies() as $class ) {
			$relative = str_replace( array( 'MHMRentiva\\', '\\' ), array( '', '/' ), $class ) . '.php';

			if ( ! is_file( $this->root() . '/src/' . $relative ) ) {
				$missing[] = $class . '  (expected src/' . $relative . ')';
			}
		}

		$this->assertSame(
			array(),
			$missing,
			"Uninstaller calls classes that do not exist at their PSR-4 path:\n  " . implode( "\n  ", $missing )
		);
	}
}
