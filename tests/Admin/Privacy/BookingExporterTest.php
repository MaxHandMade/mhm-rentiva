<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Privacy;

use MHMRentiva\Admin\Privacy\BookingExporter;
use WP_UnitTestCase;

/**
 * A person's bookings in the core personal data export (5a spec T6, K4).
 *
 * @covers \MHMRentiva\Admin\Privacy\BookingExporter
 */
final class BookingExporterTest extends WP_UnitTestCase
{
	private function booking(array $meta, string $content = ''): int
	{
		$id = (int) self::factory()->post->create(array(
			'post_type'    => 'mhmrentiva_booking',
			'post_status'  => 'publish',
			'post_content' => $content,
		));
		foreach ($meta as $key => $value) {
			update_post_meta($id, $key, $value);
		}

		return $id;
	}

	/**
	 * @return array<string, string> name => value of one exported item.
	 */
	private function fields(array $item): array
	{
		$out = array();
		foreach ($item['data'] as $pair) {
			$out[ $pair['name'] ] = $pair['value'];
		}

		return $out;
	}

	private function full_booking(): int
	{
		$vehicle = (int) self::factory()->post->create(array('post_type' => 'mhmrentiva_vehicle', 'post_title' => 'Clio 1.0'));

		return $this->booking(array(
			'_mhmrentiva_customer_user_id'           => 0,
			'_mhmrentiva_customer_email'             => 'guest@example.test',
			'_mhmrentiva_customer_name'              => 'Ahmet Yılmaz',
			'_mhmrentiva_customer_phone'             => '+90 555 000 0000',
			'_mhmrentiva_status'                     => 'cancelled',
			'_mhmrentiva_vehicle_id'                 => $vehicle,
			'_mhmrentiva_pickup_date'                => '2026-12-01',
			'_mhmrentiva_return_date'                => '2026-12-04',
			'_mhmrentiva_guests'                     => 2,
			'_mhmrentiva_special_notes'              => 'Child seat please',
			'_mhmrentiva_total_price'                => '300.00',
			'_mhmrentiva_payment_status'             => 'refunded',
			'_mhmrentiva_refund_reason'              => 'Plans changed',
			'_mhmrentiva_refund_completed_reference' => 'TR-REF-1',
			'_mhmrentiva_refund_review_dismissed_reason' => 'Outside policy',
			'_mhmrentiva_receipt_note'               => 'Receipt checked',
			'_mhmrentiva_cancellation_data'          => array(
				'cancelled_by'        => 1,
				'cancelled_by_roles'  => array('administrator'),
				'cancelled_via'       => 'admin',
				'cancellation_reason' => 'Customer asked',
			),
			'_mhmrentiva_booking_logs'               => array(array('action' => 'refund_processed', 'data' => array('processed_by' => 1))),
			'_mhmrentiva_booking_history'            => array(
				'1-abc' => array('note' => 'Called the customer about the seat', 'type' => 'note', 'user_id' => 1, 'at' => '2026-11-01 10:00:00'),
			),
		), 'Booked by phone');
	}

	public function test_item_shape_is_core_contract(): void
	{
		$id     = $this->full_booking();
		$result = BookingExporter::export('guest@example.test', 1);

		$this->assertTrue($result['done']);
		$this->assertCount(1, $result['data']);
		$item = $result['data'][0];
		$this->assertSame('mhm-rentiva-bookings', $item['group_id']);
		$this->assertNotSame('', $item['group_label']);
		$this->assertSame('booking-' . $id, $item['item_id']);
		foreach ($item['data'] as $pair) {
			$this->assertSame(array('name', 'value'), array_keys($pair));
		}
	}

	public function test_fields_listed_in_spec_t6_are_present(): void
	{
		$this->full_booking();
		$fields = $this->fields(BookingExporter::export('guest@example.test', 1)['data'][0]);
		$all    = implode("\n", $fields);

		foreach (array('Clio 1.0', '2026-12-01', '2026-12-04', 'Ahmet Yılmaz', 'guest@example.test', '+90 555 000 0000', 'Child seat please', 'Booked by phone', '300.00', 'Plans changed', 'TR-REF-1', 'Outside policy', 'Receipt checked', 'Customer asked') as $needle) {
			$this->assertStringContainsString($needle, $all, $needle);
		}
	}

	public function test_staff_history_note_is_exported(): void
	{
		$this->full_booking();
		$all = implode("\n", $this->fields(BookingExporter::export('guest@example.test', 1)['data'][0]));

		$this->assertStringContainsString('Called the customer about the seat', $all);
	}

	/**
	 * Built with the real writer: `at` is a UTC Unix time, and the export
	 * shows it as a site date, not as a number (Codex r5).
	 */
	public function test_history_written_by_the_real_writer_shows_a_date(): void
	{
		$id = $this->booking(array('_mhmrentiva_customer_email' => 'real@example.test', '_mhmrentiva_status' => 'completed'));
		\MHMRentiva\Admin\Booking\Meta\BookingMeta::add_history_note($id, 'Called about the seat', 'note', 0, array('at' => 1767225600));

		$all = implode("\n", $this->fields(BookingExporter::export('real@example.test', 1)['data'][0]));

		$this->assertStringContainsString(wp_date(get_option('date_format'), 1767225600), $all);
		$this->assertStringNotContainsString('1767225600', $all);
		$this->assertStringContainsString('Called about the seat', $all);
	}

	public function test_legacy_customer_keys_are_exported(): void
	{
		$this->booking(array(
			'_mhmrentiva_booking_customer_email' => 'legacy@example.test',
			'_mhmrentiva_booking_customer_name'  => 'Eski Ad',
			'_mhmrentiva_contact_phone'          => '+90 1',
		));

		$all = implode("\n", $this->fields(BookingExporter::export('legacy@example.test', 1)['data'][0]));

		$this->assertStringContainsString('legacy@example.test', $all);
		$this->assertStringContainsString('Eski Ad', $all);
		$this->assertStringContainsString('+90 1', $all);
	}

	public function test_cancelled_by_roles_and_booking_logs_are_absent(): void
	{
		$this->full_booking();
		$all = implode("\n", $this->fields(BookingExporter::export('guest@example.test', 1)['data'][0]));

		$this->assertStringNotContainsString('administrator', $all);
		$this->assertStringNotContainsString('refund_processed', $all);
		$this->assertStringNotContainsString('processed_by', $all);
	}

	public function test_120_bookings_export_in_three_pages(): void
	{
		for ($i = 0; $i < 120; $i++) {
			$this->booking(array('_mhmrentiva_customer_email' => 'many@example.test', '_mhmrentiva_status' => 'completed'));
		}

		$seen = array();
		$done = array();
		for ($page = 1; $page <= 3; $page++) {
			$result = BookingExporter::export('many@example.test', $page);
			$done[] = $result['done'];
			foreach ($result['data'] as $item) {
				$seen[] = $item['item_id'];
			}
		}

		$this->assertSame(array(false, false, true), $done);
		$this->assertCount(120, array_unique($seen));
	}

	public function test_nobody_gets_an_empty_done_page(): void
	{
		$this->assertSame(array('data' => array(), 'done' => true), BookingExporter::export('nobody@example.test', 1));
	}
}
