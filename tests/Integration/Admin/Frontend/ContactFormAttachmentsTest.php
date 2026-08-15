<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Frontend;

use MHMRentiva\Admin\Frontend\Shortcodes\ContactForm;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * The contact form's e-mail must not read an undefined variable.
 *
 * send_contact_email() only ever assigned $attachments inside the branch that
 * runs when the submission HAS a file. Every submission WITHOUT one therefore
 * reached `wp_mail( ..., $attachments )` with the variable never defined: PHP 8
 * emits "Warning: Undefined variable $attachments" and passes null, which
 * WordPress then feeds to str_replace() in pluggable.php and earns a
 * deprecation on top. Measured in a browser on the release stack against the
 * built 6.0.2 ZIP -- one ordinary submission with no attachment wrote two
 * entries to debug.log.
 *
 * Why that is worth a test rather than a quiet one-line fix: the contact form
 * is a PUBLIC, unauthenticated surface, and WordPress.org's review rules
 * require zero notices with WP_DEBUG on. It is one of the first things a
 * reviewer exercises. The mail still sent, so nothing but a debug log revealed
 * it -- exactly the class of defect that survives green gates.
 */
final class ContactFormAttachmentsTest extends WP_UnitTestCase {

	/**
	 * Captured wp_mail() arguments.
	 *
	 * @var array<string,mixed>|null
	 */
	private ?array $captured = null;

	/**
	 * Invoke the private mailer with a submission shaped like the real one.
	 *
	 * @param array<string,mixed> $overrides Fields to override.
	 * @return bool
	 */
	private function send( array $overrides = array() ): bool {
		$data = array_merge(
			array(
				'type'    => 'general',
				'name'    => 'Probe Person',
				'email'   => 'probe@example.test',
				'phone'   => '',
				'message' => 'A submission with no file attached.',
			),
			$overrides
		);

		$method = new ReflectionMethod( ContactForm::class, 'send_contact_email' );
		$method->setAccessible( true );

		return (bool) $method->invoke( null, $data, 0 );
	}

	/**
	 * A submission with no attachment hands wp_mail a real array.
	 *
	 * Passing null is what produces both log entries, so asserting the type is
	 * asserting the absence of the warning -- and unlike the warning itself,
	 * the type survives whatever error-reporting the runner is configured with.
	 */
	public function test_a_submission_without_a_file_passes_an_array_of_attachments(): void {
		add_filter(
			'wp_mail',
			function ( $args ) {
				$this->captured = is_array( $args ) ? $args : null;
				return $args;
			}
		);

		$this->send();

		$this->assertNotNull( $this->captured, 'wp_mail was never reached.' );
		$this->assertArrayHasKey( 'attachments', $this->captured );
		$this->assertIsArray(
			$this->captured['attachments'],
			'Attachments must be an array even when the submission carried no file; null earns a PHP warning here and a deprecation inside pluggable.php.'
		);
		$this->assertSame(
			array(),
			$this->captured['attachments'],
			'With no file there is nothing to attach.'
		);
	}
}
