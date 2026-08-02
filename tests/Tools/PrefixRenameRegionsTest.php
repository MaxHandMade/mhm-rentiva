<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Tools;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

require_once dirname( __DIR__, 2 ) . '/bin/prefix-rename.php';

use MHMRentiva\Tools\PrefixRenamer;

/**
 * The `prefix-rename:ignore-start/end` mechanism is a SUPPRESSION, and this
 * round exists to remove suppressions -- so it only earns its place if it is
 * accountable.
 *
 * A region tells both the rename tool and gate G-C to skip a span of source. It
 * exists because a token carve-out cannot express "this literal is deliberate
 * legacy HERE and a genuine rename target THERE" -- 'mhm_rentiva_queue' is both,
 * and only position distinguishes them. But a region silences EVERYTHING between
 * its markers, so without limits it is a way to make the gate green by hiding
 * code from it. That is not hypothetical: the first version had a 435-line
 * region protecting three literals and an 824-line unterminated one, which
 * together put 53% of DatabaseCleaner.php outside the tool's and the gate's
 * field of view, and "mode 4 is green" was true there by construction.
 *
 * Four properties, all enforced here:
 *   1. markers BALANCE -- an unterminated start is an error, not an instruction
 *   2. every region is within IGNORE_REGION_MAX_LINES
 *   3. every file containing regions is registered, with the right count
 *   4. every literal a region actually covers is DECLARED -- so widening a
 *      region to swallow neighbouring code fails the build
 *
 * @coversNothing
 */
final class PrefixRenameRegionsTest extends TestCase {

	/**
	 * Files that mention the markers as DATA rather than using them as
	 * annotations: this test's own fixtures, the tool, and the gate. They are
	 * exactly PrefixRenamer::NEVER_SWEEP plus the gate, i.e. the files that carry
	 * old names as data by design.
	 *
	 * @var array<int,string>
	 */
	private const MARKER_TEXT_ONLY = array(
		'bin/prefix-rename.php',
		'bin/check-prefix-inventory.php',
		'tests/Tools/PrefixRenamerTest.php',
		'tests/Tools/PrefixRenameRegionsTest.php',
		'tests/Tools/NoBareMhmStorageKeysTest.php',
	);

	/**
	 * Scan the shipped tree for regions.
	 *
	 * @return array<int, array{file:string,start:int,end:int,lines:int,body:string}>
	 */
	private function regions(): array
	{
		$root  = dirname( __DIR__, 2 );
		$found = array();

		foreach ( array( 'src', 'templates', 'assets', 'src-react' ) as $dir ) {
			$path = $root . '/' . $dir;
			if ( ! is_dir( $path ) ) {
				continue;
			}
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $file ) {
				if ( ! $file->isFile() || ! preg_match( '/\.(php|js|jsx|css)$/', $file->getFilename() ) ) {
					continue;
				}
				$rel = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
				if ( str_contains( $rel, 'node_modules/' ) || in_array( $rel, self::MARKER_TEXT_ONLY, true ) ) {
					continue;
				}

				$lines = file( $file->getPathname() );
				$open  = null;
				foreach ( $lines as $i => $line ) {
					if ( str_contains( $line, PrefixRenamer::IGNORE_START ) ) {
						$open = $i;
					}
					if ( str_contains( $line, PrefixRenamer::IGNORE_END ) && null !== $open ) {
						$found[] = array(
							'file'  => $rel,
							'start' => $open + 1,
							'end'   => $i + 1,
							'lines' => $i - $open + 1,
							'body'  => implode( '', array_slice( $lines, $open + 1, $i - $open - 1 ) ),
						);
						$open = null;
					}
				}
				if ( null !== $open ) {
					$found[] = array(
						'file'  => $rel,
						'start' => $open + 1,
						'end'   => -1, // unterminated
						'lines' => count( $lines ) - $open,
						'body'  => '',
					);
				}
			}
		}

		return $found;
	}

	/**
	 * 1. An unterminated marker must FAIL, not be interpreted.
	 *
	 * The runtime keeps protect-to-end-of-file as a fail-safe -- resuming mid-file
	 * would convert exactly the literals the marker was opened to protect -- but
	 * the two are not in conflict: the fallback keeps a typo from corrupting
	 * data, and this gate keeps the typo from being permanent. The previous
	 * version of this suite locked in the fallback with a test, which meant an
	 * unbalanced marker could never go red.
	 */
	public function test_every_ignore_region_is_balanced(): void
	{
		$unterminated = array();
		foreach ( $this->regions() as $r ) {
			if ( -1 === $r['end'] ) {
				$unterminated[] = $r['file'] . ':' . $r['start'] . ' (protects ' . $r['lines'] . ' lines to end of file)';
			}
		}

		$this->assertSame(
			array(),
			$unterminated,
			"an ignore region is never closed, so it exempts the rest of the file from the sweep AND from G-C:\n"
			. implode( "\n", $unterminated )
		);
	}

	/**
	 * 2. A region is a blind spot; its length is the size of the blind spot.
	 */
	public function test_no_ignore_region_exceeds_the_line_cap(): void
	{
		$oversized = array();
		foreach ( $this->regions() as $r ) {
			if ( -1 !== $r['end'] && $r['lines'] > PrefixRenamer::IGNORE_REGION_MAX_LINES ) {
				$oversized[] = sprintf( '%s:%d-%d is %d lines', $r['file'], $r['start'], $r['end'], $r['lines'] );
			}
		}

		$this->assertSame(
			array(),
			$oversized,
			'an ignore region exceeds ' . PrefixRenamer::IGNORE_REGION_MAX_LINES . " lines. Wrap the literal, not the method:\n"
			. implode( "\n", $oversized )
		);
	}

	/**
	 * 3. Every file that uses a region is registered, with the right count.
	 */
	public function test_every_region_bearing_file_is_registered(): void
	{
		$actual = array();
		foreach ( $this->regions() as $r ) {
			$actual[ $r['file'] ] = ( $actual[ $r['file'] ] ?? 0 ) + 1;
		}

		$registered = array();
		foreach ( PrefixRenamer::IGNORE_REGIONS as $file => $meta ) {
			$registered[ $file ] = $meta['regions'];
		}

		ksort( $actual );
		ksort( $registered );

		$this->assertSame(
			$registered,
			$actual,
			'PrefixRenamer::IGNORE_REGIONS disagrees with the source. Every region is a decision and has to be written down with its reason.'
		);
	}

	/**
	 * 3b. A registration without a reason is not a registration.
	 */
	public function test_every_registered_file_states_a_reason(): void
	{
		foreach ( PrefixRenamer::IGNORE_REGIONS as $file => $meta ) {
			$this->assertNotEmpty( $meta['why'] ?? '', "$file is registered without a reason" );
			$this->assertGreaterThan( 40, strlen( $meta['why'] ), "$file's reason is too short to be one" );
		}
	}

	/**
	 * 4. THE ONE THAT MATTERS. Every literal a region actually covers must be
	 * declared, so a region cannot be widened to silence neighbouring code.
	 */
	public function test_every_literal_inside_a_region_is_declared(): void
	{
		$undeclared = array();

		foreach ( $this->regions() as $r ) {
			if ( -1 === $r['end'] ) {
				continue; // reported by the balance test.
			}
			$declared = PrefixRenamer::IGNORE_REGIONS[ $r['file'] ]['literals'] ?? array();

			if ( preg_match_all( "/'([A-Za-z_%][A-Za-z0-9_%]*)'/", $r['body'], $m ) ) {
				foreach ( array_unique( $m[1] ) as $literal ) {
					if ( false === stripos( $literal, 'mhm' ) ) {
						continue;
					}
					if ( ! in_array( $literal, $declared, true ) ) {
						$undeclared[] = $r['file'] . ':' . $r['start'] . " covers '" . $literal . "' but does not declare it";
					}
				}
			}
		}

		$this->assertSame(
			array(),
			array_values( array_unique( $undeclared ) ),
			"an ignore region silences a literal nobody registered:\n" . implode( "\n", array_unique( $undeclared ) )
		);
	}

	/**
	 * 4b. ...and the reverse: a declared literal that no longer appears is a
	 * stale claim, which is how a registry rots into a place to hide things.
	 */
	public function test_no_declared_literal_is_stale(): void
	{
		$bodies = array();
		foreach ( $this->regions() as $r ) {
			$bodies[ $r['file'] ] = ( $bodies[ $r['file'] ] ?? '' ) . $r['body'];
		}

		$stale = array();
		foreach ( PrefixRenamer::IGNORE_REGIONS as $file => $meta ) {
			foreach ( $meta['literals'] as $literal ) {
				if ( ! str_contains( $bodies[ $file ] ?? '', "'" . $literal . "'" ) ) {
					$stale[] = "$file declares '$literal' but no region contains it";
				}
			}
		}

		$this->assertSame( array(), $stale, implode( "\n", $stale ) );
	}
}
