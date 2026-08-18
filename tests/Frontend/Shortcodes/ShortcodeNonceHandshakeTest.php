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
 *  - There are two ways a shortcode hands a token to JavaScript, and the
 *    measurement differs per class, so each is reported by name:
 *      BEHAVIOURAL -- the class rides the parent's `localize_script()` pipeline,
 *      so `get_localized_data()['nonce']` is the token the page prints and it
 *      is verified for real with `wp_verify_nonce()`.
 *      SOURCE      -- the class writes its own `wp_localize_script()` call
 *      (SearchResults does), so the parent's data is not what reaches the page;
 *      the weaker check is that the file mints a token for the action it
 *      verifies. This catches a missing producer, not a mismatched one.
 *  - A handler living in another class, an Elementor widget, a block, or a
 *    token printed by `wp_nonce_field()` inside a template is OUTSIDE the
 *    start set. This test says nothing about those surfaces.
 *  - Classes that verify no nonce are skipped, and the count of skipped and
 *    of checked classes is asserted, so a scan that silently degrades to
 *    "nothing to check" fails instead of reporting green.
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

			$mints = array();

			if ( preg_match_all( "/wp_create_nonce\(\s*'([^']+)'/", $source, $m ) ) {
				$mints = array_merge( $mints, $m[1] );
			}

			if ( preg_match_all( "/wp_nonce_field\(\s*'([^']+)'/", $source, $m ) ) {
				$mints = array_merge( $mints, $m[1] );
			}

			$found[ $class ] = array(
				'actions'      => $actions,
				'mints'        => array_values( array_unique( $mints ) ),
				'own_localize' => str_contains( $source, 'wp_localize_script(' ),
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

			// A class that writes its own wp_localize_script() does not hand the
			// parent's data to the page, so the behavioural check would measure
			// a token nobody prints. Fall back to the weaker source check and
			// say so in the tally.
			if ( $facts['own_localize'] ) {
				$this->assertNotEmpty(
					array_intersect( $facts['mints'], $actions ),
					sprintf(
						'%s localises its own script but mints no token for the action(s) it verifies (%s), '
						. 'so this surface can only fail closed.',
						$class,
						implode( ', ', $actions )
					)
				);

				++$checked;
				continue;
			}

			if ( ! method_exists( $class, 'get_localized_data' ) ) {
				$skipped[] = $class . ' (no get_localized_data)';
				continue;
			}

			$method = new ReflectionMethod( $class, 'get_localized_data' );
			$method->setAccessible( true );

			try {
				$data = (array) $method->invoke( null );
			} catch ( \Throwable $e ) {
				$skipped[] = $class . ' (' . $e->getMessage() . ')';
				continue;
			}

			if ( ! isset( $data['nonce'] ) || ! is_string( $data['nonce'] ) ) {
				$skipped[] = $class . ' (localises no nonce)';
				continue;
			}

			$opens_one = false;

			foreach ( $actions as $action ) {
				if ( false !== wp_verify_nonce( $data['nonce'], $action ) ) {
					$opens_one = true;
					break;
				}
			}

			$this->assertTrue(
				$opens_one,
				sprintf(
					'%s prints a nonce that opens none of the actions it verifies (%s). '
					. 'The token and the check have drifted apart, so this surface fails closed.',
					$class,
					implode( ', ', $actions )
				)
			);

			++$checked;
			++$behavioural;
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
	}
}
