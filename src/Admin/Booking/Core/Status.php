<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Core;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy/public hook and template naming kept for backward compatibility.







final class Status {


	// Basic statuses
	public const DRAFT           = 'draft';
	public const PENDING_PAYMENT = 'pending_payment'; // Payment pending
	public const PENDING         = 'pending';
	public const CONFIRMED       = 'confirmed';
	public const IN_PROGRESS     = 'in_progress';
	public const COMPLETED       = 'completed';
	public const CANCELLED       = 'cancelled';
	public const REFUNDED        = 'refunded';
	public const NO_SHOW         = 'no_show';

	public static function register(): void
	{
		// Can be used for global rules in the future
	}

	public static function get(int $booking_id): string
	{
		$status = get_post_meta($booking_id, '_mhm_status', true);
		return in_array($status, self::allowed(), true) ? $status : self::PENDING;
	}

	public static function allowed(): array
	{
		return array(
			self::DRAFT,
			self::PENDING_PAYMENT,
			self::PENDING,
			self::CONFIRMED,
			self::IN_PROGRESS,
			self::COMPLETED,
			self::CANCELLED,
			self::REFUNDED,
			self::NO_SHOW,
		);
	}

	public static function can_transition(string $from, string $to): bool
	{
		if ($from === $to) {
			return false; // No transition to the same status
		}

		$allowed_transitions = array(
			self::DRAFT           => array( self::PENDING_PAYMENT, self::CANCELLED ),
			self::PENDING_PAYMENT => array( self::CONFIRMED, self::CANCELLED ), // Payment made or time expired
			self::PENDING         => array( self::CONFIRMED, self::COMPLETED, self::CANCELLED ), // ✅ Allow direct completion
			self::CONFIRMED       => array( self::IN_PROGRESS, self::COMPLETED, self::CANCELLED, self::NO_SHOW, self::PENDING ), // ✅ Allow revert to pending
			self::IN_PROGRESS     => array( self::COMPLETED, self::CANCELLED ),
			self::COMPLETED       => array( self::REFUNDED ),
			self::CANCELLED       => array( self::PENDING_PAYMENT, self::CONFIRMED, self::PENDING ), // Re-booking
			self::REFUNDED        => array(), // Final status
			self::NO_SHOW         => array( self::CANCELLED ), // Can be cancelled
		);

		return isset($allowed_transitions[ $from ]) && in_array($to, $allowed_transitions[ $from ], true);
	}

	public static function update_status(int $booking_id, string $new_status, int $actor_user_id = 0): bool
	{
		// Valid status check
		if (! in_array($new_status, self::allowed(), true)) {
			return false;
		}

		// Get current status
		$old_status = self::get($booking_id);

		// Transition check
		if (! self::can_transition($old_status, $new_status)) {
			return false;
		}

		// Update meta
		$updated = update_post_meta($booking_id, '_mhm_status', $new_status);

		if ($updated) {
			// Trigger status change action
			do_action('mhm_rentiva_booking_status_changed', $booking_id, $old_status, $new_status);
			return true;
		}

		return false;
	}

	public static function get_label(string $status): string
	{
		$labels = array(
			self::DRAFT           => __('Draft', 'mhm-rentiva'),
			self::PENDING_PAYMENT => __('Pending Payment', 'mhm-rentiva'),
			self::PENDING         => __('Pending', 'mhm-rentiva'),
			self::CONFIRMED       => __('Confirmed', 'mhm-rentiva'),
			self::IN_PROGRESS     => __('In Progress', 'mhm-rentiva'),
			self::COMPLETED       => __('Completed', 'mhm-rentiva'),
			self::CANCELLED       => __('Cancelled', 'mhm-rentiva'),
			self::REFUNDED        => __('Refunded', 'mhm-rentiva'),
			self::NO_SHOW         => __('No Show', 'mhm-rentiva'),
		);

		return $labels[ $status ] ?? $status;
	}

	public static function get_color(string $status): string
	{
		$colors = array(
			self::DRAFT           => '#6c757d',
			self::PENDING_PAYMENT => '#fd7e14', // Orange - payment pending
			self::PENDING         => '#ffc107',
			self::CONFIRMED       => '#28a745',
			self::IN_PROGRESS     => '#17a2b8',
			self::COMPLETED       => '#28a745',
			self::CANCELLED       => '#dc3545',
			self::REFUNDED        => '#ffc107',
			self::NO_SHOW         => '#dc3545',
		);

		return $colors[ $status ] ?? '#6c757d';
	}
}
