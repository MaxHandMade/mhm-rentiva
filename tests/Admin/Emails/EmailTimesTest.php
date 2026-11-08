<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Emails\Core\Templates;
use MHMRentiva\Admin\Settings\Groups\EmailSettings;
use WP_UnitTestCase;

/**
 * A booking has a pickup and a return time; the e-mails printed the dates only
 * (user, 2026-11-08: "saat de önemli değil mi bir rezervasyonda?"). The context
 * now carries the times in the site's time format and a date-and-time value
 * that prints the date alone when no time is stored, and the default bodies
 * print it. The reminder's "Pickup Time:" row printed the date; it gains the
 * return row (Fable #178 D1).
 */
final class EmailTimesTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		update_option('date_format', 'F j, Y');
		update_option('time_format', 'g:i a');
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

	private function formats(): void
	{
		update_option('date_format', 'd.m.Y');
		update_option('time_format', 'H:i');
	}

	public function test_times_use_the_site_format_and_keep_the_raw_value(): void
	{
		$this->formats();
		update_option('time_format', 'g:i a');
		$ctx = Mailer::getBookingContext($this->booking(array(
			'_mhmrentiva_start_time' => '09:30',
			'_mhmrentiva_end_time'   => '17:00',
		)));

		$this->assertSame('9:30 am', $ctx['booking']['pickup_time']);
		$this->assertSame('5:00 pm', $ctx['booking']['return_time']);
		$this->assertSame('09:30', $ctx['booking']['pickup_time_raw']);
		$this->assertSame('17:00', $ctx['booking']['return_time_raw']);
	}

	public function test_the_date_and_time_value_joins_both(): void
	{
		$this->formats();
		$ctx = Mailer::getBookingContext($this->booking(array(
			'_mhmrentiva_start_time' => '09:30',
			'_mhmrentiva_end_time'   => '17:00',
		)));

		$this->assertSame('20.11.2026, 09:30', $ctx['booking']['pickup_datetime']);
		$this->assertSame('23.11.2026, 17:00', $ctx['booking']['return_datetime']);
	}

	public function test_no_time_prints_the_date_alone(): void
	{
		$this->formats();
		$ctx = Mailer::getBookingContext($this->booking(array( '_mhmrentiva_start_time' => '0' )));

		$this->assertSame('', $ctx['booking']['pickup_time']);
		$this->assertSame('20.11.2026', $ctx['booking']['pickup_datetime'], 'No trailing comma when the time is missing.');
		$this->assertSame('23.11.2026', $ctx['booking']['return_datetime']);
	}

	public function test_an_unreadable_time_prints_empty(): void
	{
		$this->formats();
		$ctx = Mailer::getBookingContext($this->booking(array( '_mhmrentiva_start_time' => '25:99' )));

		$this->assertSame('', $ctx['booking']['pickup_time']);
		$this->assertSame('25:99', $ctx['booking']['pickup_time_raw']);
		$this->assertSame('20.11.2026', $ctx['booking']['pickup_datetime']);
	}

	/**
	 * The edit screen reads the time in this order (BookingEditDates); a
	 * booking created before the current key carries only the older ones.
	 */
	public function test_older_time_keys_are_read_in_the_edit_screens_order(): void
	{
		$this->formats();
		$ctx = Mailer::getBookingContext($this->booking(array(
			'_mhmrentiva_booking_pickup_time'  => '08:00',
			'_mhmrentiva_dropoff_time'         => '18:15',
			'_mhmrentiva_booking_dropoff_time' => '07:00',
		)));

		$this->assertSame('08:00', $ctx['booking']['pickup_time']);
		$this->assertSame('18:15', $ctx['booking']['return_time']);
	}

	public function test_a_time_with_seconds_is_read(): void
	{
		$this->formats();
		$ctx = Mailer::getBookingContext($this->booking(array( '_mhmrentiva_start_time' => '10:00:00' )));

		$this->assertSame('10:00', $ctx['booking']['pickup_time']);
	}

	public function test_the_tokens_resolve(): void
	{
		$ctx = array(
			'booking' => array(
				'pickup_time'     => '09:30',
				'return_time'     => '17:00',
				'pickup_datetime' => '20.11.2026, 09:30',
				'return_datetime' => '23.11.2026, 17:00',
			),
		);

		$this->assertSame(
			'09:30|17:00|17:00|20.11.2026, 09:30|23.11.2026, 17:00|23.11.2026, 17:00',
			Templates::replace_placeholders('{pickup_time}|{return_time}|{dropoff_time}|{pickup_datetime}|{return_datetime}|{dropoff_datetime}', $ctx)
		);
	}

	/**
	 * @dataProvider customer_keys
	 */
	public function test_the_default_bodies_print_pickup_and_return_with_time(string $key): void
	{
		$this->formats();
		$id  = $this->booking(array(
			'_mhmrentiva_start_time' => '09:30',
			'_mhmrentiva_end_time'   => '17:00',
		));
		$ctx = Mailer::getBookingContext($id);

		$text = wp_strip_all_tags(Templates::render_body($key, $ctx));

		$this->assertStringContainsString('20.11.2026, 09:30', $text);
		$this->assertStringContainsString('23.11.2026, 17:00', $text);
	}

	/** @return array<string, array{0: string}> */
	public static function customer_keys(): array
	{
		$keys = array( 'booking_created_customer', 'booking_reminder_customer', 'booking_cancelled', 'booking_created_admin', 'booking_status_changed_customer' );

		return array_combine($keys, array_map(static fn ($k) => array( $k ), $keys));
	}

	public function test_the_reminder_no_longer_labels_the_date_as_a_time(): void
	{
		$body = EmailSettings::get_default_booking_reminder_body();

		$this->assertStringNotContainsString('{pickup_date}', $body);
		$this->assertStringContainsString('{pickup_datetime}', $body);
		$this->assertStringContainsString('{return_datetime}', $body);
	}
}
