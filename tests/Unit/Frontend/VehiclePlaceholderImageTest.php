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
	 * RESOLVE -- it must name a file that is on disk, never a URL to something
	 * that is not shipped.
	 */
	public function test_placeholder_url_resolves_to_a_file_that_exists(): void {
		$url = VehicleDataHelper::get_placeholder_image_url();

		$relative = ltrim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		$filename = basename( $relative );

		$this->assertFileExists(
			self::PLUGIN_ROOT . 'assets/images/' . $filename,
			'A placeholder URL pointing at a file means that file must ship.'
		);
	}

	/**
	 * THE REGRESSION LOCK for the defect the browser tour caught.
	 *
	 * Every PHP exit point prints this through esc_url(), and esc_url() drops
	 * any scheme that is not in wp_allowed_protocols() -- `data` is not, and
	 * never has been. So the inline SVG data URI the helper used to fall back
	 * to was ERASED on the way to the page: measured in the container, 318
	 * bytes in, 0 bytes out, i.e. `<img src="">`. The 404 was gone and the
	 * image was still broken.
	 *
	 * The fix is a real file, not weaker escaping: esc_url() stays exactly as
	 * strict as it is, and the helper's candidate list -- which already probed
	 * for `placeholder-vehicle.svg` -- is finally given something to find.
	 *
	 * This test is what makes deleting that file loud: with no candidate on
	 * disk the helper returns the data URI again, esc_url() returns '', and
	 * this goes red.
	 */
	public function test_the_placeholder_url_survives_esc_url_intact(): void {
		$raw     = VehicleDataHelper::get_placeholder_image_url();
		$escaped = esc_url( $raw );

		$this->assertNotSame( '', $escaped, 'esc_url() emptied the placeholder -- the exit points would render <img src="">.' );
		$this->assertSame( $raw, $escaped, 'esc_url() must pass the placeholder through unchanged, not merely non-empty.' );
		$this->assertNotContains( 'data', array( (string) wp_parse_url( $raw, PHP_URL_SCHEME ) ), 'A data: URI cannot reach the page through esc_url().' );
		$this->assertContains(
			(string) wp_parse_url( $raw, PHP_URL_SCHEME ),
			wp_allowed_protocols(),
			'The placeholder scheme must be one WordPress allows through esc_url().'
		);
	}

	/**
	 * The shipped candidate itself: present, small, self-contained, and real
	 * SVG. "Self-contained" is the load-bearing one -- an external reference in
	 * a placeholder would be a third-party request on a page that is only
	 * showing a grey card.
	 */
	public function test_the_shipped_placeholder_file_is_a_self_contained_svg(): void {
		$path = self::PLUGIN_ROOT . 'assets/images/placeholder-vehicle.svg';

		$this->assertFileExists( $path );

		$svg = (string) file_get_contents( $path );

		$this->assertLessThan( 8192, strlen( $svg ), 'The placeholder must stay small; it ships on every install.' );
		$this->assertStringContainsString( '<svg', $svg );
		$this->assertStringContainsString( 'xmlns="http://www.w3.org/2000/svg"', $svg );

		foreach ( array( '<script', '<image', 'xlink:href', '@font-face', '<foreignObject', '<use ' ) as $forbidden ) {
			$this->assertStringNotContainsStringIgnoringCase( $forbidden, $svg, 'The placeholder must not reference or execute anything external.' );
		}

		// The only URL allowed inside it is the SVG namespace itself.
		preg_match_all( '#https?://[^"\'\s]+#', $svg, $urls );
		$this->assertSame(
			array( 'http://www.w3.org/2000/svg' ),
			array_values( array_unique( $urls[0] ) ),
			'The only URL in the placeholder must be the SVG XML namespace.'
		);

		$previous = libxml_use_internal_errors( true );
		$doc      = new \DOMDocument();
		$parsed   = $doc->loadXML( $svg );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$this->assertTrue( $parsed, 'The placeholder must be valid standalone XML.' );
		$this->assertSame( 'svg', $doc->documentElement->tagName );
	}

	/**
	 * The inline data URI stays in the code as a last-resort fallback, so it
	 * must remain valid -- but it is no longer what any PHP exit point prints
	 * (see the esc_url lock above).
	 */
	public function test_the_inline_fallback_is_still_valid_svg(): void {
		$reflection = new \ReflectionClass( VehicleDataHelper::class );
		$uri        = (string) $reflection->getConstant( 'PLACEHOLDER_IMAGE_DATA_URI' );

		$this->assertStringStartsWith( 'data:image/svg+xml;base64,', $uri );

		$decoded = base64_decode( substr( $uri, strpos( $uri, ',' ) + 1 ), true );

		$this->assertIsString( $decoded );
		$this->assertStringContainsString( '<svg', (string) $decoded );
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
	 * THE CLASS GATE, not the three cited lines. Shipped source is scanned for
	 * a direct `assets/images/<name>` URL construction, and each named file
	 * must exist.
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
	 *
	 * NOT CHECKED BY THIS GATE -- declared, in the house style, because a probe
	 * that does not state its blind spots gets read as covering everything.
	 * All of these were MEASURED to hold zero `assets/images` references at the
	 * time this gate was written, so none is a live miss; they are prospective:
	 *   - roots outside src/, templates/, assets/js and assets/blocks -- so
	 *     build/admin/*.js (the compiled React bundles), src-react/**, and
	 *     assets/vendor/*.js are unscanned, as are the two root PHP files
	 *     (mhm-rentiva.php, uninstall.php)
	 *   - file types other than .php and .js -- notably a CSS `url()` pointing
	 *     into assets/images/
	 *   - URL shapes other than the two above: plugins_url(),
	 *     plugin_dir_url(), or any construction that assembles the filename
	 *     from a variable
	 *   - whether a file that DOES exist is the right image; only existence is
	 *     asserted
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

		// The declared blind spots above are only honest while they stay empty.
		// Measure them here rather than asserting they are irrelevant: if a
		// future change puts an assets/images reference into build/admin,
		// src-react, assets/vendor or either root PHP file, this fails and
		// says so, instead of the gate silently not covering it.
		$this->assertSame(
			array(),
			$this->references_in_unscanned_roots(),
			'A declared blind spot stopped being empty -- either extend the gate or move the reference.'
		);

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
	 * Any `assets/images` mention living in a root or a file type the main
	 * gate does not walk. Keeps the declared blind spots measured rather than
	 * assumed. Matches the bare path substring on purpose -- this is a tripwire,
	 * not a URL parser, so it is allowed to be broader than the gate itself.
	 *
	 * @return list<string>
	 */
	private function references_in_unscanned_roots(): array {
		$hits = array();

		// `build/admin` and NOT `build`: `bin/build-release.py --list-shipped`
		// reports build/admin as the only shipped path under build/, because
		// .distignore excludes build/zip-staging -- which holds a full COPY of
		// the plugin from whenever the ZIP was last staged. Scanning all of
		// build/ makes this tripwire fire on a stale staging directory, which
		// is a real thing to fix but not a defect in the shipped tree, and not
		// something a test should depend on the state of.
		$roots = array(
			self::PLUGIN_ROOT . 'build/admin',
			self::PLUGIN_ROOT . 'src-react',
			self::PLUGIN_ROOT . 'assets/vendor',
			self::PLUGIN_ROOT . 'assets/css',
		);

		foreach ( $roots as $root ) {
			if ( ! is_dir( $root ) ) {
				continue;
			}

			$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );

			foreach ( $iterator as $entry ) {
				if ( ! $entry->isFile() ) {
					continue;
				}
				if ( ! in_array( strtolower( $entry->getExtension() ), array( 'php', 'js', 'jsx', 'css' ), true ) ) {
					continue;
				}
				if ( false !== strpos( (string) file_get_contents( $entry->getPathname() ), 'assets/images' ) ) {
					$hits[] = str_replace( self::PLUGIN_ROOT, '', $entry->getPathname() );
				}
			}
		}

		foreach ( array( 'mhm-rentiva.php', 'uninstall.php' ) as $rootFile ) {
			$path = self::PLUGIN_ROOT . $rootFile;
			if ( is_file( $path ) && false !== strpos( (string) file_get_contents( $path ), 'assets/images' ) ) {
				$hits[] = $rootFile;
			}
		}

		sort( $hits );

		return $hits;
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
