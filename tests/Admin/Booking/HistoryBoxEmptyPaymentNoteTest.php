<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking;

use MHMRentiva\Admin\Booking\Core\PaymentView;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use WP_UnitTestCase;

/**
 * An anonymised booking keeps payment changes with their note emptied
 * (5a spec T5); the History box must still say what changed, and must not
 * print an empty paragraph.
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta::render_history_section
 */
final class HistoryBoxEmptyPaymentNoteTest extends WP_UnitTestCase
{
	public function test_empty_payment_note_renders_the_transition(): void
	{
		wp_set_current_user((int) self::factory()->user->create(array('role' => 'administrator')));
		$id = (int) self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));
		update_post_meta($id, '_mhmrentiva_booking_history', array(
			'1-a' => array('type' => 'payment_update', 'note' => '', 'payment_from' => 'pending', 'payment_to' => 'paid', 'user_id' => 0, 'timestamp' => 1700000000),
		));

		ob_start();
		BookingMeta::render_history_section(get_post($id));
		$html = (string) ob_get_clean();

		$this->assertStringContainsString(esc_html(PaymentView::label('pending') . ' → ' . PaymentView::label('paid')), $html);
		$this->assertStringContainsString('System', $html);
		$this->assertDoesNotMatchRegularExpression('#<p>\s*</p>#', $html);
	}
}
