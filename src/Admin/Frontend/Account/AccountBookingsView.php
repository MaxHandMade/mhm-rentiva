<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\RvsStatus;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * View values of the "My bookings" list (PR D, Task 2): the section rule and
 * the table rows. No query here -- AccountBookingsQuery finds the bookings.
 *
 * @since 6.4.0
 */
final class AccountBookingsView {

	/**
	 * The section of a booking (R5): Cancelled for cancelled/refunded; Past for
	 * completed, no-show, or a return day before today; Upcoming otherwise. A
	 * missing, empty or malformed return day is no return day: Upcoming (r3 Y4).
	 *
	 * @param string $status       Booking status.
	 * @param string $dropoff_date Return day, local `Y-m-d` (AccountBookingsQuery::return_day()).
	 * @param string $today        Local `Y-m-d`.
	 * @return string 'upcoming'|'past'|'cancelled'
	 */
	public static function segment_of(string $status, string $dropoff_date, string $today): string
	{
		if (in_array($status, AccountBookingsQuery::CANCELLED_STATUSES, true)) {
			return AccountBookingsQuery::CANCELLED;
		}

		if (in_array($status, AccountBookingsQuery::PAST_STATUSES, true)) {
			return AccountBookingsQuery::PAST;
		}

		if (self::is_day($dropoff_date) && substr($dropoff_date, 0, 10) < $today) {
			return AccountBookingsQuery::PAST;
		}

		return AccountBookingsQuery::UPCOMING;
	}

	/**
	 * Whether $value starts with a real calendar day (`Y-m-d`, checkdate());
	 * "2026-13-45" or "garbage" is no day.
	 */
	public static function is_day(string $value): bool
	{
		if (1 !== preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
			return false;
		}

		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1]);
	}

	/**
	 * Table rows from AccountRenderer::booking_view_row() rows (or the editor's
	 * sample rows, which have the same shape).
	 *
	 * @param array<int, array<string, mixed>> $booking_rows
	 * @return list<array{id:int, number:string, vehicle:string, image:string, dates:string, amount_html:string, status:array{modifier:string,label:string}, url:string}>
	 */
	public static function rows(array $booking_rows): array
	{
		$rows = array();
		foreach ($booking_rows as $row) {
			$id = (int) ( $row['id'] ?? 0 );

			$vehicle = $row['vehicle_title'] ?? null;
			$total   = $row['total_price'] ?? '';

			$rows[] = array(
				'id'          => $id,
				// R4: the number every other screen and email shows.
				'number'      => (string) ( $row['display_id'] ?? $id ),
				'vehicle'     => is_string($vehicle) && '' !== $vehicle ? $vehicle : __('N/A', 'mhm-rentiva'),
				'image'       => (string) ( $row['vehicle_image'] ?? '' ),
				'dates'       => self::dates( (string) ( $row['pickup_date'] ?? '' ), (string) ( $row['return_day'] ?? $row['dropoff_date'] ?? '' )),
				'amount_html' => is_numeric($total) ? PriceDisplay::for_cart( (float) $total)['html'] : '',
				'status'      => RvsStatus::for( (string) ( $row['status'] ?? '' )),
				'url'         => (string) ( $row['view_url'] ?? '' ),
			);
		}

		return $rows;
	}

	/**
	 * The rental's two days, each a full date in the site's date format and
	 * timezone (r3 Y2: no "21–24 Oct" shortening). One day when the other is
	 * missing, '' when neither parses.
	 *
	 * @param string $start Local `Y-m-d` (a time after it is ignored).
	 * @param string $end   Local `Y-m-d`.
	 */
	public static function dates(string $start, string $end): string
	{
		$from = self::day($start);
		$to   = self::day($end);

		if ('' === $from || '' === $to) {
			return '' !== $from ? $from : $to;
		}

		/* translators: 1: start date, 2: end date */
		return sprintf(__('%1$s – %2$s', 'mhm-rentiva'), $from, $to);
	}

	/**
	 * A local `Y-m-d` in the site's date format; '' when it does not parse.
	 */
	private static function day(string $date): string
	{
		$ts = SiteTime::instant(substr(trim($date), 0, 10));
		if (null === $ts) {
			return '';
		}

		return (string) wp_date( (string) get_option('date_format'), $ts);
	}
}
