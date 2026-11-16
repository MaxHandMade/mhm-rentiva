<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use MHMRentiva\Admin\Emails\Core\Templates;
use MHMRentiva\Admin\Emails\Templates\BookingNotifications;
use MHMRentiva\Admin\Settings\Groups\EmailSettings;
use WP_UnitTestCase;

/**
 * PR-C -- the e-mail a customer gets when a booking is made says it was
 * RECEIVED, not confirmed.
 *
 * Customer walkthrough (11-11): a booking still waiting for its payment got
 * "Booking Confirmed / Rezervasyon onaylandı". The booking is confirmed when
 * the payment lands, and the status e-mail of that change says so in its
 * body ("Confirmed").
 *
 * Saving the e-mail settings screen writes the subject field as shown, so a
 * site that saved it before this change holds the old default as if an
 * administrator had typed it. That exact old default (English or the shipped
 * Turkish) counts as not saved -- on the screen and in the e-mail alike.
 *
 * @covers \MHMRentiva\Admin\Settings\Groups\EmailSettings::default_subject
 * @covers \MHMRentiva\Admin\Settings\Groups\EmailSettings::is_legacy_default_value
 */
final class BookingReceivedSubjectTest extends WP_UnitTestCase
{
	private const CTX = array(
		'booking'  => array( 'id' => 42, 'order_id' => 42 ),
		'customer' => array( 'name' => 'Ali' ),
		'site'     => array( 'name' => 'Site' ),
	);

	public function tearDown(): void
	{
		delete_option('mhmrentiva_booking_created_subject');
		parent::tearDown();
	}

	public function test_the_booking_e_mail_says_received_not_confirmed(): void
	{
		$subject = Templates::compile_subject('booking_created_customer', self::CTX);

		$this->assertStringContainsString('Received', $subject);
		$this->assertStringNotContainsString('Confirmed', $subject);
	}

	/** @dataProvider legacy_defaults */
	public function test_a_saved_old_default_is_read_as_not_saved( string $saved ): void
	{
		update_option('mhmrentiva_booking_created_subject', $saved);

		$this->assertStringContainsString('Received', Templates::compile_subject('booking_created_customer', self::CTX), 'The e-mail.');

		ob_start();
		BookingNotifications::render();
		$screen = (string) ob_get_clean();

		$this->assertStringContainsString('value="' . esc_attr(EmailSettings::default_subject('booking_created_customer')) . '"', $screen, 'The screen shows what goes out.');
		$this->assertStringNotContainsString(esc_attr($saved), $screen);
	}

	/** @return array<string, array{0: string}> */
	public static function legacy_defaults(): array
	{
		return array(
			'English'          => array( 'Booking Confirmed: #{booking_id}' ),
			'shipped Turkish'  => array( 'Rezervasyon onaylandı: #{booking_id}' ),
			'stray whitespace' => array( '  Booking Confirmed: #{booking_id} ' ),
		);
	}

	public function test_a_subject_the_administrator_wrote_is_kept(): void
	{
		update_option('mhmrentiva_booking_created_subject', 'Your booking at {site_name}: confirmed!');

		$this->assertSame('Your booking at Site: confirmed!', Templates::compile_subject('booking_created_customer', self::CTX));
	}
}
