<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\IconConcepts;
use MHMRentiva\Admin\Core\ProIconConcepts;
use WP_UnitTestCase;

/**
 * The product's icon vocabulary has a PHP home and a JSX twin, and nothing
 * else makes them agree.
 *
 * ui-core's PHP registry lives in the winning copy, so one `Icons::register()`
 * call serves every plugin on the site. Its JSX registry does not: it lives
 * inside whichever bundle registered it and reaches no other bundle. So a
 * React card that writes `icon: 'vehicles'` in a bundle whose entry point
 * never calls `registerIcons()` gets the value passed through untouched, the
 * kit prints `dashicons-vehicles`, and the browser draws NOTHING. No error, no
 * failing test, no console warning — an empty square where an icon was.
 *
 * That is the failure this file exists to make loud.
 */
final class IconConceptsTwinTest extends WP_UnitTestCase {

	/** Bundle entry points, one per React admin screen. */
	private const BUNDLE_ROOT = 'src-react/admin';

	public function test_every_registered_jsx_concept_matches_the_php_map(): void {
		$found = 0;

		foreach ( $this->bundles() as $bundle => $dir ) {
			$entry = $dir . '/index.js';
			if ( ! is_file( $entry ) ) {
				continue;
			}

			foreach ( $this->registrations( (string) file_get_contents( $entry ) ) as $concept => $suffix ) {
				++$found;
				$this->assertArrayHasKey(
					$concept,
					IconConcepts::MAP,
					sprintf( '%s registers the concept "%s", which IconConcepts::MAP does not define.', $bundle, $concept )
				);
				$this->assertSame(
					IconConcepts::MAP[ $concept ],
					$suffix,
					sprintf( '%s maps "%s" to a different glyph than the PHP side does.', $bundle, $concept )
				);
			}
		}

		// A twin test that finds no twins has measured nothing.
		$this->assertGreaterThan( 0, $found, 'No registerIcons() call was found in any bundle entry point.' );
	}

	public function test_a_bundle_that_uses_a_product_concept_registers_it(): void {
		$checked = 0;

		foreach ( $this->bundles() as $bundle => $dir ) {
			$used = $this->product_concepts_used( $dir );
			if ( array() === $used ) {
				continue;
			}

			$entry = $dir . '/index.js';
			$this->assertFileExists( $entry, sprintf( '%s uses a product concept but has no entry point.', $bundle ) );

			$registered = $this->registrations( (string) file_get_contents( $entry ) );

			foreach ( $used as $concept ) {
				++$checked;
				$this->assertArrayHasKey(
					$concept,
					$registered,
					sprintf(
						'%s renders a card with icon "%s" but its entry point never registers it. '
						. 'The kit would print dashicons-%s and the browser would draw nothing.',
						$bundle,
						$concept,
						$concept
					)
				);
			}
		}

		$this->assertGreaterThan( 0, $checked, 'No bundle used a product concept; this test measured nothing.' );
	}

	/**
	 * Lite and Pro both define `bookings` and `vehicles`, on purpose. ui-core's
	 * PHP registry is last-write-wins and serves both plugins, so if the two
	 * maps ever disagree, whichever plugin registers second silently decides
	 * the glyph on BOTH plugins' PHP strips, while each JSX bundle keeps its
	 * own -- one concept, two pictures, no error.
	 *
	 * Pro's suite asserts the same thing from its side, but that runs only
	 * when Pro's CI runs. This is the half that fires when LITE changes the
	 * shared entry (an independent audit found the guard was one-sided,
	 * 2026-09-22). It needs Pro checked out as a sibling, which is how the
	 * development tree is laid out; Lite's own CI has no Pro and skips it.
	 */
	/**
	 * The half of the Lite/Pro contract that cannot skip. Lite's CI has no Pro
	 * (it is private), so the comparison below skips there; this one reads the
	 * shared-entry contract committed in tests/fixtures and runs everywhere.
	 * Pro's suite asserts the same file against Pro's map.
	 */
	public function test_the_shared_entries_match_the_contract_pro_also_asserts(): void {
		$contract = require MHMRENTIVA_PLUGIN_PATH . 'tests/fixtures/icon-concepts-shared-with-pro.php';

		$this->assertIsArray( $contract );
		$this->assertNotEmpty( $contract, 'The shared-entry contract is empty; this test measured nothing.' );

		foreach ( $contract as $concept => $suffix ) {
			$this->assertArrayHasKey(
				$concept,
				IconConcepts::MAP,
				sprintf( 'The contract shares "%s" with Pro, but IconConcepts::MAP no longer defines it.', $concept )
			);
			$this->assertSame(
				$suffix,
				IconConcepts::MAP[ $concept ],
				sprintf(
					'Lite draws the shared concept "%s" as %s; the contract Pro also asserts says %s. '
					. 'Changing a shared glyph moves the contract, Lite and Pro together.',
					$concept,
					IconConcepts::MAP[ $concept ],
					$suffix
				)
			);
		}
	}

	public function test_lite_and_pro_agree_on_the_concepts_they_share(): void {
		$pro = dirname( MHMRENTIVA_PLUGIN_PATH ) . '/mhm-rentiva-pro/src/Admin/Core/ProIconConcepts.php';

		if ( ! is_file( $pro ) ) {
			$this->markTestSkipped( 'Pro is not checked out as a sibling; the overlap cannot be measured here.' );
		}

		if ( ! class_exists( ProIconConcepts::class, false ) ) {
			require_once $pro;
		}

		$shared = array_intersect_key( IconConcepts::MAP, ProIconConcepts::MAP );
		$this->assertNotEmpty( $shared, 'Lite and Pro share no concept; this test measured nothing.' );

		// The contract must name exactly the concepts the two maps share: a new
		// shared concept missing from it would be guarded nowhere in Lite's CI.
		$contract = require MHMRENTIVA_PLUGIN_PATH . 'tests/fixtures/icon-concepts-shared-with-pro.php';
		$this->assertEqualsCanonicalizing(
			array_keys( $shared ),
			array_keys( $contract ),
			'tests/fixtures/icon-concepts-shared-with-pro.php does not list exactly the concepts Lite and Pro share.'
		);

		foreach ( $shared as $concept => $suffix ) {
			$this->assertSame(
				ProIconConcepts::MAP[ $concept ],
				$suffix,
				sprintf( 'Lite draws "%s" as %s while Pro draws it as %s; one concept, two pictures.', $concept, $suffix, ProIconConcepts::MAP[ $concept ] )
			);
		}
	}

	/** @return array<string, string> bundle name => absolute directory */
	private function bundles(): array {
		$root = MHMRENTIVA_PLUGIN_PATH . self::BUNDLE_ROOT;
		$out  = array();

		foreach ( (array) glob( $root . '/*', GLOB_ONLYDIR ) as $dir ) {
			$out[ basename( (string) $dir ) ] = (string) $dir;
		}

		return $out;
	}

	/**
	 * `registerIcons( { a: 'b', c: 'd' } )` -> [ a => b, c => d ].
	 *
	 * @return array<string, string>
	 */
	private function registrations( string $source ): array {
		if ( ! preg_match( '/registerIcons\(\s*\{(.*?)\}\s*\)/s', $source, $call ) ) {
			return array();
		}

		preg_match_all( "/([a-zA-Z0-9_]+)\s*:\s*'([^']+)'/", $call[1], $pairs, PREG_SET_ORDER );

		$out = array();
		foreach ( $pairs as $pair ) {
			$out[ $pair[1] ] = $pair[2];
		}

		return $out;
	}

	/**
	 * Product concepts (not seed ones) written as `icon:` in a bundle's tree.
	 *
	 * @return array<int, string>
	 */
	private function product_concepts_used( string $dir ): array {
		$used = array();

		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir ) );
		foreach ( $files as $file ) {
			if ( ! preg_match( '/\.jsx?$/', $file->getFilename() ) ) {
				continue;
			}
			preg_match_all( "/\bicon:\s*'([^']+)'/", (string) file_get_contents( $file->getPathname() ), $hits );
			foreach ( $hits[1] as $value ) {
				if ( isset( IconConcepts::MAP[ $value ] ) ) {
					$used[ $value ] = true;
				}
			}
		}

		return array_keys( $used );
	}
}
