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
 * Field inventory (grepped directly from assets/js/admin/email-templates.js):
 *   ajax_url          :156,268,294,326,523,576   admin_post_url    :438
 *   nonce             :162,273,301,331            send_test_nonce  :439
 *   preview_email     :53                         send_test        :135
 *   processing        :152,290,515,572             test_email_sent :166
 *   test_email_failed :169                         error_occurred  :173,310,314,339,343
 *   strings.enterEmail:102        strings.sendTestEmail:123   strings.emailAddress:124
 *   strings.cancel    :125,229    strings.editTemplate  :225  strings.subject     :226
 *   strings.content   :227        strings.save          :228  strings.templateSaved:305
 *   strings.templateReset:335
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
	 * @return array<string, array{0: string}>
	 */
	public function payload_field_provider(): array {
		return array(
			'ajax_url'               => array( 'ajax_url' ),
			'admin_post_url'         => array( 'admin_post_url' ),
			'nonce'                  => array( 'nonce' ),
			'send_test_nonce'        => array( 'send_test_nonce' ),
			'preview_email'          => array( 'preview_email' ),
			'send_test'              => array( 'send_test' ),
			'test_email_sent'        => array( 'test_email_sent' ),
			'test_email_failed'      => array( 'test_email_failed' ),
			'processing'             => array( 'processing' ),
			'error_occurred'         => array( 'error_occurred' ),
			'strings.enterEmail'     => array( 'strings.enterEmail' ),
			'strings.sendTestEmail'  => array( 'strings.sendTestEmail' ),
			'strings.emailAddress'   => array( 'strings.emailAddress' ),
			'strings.cancel'         => array( 'strings.cancel' ),
			'strings.editTemplate'   => array( 'strings.editTemplate' ),
			'strings.subject'        => array( 'strings.subject' ),
			'strings.content'        => array( 'strings.content' ),
			'strings.save'           => array( 'strings.save' ),
			'strings.templateSaved'  => array( 'strings.templateSaved' ),
			'strings.templateReset'  => array( 'strings.templateReset' ),
		);
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
