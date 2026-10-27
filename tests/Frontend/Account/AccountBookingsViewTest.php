<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Frontend\Account\AccountBookingsView;
use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * PR D, Task 2: the "My bookings" section rule (R5, r3 Y4) and row values
 * (R4, r3 Y2).
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountBookingsView
 */
final class AccountBookingsViewTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		update_option('timezone_string', '');
		update_option('gmt_offset', 0);
		update_option('date_format', 'F j, Y');
		parent::tearDown();
	}

	public function test_segment_rules(): void
	{
		$today     = SiteTime::today();
		$yesterday = SiteTime::days_ago(1);
		$tomorrow  = SiteTime::days_ahead(1);

		$cases = array(
			array( 'confirmed', $today, 'upcoming' ),
			array( 'confirmed', $yesterday, 'past' ),
			array( 'no_show', $tomorrow, 'past' ),
			array( 'refunded', $tomorrow, 'cancelled' ),
			array( 'pending_payment', $tomorrow, 'upcoming' ),
			array( 'in_progress', $today, 'upcoming' ),
			// Beyond the plan's table: the rest of R5 and r3 Y4.
			array( 'completed', $tomorrow, 'past' ),
			array( 'cancelled', $yesterday, 'cancelled' ),
			array( 'confirmed', '', 'upcoming' ),
			array( 'cancelled', '', 'cancelled' ),
			array( 'confirmed', 'not a date', 'upcoming' ),
			// Fix round 1, minor 3: an impossible day is no day (string-wise it is "past").
			array( 'confirmed', '2020-13-45', 'upcoming' ),
			array( 'confirmed', '2021-02-30', 'upcoming' ),
			array( 'draft', $yesterday, 'past' ),
			array( '', $tomorrow, 'upcoming' ),
		);

		foreach ($cases as $case) {
			$this->assertSame($case[2], AccountBookingsView::segment_of($case[0], $case[1], $today), "{$case[0]} / '{$case[1]}'");
		}
	}

	public function test_dates_are_two_full_site_formatted_dates(): void
	{
		update_option('date_format', 'd/m/Y');
		update_option('timezone_string', 'Europe/Istanbul');

		$this->assertSame('21/10/2026 – 24/10/2026', AccountBookingsView::dates('2026-10-21', '2026-10-24'), 'UTC+3: no day shift.');
		$this->assertSame('21/10/2026 – 24/10/2026', AccountBookingsView::dates('2026-10-21 10:00', '2026-10-24'), 'A time after the date is ignored.');
		$this->assertSame('21/10/2026', AccountBookingsView::dates('2026-10-21', ''), 'One day when the other is missing.');
		$this->assertSame('', AccountBookingsView::dates('', 'nope'));

		update_option('timezone_string', 'America/Los_Angeles');
		$this->assertSame('21/10/2026 – 24/10/2026', AccountBookingsView::dates('2026-10-21', '2026-10-24'), 'UTC-7: no day shift.');
	}

	public function test_row_number_is_display_id(): void
	{
		$user    = ShortcodeFixtures::customer();
		$booking = ShortcodeFixtures::booking($user, ShortcodeFixtures::vehicle(), 'confirmed', '480', 3);
		update_post_meta($booking, '_mhmrentiva_wc_order_id', 777001);

		$rows = AccountBookingsView::rows(array( AccountRenderer::booking_view_row(get_post($booking)) ));

		$this->assertCount(1, $rows);
		$this->assertSame($booking, $rows[0]['id'], 'The link side keeps the post ID.');
		$this->assertSame('777001', $rows[0]['number'], 'R4: the number shown is mhmrentiva_get_display_id().');
		$this->assertStringContainsString((string) $booking, $rows[0]['url'], 'The view link uses the post ID.');
		$this->assertSame(array( 'modifier' => 'confirmed', 'label' => \MHMRentiva\Admin\Booking\Core\Status::get_label('confirmed') ), $rows[0]['status']);
		$this->assertStringContainsString('480', wp_strip_all_tags($rows[0]['amount_html']));
	}

	public function test_sample_row_without_display_id_shows_its_id(): void
	{
		$rows = AccountBookingsView::rows(\MHMRentiva\Frontend\PreviewSamples::bookings_rows());
		$this->assertSame('9003', $rows[0]['number']);
		$this->assertSame('', $rows[0]['url']);
	}
}
