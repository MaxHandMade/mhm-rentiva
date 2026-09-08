<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails\Core;

use MHMRentiva\Admin\Emails\Core\EmailTemplates;
use WP_UnitTestCase;

/**
 * T8 Görev 11 Part 1 (independent nonce-behavior audit, Fable#2 Minor-1): the
 * LIVE localize payload EmailTemplates::enqueue_scripts() attaches for the
 * Settings -> Email Templates tab (the only reachable renderer since Görev
 * 10b deleted render_page()/render_standalone_page(); the survivor is
 * render_content_only(), called from TabRendererRegistry) never carried a
 * `strings` sub-array -- yet assets/js/admin/email-templates.js reads ten
 * distinct `mhmrentiva_email_templates_vars.strings.*` leaves, each guarded
 * by `(vars.strings && vars.strings.x) || 'English literal'`. With `strings`
 * undefined, every one of those reads silently takes the English fallback --
 * no console error, no visible symptom, just a translation that can never
 * appear regardless of site locale.
 *
 * A prior commit (c7a508a6) claimed the live payload "localizes a SUPERSET"
 * of a dead AssetManager.php duplicate it was deleting (that duplicate DID
 * carry `strings`) because the live payload has two fields the duplicate
 * lacked (admin_post_url, send_test_nonce) -- true, but only a partial
 * superset: the live payload was simultaneously missing all ten `strings.*`
 * leaves the duplicate had. This test enumerates every field
 * email-templates.js actually reads (top-level leaves directly, `strings.*`
 * leaves via dot-path) so the missing sub-array fails loudly instead of
 * degrading silently.
 *
 * 🔴 The inventory is DERIVED, not transcribed. An earlier version of this test
 * listed the fields by hand -- with line numbers -- under the heading "grepped
 * directly from assets/js/...". Those line numbers were stale within two
 * commits, and when the dead half of that script was deleted the list went on
 * demanding a `nonce` key nothing read any more, turning a correct deletion
 * into a red suite. A contract with a file must be read FROM that file.
 *
 * `auto_refresh` (:664) is deliberately NOT in this contract: it is read
 * behind `typeof vars !== 'undefined' && vars.auto_refresh` and, even when
 * true, drives an empty setInterval body -- functionally inert, and it was
 * never present in either historical payload (the deleted AssetManager.php
 * duplicate or the live one). Asserting it would fail against the reference
 * payload this fix is restoring, for a field with no observable behaviour.
 *
 * @covers \MHMRentiva\Admin\Emails\Core\EmailTemplates::enqueue_scripts
 */
final class EmailTemplatesPayloadContractTest extends WP_UnitTestCase {

	private const HOOK   = 'mhm-rentiva_page_mhm-rentiva-settings';
	private const HANDLE = 'mhm-rentiva-email-templates';

	protected function setUp(): void {
		parent::setUp();
		$this->reset_handle();
	}

	protected function tearDown(): void {
		$this->reset_handle();
		parent::tearDown();
	}

	private function reset_handle(): void {
		wp_dequeue_script( self::HANDLE );
		wp_deregister_script( self::HANDLE );
	}

	/**
	 * The script this payload exists for.
	 */
	private static function script_path(): string {
		return dirname( __DIR__, 4 ) . '/assets/js/admin/email-templates.js';
	}

	/**
	 * Every `mhmrentiva_email_templates_vars.<path>` the script actually reads.
	 *
	 * Read out of the file rather than listed here, so deleting a script that
	 * stops reading a field shrinks this contract by itself and adding a read
	 * extends it. `auto_refresh` is the one deliberate exclusion: it is guarded
	 * by `typeof vars !== 'undefined' && vars.auto_refresh` and, even when true,
	 * drives an empty setInterval body -- inert, and absent from every payload
	 * this plugin has ever localized.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function payload_field_provider(): array {
		$source = (string) file_get_contents( self::script_path() );

		preg_match_all(
			'/mhmrentiva_email_templates_vars\.([A-Za-z_][A-Za-z0-9_]*)(?:\.([A-Za-z_][A-Za-z0-9_]*))?/',
			$source,
			$matches,
			PREG_SET_ORDER
		);

		$leaves = array();
		$parents = array();
		foreach ( $matches as $m ) {
			$top = $m[1];
			if ( 'auto_refresh' === $top ) {
				continue;
			}
			if ( isset( $m[2] ) && '' !== $m[2] ) {
				$leaves[ $top . '.' . $m[2] ] = true;
				$parents[ $top ]              = true;
				continue;
			}
			$leaves[ $top ] = true;
		}

		// A branch that has leaves is not itself a leaf: the script reads
		// `vars.strings.cancel`, and also `vars.strings` as the guard in front of
		// it. Asserting the guard would be asserting the same thing twice.
		foreach ( array_keys( $parents ) as $parent ) {
			unset( $leaves[ $parent ] );
		}

		ksort( $leaves );

		$cases = array();
		foreach ( array_keys( $leaves ) as $path ) {
			$cases[ $path ] = array( $path );
		}

		return $cases;
	}

	/**
	 * 🔴 A derived provider that finds nothing passes every test after it
	 * without reading a single field -- the quietest way for this contract to
	 * stop meaning anything. Pin the extraction itself.
	 */
	public function test_the_field_inventory_is_actually_extracted(): void {
		$this->assertFileExists( self::script_path(), 'The script this test is a contract with has moved.' );

		$fields = $this->payload_field_provider();

		// Not a threshold: a number here would be the same staleness this test just
		// removed -- it would have to be re-tuned every time the script changes.
		// Emptiness is the failure mode worth naming, and the named fields below
		// are what make a non-empty result meaningful.
		$this->assertNotEmpty(
			$fields,
			'The inventory parser found nothing, which makes the provider below vacuous rather than satisfied.'
		);
		$this->assertArrayHasKey( 'ajax_url', $fields, 'The parser missed a top-level field the script certainly reads.' );
		$this->assertArrayHasKey( 'send_test_nonce', $fields, 'The parser missed the live test-email nonce.' );
		$this->assertArrayNotHasKey( 'strings', $fields, 'A branch with leaves was asserted as a leaf.' );
		$this->assertArrayNotHasKey( 'auto_refresh', $fields, 'The documented exclusion leaked back in.' );
	}

	/**
	 * @dataProvider payload_field_provider
	 */
	public function test_localized_payload_has_every_field_the_js_reads( string $field_path ): void {
		$payload = $this->get_localized_payload();

		$segments = explode( '.', $field_path );
		$value    = $payload;
		foreach ( $segments as $segment ) {
			$this->assertIsArray(
				$value,
				"Cannot descend into 'mhmrentiva_email_templates_vars.$field_path': '$segment's parent is not an array."
			);
			$this->assertArrayHasKey(
				$segment,
				$value,
				"email-templates.js reads mhmrentiva_email_templates_vars.$field_path, but the live enqueue_scripts() payload is missing it."
			);
			$value = $value[ $segment ];
		}

		$this->assertNotSame( '', $value, "mhmrentiva_email_templates_vars.$field_path must not be empty." );
	}

	/**
	 * Positive control: a payload assembled from nothing at all would satisfy
	 * every assertion above vacuously if get_localized_payload() itself were
	 * broken. Pin that the handle is really enqueued and the payload is a
	 * non-empty array before trusting the per-field cases.
	 */
	public function test_premise_the_script_is_enqueued_with_a_non_empty_payload(): void {
		$payload = $this->get_localized_payload();

		$this->assertTrue( wp_script_is( self::HANDLE, 'enqueued' ) );
		$this->assertNotSame( array(), $payload );
	}

	/**
	 * @return array<mixed>
	 */
	private function get_localized_payload(): array {
		EmailTemplates::enqueue_scripts( self::HOOK );

		$raw = wp_scripts()->get_data( self::HANDLE, 'data' );
		$this->assertIsString( $raw, 'Premise: enqueue_scripts() must localize data onto the handle.' );

		$this->assertMatchesRegularExpression( '/var mhmrentiva_email_templates_vars = (\{.*\});/', $raw );
		preg_match( '/var mhmrentiva_email_templates_vars = (\{.*\});/', $raw, $matches );

		$payload = json_decode( $matches[1], true );
		$this->assertIsArray( $payload );

		return $payload;
	}
}
