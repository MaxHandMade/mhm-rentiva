<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use MHMRentiva\Admin\Emails\Core\Mailer;
use WP_UnitTestCase;

/**
 * PR 3b-3 T1: the booking context every customer e-mail is built from prints
 * dates in the site's date format (not raw Y-m-d) without shifting the day in a
 * western time zone, and offers a salutation name that is never empty.
 * `customer.name` keeps its meaning (admin templates print it as is).
 */
final class BookingContextDisplayTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		update_option('timezone_string', '');
		update_option('date_format', 'F j, Y');
		parent::tearDown();
	}

	private function booking(array $meta): int
	{
		return (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
			'meta_input'  => $meta + array(
				'_mhmrentiva_pickup_date'  => '2026-11-20',
				'_mhmrentiva_dropoff_date' => '2026-11-23',
			),
		));
	}

	public function test_dates_use_the_site_format_and_keep_the_raw_value(): void
	{
		update_option('date_format', 'd.m.Y');
		$ctx = Mailer::getBookingContext($this->booking(array()));

		$this->assertSame('20.11.2026', $ctx['booking']['pickup_date']);
		$this->assertSame('23.11.2026', $ctx['booking']['return_date']);
		$this->assertSame('2026-11-20', $ctx['booking']['pickup_date_raw']);
		$this->assertSame('2026-11-23', $ctx['booking']['return_date_raw']);
	}

	public function test_a_western_time_zone_does_not_shift_the_day(): void
	{
		update_option('timezone_string', 'America/Los_Angeles');
		update_option('date_format', 'Y-m-d');

		$ctx = Mailer::getBookingContext($this->booking(array()));

		$this->assertSame('2026-11-20', $ctx['booking']['pickup_date']);
	}

	public function test_an_unreadable_date_prints_empty(): void
	{
		$ctx = Mailer::getBookingContext($this->booking(array( '_mhmrentiva_pickup_date' => 'yarın' )));

		$this->assertSame('', $ctx['booking']['pickup_date']);
		$this->assertSame('yarın', $ctx['booking']['pickup_date_raw']);
	}

	public function test_an_empty_name_has_a_salutation_name_but_customer_name_stays_empty(): void
	{
		$ctx = Mailer::getBookingContext($this->booking(array()));

		$this->assertSame('', $ctx['customer']['name'], 'Admin templates print customer.name as is.');
		$this->assertNotSame('', trim($ctx['customer']['salutation_name']));
	}

	public function test_a_known_name_is_the_salutation_name(): void
	{
		$ctx = Mailer::getBookingContext($this->booking(array(
			'_mhmrentiva_customer_first_name' => 'Ayşe',
			'_mhmrentiva_customer_last_name'  => 'Yılmaz',
		)));

		$this->assertSame('Ayşe Yılmaz', $ctx['customer']['name']);
		$this->assertSame('Ayşe Yılmaz', $ctx['customer']['salutation_name']);
	}
}
