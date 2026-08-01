<?php

namespace MHMRentiva\Tests\Integration\Admin\Ajax;

use MHMRentiva\Admin\Frontend\Shortcodes\ContactForm;
use WP_Ajax_UnitTestCase;

/**
 * Locks the T7 nonce-first fix: ContactForm's AJAX submit endpoint must
 * reject a missing/invalid nonce as its literal first action, before any
 * $_POST or $_FILES field is read/sanitized/validated.
 *
 * (The separate mhm_rentiva_upload_attachment endpoint this file used to
 * also cover was deleted in a follow-up fix round -- nothing in this plugin
 * or Pro ever called it; the attachment field rides along in the same
 * submit request's multipart $_FILES instead. See ajax_submit_contact_form()
 * and its own $_FILES handling.)
 *
 * `test_submit_rejects_before_touching_fields_when_nonce_missing` deliberately
 * submits an invalid email address alongside the missing nonce. If the nonce
 * check ever moved after the field-sanitization/validation step again, the
 * response would surface a "please enter a valid email" validation error
 * instead of "Security check failed" -- that is what makes this a real lock
 * on ordering, not just a lock on "the endpoint checks a nonce somewhere".
 */
class ContactFormAjaxTest extends WP_Ajax_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		ContactForm::register();
	}

	public function tearDown(): void {
		unset( $_POST['nonce'], $_POST['name'], $_POST['email'], $_POST['message'], $_POST['type'] );
		unset( $_FILES['attachment'] );
		parent::tearDown();
	}

	public function test_submit_rejects_before_touching_fields_when_nonce_missing(): void {
		unset( $_POST['nonce'] );
		$_POST['action']  = 'mhm_rentiva_submit_contact_form';
		$_POST['name']    = 'Test User';
		// Deliberately invalid: if fields were ever sanitized/validated before
		// the nonce check, this would surface as an email-validation error
		// instead of "Security check failed".
		$_POST['email']   = 'not-an-email';
		$_POST['message'] = 'Hello';

		$this->dispatch_ajax( 'mhm_rentiva_submit_contact_form' );
		$response = $this->decode_response();

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Security check failed', $response['data']['message'] );
	}

	public function test_submit_rejects_invalid_nonce_before_touching_fields(): void {
		$_POST['action']  = 'mhm_rentiva_submit_contact_form';
		$_POST['nonce']   = 'clearly-invalid-nonce';
		$_POST['name']    = 'Test User';
		$_POST['email']   = 'not-an-email';
		$_POST['message'] = 'Hello';

		$this->dispatch_ajax( 'mhm_rentiva_submit_contact_form' );
		$response = $this->decode_response();

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Security check failed', $response['data']['message'] );
	}

	private function dispatch_ajax( string $action ): void {
		try {
			$this->_handleAjax( $action );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected path for WP_Ajax_UnitTestCase.
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private function decode_response(): array {
		$raw     = trim( (string) $this->_last_response );
		$decoded = json_decode( $raw, true );

		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		$json_start = strpos( $raw, '{"success"' );
		if ( false === $json_start ) {
			$json_start = strpos( $raw, '{' );
		}

		if ( false !== $json_start ) {
			$decoded = json_decode( substr( $raw, $json_start ), true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		$this->fail( 'Unable to decode AJAX response as JSON: ' . $raw );
	}
}
