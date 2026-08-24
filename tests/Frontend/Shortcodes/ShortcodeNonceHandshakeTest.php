<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Every shortcode that checks a nonce must print the nonce it checks.
 *
 * `AbstractShortcode::get_localized_data()` mints a token from the shortcode
 * tag (`mhmrentiva_{tag}_nonce`). A shortcode whose handler verifies a
 * different action has to override that token, and all but one did: Testimonials
 * printed `mhmrentiva_rentiva_testimonials_nonce` while its endpoint verified
 * `mhmrentiva_testimonials_nonce`, so "Load More" failed closed for every
 * visitor and no test noticed, because the endpoint tests spelled the action
 * name themselves instead of taking it from the page.
 *
 * WHERE THIS TOOL STARTS -- and therefore what it cannot see:
 *  - It walks `src/Admin/Frontend/Shortcodes/*.php` and pairs each class with
 *    the nonce actions verified *in that same file*.
 *  - There are two ways a shortcode hands a token to JavaScript, and a class may
 *    use either or BOTH, so both are tried and one passing is enough:
 *      BEHAVIOURAL -- `get_localized_data()['nonce']` is checked for real with
 *      `wp_verify_nonce()` against the actions the class verifies.
 *      SOURCE      -- a token minted inside the class's own
 *      `wp_localize_script()` call (SearchResults reaches the page this way).
 *      Weaker: it sees a missing producer, not a mismatched one. Only mints
 *      inside that call count, so a second mint of the same action elsewhere in
 *      the file cannot mask a drifted payload.
 *  - A handler living in another class, an Elementor widget, a block, or a
 *    token printed by `wp_nonce_field()` inside a template is OUTSIDE the
 *    start set. This test says nothing about those surfaces.
 *  - Classes that verify no nonce are skipped. Floors are asserted on how many
 *    classes were found, measured, and measured BEHAVIOURALLY, and a ceiling on
 *    how many were skipped -- so a class that quietly starts throwing inside
 *    get_localized_data() cannot slip out of the measurement unnoticed.
 */
final class ShortcodeNonceHandshakeTest extends WP_UnitTestCase
{
	private const SHORTCODE_DIR = MHMRENTIVA_PLUGIN_DIR . 'src/Admin/Frontend/Shortcodes';

	private const NAMESPACE_PREFIX = 'MHMRentiva\\Admin\\Frontend\\Shortcodes\\';

	/**
	 * @return array<string, array{actions: list<string>, mints: list<string>, own_localize: bool}>
	 */
	private function verifiers(): array
	{
		$found = array();

		foreach ( (array) glob( self::SHORTCODE_DIR . '/*.php' ) as $path ) {
			$source = (string) file_get_contents( (string) $path );
			$class  = self::NAMESPACE_PREFIX . basename( (string) $path, '.php' );

			if ( ! class_exists( $class ) ) {
				continue;
			}

			$actions = array();

			if ( preg_match_all( "/check_ajax_referer\(\s*'([^']+)'/", $source, $m ) ) {
				$actions = array_merge( $actions, $m[1] );
			}

			if ( preg_match_all( "/wp_verify_nonce\([^,]+,\s*'([^']+)'/", $source, $m ) ) {
				$actions = array_merge( $actions, $m[1] );
			}

			$actions = array_values( array_unique( $actions ) );

			if ( array() === $actions ) {
				continue;
			}

			/*
			 * Only tokens minted INSIDE the wp_localize_script() call count. An
			 * earlier version accepted any wp_create_nonce/wp_nonce_field
			 * anywhere in the file, which let a second mint of the same action
			 * elsewhere (SearchResults prints one in a form field as well as in
			 * its script payload) keep the gate green while the payload's token
			 * drifted -- exactly the Testimonials failure this file exists for.
			 */
			$mints = array();

			if ( preg_match_all( "/wp_localize_script\s*\((?:[^;]*?)wp_create_nonce\(\s*'([^']+)'/s", $source, $m ) ) {
				$mints = array_merge( $mints, $m[1] );
			}

			// A comment mentioning the call must not move a class to the weaker
			// branch, so look for it on code lines only.
			$own_localize = false;

			foreach ( explode( "\n", $source ) as $line ) {
				$trimmed = ltrim( $line );

				if ( str_starts_with( $trimmed, '//' ) || str_starts_with( $trimmed, '*' ) || str_starts_with( $trimmed, '/*' ) ) {
					continue;
				}

				if ( str_contains( $trimmed, 'wp_localize_script(' ) ) {
					$own_localize = true;
					break;
				}
			}

			$found[ $class ] = array(
				'actions'      => $actions,
				'mints'        => array_values( array_unique( $mints ) ),
				'own_localize' => $own_localize,
			);
		}

		return $found;
	}

	public function test_the_printed_nonce_opens_the_endpoint_of_every_shortcode_that_checks_one(): void
	{
		$verifiers = $this->verifiers();

		$this->assertGreaterThanOrEqual(
			5,
			count( $verifiers ),
			'The scan found almost no nonce-checking shortcodes; it has stopped reaching the class it was aimed at.'
		);

		$checked     = 0;
		$behavioural = 0;
		$skipped     = array();

		foreach ( $verifiers as $class => $facts ) {
			$actions = $facts['actions'];

			/*
			 * A class can reach the page by EITHER route, and some use both
			 * (AvailabilityCalendar overrides the parent's data AND calls
			 * wp_localize_script itself). So both are tried and one passing is
			 * enough; an earlier version treated them as exclusive and accused
			 * a working class of being broken.
			 */
			$opens_one   = false;
			$measured_by = null;

			if ( method_exists( $class, 'get_localized_data' ) ) {
				$method = new ReflectionMethod( $class, 'get_localized_data' );
				$method->setAccessible( true );

				try {
					$data = (array) $method->invoke( null );
				} catch ( \Throwable $e ) {
					$data = array();
					$skipped[] = $class . ' (' . $e->getMessage() . ')';
				}

				if ( isset( $data['nonce'] ) && is_string( $data['nonce'] ) ) {
					foreach ( $actions as $action ) {
						if ( false !== wp_verify_nonce( $data['nonce'], $action ) ) {
							$opens_one   = true;
							$measured_by = 'behavioural';
							break;
						}
					}
				}
			}

			// Source-level fallback: a token minted inside the class's own
			// wp_localize_script() call for an action it verifies. Weaker -- it
			// sees a missing producer, not a mismatched one.
			if ( ! $opens_one && array() !== array_intersect( $facts['mints'], $actions ) ) {
				$opens_one   = true;
				$measured_by = 'source';
			}

			$this->assertTrue(
				$opens_one,
				sprintf(
					'%s prints no token that opens any action it verifies (%s). '
					. 'The token and the check have drifted apart, so this surface fails closed.',
					$class,
					implode( ', ', $actions )
				)
			);

			++$checked;

			if ( 'behavioural' === $measured_by ) {
				++$behavioural;
			}
		}

		$this->assertGreaterThanOrEqual(
			4,
			$checked,
			'Too few shortcodes were actually measured; skipped: ' . implode( ' | ', $skipped )
		);

		// The source-level fallback cannot see a mismatched token, only a
		// missing one. If every class ended up there, this gate has quietly
		// become the weaker of the two and should not read as full coverage.
		$this->assertGreaterThanOrEqual(
			2,
			$behavioural,
			'No shortcode was measured behaviourally; the gate degraded to the source-level check only.'
		);

		// A class that starts throwing inside get_localized_data() would land in
		// $skipped and vanish from the measurement silently. Cap it.
		$this->assertLessThanOrEqual(
			1,
			count( $skipped ),
			'Too many shortcodes dropped out of the measurement: ' . implode( ' | ', $skipped )
		);
	}
}
