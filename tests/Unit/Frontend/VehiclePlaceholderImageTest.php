<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Frontend;

use MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper;
use WP_UnitTestCase;

/**
 * T8 final review I-6: three account templates named
 * `assets/images/no-image.png` as the vehicle placeholder, and that file has
 * never existed in this plugin -- assets/images/ ships exactly two files
 * (mhm-logo.png, placeholder-avatar.svg). Every booking whose vehicle had no
 * featured image therefore rendered a broken <img> on the customer's bookings
 * list and booking-detail screens.
 *
 * A fourth site the review did not list carried the same defect plus a second
 * one: assets/js/frontend/booking-form.js built the URL as
 * `window.location.origin + '/wp-content/plugins/mhm-rentiva/assets/images/no-image.png'`
 * -- wrong on any install with a moved wp-content, a renamed plugin folder or
 * WordPress in a subdirectory, even if the file had existed.
 *
 * The fix reuses the mechanism the plugin ALREADY had for exactly this case
 * (VehiclesGrid/VehiclesList each carried a byte-identical private copy of it)
 * rather than shipping a new binary: one owner on VehicleDataHelper, reachable
 * from templates, from the shortcodes and -- via the booking form's existing
 * localize payload -- from JavaScript.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper
 */
final class VehiclePlaceholderImageTest extends WP_UnitTestCase {

	private const PLUGIN_ROOT = MHMRENTIVA_PLUGIN_DIR;

	public function test_placeholder_url_is_never_empty(): void {
		$this->assertNotSame( '', VehicleDataHelper::get_placeholder_image_url() );
	}

	/**
	 * The whole point of the finding: whatever this returns must actually
	 * RESOLVE. Either it is a self-contained data URI, or it names a file that
	 * is on disk -- never a URL to something that is not shipped.
	 */
	public function test_placeholder_url_resolves_to_something_that_exists(): void {
		$url = VehicleDataHelper::get_placeholder_image_url();

		if ( 0 === strpos( $url, 'data:image/' ) ) {
			$this->assertStringContainsString( ';base64,', $url, 'The inline fallback must be a base64 data URI.' );

			$payload = substr( $url, strpos( $url, ',' ) + 1 );
			$decoded = base64_decode( $payload, true );

			$this->assertIsString( $decoded, 'The data URI payload must be valid base64.' );
			$this->assertStringContainsString( '<svg', (string) $decoded, 'The inline fallback must decode to real SVG markup.' );
			return;
		}

		$relative = ltrim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		$filename = basename( $relative );

		$this->assertFileExists(
			self::PLUGIN_ROOT . 'assets/images/' . $filename,
			'A placeholder URL pointing at a file means that file must ship.'
		);
	}

	/**
	 * The shortcodes' two private copies now delegate here, so all three
	 * surfaces must agree -- otherwise the account screens and the vehicle grid
	 * could drift apart again.
	 */
	public function test_grid_and_list_resolve_to_the_same_placeholder(): void {
		$expected = VehicleDataHelper::get_placeholder_image_url();

		foreach ( array(
			\MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid::class,
			\MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::class,
		) as $class ) {
			$method = new \ReflectionMethod( $class, 'get_placeholder_image_url' );
			$method->setAccessible( true );

			$this->assertSame(
				$expected,
				$method->invoke( null ),
				$class . ' must resolve to the one shared placeholder.'
			);
		}
	}

	/**
	 * THE CLASS GATE, not the three cited lines. Every shipped source file is
	 * scanned for a direct `assets/images/<name>` URL construction, and each
	 * named file must exist.
	 *
	 * The two shapes it looks for are the two that ship a URL to the browser
	 * with nothing checking it first:
	 *   - PHP:  MHMRENTIVA_PLUGIN_URL . 'assets/images/<name>'
	 *   - JS:   '/wp-content/plugins/mhm-rentiva/assets/images/<name>'
	 *
	 * VehicleDataHelper's own candidate list is deliberately NOT a violation:
	 * it builds `MHMRENTIVA_PLUGIN_URL . 'assets/images/' . $filename` from a
	 * variable, behind file_exists(), so it can name files that are not there.
	 * The regex only matches a literal filename, so it skips that by
	 * construction.
	 */
	public function test_no_shipped_file_links_an_image_that_is_not_shipped(): void {
		$scanned    = 0;
		$references = array();

		foreach ( $this->shipped_source_files() as $file ) {
			++$scanned;
			$source = (string) file_get_contents( $file );

			if ( preg_match_all( '#MHMRENTIVA_PLUGIN_URL\s*\.\s*[\'"]assets/images/([A-Za-z0-9._-]+)[\'"]#', $source, $m ) ) {
				foreach ( $m[1] as $name ) {
					$references[] = array( $file, $name );
				}
			}

			if ( preg_match_all( '#[\'"`][^\'"`]*/wp-content/plugins/[A-Za-z0-9._-]+/assets/images/([A-Za-z0-9._-]+)#', $source, $m ) ) {
				foreach ( $m[1] as $name ) {
					$references[] = array( $file, $name );
				}
			}
		}

		$this->assertGreaterThan( 100, $scanned, 'The scanner must actually have read the shipped tree.' );

		// Non-vacuous: this gate is worthless if it finds nothing to check.
		// AboutController links assets/images/mhm-logo.png, a file that DOES
		// ship -- so a passing run proves the regex matched and the file check
		// ran, not that the corpus was empty.
		$this->assertNotEmpty( $references, 'The gate found no image reference at all -- it is measuring nothing.' );

		foreach ( $references as list( $file, $name ) ) {
			$this->assertFileExists(
				self::PLUGIN_ROOT . 'assets/images/' . $name,
				sprintf( '%s links assets/images/%s, which this plugin does not ship.', str_replace( self::PLUGIN_ROOT, '', $file ), $name )
			);
		}
	}

	/**
	 * Regression lock on the exact filename the finding was about, so it cannot
	 * come back as a literal anywhere that ships.
	 */
	public function test_no_image_png_is_no_longer_referenced_as_a_url(): void {
		foreach ( $this->shipped_source_files() as $file ) {
			$source = (string) file_get_contents( $file );

			// Comments explaining the history are fine; a live string is not.
			$source = preg_replace( '#^\s*(//|\*|/\*).*$#m', '', $source ) ?? $source;

			$this->assertDoesNotMatchRegularExpression(
				'#[\'"]assets/images/no-image\.png[\'"]#',
				$source,
				str_replace( self::PLUGIN_ROOT, '', $file ) . ' hardcodes a placeholder filename this plugin does not ship.'
			);
		}
	}

	/**
	 * The booking form must hand the placeholder to its script, because
	 * booking-form.js can no longer build the path itself.
	 */
	public function test_booking_form_localizes_the_placeholder(): void {
		$method = new \ReflectionMethod( \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::class, 'get_localized_data' );
		$method->setAccessible( true );

		$data = $method->invoke( null );

		$this->assertArrayHasKey( 'placeholder_image', $data );
		$this->assertSame( VehicleDataHelper::get_placeholder_image_url(), $data['placeholder_image'] );
	}

	/**
	 * @return list<string>
	 */
	private function shipped_source_files(): array {
		$roots = array(
			self::PLUGIN_ROOT . 'src',
			self::PLUGIN_ROOT . 'templates',
			self::PLUGIN_ROOT . 'assets/js',
			self::PLUGIN_ROOT . 'assets/blocks',
		);

		$files = array();

		foreach ( $roots as $root ) {
			if ( ! is_dir( $root ) ) {
				continue;
			}

			$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );

			foreach ( $iterator as $entry ) {
				if ( ! $entry->isFile() ) {
					continue;
				}
				if ( ! in_array( strtolower( $entry->getExtension() ), array( 'php', 'js' ), true ) ) {
					continue;
				}
				$files[] = $entry->getPathname();
			}
		}

		return $files;
	}
}
