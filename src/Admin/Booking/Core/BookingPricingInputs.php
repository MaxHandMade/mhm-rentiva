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
	 * Current key => the keys BookingEditMetaBox::render() falls back to, in order.
	 */
	private const LEGACY_KEYS = array(
		'_mhmrentiva_vehicle_id'   => array( '_mhmrentiva_booking_vehicle_id' ),
		'_mhmrentiva_pickup_date'  => array( '_mhmrentiva_booking_pickup_date' ),
		'_mhmrentiva_start_time'   => array( '_mhmrentiva_pickup_time', '_mhmrentiva_booking_pickup_time' ),
		'_mhmrentiva_dropoff_date' => array( '_mhmrentiva_booking_dropoff_date' ),
		'_mhmrentiva_end_time'     => array( '_mhmrentiva_dropoff_time', '_mhmrentiva_booking_dropoff_time' ),
	);

	/** The legacy add-on selection key (render() and AddonBooking fall back to it). */
	private const LEGACY_ADDONS_KEY = 'mhmrentiva_selected_addons';

	/**
	 * The price inputs as one comparable string. Scalars go through first():
	 * '' and '0' are the same "empty" the edit box's `?:` reads -- a stored
	 * '0' time renders empty and is posted back as ''.
	 */
	public static function fingerprint(int $booking_id): string {
		$selection = AddonSelection::for_booking($booking_id);
		$addons    = $selection->quantities();
		ksort($addons);
		// A set, not a sequence: the edit box posts add-ons in menu order.
		$ids = $selection->ids();
		sort($ids);

		return (string) wp_json_encode(
			array(
				'vehicle'  => (int) self::first($booking_id, '_mhmrentiva_vehicle_id'),
				'pickup'   => self::first($booking_id, '_mhmrentiva_pickup_date'),
				'start'    => self::first($booking_id, '_mhmrentiva_start_time'),
				'dropoff'  => self::first($booking_id, '_mhmrentiva_dropoff_date'),
				'end'      => self::first($booking_id, '_mhmrentiva_end_time'),
				'addons'   => $ids,
				'quantity' => $addons,
				'payment'  => self::first($booking_id, '_mhmrentiva_payment_type'),
			)
		);
	}

	public static function open(int $post_id, \WP_Post $post): void {
		if (! self::is_booking_save($post_id, $post)) {
			return;
		}
		if (empty(self::$depth[ $post_id ])) {
			wp_cache_delete($post_id, 'post_meta');
			self::adopt_legacy_keys($post_id);
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
	 * Before anything reads the booking, carry the values the edit box shows
	 * from legacy keys into the current ones -- once, before the fingerprint.
	 *
	 * The edit box's render() falls back to legacy keys; the writers (this fingerprint, the
	 * vehicle-change check, the add-on quantity cap via for_booking()) read
	 * the current keys. On a booking with only legacy keys the first Update
	 * therefore saw a change where the operator made none and re-priced it
	 * (Codex review of #171). Adopting the legacy values makes every reader
	 * agree. The legacy add-on list is then deleted: left behind, it would
	 * shadow a selection cleared later (`?:` treats [] as missing). The
	 * legacy date/time/vehicle keys stay -- list columns and queries still
	 * read them, and the edit box keeps writing them.
	 */
	private static function adopt_legacy_keys(int $booking_id): void {
		foreach (self::LEGACY_KEYS as $current => $legacy) {
			if (self::first($booking_id, $current) !== '') {
				continue;
			}
			$value = self::first($booking_id, ...$legacy);
			if ('' !== $value) {
				update_post_meta($booking_id, $current, $value);
			}
		}

		$legacy_addons = get_post_meta($booking_id, self::LEGACY_ADDONS_KEY, true);
		if ('' === $legacy_addons || false === $legacy_addons) {
			return;
		}
		if (empty(get_post_meta($booking_id, MetaKeys::BOOKING_SELECTED_ADDONS, true))) {
			$adopted = array_values(array_map('intval', (array) $legacy_addons));
			update_post_meta($booking_id, MetaKeys::BOOKING_SELECTED_ADDONS, $adopted);
			// Delete the source only once the copy is really stored: a write a
			// filter refused would otherwise lose the selection.
			wp_cache_delete($booking_id, 'post_meta');
			if (get_post_meta($booking_id, MetaKeys::BOOKING_SELECTED_ADDONS, true) !== $adopted) {
				return;
			}
		}
		delete_post_meta($booking_id, self::LEGACY_ADDONS_KEY);
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
