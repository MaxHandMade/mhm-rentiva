<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use WP_UnitTestCase;

/**
 * Pre-submission privacy audit (2026-08-09): the wp_ajax_mhmrentiva_update_booking
 * handler read post_ID from the request, verified the classic-editor nonce and
 * current_user_can( 'edit_post', $post_id ) against THAT id, then wrote customer
 * PII (name/email/phone) to a DIFFERENT request field, booking_id. The checked
 * object and the mutated object were not the same, so any role with edit_posts
 * (an author or contributor editing one of their OWN ordinary posts, whose
 * post_ID passes both the nonce and the capability check) could overwrite the
 * customer contact details of any booking by naming it in booking_id -- a
 * classic IDOR.
 *
 * The handler had no caller: the live edit path is the classic-editor save
 * (BookingMeta::save_meta / BookingEditMetaBox::save_booking_details), and no
 * JS or server-rendered form ever posted action=mhmrentiva_update_booking. It
 * was a dead duplicate reachable only by a crafted admin-ajax POST. The shape is
 * eliminated by removing the registration entirely rather than re-checking it,
 * so the endpoint no longer exists.
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta::register
 */
final class UpdateBookingEndpointRemovedTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();

		// register() is guarded by a static flag and the plugin bootstrap already
		// tripped it, so a plain call would be a no-op. Reset the flag and clear
		// the two hooks this test observes, then run register() fresh, so the
		// assertions read a deterministic registration produced by the current
		// source in this test -- independent of suite order and the test
		// framework's hook backup/restore.
		$flag = new \ReflectionProperty( BookingMeta::class, 'registered' );
		$flag->setAccessible( true );
		$flag->setValue( null, false );

		remove_all_actions( 'wp_ajax_mhmrentiva_update_booking' );
		remove_all_actions( 'wp_ajax_mhmrentiva_send_customer_email' );

		BookingMeta::register();
	}

	/**
	 * The IDOR endpoint must not be wired to admin-ajax any more.
	 */
	public function test_update_booking_ajax_action_is_not_registered(): void
	{
		$this->assertFalse(
			has_action( 'wp_ajax_mhmrentiva_update_booking' ),
			'The dead, IDOR-prone mhmrentiva_update_booking admin-ajax endpoint must not be registered.'
		);
	}

	/**
	 * Non-vacuous guard: prove the class really does wire admin-ajax handlers, so
	 * the assertion above is a real absence and not the result of register()
	 * never having run. A sibling AJAX handler on the same class must still be
	 * registered.
	 */
	public function test_register_still_wires_the_sibling_email_handler(): void
	{
		$this->assertNotFalse(
			has_action( 'wp_ajax_mhmrentiva_send_customer_email' ),
			'The class must still wire its other AJAX handlers -- otherwise the removal test is vacuous.'
		);
	}
}
