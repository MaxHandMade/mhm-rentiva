<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking;

use MHMRentiva\Admin\Booking\Meta\BookingDepositMetaBox;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use WP_UnitTestCase;

/**
 * Customer-eye round #20 (2026-11-10). The cancellation deadline had two
 * answers: CancellationHandler::deadline_moment() (pickup minus the setting
 * hours -- what the customer is shown and refused by) and the stored meta
 * _mhmrentiva_cancellation_deadline, which three writers set to "booking
 * time + 24 h". The refund handler and the deposit box read the meta, so the
 * operator saw, and the refund obeyed, a deadline the customer never had.
 *
 * The meta now has no writers and no readers; the only file allowed to name
 * it is DatabaseCleaner, which removes what older versions left behind.
 */
final class CancellationDeadlineHasOneSourceTest extends WP_UnitTestCase
{
	private const META = '_mhmrentiva_cancellation_deadline';

	public function setUp(): void
	{
		parent::setUp();
		update_option('timezone_string', 'Europe/Istanbul');
		$settings = (array) get_option(SettingsCore::OPTION_NAME, array());
		$settings['mhmrentiva_booking_cancellation_deadline_hours'] = 24;
		update_option(SettingsCore::OPTION_NAME, $settings);
	}

	/**
	 * A structural guard, not a behaviour test: a new writer or reader of the
	 * meta reopens the second answer, whatever any one surface does today.
	 */
	public function test_no_source_file_but_the_cleaner_names_the_stored_deadline(): void
	{
		$root     = dirname(__DIR__, 3) . '/src';
		$offenders = array();

		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
		foreach ($files as $file) {
			if ('php' !== $file->getExtension()) {
				continue;
			}
			$path = str_replace('\\', '/', $file->getPathname());
			if (str_ends_with($path, '/Admin/Core/Utilities/DatabaseCleaner.php')) {
				continue;
			}
			$source = (string) file_get_contents($path);
			if (false !== strpos($source, self::META) || false !== strpos($source, "'cancellation_deadline'")) {
				$offenders[] = substr($path, strlen($root));
			}
		}

		$this->assertSame(array(), $offenders, 'The cancellation deadline is computed from pickup, never stored.');
	}

	/**
	 * The operator's box shows the deadline the customer has: pickup
	 * 14:00 on day D with a 24 h setting -> D-1 14:00 site time, even when an
	 * older version left a stale "booking + 24 h" meta behind. RED before the
	 * fix: the box printed the meta.
	 */
	public function test_the_deposit_box_shows_the_pickup_based_deadline_not_the_stored_one(): void
	{
		$pickup_day = new \DateTimeImmutable('+30 days', wp_timezone());
		$booking_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		update_post_meta($booking_id, '_mhmrentiva_payment_type', 'deposit');
		update_post_meta($booking_id, '_mhmrentiva_status', 'confirmed');
		update_post_meta($booking_id, '_mhmrentiva_payment_status', 'pending');
		update_post_meta($booking_id, '_mhmrentiva_cancellation_policy', '24_hours');
		update_post_meta($booking_id, '_mhmrentiva_pickup_date', $pickup_day->format('Y-m-d'));
		update_post_meta($booking_id, '_mhmrentiva_start_time', '14:00');
		update_post_meta($booking_id, self::META, gmdate('Y-m-d H:i:s', strtotime('-2 days')));

		ob_start();
		BookingDepositMetaBox::render_deposit_management(get_post($booking_id));
		$html = (string) ob_get_clean();

		$expected = $pickup_day->modify('-1 day')->format('d.m.Y') . ' 14:00';
		$this->assertStringContainsString($expected, $html);
		$this->assertStringNotContainsString(
			get_date_from_gmt(gmdate('Y-m-d H:i:s', strtotime('-2 days')), 'd.m.Y H:i'),
			$html
		);
		$this->assertStringContainsString('This booking can be cancelled.', $html);
		$this->assertStringNotContainsString('Cancellation period expired.', $html);

		// A cancelled booking keeps the deadline line but is not told it
		// "can be cancelled" (measured on 10595, 2026-11-10).
		update_post_meta($booking_id, '_mhmrentiva_status', 'cancelled');
		ob_start();
		BookingDepositMetaBox::render_deposit_management(get_post($booking_id));
		$cancelled_html = (string) ob_get_clean();

		$this->assertStringContainsString($expected, $cancelled_html);
		$this->assertStringNotContainsString('This booking can be cancelled.', $cancelled_html);
		$this->assertStringNotContainsString('Cancellation period expired.', $cancelled_html);
	}
}
