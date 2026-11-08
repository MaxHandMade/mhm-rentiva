<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use WP_UnitTestCase;

/**
 * Backslash class (2026-11-08): the customer's cancellation reason arrives
 * unslashed (AccountController reads it with wp_unslash()) and is stored inside
 * `_mhmrentiva_cancellation_data`; update_post_meta() unslashes arrays deeply,
 * so a typed backslash was lost. Every caller passes an unslashed reason.
 */
final class CancellationReasonKeepsBackslashTest extends WP_UnitTestCase
{
	public function test_the_cancellation_reason_keeps_its_backslash(): void
	{
		$customer = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		$booking  = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
			'meta_input'  => array(
				'_mhmrentiva_status'           => 'confirmed',
				'_mhmrentiva_payment_status'   => 'pending',
				'_mhmrentiva_customer_user_id' => $customer,
				'_mhmrentiva_pickup_date'      => gmdate('Y-m-d', strtotime('+20 days')),
				'_mhmrentiva_dropoff_date'     => gmdate('Y-m-d', strtotime('+22 days')),
			),
		));
		wp_set_current_user($customer);

		$result = CancellationHandler::cancel_booking($booking, $customer, 'Uçuş iptal, dosya C:\bilet\4', true, false, 'customer_account');
		$this->assertNotWPError($result);

		$data = (array) get_post_meta($booking, '_mhmrentiva_cancellation_data', true);
		$this->assertSame('Uçuş iptal, dosya C:\bilet\4', $data['cancellation_reason'] ?? null);
	}
}
