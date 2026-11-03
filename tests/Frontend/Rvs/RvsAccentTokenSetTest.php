<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Rvs;

use MHMRentiva\Tests\Support\RvsTokenFile;
use WP_UnitTestCase;

/**
 * Colour setting PR 1: one accent drives the front end.
 *
 * Eight tokens stay literal (the ones PR 2's inline block overrides); every other
 * accent copy is bound to them with `var()`. The spec's acceptance greps live
 * here as sets, so a later PR cannot slide them back.
 */
final class RvsAccentTokenSetTest extends WP_UnitTestCase
{
	private const OVERRIDABLE = array(
		'--rvs-bg',
		'--rvs-accent-strong',
		'--rvs-accent-deep',
		'--rvs-accent-100',
		'--rvs-sel-text',
		'--rvs-on-accent',
		'--rvs-accent-text',
		'--rvs-accent-text-hover',
	);

	public function test_new_tokens_are_literal_with_designer_defaults(): void
	{
		$decls = RvsTokenFile::declarations();

		$this->assertSame('#ffffff', $decls['--rvs-on-accent'] ?? null);
		$this->assertSame('#2271b1', $decls['--rvs-accent-text'] ?? null);
		$this->assertSame('#135e96', $decls['--rvs-accent-text-hover'] ?? null);
	}

	public function test_eight_overridable_tokens_stay_literal_hex(): void
	{
		$decls = RvsTokenFile::declarations();

		foreach (self::OVERRIDABLE as $name) {
			$this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $decls[ $name ] ?? '', "{$name} is not a literal hex");
		}
	}

	public function test_copies_are_bound_to_the_accent(): void
	{
		$decls = RvsTokenFile::declarations();

		foreach (
			array(
				'--rvs-sel-bg'            => 'var(--rvs-accent-100)',
				'--rvs-sel-line'          => 'var(--rvs-accent-strong)',
				'--rvs-hover-shadow'      => '0 2px 6px color-mix(in srgb, var(--rvs-accent-deep) 35%, transparent)',
				'--rvs-card-title-hover'  => 'var(--rvs-accent-text)',
				'--rvs-card-btn-bg'       => 'var(--rvs-accent-strong)',
				'--rvs-card-btn-bg-hover' => 'var(--rvs-accent-deep)',
				'--rvs-booking-cta'       => 'var(--rvs-accent-strong)',
				'--rvs-booking-cta-hover' => 'var(--rvs-accent-deep)',
				'--rvs-st-confirmed-bg'   => 'var(--rvs-accent-100)',
				'--rvs-st-confirmed-fg'   => 'var(--rvs-sel-text)',
			) as $name => $value
		) {
			$this->assertSame($value, $decls[ $name ] ?? null, $name);
		}
	}

	public function test_bound_copies_resolve_to_todays_hex(): void
	{
		foreach (
			array(
				'--rvs-sel-bg'            => '#dcecf9',
				'--rvs-sel-line'          => '#2271b1',
				'--rvs-card-title-hover'  => '#2271b1',
				'--rvs-card-btn-bg'       => '#2271b1',
				'--rvs-card-btn-bg-hover' => '#135e96',
				'--rvs-booking-cta'       => '#2271b1',
				'--rvs-booking-cta-hover' => '#135e96',
				'--rvs-st-confirmed-bg'   => '#dcecf9',
				// Deliberate difference 1: the confirmed tag text was #135e96.
				'--rvs-st-confirmed-fg'   => '#0a3c63',
			) as $name => $hex
		) {
			$this->assertSame($hex, RvsTokenFile::resolve($name), $name);
		}
	}

	public function test_unused_tokens_are_gone(): void
	{
		$decls = RvsTokenFile::declarations();
		$names = array( '--rvs-' . 'accent-200', '--rvs-' . 'n-700' );

		foreach ($names as $name) {
			$this->assertArrayNotHasKey($name, $decls, "{$name} is still declared");
		}

		$pattern = '/(' . implode('|', array_map(static fn (string $n): string => preg_quote($n, '/'), $names)) . ')(?![-\w])/';
		$this->assertSame(array(), self::files_matching($pattern, array( 'assets', 'templates', 'src' )));
	}

	public function test_new_tokens_classify_as_canonical(): void
	{
		require_once dirname(__DIR__, 3) . '/bin/check-token-definitions.php';

		// The CLI default of bin/check-token-definitions.php: the plugin root and
		// its three canonical stylesheets.
		$root   = dirname(__DIR__, 3);
		$report = mhmrentiva_classify_tokens(
			array( $root ),
			array(
				$root . '/assets/css/core/css-variables.css',
				$root . '/assets/css/core/golden-ratio-contract.css',
				$root . '/assets/css/rvs/rvs-tokens.css',
			)
		);

		foreach (array( '--rvs-on-accent', '--rvs-accent-text', '--rvs-accent-text-hover' ) as $name) {
			$this->assertSame('canonical', $report['tokens'][ $name ]['class'] ?? null, $name);
		}
	}

	/**
	 * Files (relative to the plugin root) under the given directories whose
	 * .css/.php/.js source matches the pattern, as "path:line".
	 *
	 * @param list<string> $dirs
	 * @return list<string>
	 */
	private static function files_matching(string $pattern, array $dirs): array
	{
		$root = rtrim(MHMRENTIVA_PLUGIN_DIR, '/');
		$hits = array();
		foreach ($dirs as $dir) {
			$base = $root . '/' . $dir;
			if (! is_dir($base)) {
				continue;
			}
			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
			foreach ($iterator as $file) {
				if (! $file->isFile() || ! in_array(strtolower($file->getExtension()), array( 'css', 'php', 'js' ), true)) {
					continue;
				}
				$lines = file($file->getPathname()) ?: array();
				foreach ($lines as $i => $line) {
					if (preg_match($pattern, $line)) {
						$hits[] = substr($file->getPathname(), strlen($root) + 1) . ':' . ( $i + 1 );
					}
				}
			}
		}
		return $hits;
	}
}
