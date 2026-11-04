<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Core;

use MHMRentiva\Admin\Booking\Meta\BookingMeta;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * A booking's payment view: one status derived from the stored payment
 * status, the payment type and the remaining amount, and the history row
 * that records when it changes (spec PR 3a).
 *
 * The writers of those three meta keys are not touched. Each payment entry
 * point opens a scope around its work instead:
 *
 *     $token = PaymentView::begin( $booking_id );
 *     try { ... } finally { PaymentView::end( $token ); }
 *
 * A scope is a token. The view is read when a booking's first token opens and
 * compared when its last token closes, so nested entry points (an AJAX action
 * whose status change runs the WooCommerce order hook) write one row, and
 * closing the same token twice -- once before wp_send_json_*(), once in
 * `finally` -- is harmless. wp_die() exits without running `finally`, so the
 * first begin() also hooks flush() on `shutdown` (priority 0, before the
 * object cache closes) to close whatever is still open.
 */
final class PaymentView {

	/** @var array<string, int> Open token => booking id. */
	private static array $open = array();

	/** @var array<int, string> Booking id => status when its first token opened. */
	private static array $before = array();

	/** @var array<int, list<string>> Post id => stacked Update-scope tokens. */
	private static array $update_tokens = array();

	private static int $sequence = 0;

	private static bool $hooked = false;

	/**
	 * Stored values the old writers left behind, read as today's statuses.
	 */
	private const LEGACY = array(
		'completed'  => 'paid',
		'processing' => 'pending',
		'on-hold'    => 'pending',
	);

	/**
	 * The derived status: a deposit booking that is paid but still owes a
	 * balance is partially paid. The current key wins over the legacy one.
	 */
	public static function status(int $booking_id): string {
		$raw = (string) get_post_meta($booking_id, '_mhmrentiva_payment_status', true);
		if ('' === $raw) {
			$raw = (string) get_post_meta($booking_id, '_mhmrentiva_booking_payment_status', true);
		}
		if ('' === $raw) {
			return 'unpaid';
		}

		$status = self::LEGACY[ $raw ] ?? $raw;

		if ('paid' === $status
			&& 'deposit' === (string) get_post_meta($booking_id, '_mhmrentiva_payment_type', true)
			&& (float) get_post_meta($booking_id, '_mhmrentiva_remaining_amount', true) > 0
		) {
			return 'partially_paid';
		}

		return $status;
	}

	/**
	 * Label of a derived status. An unknown one is "Other", never the raw key.
	 */
	public static function label(string $status): string {
		$labels = array(
			'unpaid'               => __('Unpaid', 'mhm-rentiva'),
			'pending'              => __('Pending', 'mhm-rentiva'),
			'pending_verification' => __('Pending Verification', 'mhm-rentiva'),
			'paid'                 => __('Paid', 'mhm-rentiva'),
			'partially_paid'       => __('Partially paid', 'mhm-rentiva'),
			'refunded'             => __('Refunded', 'mhm-rentiva'),
			'partially_refunded'   => __('Partially refunded', 'mhm-rentiva'),
			'cancelled'            => __('Cancelled', 'mhm-rentiva'),
			'failed'               => __('Failed', 'mhm-rentiva'),
		);

		return $labels[ $status ] ?? __('Other', 'mhm-rentiva');
	}

	/**
	 * Open a scope on a booking; returns its token.
	 */
	public static function begin(int $booking_id): string {
		if (! self::$hooked) {
			add_action('shutdown', array( self::class, 'flush' ), 0);
			self::$hooked = true;
		}

		if (! in_array($booking_id, self::$open, true)) {
			// A persistent object cache may hold what another request replaced.
			wp_cache_delete($booking_id, 'post_meta');
			self::$before[ $booking_id ] = self::status($booking_id);
		}

		$token                 = $booking_id . ':' . ( ++self::$sequence );
		self::$open[ $token ] = $booking_id;

		return $token;
	}

	/**
	 * Close a scope. A second call, or a token never opened, does nothing.
	 */
	public static function end(string $token): void {
		if (! isset(self::$open[ $token ])) {
			return;
		}

		$booking_id = self::$open[ $token ];
		unset(self::$open[ $token ]);

		if (! in_array($booking_id, self::$open, true)) {
			self::record($booking_id);
		}
	}

	/**
	 * The Update scope: generic `save_post` at 1 opens, at 99 closes. Core
	 * fires save_post_{type} before the generic hook, and the booking's
	 * writers (BookingEditMetaBox at 5, BookingMeta::save_meta at 10) are on
	 * the generic one. A save inside the save (wp_update_post() from a
	 * writer) nests: tokens stack per post, last opened closes first.
	 */
	public static function open_update_scope(int $post_id, \WP_Post $post): void {
		if (! self::is_booking_save($post_id, $post)) {
			return;
		}
		self::$update_tokens[ $post_id ][] = self::begin($post_id);
	}

	public static function close_update_scope(int $post_id, \WP_Post $post): void {
		if (! self::is_booking_save($post_id, $post) || empty(self::$update_tokens[ $post_id ])) {
			return;
		}
		self::end( (string) array_pop(self::$update_tokens[ $post_id ]));
	}

	private static function is_booking_save(int $post_id, \WP_Post $post): bool {
		return 'mhmrentiva_booking' === $post->post_type
			&& ! wp_is_post_revision($post_id)
			&& ! wp_is_post_autosave($post_id);
	}

	/**
	 * Close every scope still open (the request is ending).
	 */
	public static function flush(): void {
		foreach (array_keys(self::$open) as $token) {
			self::end( (string) $token);
		}
	}

	/**
	 * @internal Tests only.
	 */
	public static function reset(): void {
		self::$open          = array();
		self::$before        = array();
		self::$update_tokens = array();
		self::$sequence = 0;
		if (self::$hooked) {
			remove_action('shutdown', array( self::class, 'flush' ), 0);
			self::$hooked = false;
		}
	}

	private static function record(int $booking_id): void {
		$from = self::$before[ $booking_id ] ?? null;
		unset(self::$before[ $booking_id ]);

		wp_cache_delete($booking_id, 'post_meta');
		$to = self::status($booking_id);

		if (null === $from || $from === $to) {
			return;
		}

		BookingMeta::add_history_note(
			$booking_id,
			sprintf(
				/* translators: 1: payment status before, 2: payment status after. */
				__('Payment: %1$s → %2$s', 'mhm-rentiva'),
				self::label($from),
				self::label($to)
			),
			'payment_update',
			null,
			array(
				'payment_from' => $from,
				'payment_to'   => $to,
			)
		);
	}
}
