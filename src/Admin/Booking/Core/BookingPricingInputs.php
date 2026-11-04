<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Core;

use MHMRentiva\Admin\Addons\AddonSelection;
use MHMRentiva\Admin\Core\MetaKeys;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * What a booking's price is computed from, and whether an Update changed it.
 *
 * An Update on the booking edit screen used to re-price the booking whenever
 * its dates were filled in -- that is, always -- so changing only the status
 * of a paid booking priced it again at today's rates; the offline paid amount
 * being total minus remaining, the new total then read as paid (functional
 * sweep H2, 2026-11-04). The re-pricing now runs only when one of its inputs
 * moved: vehicle, pickup/return date and time, add-ons and their quantities,
 * payment type.
 *
 * The fingerprint is taken on the generic `save_post` at priority 1, before
 * the writers (BookingEditMetaBox at 5, BookingMeta::save_meta at 10), and
 * dropped at 99. A save inside the save (wp_update_post() on a vehicle
 * change) keeps the first fingerprint. Outside an Update -- no fingerprint --
 * changed() says yes, so other callers keep their behaviour.
 */
final class BookingPricingInputs {

	/** @var array<int, string> Post id => fingerprint taken when its outermost save opened. */
	private static array $before = array();

	/** @var array<int, int> Post id => save nesting depth. */
	private static array $depth = array();

	/**
	 * The price inputs as one comparable string.
	 */
	public static function fingerprint(int $booking_id): string {
		// The values the edit box shows -- BookingEditMetaBox::render() falls back
		// to the legacy keys -- or the first Update of a legacy booking, writing
		// the same values under the current keys, would read as a change.
		$legacy_ids = get_post_meta($booking_id, '_mhmrentiva_selected_addons', true)
			?: get_post_meta($booking_id, 'mhmrentiva_selected_addons', true);
		$selection  = AddonSelection::of(
			array_map('intval', is_array($legacy_ids) ? $legacy_ids : array()),
			(array) get_post_meta($booking_id, MetaKeys::BOOKING_ADDON_QUANTITIES, true)
		);
		$addons     = $selection->quantities();
		ksort($addons);
		// A set, not a sequence: the edit box posts add-ons in menu order.
		$ids = $selection->ids();
		sort($ids);

		return (string) wp_json_encode(
			array(
				'vehicle'  => (int) self::first($booking_id, '_mhmrentiva_vehicle_id', '_mhmrentiva_booking_vehicle_id'),
				'pickup'   => self::first($booking_id, '_mhmrentiva_pickup_date', '_mhmrentiva_booking_pickup_date'),
				'start'    => self::first($booking_id, '_mhmrentiva_start_time', '_mhmrentiva_pickup_time', '_mhmrentiva_booking_pickup_time'),
				'dropoff'  => self::first($booking_id, '_mhmrentiva_dropoff_date', '_mhmrentiva_booking_dropoff_date'),
				'end'      => self::first($booking_id, '_mhmrentiva_end_time', '_mhmrentiva_dropoff_time', '_mhmrentiva_booking_dropoff_time'),
				'addons'   => $ids,
				'quantity' => $addons,
				'payment'  => (string) get_post_meta($booking_id, '_mhmrentiva_payment_type', true),
			)
		);
	}

	public static function open(int $post_id, \WP_Post $post): void {
		if (! self::is_booking_save($post_id, $post)) {
			return;
		}
		if (empty(self::$depth[ $post_id ])) {
			wp_cache_delete($post_id, 'post_meta');
			self::$before[ $post_id ] = self::fingerprint($post_id);
		}
		self::$depth[ $post_id ] = ( self::$depth[ $post_id ] ?? 0 ) + 1;
	}

	public static function close(int $post_id, \WP_Post $post): void {
		if (! self::is_booking_save($post_id, $post) || empty(self::$depth[ $post_id ])) {
			return;
		}
		--self::$depth[ $post_id ];
		if (0 === self::$depth[ $post_id ]) {
			unset(self::$depth[ $post_id ], self::$before[ $post_id ]);
		}
	}

	/**
	 * Did a price input move since the Update opened? True outside an Update.
	 */
	public static function changed(int $booking_id): bool {
		if (! isset(self::$before[ $booking_id ])) {
			return true;
		}
		wp_cache_delete($booking_id, 'post_meta');
		return self::$before[ $booking_id ] !== self::fingerprint($booking_id);
	}

	/**
	 * @internal Tests only.
	 */
	public static function reset(): void {
		self::$before = array();
		self::$depth  = array();
	}

	/**
	 * The first non-empty value of the keys, in order (the edit box's `?:` chain).
	 */
	private static function first(int $booking_id, string ...$keys): string {
		foreach ($keys as $key) {
			$value = (string) get_post_meta($booking_id, $key, true);
			if ('' !== $value && '0' !== $value) {
				return $value;
			}
		}
		return '';
	}

	private static function is_booking_save(int $post_id, \WP_Post $post): bool {
		return 'mhmrentiva_booking' === $post->post_type
			&& ! wp_is_post_revision($post_id)
			&& ! wp_is_post_autosave($post_id);
	}
}
