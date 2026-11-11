<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\PostTypes\Maintenance;

use MHMRentiva\Admin\PostTypes\Maintenance\AutoCancel;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use WP_UnitTestCase;

/**
 * A booking the operator entered by hand and will be paid outside the site
 * (cash or card at pickup, a bank transfer they check themselves) has no
 * payment the site waits for, so neither unattended sweep may cancel it.
 *
 * Until 6.4.0 both did: the deadline sweep after the configured minutes
 * (30 by default), the past-pickup sweep once the pickup day had passed. A
 * phone booking was cancelled half an hour after it was entered and the
 * customer was e-mailed that the payment window had expired (admin lens
 * tour 2026-11-11, #8). The positive controls keep the sweeps honest: a
 * hand-entered booking the customer pays online, and a booking made on the
 * site, are still swept -- a sweep that selects nothing passes the negative
 * controls just as well.
 */
final class ManualOfflineBookingIsNotAutoCancelledTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		delete_option('mhmrentiva_settings');
		parent::tearDown();
	}

	/**
	 * Unpaid, pending, created two hours ago: past the default deadline.
	 * No pickup date, so only the deadline sweep can see it.
	 */
	private function expired(?string $created_via, ?string $payment_method): int
	{
		$booking_id = (int) self::factory()->post->create(array(
			'post_type' => 'mhmrentiva_booking',
			'post_date' => gmdate('Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS),
		));
		update_post_meta($booking_id, '_mhmrentiva_payment_status', 'pending');
		update_post_meta($booking_id, '_mhmrentiva_status', 'pending');
		$this->origin($booking_id, $created_via, $payment_method);

		return $booking_id;
	}

	/** Unpaid, pending, pickup a week ago: what the past-pickup sweep selects. */
	private function past_pickup(?string $created_via, ?string $payment_method): int
	{
		$booking_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		update_post_meta($booking_id, '_mhmrentiva_pickup_date', wp_date('Y-m-d', strtotime('-7 days')));
		update_post_meta($booking_id, '_mhmrentiva_payment_status', 'pending');
		update_post_meta($booking_id, '_mhmrentiva_status', 'pending');
		$this->origin($booking_id, $created_via, $payment_method);

		return $booking_id;
	}

	private function origin(int $booking_id, ?string $created_via, ?string $payment_method): void
	{
		if (null !== $created_via) {
			update_post_meta($booking_id, '_mhmrentiva_created_via', $created_via);
		}
		if (null !== $payment_method) {
			update_post_meta($booking_id, '_mhmrentiva_payment_method', $payment_method);
		}
	}

	private function status(int $booking_id): string
	{
		wp_cache_delete($booking_id, 'post_meta');

		return (string) get_post_meta($booking_id, '_mhmrentiva_status', true);
	}

	public function test_the_deadline_sweep_leaves_a_hand_entered_offline_booking_alone(): void
	{
		$manual_offline = $this->expired('admin_manual', 'offline');
		$manual_online  = $this->expired('admin_manual', 'online');
		$from_the_site  = $this->expired(null, 'woocommerce');
		SettingsCore::set('mhmrentiva_booking_auto_cancel_enabled', '1');

		AutoCancel::run();

		$this->assertSame('pending', $this->status($manual_offline), 'No payment is awaited on the site: nothing expires.');
		$this->assertSame('cancelled', $this->status($manual_online), 'Positive control: a hand-entered booking paid online still expires.');
		$this->assertSame('cancelled', $this->status($from_the_site), 'Positive control: a booking made on the site still expires.');
	}

	public function test_the_past_pickup_sweep_leaves_a_hand_entered_offline_booking_alone(): void
	{
		$manual_offline = $this->past_pickup('admin_manual', 'offline');
		$from_the_site  = $this->past_pickup(null, 'woocommerce');

		$result = AutoCancel::sync_stale_past_bookings();

		$this->assertSame('pending', $this->status($manual_offline), 'A past rental the operator recorded, to be paid by hand, is theirs to close.');
		$this->assertSame('cancelled', $this->status($from_the_site), 'Positive control.');
		$this->assertSame(1, $result['cancelled']);
	}
}
