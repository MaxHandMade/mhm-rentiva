<?php

declare(strict_types=1);

namespace MHMRentiva\Frontend;

use MHMRentiva\Admin\Core\Utilities\MetaQueryHelper;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper;
use MHMRentiva\Admin\Vehicle\PostType\Vehicle;

/**
 * Sample data for editor previews (Elementor, block editor).
 *
 * Nothing here writes to the database, an option, an Elementor setting or a
 * block attribute: vehicles are read from existing posts, account rows are
 * static literals. The values do not depend on the render context, so they are
 * cached for the request; reset() empties that cache for tests.
 *
 * @package MHMRentiva\Frontend
 */
final class PreviewSamples {

	private const BATCH = 20;

	/**
	 * Resolved vehicle ids, ascending. Filled lazily by the largest request so far.
	 *
	 * @var array<int, int>
	 */
	private static array $vehicle_cache = array();

	/** True once the query ran out of posts, so a larger request cannot find more. */
	private static bool $vehicle_exhausted = false;

	/**
	 * Lowest-ID published, active, publicly readable vehicle; 0 when none.
	 */
	public static function vehicle_id(): int
	{
		$ids = self::vehicle_ids(1);
		return $ids[0] ?? 0;
	}

	/**
	 * The first $n such vehicles, ascending by ID. Empty when there are none.
	 *
	 * @return int[]
	 */
	public static function vehicle_ids( int $n ): array
	{
		if ($n < 1) {
			return array();
		}

		if (count(self::$vehicle_cache) < $n && ! self::$vehicle_exhausted) {
			self::fill_vehicle_cache($n);
		}

		return array_slice(self::$vehicle_cache, 0, $n);
	}

	/**
	 * Sample rows for the "My Reservations" list.
	 *
	 * Plain rows, one per booking, with exactly the values templates/account/
	 * bookings.php displays per row:
	 *
	 *  - id            int    booking ID (shown as #id; the view link is built from it)
	 *  - vehicle_title string vehicle name column ('' shows "N/A")
	 *  - vehicle_image string thumbnail URL ('' falls back to the vehicle placeholder)
	 *  - status        string pending|confirmed|in_progress|completed|cancelled
	 *  - pickup_date   string Y-m-d
	 *  - pickup_time   string H:i
	 *  - dropoff_date  string Y-m-d (decides the upcoming / past section)
	 *  - total_price   float  fee column
	 *
	 * Dates are fixed literals: the far-future drop-off keeps the first rows in
	 * the "upcoming" section and the old one keeps the last row in "past",
	 * whatever day it is.
	 *
	 * @return array<int, array{id:int,vehicle_title:string,vehicle_image:string,status:string,pickup_date:string,pickup_time:string,dropoff_date:string,total_price:float}>
	 */
	public static function bookings_rows(): array
	{
		return array(
			array(
				'id'            => 9003,
				'vehicle_title' => __('Sample Sedan', 'mhm-rentiva'),
				'vehicle_image' => '',
				'status'        => 'confirmed',
				'pickup_date'   => '2099-06-10',
				'pickup_time'   => '10:00',
				'dropoff_date'  => '2099-06-14',
				'total_price'   => 480.0,
			),
			array(
				'id'            => 9002,
				'vehicle_title' => __('Sample SUV', 'mhm-rentiva'),
				'vehicle_image' => '',
				'status'        => 'pending',
				'pickup_date'   => '2099-07-01',
				'pickup_time'   => '09:30',
				'dropoff_date'  => '2099-07-05',
				'total_price'   => 720.0,
			),
			array(
				'id'            => 9001,
				'vehicle_title' => __('Sample Hatchback', 'mhm-rentiva'),
				'vehicle_image' => '',
				'status'        => 'completed',
				'pickup_date'   => '2020-03-02',
				'pickup_time'   => '14:00',
				'dropoff_date'  => '2020-03-05',
				'total_price'   => 300.0,
			),
		);
	}

	/**
	 * Sample rows for the payment history, in the shape
	 * AccountRenderer::get_user_payments() returns in `items` and
	 * templates/account/payment-history.php reads (sort_ts and has_amount are
	 * kept for shape parity).
	 *
	 * @return array<int, array{booking_id:int,booking_title:string,date:string,sort_ts:int,status:string,status_label:string,method:string,amount:float,has_amount:bool,total:float,type:string,receipt:array{attachment_id:int,status:string,url:string}}>
	 */
	public static function payment_rows(): array
	{
		$completed_ts = 1717977600; // 2024-06-10 00:00 UTC.
		$pending_ts   = 1709337600; // 2024-03-02 00:00 UTC.
		$format       = (string) get_option('date_format');

		return array(
			array(
				'booking_id'    => 9003,
				'booking_title' => __('Sample Sedan', 'mhm-rentiva'),
				'date'          => date_i18n($format, $completed_ts),
				'sort_ts'       => $completed_ts,
				'status'        => 'completed',
				'status_label'  => __('Completed', 'mhm-rentiva'),
				'method'        => __('Credit Card', 'mhm-rentiva'),
				'amount'        => 480.0,
				'has_amount'    => true,
				'total'         => 480.0,
				'type'          => 'full',
				'receipt'       => array(
					'attachment_id' => 0,
					'status'        => '',
					'url'           => '',
				),
			),
			array(
				'booking_id'    => 9002,
				'booking_title' => __('Sample SUV', 'mhm-rentiva'),
				'date'          => date_i18n($format, $pending_ts),
				'sort_ts'       => $pending_ts,
				'status'        => 'pending',
				'status_label'  => __('Pending', 'mhm-rentiva'),
				'method'        => __('Bank Transfer', 'mhm-rentiva'),
				'amount'        => 144.0,
				'has_amount'    => true,
				'total'         => 720.0,
				'type'          => 'deposit',
				'receipt'       => array(
					'attachment_id' => 0,
					'status'        => '',
					'url'           => '',
				),
			),
		);
	}

	/**
	 * Empty the request cache. For tests only.
	 *
	 * @internal
	 */
	public static function reset(): void
	{
		self::$vehicle_cache     = array();
		self::$vehicle_exhausted = false;
	}

	/**
	 * Query published, active vehicles in ascending ID order, keep the publicly
	 * readable ones (password-protected posts are not), until $want are found
	 * or the posts run out.
	 */
	private static function fill_vehicle_cache( int $want ): void
	{
		$found      = array();
		$paged      = 1;
		$batch_size = 0;

		do {
			$ids = get_posts(
				array(
					'post_type'              => Vehicle::POST_TYPE,
					'post_status'            => 'publish',
					'fields'                 => 'ids',
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'posts_per_page'         => self::BATCH,
					'paged'                  => $paged,
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
					'suppress_filters'       => false,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- active-vehicle rule shared with the shortcodes; bounded by BATCH.
					'meta_query'             => array( MetaQueryHelper::get_active_vehicle_meta_query() ),
				)
			);

			$batch_size = count($ids);

			foreach ($ids as $id) {
				$id = (int) $id;
				if (VehicleDataHelper::is_publicly_readable($id)) {
					$found[] = $id;
					if (count($found) >= $want) {
						break 2;
					}
				}
			}

			++$paged;
		} while ($batch_size === self::BATCH);

		self::$vehicle_exhausted = count($found) < $want;
		self::$vehicle_cache     = $found;
	}
}
