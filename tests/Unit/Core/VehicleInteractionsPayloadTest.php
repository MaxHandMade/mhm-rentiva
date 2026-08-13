<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core;

use MHMRentiva\Admin\Core\AssetManager;
use WP_UnitTestCase;

/**
 * Regression coverage: `mhmrentiva_vars` used to have TWO definitions.
 *
 * The complete one (with the 14-string `i18n` sub-array) lived inline in
 * AssetManager::enqueue_frontend_assets(), which runs only when
 * should_load_assets() returns true -- and that check reads $post->post_content
 * for '[rentiva_' or a registered block comment. An Elementor-built page keeps
 * its content in the _elementor_data postmeta, so on those pages the check said
 * no and the payload was never emitted. vehicle-interactions.js still loaded,
 * because five shortcodes name it in their JS dependencies, and its init() bails
 * on `typeof mhmrentiva_vars === 'undefined'` -- every favourite and compare
 * button on the page silently did nothing, with a clean console.
 *
 * The second, SHORTER definition lived in SearchResults::enqueue_assets() and
 * carried no `i18n` key at all. wp_localize_script() concatenates rather than
 * replaces, so where it ran it left the JS with a payload that had ajax_url and
 * nonces but no strings: the first compare click threw `Cannot read properties
 * of undefined (reading 'adding_compare')` and left the button stuck mid-
 * optimistic-update.
 *
 * Fix: one definition, AssetManager::enqueue_vehicle_interactions(), called from
 * every site that pulls the script in. These tests pin the two properties that
 * made the bug possible -- the payload is COMPLETE, and there is exactly ONE of
 * it -- plus the shape gate that stops a second copy being reintroduced.
 *
 * @covers \MHMRentiva\Admin\Core\AssetManager::enqueue_vehicle_interactions
 */
final class VehicleInteractionsPayloadTest extends WP_UnitTestCase {

	private const HANDLE = 'mhm-rentiva-vehicle-interactions';

	protected function setUp(): void {
		parent::setUp();
		$this->reset_handle();
		// Sibling test classes deregister this handle in their own tearDown();
		// re-register so these assertions never depend on test ordering.
		AssetManager::register_common_assets();
	}

	protected function tearDown(): void {
		$this->reset_handle();
		parent::tearDown();
	}

	private function reset_handle(): void {
		if ( wp_script_is( self::HANDLE, 'enqueued' ) ) {
			wp_dequeue_script( self::HANDLE );
		}
		if ( wp_script_is( self::HANDLE, 'registered' ) ) {
			wp_deregister_script( self::HANDLE );
		}
	}

	private function attached_data(): string {
		return (string) wp_scripts()->get_data( self::HANDLE, 'data' );
	}

	/**
	 * The payload must carry its i18n strings. Without them the JS throws on the
	 * first click instead of merely mislabelling a toast.
	 */
	public function test_payload_carries_the_i18n_strings(): void {
		AssetManager::enqueue_vehicle_interactions();

		$data = $this->attached_data();

		$this->assertStringContainsString( 'mhmrentiva_vars', $data, 'The helper must localize mhmrentiva_vars.' );
		$this->assertStringContainsString( '"i18n"', $data, 'mhmrentiva_vars must carry its i18n sub-array -- vehicle-interactions.js reads it on every click.' );

		// The two keys the reported TypeErrors named, byte for byte.
		$this->assertStringContainsString( 'adding_compare', $data );
		$this->assertStringContainsString( 'adding_favorite', $data );
	}

	/**
	 * Calling it repeatedly -- which is exactly what happens when several
	 * shortcodes render on one page -- must leave ONE definition, not a race
	 * between two whose winner depends on source order.
	 */
	public function test_repeated_calls_emit_exactly_one_definition(): void {
		AssetManager::enqueue_vehicle_interactions();
		AssetManager::enqueue_vehicle_interactions();
		AssetManager::enqueue_vehicle_interactions();

		$this->assertSame(
			1,
			substr_count( $this->attached_data(), 'var mhmrentiva_vars' ),
			'Every extra definition is a chance for a shorter payload to win on source order -- the original bug.'
		);
		$this->assertTrue( wp_script_is( self::HANDLE, 'enqueued' ), 'Repeat calls must still leave the script enqueued.' );
	}

	/**
	 * Shape gate. The bug was not a typo, it was a SECOND copy of the payload;
	 * a passing behavioural test above would not stop someone adding a third.
	 * AssetManager is the only file allowed to name mhmrentiva_vars in a
	 * wp_localize_script() call.
	 */
	public function test_only_asset_manager_localizes_the_variable(): void {
		$src      = \dirname( __DIR__, 3 ) . '/src';
		$offences = array();

		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $src ) );
		foreach ( $files as $file ) {
			if ( ! $file->isFile() || $file->getExtension() !== 'php' ) {
				continue;
			}
			$path = str_replace( '\\', '/', $file->getPathname() );
			if ( str_ends_with( $path, 'src/Admin/Core/AssetManager.php' ) ) {
				continue;
			}
			// Tokenize rather than grep the raw text: the docblocks that explain
			// this bug necessarily quote both `wp_localize_script` and
			// `mhmrentiva_vars` in prose, and a naive regex reads a comment as
			// an offence. (It did, the first time this test ran.)
			if ( $this->localizes_the_variable_in_code( (string) file_get_contents( $path ) ) ) {
				$offences[] = $path;
			}
		}

		$this->assertSame(
			array(),
			$offences,
			"mhmrentiva_vars must have exactly one definition, in AssetManager::enqueue_vehicle_interactions(). Call that method instead of localizing a second copy here:\n" . implode( "\n", $offences )
		);
	}

	/**
	 * True when the source calls wp_localize_script() naming 'mhmrentiva_vars',
	 * counting only real code -- comments and docblocks are dropped first.
	 */
	private function localizes_the_variable_in_code( string $contents ): bool {
		$code = '';
		foreach ( token_get_all( $contents ) as $token ) {
			if ( is_array( $token ) ) {
				if ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
					continue;
				}
				$code .= $token[1];
				continue;
			}
			$code .= $token;
		}

		return (bool) preg_match( '/wp_localize_script\s*\([^;]*mhmrentiva_vars/s', $code );
	}
}
