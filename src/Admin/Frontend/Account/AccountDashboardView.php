<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Core\LocationContext;
use MHMRentiva\Admin\Frontend\Shortcodes\Rvs\RvsStatus;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * View values of the account dashboard (PR D, Task 3): the greeting, the line
 * under it and the next-rental card. No query of its own -- the candidates
 * come from AccountBookingsQuery::next_rental_candidates().
 *
 * @since 6.4.0
 */
final class AccountDashboardView {

	/**
	 * The booking the card shows: one in progress first; otherwise the earliest
	 * confirmed / pending / awaiting-payment one whose pickup is not past.
	 *
	 * @param list<array{id:int, status:string, pickup_ts:int}> $candidates
	 * @return int|null Booking ID.
	 */
	public static function pick_next(array $candidates, int $now_ts): ?int
	{
		$in_progress = null;
		$upcoming    = null;
		foreach ($candidates as $candidate) {
			$ts = (int) $candidate['pickup_ts'];
			if (Status::IN_PROGRESS === $candidate['status']) {
				if (null === $in_progress || $ts < (int) $in_progress['pickup_ts']) {
					$in_progress = $candidate;
				}
				continue;
			}

			if (! in_array($candidate['status'], array( Status::CONFIRMED, Status::PENDING, Status::PENDING_PAYMENT ), true) || $ts <= 0 || $ts < $now_ts) {
				continue;
			}
			if (null === $upcoming || $ts < (int) $upcoming['pickup_ts']) {
				$upcoming = $candidate;
			}
		}

		$picked = $in_progress ?? $upcoming;
		return null === $picked ? null : (int) $picked['id'];
	}

	/**
	 * The line under the greeting (four states). Days are calendar days of the
	 * site's timezone.
	 *
	 * @param array{status:string, pickup_ts:int, return_text?:string}|null $next The card's booking, or null.
	 */
	public static function subline(?array $next, int $now_ts): string
	{
		if (null === $next) {
			return __('You have no upcoming rentals.', 'mhm-rentiva');
		}

		if (Status::IN_PROGRESS === $next['status']) {
			if ('' === (string) ( $next['return_text'] ?? '' )) {
				return __('Your rental is in progress.', 'mhm-rentiva');
			}

			/* translators: %s: return date and time. */
			return sprintf(__('Your rental is in progress. Return: %s', 'mhm-rentiva'), (string) $next['return_text']);
		}

		$days = self::days_between($now_ts, (int) $next['pickup_ts']);
		if ($days <= 0) {
			return __('Your next rental starts today.', 'mhm-rentiva');
		}

		return sprintf(
			/* translators: %d: number of days until the next rental starts. */
			_n('Your next rental starts in %d day.', 'Your next rental starts in %d days.', $days, 'mhm-rentiva'),
			$days
		);
	}

	/**
	 * The name the greeting uses: first name, else the display name.
	 */
	public static function greeting_name(\WP_User $user): string
	{
		$first = trim( (string) $user->first_name);
		return '' !== $first ? $first : (string) $user->display_name;
	}

	/**
	 * The user's next booking (the card's), or null.
	 */
	public static function next_booking_id(int $user_id, ?int $now_ts = null): ?int
	{
		return self::pick_next(AccountBookingsQuery::next_rental_candidates($user_id), $now_ts ?? time());
	}

	/**
	 * Values of the next-rental card.
	 *
	 * `payment` states come from the same truth the booking detail reads: money
	 * received (paid amount or a paid order), payment type, remaining amount.
	 * `summary_url` opens the printable booking summary (BookingSummaryPage).
	 *
	 * @return array{number:string, status:array{modifier:string,label:string}, in_progress:bool, kicker:string, vehicle:string, image:string, pickup:string, return:string, location:?string, payment:array{label:string,amount_html:?string,note:?string}, details_url:string, summary_url:string, free_cancel:?string}
	 */
	public static function next_card(int $booking_id): array
	{
		$status     = Status::get($booking_id);
		$number     = (string) mhmrentiva_get_display_id($booking_id);
		$vehicle_id = (int) get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true);
		$vehicle    = $vehicle_id > 0 ? get_post($vehicle_id) : null;

		$image = $vehicle_id > 0 ? get_the_post_thumbnail_url($vehicle_id, 'medium_large') : false;
		if (! $image) {
			$image = VehicleDataHelper::get_placeholder_image_url();
		}

		// The vehicle's pickup point today: no location is stored on the booking (m17).
		$location      = $vehicle_id > 0 ? LocationContext::for_vehicle($vehicle_id) : null;
		$location_name = null !== $location && '' !== trim( (string) ( $location->name ?? '' )) ? trim( (string) $location->name) : null;

		$in_progress = Status::IN_PROGRESS === $status;
		$kicker      = $in_progress
			/* translators: %s: booking number. */
			? sprintf(__('In progress · %s', 'mhm-rentiva'), '#' . $number)
			/* translators: %s: booking number. */
			: sprintf(__('Upcoming · %s', 'mhm-rentiva'), '#' . $number);

		$cancel      = CancellationView::for_booking($booking_id, (int) get_current_user_id());
		$free_cancel = 'open' === $cancel['state'] && null !== $cancel['deadline_text']
			/* translators: %s: cancellation deadline (date and time). */
			? sprintf(__('Free cancellation until %s', 'mhm-rentiva'), $cancel['deadline_text'])
			: null;

		return array(
			'number'      => $number,
			'status'      => RvsStatus::for($status),
			'in_progress' => $in_progress,
			'kicker'      => $kicker,
			'vehicle'     => $vehicle instanceof \WP_Post ? $vehicle->post_title : __('N/A', 'mhm-rentiva'),
			'image'       => (string) $image,
			'pickup'      => self::pickup_text($booking_id),
			'return'      => self::return_text($booking_id),
			'location'    => $location_name,
			'payment'     => self::payment($booking_id, $status),
			'details_url' => AccountController::get_booking_view_url($booking_id),
			'summary_url' => BookingSummaryPage::url($booking_id),
			'free_cancel' => $free_cancel,
		);
	}

	/**
	 * Return moment text of a booking ("date · time"; the day alone when no
	 * time is recorded). '' when nothing is recorded.
	 */
	public static function return_text(int $booking_id): string
	{
		$end_ts = (string) get_post_meta($booking_id, AccountBookingsQuery::END_TS_KEY, true);
		if (1 === preg_match('/^[1-9]\d*$/', $end_ts)) {
			return BookingTimeline::moment_text( (int) $end_ts);
		}

		return self::day_time_text(
			AccountBookingsQuery::return_day($booking_id),
			array( '_mhmrentiva_end_time', '_mhmrentiva_dropoff_time', '_mhmrentiva_booking_dropoff_time' ),
			$booking_id
		);
	}

	/**
	 * Pickup moment text of a booking, same shape as return_text().
	 */
	private static function pickup_text(int $booking_id): string
	{
		$start_ts = (string) get_post_meta($booking_id, '_mhmrentiva_start_ts', true);
		if (1 === preg_match('/^[1-9]\d*$/', $start_ts)) {
			return BookingTimeline::moment_text( (int) $start_ts);
		}

		return self::day_time_text(
			substr( (string) get_post_meta($booking_id, '_mhmrentiva_pickup_date', true), 0, 10),
			array( '_mhmrentiva_start_time', '_mhmrentiva_pickup_time', '_mhmrentiva_booking_pickup_time' ),
			$booking_id
		);
	}

	/**
	 * @param list<string> $time_keys
	 */
	private static function day_time_text(string $day, array $time_keys, int $booking_id): string
	{
		$time = '';
		foreach ($time_keys as $key) {
			$time = (string) get_post_meta($booking_id, $key, true);
			if ('' !== $time) {
				break;
			}
		}

		if ('' !== $time) {
			$ts = SiteTime::instant($day, $time);
			if (null !== $ts) {
				return BookingTimeline::moment_text($ts);
			}
		}

		$ts = SiteTime::instant($day);
		return null === $ts ? '' : (string) wp_date( (string) get_option('date_format'), $ts);
	}

	/**
	 * The card's payment cell, from the truth the detail page reads.
	 *
	 * @return array{label:string, amount_html:?string, note:?string}
	 */
	private static function payment(int $booking_id, string $status): array
	{
		$summary = AccountPaymentSummary::for_booking($booking_id, $status);

		switch ($summary['kind']) {
			case AccountPaymentSummary::REFUNDED:
				return array(
					/* translators: %s: refunded amount. */
					'label'       => sprintf(__('%s refunded', 'mhm-rentiva'), wp_strip_all_tags(BookingTimeline::recorded_html($summary['refunded_minor'], $summary['currency']))),
					'amount_html' => null,
					'note'        => null,
				);
			case AccountPaymentSummary::DEPOSIT:
				return array(
					'label'       => __('Due at pickup', 'mhm-rentiva'),
					'amount_html' => PriceDisplay::for_cart($summary['remaining'])['html'],
					'note'        => __('Deposit paid', 'mhm-rentiva'),
				);
			case AccountPaymentSummary::PAID:
				return array(
					'label'       => __('Paid in full', 'mhm-rentiva'),
					'amount_html' => null,
					'note'        => null,
				);
		}

		return array(
			'label'       => __('Awaiting payment', 'mhm-rentiva'),
			'amount_html' => null,
			'note'        => null,
		);
	}
	/**
	 * Whole calendar days (site timezone) from $now_ts to $then_ts; 0 or less
	 * means today or earlier.
	 */
	private static function days_between(int $now_ts, int $then_ts): int
	{
		$from = new \DateTimeImmutable(SiteTime::local_date($now_ts), wp_timezone());
		$to   = new \DateTimeImmutable(SiteTime::local_date($then_ts), wp_timezone());

		return (int) $from->diff($to)->format('%r%a');
	}
}
