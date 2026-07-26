<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Tools;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Every handle this plugin names as a dependency has to be registered somewhere.
 *
 * `wp_enqueue_script( 'x', $src, array( 'y' ) )` with an unregistered `y` does
 * not warn, does not throw and does not stop the page: WordPress simply declines
 * to output `x`. The screen renders without its behaviour and the only symptom
 * is a feature that quietly does nothing — which is why a renamed handle is one
 * of the few changes that can break the product without failing a test or
 * showing an error.
 *
 * The check is static because the alternative — loading every admin screen —
 * only ever covers the screens someone remembered to open.
 */
final class ScriptDependenciesResolveTest extends TestCase
{
	/**
	 * Handles WordPress itself registers, plus the ones WooCommerce and Elementor
	 * provide on the screens this plugin extends.
	 *
	 * @var list<string>
	 */
	private const EXTERNAL = array(
		'jquery',
		'jquery-core',
		'jquery-migrate',
		'jquery-ui-core',
		'jquery-ui-datepicker',
		'jquery-ui-sortable',
		'jquery-ui-draggable',
		'jquery-ui-dialog',
		'jquery-ui-autocomplete',
		'jquery-ui-tooltip',
		'wp-i18n',
		'wp-api-fetch',
		'wp-element',
		'wp-components',
		'wp-blocks',
		'wp-block-editor',
		'wp-editor',
		'wp-data',
		'wp-hooks',
		'wp-util',
		'wp-color-picker',
		'wp-pointer',
		'wp-tinymce',
		'thickbox',
		'media-upload',
		'media-editor',
		'media-views',
		'underscore',
		'backbone',
		'dashicons',
		'common',
		'wp-admin',
		'editor-buttons',
		'woocommerce',
		'wc-blocks-checkout',
		'wc-settings',
		'elementor-frontend',
		'elementor-editor',
	);

	/**
	 * Both trees: the add-on registers handles Lite depends on and the reverse.
	 *
	 * @return list<string>
	 */
	private function roots(): array
	{
		$lite = dirname( __DIR__, 2 );
		$pro  = dirname( $lite ) . '/mhm-rentiva-pro';

		return is_dir( $pro ) ? array( $lite, $pro ) : array( $lite );
	}

	/**
	 * @return array{registered: list<string>, required: array<string, list<string>>}
	 */
	private function collect(): array
	{
		$registered = array();
		$required   = array();

		foreach ( $this->roots() as $root ) {
			foreach ( array( '/src', '/templates' ) as $dir ) {
				if ( ! is_dir( $root . $dir ) ) {
					continue;
				}

				$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . $dir ) );

				foreach ( $it as $file ) {
					if ( 'php' !== $file->getExtension() ) {
						continue;
					}

					$code  = (string) file_get_contents( $file->getPathname() );
					$label = basename( $root ) . '/' . str_replace( $root . DIRECTORY_SEPARATOR, '', $file->getPathname() );

					// Handles declared as the KEY of a registration config array and
					// enqueued by a loop over it (AssetManager's STYLES/SCRIPTS maps).
					// A call-site regex cannot see those -- the same blind spot that
					// let a bare transient prefix live in a class constant.
					if ( preg_match_all(
						'/^\s*[\'"]([a-z0-9_\-]+)[\'"]\s*=>\s*array\(\s*$/im',
						$code,
						$km
					) ) {
						foreach ( $km[1] as $handle ) {
							$registered[] = $handle;
						}
					}

					// First argument of a register/enqueue call.
					if ( preg_match_all(
						'/wp_(?:register|enqueue)_(?:script|style)\s*\(\s*[\'"]([a-z0-9_\-]+)[\'"]/i',
						$code,
						$m
					) ) {
						foreach ( $m[1] as $handle ) {
							$registered[] = $handle;
						}
					}

					// Dependency arrays: the third argument of the same calls.
					if ( preg_match_all(
						'/wp_(?:register|enqueue)_(?:script|style)\s*\(\s*[\'"][a-z0-9_\-]+[\'"]\s*,[^,]*,\s*array\(([^)]*)\)/is',
						$code,
						$dm
					) ) {
						foreach ( $dm[1] as $deps ) {
							if ( preg_match_all( '/[\'"]([a-z0-9_\-]+)[\'"]/i', $deps, $handles ) ) {
								foreach ( $handles[1] as $handle ) {
									$required[ $handle ][] = $label;
								}
							}
						}
					}
				}
			}
		}

		return array(
			'registered' => array_values( array_unique( $registered ) ),
			'required'   => $required,
		);
	}

	public function test_every_declared_dependency_is_registered_somewhere(): void
	{
		$collected  = $this->collect();
		$registered = $collected['registered'];
		$unresolved = array();

		foreach ( $collected['required'] as $handle => $sites ) {
			if ( in_array( $handle, $registered, true ) || in_array( $handle, self::EXTERNAL, true ) ) {
				continue;
			}

			$unresolved[] = $handle . '  (' . implode( ', ', array_slice( array_unique( $sites ), 0, 3 ) ) . ')';
		}

		sort( $unresolved );

		$this->assertSame(
			array(),
			$unresolved,
			"These handles are named as dependencies but registered nowhere in either plugin.\n"
				. "WordPress silently declines to output the dependent asset, so the feature it\n"
				. "carries stops working with no error anywhere. Register them, correct the name,\n"
				. "or add a genuinely external handle to the EXTERNAL list:\n  "
				. implode( "\n  ", $unresolved )
		);
	}

	/**
	 * Guards the scan: a regex that stopped matching would make the assertion
	 * above pass while checking nothing.
	 */
	public function test_the_scan_finds_registrations_and_dependencies(): void
	{
		$collected = $this->collect();

		$this->assertGreaterThan( 40, count( $collected['registered'] ), 'Implausibly few registrations found.' );
		$this->assertGreaterThan( 10, count( $collected['required'] ), 'Implausibly few dependencies found.' );
	}
}
