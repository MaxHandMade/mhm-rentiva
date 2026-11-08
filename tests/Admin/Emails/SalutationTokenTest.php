<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Emails\Core\Templates;
use WP_UnitTestCase;

/**
 * PR 3b-3 T1: customer greetings print `salutation_name` (never empty for a
 * booking), while every other use of the name keeps `customer.name` as is.
 * A context without the key (Pro's message e-mails) falls back to the name.
 */
final class SalutationTokenTest extends WP_UnitTestCase
{
	public function test_the_token_reads_the_salutation_name(): void
	{
		$ctx = array( 'customer' => array( 'name' => '', 'salutation_name' => 'valued customer' ) );

		$this->assertSame('Dear valued customer,', Templates::replace_placeholders('Dear {salutation_name},', $ctx));
		$this->assertSame('Dear valued customer,', Templates::replace_placeholders('Dear {{customer.salutation_name}},', $ctx));
	}

	public function test_a_context_without_it_falls_back_to_the_name(): void
	{
		$ctx = array( 'customer' => array( 'name' => 'Ali Kaya' ) );

		$this->assertSame('Dear Ali Kaya,', Templates::replace_placeholders('Dear {salutation_name},', $ctx));
		$this->assertSame('Dear Ali Kaya,', Templates::replace_placeholders('Dear {{customer.salutation_name}},', $ctx));
	}

	public function test_contact_name_keeps_meaning_the_name(): void
	{
		$ctx = array( 'customer' => array( 'name' => '', 'salutation_name' => 'valued customer' ) );

		$this->assertSame('Customer: ', Templates::replace_placeholders('Customer: {contact_name}', $ctx));
	}

	public function test_a_nameless_booking_is_greeted_in_the_default_customer_body(): void
	{
		$booking = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
			'meta_input'  => array( '_mhmrentiva_pickup_date' => '2026-11-20', '_mhmrentiva_dropoff_date' => '2026-11-23' ),
		));
		$ctx = Mailer::getBookingContext($booking);

		// Two default bodies and one file template (remaining payment link).
		$ctx['payment'] = array( 'url' => 'https://example.org/pay' );
		foreach (array( 'booking_created_customer', 'booking_cancelled', 'remaining_payment_link_customer' ) as $key) {
			$html = Templates::render_body($key, $ctx);
			$this->assertStringNotContainsString('Dear ,', $html, $key);
			$this->assertStringContainsString($ctx['customer']['salutation_name'], $html, $key);
		}
	}
}
