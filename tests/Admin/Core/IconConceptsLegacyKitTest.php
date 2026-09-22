<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Core\IconConcepts;
use MHMUiCore\Kit\Icons;
use WP_UnitTestCase;

/**
 * A kit copy that predates the concept vocabulary is still allowed to win, and
 * it draws whatever string the call site handed it.
 *
 * ui-core arbitrates by version: the HIGHEST registered copy boots, and one
 * copy serves every plugin on the site. So a sibling MHM plugin bundling
 * 0.11-0.13 can win over Rentiva's 0.14 -- that is the documented
 * compatibility case, not a broken install. Those copies have no
 * `Kit\Icons`: their StatCard does `'dashicons dashicons-' . $icon` with no
 * resolution step.
 *
 * 🔴 WHAT THAT COSTS IF NOTHING PRE-RESOLVES. Converting the call sites from
 * raw suffixes to concepts means they now hand the kit `revenue` where they
 * used to hand it `money-alt`. Under a legacy winner the kit prints
 * `dashicons-revenue`, a class no stylesheet defines, and EVERY PHP KPI card
 * on the site loses its icon. Silently: no error, no failing gate, no console
 * warning -- CI was green on exactly this defect.
 *
 * So Rentiva resolves concepts itself when the winning copy cannot, and these
 * tests pin both halves of that: the glyphs match what the package would have
 * resolved, and the vocabulary covers every concept a PHP call site writes.
 */
final class IconConceptsLegacyKitTest extends WP_UnitTestCase {

	/** Files whose `icon` values reach the kit, found the way the gate finds them. */
	private const ANCHOR = 'stats_grid_html';

	public function set_up(): void {
		parent::set_up();
		// Other tests use Icons::reset(); the product's own vocabulary has to
		// be present for `vehicles` to resolve here.
		Icons::register( IconConcepts::MAP );
	}

	public function test_a_legacy_kit_draws_the_glyph_the_call_site_used_to_draw(): void {
		// 🔴 WHY reset() IS WHAT MAKES THIS TEST MEAN ANYTHING. Both paths draw
		// the same glyph by design, so with the vocabulary registered this test
		// would pass even if the legacy branch were never reached -- the first
		// draft did exactly that, and passed against code that did not exist
		// yet. Dropping the product's registrations makes `vehicles`
		// unresolvable BY THE KIT, so the two paths are forced apart: only
		// Rentiva's own pre-resolution can still produce `car`.
		Icons::reset();

		$legacy    = AssetManager::stats_grid_html( $this->cards(), 4, true, false );
		$resolving = AssetManager::stats_grid_html( $this->cards(), 4, true, true );

		// Only the PRODUCT concept discriminates here: this test still renders
		// through the 0.14 kit, which resolves seed concepts (`revenue`) on its
		// own even after reset(). What a legacy kit is actually handed is
		// pinned by test_a_legacy_kit_is_handed_suffixes_never_concepts.
		$this->assertStringContainsString( 'dashicons-car', $legacy, 'A product concept reached a legacy kit unresolved.' );
		$this->assertStringNotContainsString( 'dashicons-revenue', $legacy );
		$this->assertStringNotContainsString( 'dashicons-vehicles', $legacy );

		// The control: with the vocabulary gone, the kit itself cannot draw the
		// product concept. If this said `car` too, the paths would be
		// indistinguishable and the assertions above would prove nothing.
		$this->assertStringContainsString( 'dashicons-vehicles', $resolving );
	}

	/**
	 * What a pre-0.14 kit RECEIVES, not what the 0.14 kit in this test process
	 * draws. A real legacy StatCard prints `'dashicons dashicons-' . $icon`, so
	 * every concept a PHP call site writes must reach it already turned into a
	 * suffix -- seed concepts included. The render-based test above cannot see
	 * a missing seed entry (the 0.14 kit resolves seeds itself); this can: it
	 * fails if any LEGACY_SUFFIX entry is dropped (first independent audit's
	 * M-1, 2026-09-21).
	 */
	public function test_a_legacy_kit_is_handed_suffixes_never_concepts(): void {
		$method = new \ReflectionMethod( AssetManager::class, 'legacy_icon_cards' );
		$method->setAccessible( true );

		// Inputs and expectations come from two sources independent of
		// LEGACY_SUFFIX: the concepts the PHP call sites really write, and what
		// the package resolves them to. Building either from the map itself
		// would skip exactly the entry that went missing.
		$vocabulary = Icons::map();
		$concepts   = array();
		foreach ( $this->call_sites() as $file ) {
			preg_match_all( "/'icon'\s*=>\s*'([^']*)'/", (string) file_get_contents( $file ), $hits );
			foreach ( $hits[1] as $value ) {
				if ( isset( $vocabulary[ $value ] ) ) {
					$concepts[ $value ] = true;
				}
			}
		}
		$this->assertNotEmpty( $concepts, 'No PHP call site wrote a concept; this test measured nothing.' );

		$cards = array();
		foreach ( array_keys( $concepts ) as $concept ) {
			$cards[] = array( 'label' => $concept, 'value' => '1', 'icon' => $concept );
		}
		$cards[] = array( 'label' => 'raw', 'value' => '1', 'icon' => 'yes' );

		$handed = $method->invoke( null, $cards );

		foreach ( $handed as $index => $card ) {
			$written  = $cards[ $index ]['icon'];
			$expected = isset( $concepts[ $written ] ) ? Icons::resolve( $written ) : $written;
			$this->assertSame(
				$expected,
				$card['icon'],
				sprintf( 'A legacy kit would be handed "%s" for "%s"; it prints that string verbatim.', $card['icon'], $written )
			);
		}
	}

	public function test_both_kits_draw_the_same_markup(): void {
		$this->assertSame(
			AssetManager::stats_grid_html( $this->cards(), 4, true, true ),
			AssetManager::stats_grid_html( $this->cards(), 4, true, false ),
			'A card renders differently depending on which ui-core copy won arbitration.'
		);
	}

	public function test_a_raw_suffix_still_passes_through_a_legacy_kit(): void {
		// Not every value handed to the kit is a concept: a plain Dashicon
		// suffix (`yes`) must pass through pre-resolution untouched. No Lite
		// call site writes one today, but the wrapper is shared.
		$html = AssetManager::stats_grid_html(
			array( array( 'label' => 'Onay', 'value' => '1', 'icon' => 'yes' ) ),
			4,
			true,
			false
		);

		$this->assertStringContainsString( 'dashicons-yes', $html );
	}

	public function test_every_legacy_suffix_is_what_the_package_would_resolve(): void {
		$this->assertNotEmpty( IconConcepts::LEGACY_SUFFIX );

		foreach ( IconConcepts::LEGACY_SUFFIX as $concept => $suffix ) {
			$this->assertSame(
				Icons::resolve( $concept ),
				$suffix,
				sprintf( 'The legacy map draws "%s" differently than ui-core 0.14 resolves it.', $concept )
			);
		}
	}

	public function test_every_concept_a_php_call_site_writes_is_covered(): void {
		$vocabulary = Icons::map();
		$checked    = 0;

		foreach ( $this->call_sites() as $file ) {
			preg_match_all( "/'icon'\s*=>\s*'([^']*)'/", (string) file_get_contents( $file ), $hits );

			foreach ( $hits[1] as $value ) {
				if ( ! isset( $vocabulary[ $value ] ) ) {
					continue; // A raw suffix, not a concept: nothing to resolve.
				}
				++$checked;
				$this->assertArrayHasKey(
					$value,
					IconConcepts::LEGACY_SUFFIX,
					sprintf( '%s writes the concept "%s", which a legacy kit could not draw.', $file, $value )
				);
			}
		}

		$this->assertGreaterThan( 0, $checked, 'No PHP call site wrote a concept; this test measured nothing.' );
	}

	/** @return array<int, array<string, string>> */
	private function cards(): array {
		return array(
			array( 'label' => 'Ciro', 'value' => '1', 'icon' => 'revenue' ),
			array( 'label' => 'Araclar', 'value' => '2', 'icon' => 'vehicles' ),
		);
	}

	/**
	 * PHP files that hand cards to the kit, anchored the way the CI gate is.
	 *
	 * @return array<int, string>
	 */
	private function call_sites(): array {
		$out = array();

		foreach ( array( 'src', 'templates' ) as $dir ) {
			$files = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( MHMRENTIVA_PLUGIN_PATH . $dir )
			);
			foreach ( $files as $file ) {
				if ( 'php' !== $file->getExtension() || 'AssetManager.php' === $file->getFilename() ) {
					continue;
				}
				if ( false !== strpos( (string) file_get_contents( $file->getPathname() ), self::ANCHOR ) ) {
					$out[] = $file->getPathname();
				}
			}
		}

		return $out;
	}
}
