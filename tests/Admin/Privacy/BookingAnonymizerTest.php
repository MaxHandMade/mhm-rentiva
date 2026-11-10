<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Privacy;

use MHMRentiva\Admin\Privacy\BookingAnonymizer;
use MHMRentiva\Admin\Privacy\BookingMatcher;
use WP_UnitTestCase;

/**
 * Anonymising one closed booking and its order-line copy (5a spec T5, T4 order).
 *
 * @covers \MHMRentiva\Admin\Privacy\BookingAnonymizer
 */
final class BookingAnonymizerTest extends WP_UnitTestCase
{
	private function booking(array $meta, array $post = array()): int
	{
		$id = (int) self::factory()->post->create(array_merge(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		), $post));
		foreach ($meta as $key => $value) {
			update_post_meta($id, $key, $value);
		}

		return $id;
	}

	private function closed_guest(array $extra = array(), array $post = array()): int
	{
		return $this->booking(array_merge(array(
			'_mhmrentiva_customer_user_id'    => 0,
			'_mhmrentiva_customer_email'      => 'guest@example.test',
			'_mhmrentiva_customer_name'       => 'Ahmet Yılmaz',
			'_mhmrentiva_customer_first_name' => 'Ahmet',
			'_mhmrentiva_customer_last_name'  => 'Yılmaz',
			'_mhmrentiva_customer_phone'      => '+90 555 000 0000',
			'_mhmrentiva_status'              => 'completed',
			'_mhmrentiva_total_price'         => '300.00',
			'_mhmrentiva_pickup_date'         => '2026-12-01',
			'_mhmrentiva_return_date'         => '2026-12-04',
			'_mhmrentiva_vehicle_id'          => 77,
			'_mhmrentiva_payment_status'      => 'paid',
		), $extra), $post);
	}

	public function test_customer_fields_follow_core_anonymiser(): void
	{
		$id = $this->closed_guest();

		$this->assertSame(array(), BookingAnonymizer::anonymize($id, 0));
		$this->assertSame(wp_privacy_anonymize_data('text'), get_post_meta($id, '_mhmrentiva_customer_name', true));
		$this->assertSame(wp_privacy_anonymize_data('text'), get_post_meta($id, '_mhmrentiva_customer_first_name', true));
		$this->assertSame(wp_privacy_anonymize_data('email'), get_post_meta($id, '_mhmrentiva_customer_email', true));
		$this->assertSame('', get_post_meta($id, '_mhmrentiva_customer_phone', true));
		$this->assertSame('0', (string) get_post_meta($id, '_mhmrentiva_customer_user_id', true));
		$this->assertNotSame('', get_post_meta($id, '_mhmrentiva_personal_data_erased', true));
		$this->assertFalse(BookingMatcher::matches($id, 'guest@example.test'));
	}

	public function test_free_text_keys_and_post_content_are_gone(): void
	{
		$id = $this->closed_guest(array(
			'_mhmrentiva_special_notes'                  => 'Child seat',
			'_mhmrentiva_refund_reason'                  => 'Plans changed',
			'_mhmrentiva_refund_completed_reference'     => 'REF',
			'_mhmrentiva_refund_review_dismissed_reason' => 'Outside policy',
			'_mhmrentiva_booking_customer_name'          => 'Legacy Name',
			'_mhmrentiva_booking_customer_phone'         => '+90 1',
			'_mhmrentiva_contact_phone'                  => '+90 2',
		), array('post_content' => 'Booked by phone'));

		BookingAnonymizer::anonymize($id, 0);

		foreach (array('_mhmrentiva_special_notes', '_mhmrentiva_refund_reason', '_mhmrentiva_refund_completed_reference', '_mhmrentiva_refund_review_dismissed_reason', '_mhmrentiva_booking_customer_phone', '_mhmrentiva_contact_phone') as $key) {
			$this->assertSame('', get_post_meta($id, $key, true), $key);
		}
		$this->assertSame(wp_privacy_anonymize_data('text'), get_post_meta($id, '_mhmrentiva_booking_customer_name', true));
		$this->assertSame('', get_post_field('post_content', $id));
	}

	public function test_cancellation_keeps_only_cancelled_via(): void
	{
		$id = $this->closed_guest(array('_mhmrentiva_cancellation_data' => array(
			'cancelled_by'        => 1,
			'cancelled_by_roles'  => array('administrator'),
			'cancelled_via'       => 'admin',
			'cancellation_reason' => 'Customer asked',
			'cancelled_at'        => '2026-11-01 10:00:00',
		)));

		BookingAnonymizer::anonymize($id, 0);

		$data = get_post_meta($id, '_mhmrentiva_cancellation_data', true);
		$this->assertSame('admin', $data['cancelled_via']);
		$this->assertArrayNotHasKey('cancellation_reason', $data);
		$this->assertArrayNotHasKey('cancelled_by', $data);
		$this->assertArrayNotHasKey('cancelled_by_roles', $data);
	}

	public function test_receipt_attachment_and_four_meta_are_deleted(): void
	{
		$attachment = (int) self::factory()->attachment->create(array('post_mime_type' => 'image/png'));
		$id         = $this->closed_guest(array(
			'_mhmrentiva_receipt_attachment_id' => $attachment,
			'_mhmrentiva_receipt_uploaded_by'   => 5,
			'_mhmrentiva_receipt_uploaded_at'   => '2026-11-01',
			'_mhmrentiva_receipt_note'          => 'Checked',
		));

		$this->assertSame(array(), BookingAnonymizer::anonymize($id, 0));
		$this->assertNull(get_post($attachment));
		foreach (array('_mhmrentiva_receipt_attachment_id', '_mhmrentiva_receipt_uploaded_by', '_mhmrentiva_receipt_uploaded_at', '_mhmrentiva_receipt_note') as $key) {
			$this->assertSame('', get_post_meta($id, $key, true), $key);
		}
	}

	public function test_author_cleared_when_it_is_the_bookings_customer_even_for_an_old_email(): void
	{
		$user = (int) self::factory()->user->create(array('user_email' => 'new@example.test'));
		$id   = $this->closed_guest(array(
			'_mhmrentiva_customer_user_id' => $user,
			'_mhmrentiva_customer_email'   => 'old@example.test',
		), array('post_author' => $user));

		BookingAnonymizer::anonymize($id, 0);

		$this->assertSame('0', (string) get_post_field('post_author', $id));
	}

	public function test_admin_author_is_kept(): void
	{
		$admin = (int) self::factory()->user->create(array('role' => 'administrator'));
		$id    = $this->closed_guest(array(), array('post_author' => $admin));

		BookingAnonymizer::anonymize($id, 0);

		$this->assertSame((string) $admin, (string) get_post_field('post_author', $id));
	}

	public function test_history_keeps_status_change_and_structured_payment_update_with_empty_note(): void
	{
		$id = $this->closed_guest(array('_mhmrentiva_booking_history' => array(
			'1-a' => array('type' => 'status_change', 'note' => 'Status changed from Pending to Completed', 'status_from' => 'pending', 'status_to' => 'completed', 'user_id' => 3, 'timestamp' => 1),
			'2-b' => array('type' => 'payment_update', 'note' => 'Payment: Pending → Paid', 'payment_from' => 'pending', 'payment_to' => 'paid', 'user_id' => 3, 'timestamp' => 2),
			'3-c' => array('type' => 'payment_update', 'note' => 'Recorded that no refund is due. Reason: called Ahmet', 'user_id' => 3, 'timestamp' => 3),
			'4-d' => array('type' => 'note', 'note' => 'Ahmet asked for a seat', 'user_id' => 3, 'timestamp' => 4),
			'5-e' => array('type' => 'customer_contact', 'note' => 'E-mail sent', 'user_id' => 3, 'timestamp' => 5),
			'6-f' => array('type' => 'system', 'note' => 'Booking created', 'user_id' => 0, 'timestamp' => 6),
			'7-g' => array('type' => 'manual', 'note' => 'Manual', 'user_id' => 3, 'timestamp' => 7),
		)));

		BookingAnonymizer::anonymize($id, 0);

		$history = get_post_meta($id, '_mhmrentiva_booking_history', true);
		$this->assertSame(array('1-a', '2-b'), array_keys($history));
		$this->assertSame('Status changed from Pending to Completed', $history['1-a']['note']);
		$this->assertSame('', $history['2-b']['note']);
		$this->assertSame('paid', $history['2-b']['payment_to']);
		$this->assertSame(0, $history['1-a']['user_id']);
		$this->assertSame(0, $history['2-b']['user_id']);
	}

	public function test_logs_keep_only_the_allowlist(): void
	{
		$id = $this->closed_guest(array('_mhmrentiva_booking_logs' => array(
			array('action' => 'manual_refund_closed', 'timestamp' => '2026-11-01', 'user_id' => 3, 'data' => array('reference' => 'TR1', 'processed_by' => 3, 'refund_amount' => '50.00')),
			array('action' => 'wc_status_change', 'timestamp' => '2026-11-02', 'user_id' => 0, 'data' => array('order_id' => 9, 'old_status' => 'pending', 'new_status' => 'completed')),
		)));

		BookingAnonymizer::anonymize($id, 0);

		$logs = get_post_meta($id, '_mhmrentiva_booking_logs', true);
		$this->assertSame(array('action' => 'manual_refund_closed', 'timestamp' => '2026-11-01', 'data' => array('refund_amount' => '50.00')), $logs[0]);
		$this->assertSame(array('order_id' => 9, 'old_status' => 'pending', 'new_status' => 'completed'), $logs[1]['data']);
		$this->assertArrayNotHasKey('user_id', $logs[1]);
	}

	private function order_with_lines(array $lines): array
	{
		$order = wc_create_order(array('status' => 'completed'));
		$ids   = array();
		foreach ($lines as $booking_id => $snapshot) {
			$item = new \WC_Order_Item_Product();
			$item->set_name('Rental');
			$item->add_meta_data('_mhmrentiva_booking_id', $booking_id, true);
			$item->add_meta_data('_mhmrentiva_booking_data', $snapshot, true);
			$order->add_item($item);
			$item->save();
			$ids[ $booking_id ] = (int) $item->get_id();
		}
		$order->save();

		return $ids;
	}

	private function snapshot(string $email): array
	{
		return array('vehicle_id' => 77, 'customer_user_id' => 4, 'customer_name' => 'Ahmet Yılmaz', 'customer_first_name' => 'Ahmet', 'customer_last_name' => 'Yılmaz', 'customer_email' => $email, 'customer_phone' => '+90 5', 'total_price' => '300.00');
	}

	public function test_order_item_snapshot_for_this_booking_only(): void
	{
		$a     = $this->closed_guest();
		$b     = $this->closed_guest(array('_mhmrentiva_customer_email' => 'other@example.test'));
		$items = $this->order_with_lines(array($a => $this->snapshot('guest@example.test'), $b => $this->snapshot('other@example.test')));

		$this->assertSame(array(), BookingAnonymizer::anonymize($a, 0));

		$snap_a = wc_get_order_item_meta($items[ $a ], '_mhmrentiva_booking_data', true);
		$snap_b = wc_get_order_item_meta($items[ $b ], '_mhmrentiva_booking_data', true);
		$this->assertSame(wp_privacy_anonymize_data('email'), $snap_a['customer_email']);
		$this->assertSame(wp_privacy_anonymize_data('text'), $snap_a['customer_name']);
		$this->assertSame('', $snap_a['customer_phone']);
		$this->assertSame(0, $snap_a['customer_user_id']);
		$this->assertSame('300.00', $snap_a['total_price']);
		$this->assertSame($this->snapshot('other@example.test'), $snap_b);
	}

	public function test_legacy_string_snapshot_is_handled(): void
	{
		$a     = $this->closed_guest();
		$items = $this->order_with_lines(array($a => serialize($this->snapshot('guest@example.test'))));

		$this->assertSame(array(), BookingAnonymizer::anonymize($a, 0));

		$snap = maybe_unserialize(wc_get_order_item_meta($items[ $a ], '_mhmrentiva_booking_data', true));
		$this->assertSame(wp_privacy_anonymize_data('email'), $snap['customer_email']);
	}

	public function test_amounts_dates_vehicle_status_are_unchanged(): void
	{
		$id = $this->closed_guest();

		BookingAnonymizer::anonymize($id, 0);

		$this->assertSame('300.00', get_post_meta($id, '_mhmrentiva_total_price', true));
		$this->assertSame('2026-12-01', get_post_meta($id, '_mhmrentiva_pickup_date', true));
		$this->assertSame('2026-12-04', get_post_meta($id, '_mhmrentiva_return_date', true));
		$this->assertSame('77', (string) get_post_meta($id, '_mhmrentiva_vehicle_id', true));
		$this->assertSame('completed', get_post_meta($id, '_mhmrentiva_status', true));
		$this->assertSame('paid', get_post_meta($id, '_mhmrentiva_payment_status', true));
	}

	public function test_identity_is_kept_when_another_target_fails(): void
	{
		$id    = $this->closed_guest();
		$block = static function ($check, $object_id, $meta_key) use ($id) {
			return ((int) $object_id === $id && '_mhmrentiva_customer_phone' === $meta_key) ? false : $check;
		};
		add_filter('delete_post_metadata', $block, 10, 3);

		$left = BookingAnonymizer::anonymize($id, 0);

		remove_filter('delete_post_metadata', $block, 10);
		$this->assertSame(array('customer_phone'), $left);
		$this->assertSame('guest@example.test', get_post_meta($id, '_mhmrentiva_customer_email', true));
		$this->assertTrue(BookingMatcher::matches($id, 'guest@example.test'));
		$this->assertSame('', get_post_meta($id, '_mhmrentiva_personal_data_erased', true));
	}

	/**
	 * wp_delete_attachment() can drop the post and leave the file (Codex r4 B4):
	 * the receipt counts as erased only when the file is gone, and until then
	 * the booking keeps matching its person.
	 */
	public function test_a_receipt_file_left_on_disk_keeps_the_booking_findable(): void
	{
		$upload = wp_upload_bits('receipt-' . wp_generate_password(6, false) . '.png', null, 'png-bytes');
		$this->assertEmpty($upload['error']);
		$attachment = (int) self::factory()->attachment->create_object($upload['file'], 0, array('post_mime_type' => 'image/png'));
		$id         = $this->closed_guest(array('_mhmrentiva_receipt_attachment_id' => $attachment));
		$keep_file  = static fn() => '';
		add_filter('wp_delete_file', $keep_file);

		$left = BookingAnonymizer::anonymize($id, 0);

		remove_filter('wp_delete_file', $keep_file);
		$this->assertContains('receipt', $left);
		$this->assertFileExists($upload['file']);
		$this->assertTrue(BookingMatcher::matches($id, 'guest@example.test'));
		wp_delete_file($upload['file']);
	}

	/**
	 * A second row of the same key holds the real name (Codex r5): every row
	 * is anonymised and checked, not only the first.
	 */
	public function test_every_row_of_a_name_key_is_anonymised(): void
	{
		$id = $this->closed_guest();
		update_post_meta($id, '_mhmrentiva_customer_name', wp_privacy_anonymize_data('text'));
		add_post_meta($id, '_mhmrentiva_customer_name', 'Ahmet Yılmaz');

		$this->assertSame(array(), BookingAnonymizer::anonymize($id, 0));

		$rows = get_post_meta($id, '_mhmrentiva_customer_name', false);
		$this->assertCount(2, $rows);
		$this->assertSame(array(wp_privacy_anonymize_data('text')), array_values(array_unique($rows)));
	}

	/**
	 * The erased mark is what takes a booking out of the queue for core's
	 * fixed anonymous address; if it cannot be written the booking is not done
	 * (Codex r5 B2).
	 */
	public function test_an_unwritable_erased_mark_is_a_target_left(): void
	{
		$id    = $this->closed_guest();
		$block = static fn($check, $object_id, $meta_key) => ((int) $object_id === $id && '_mhmrentiva_personal_data_erased' === $meta_key) ? false : $check;
		add_filter('add_post_metadata', $block, 10, 3);
		add_filter('update_post_metadata', $block, 10, 3);

		$left = BookingAnonymizer::anonymize($id, 0, 'guest@example.test');

		remove_filter('add_post_metadata', $block, 10);
		remove_filter('update_post_metadata', $block, 10);
		$this->assertSame(array('erased_mark'), $left);
		$this->assertNotSame('', get_post_meta($id, '_mhmrentiva_privacy_subject', true));
	}

	/**
	 * Image receipts have generated sizes; every file must go before the link
	 * does (Codex r5).
	 */
	public function test_a_receipt_size_file_left_on_disk_keeps_the_link(): void
	{
		$upload = wp_upload_bits('receipt-' . wp_generate_password(6, false) . '.png', null, 'png-bytes');
		$attachment = (int) self::factory()->attachment->create_object($upload['file'], 0, array('post_mime_type' => 'image/png'));
		$size_file  = dirname($upload['file']) . '/' . pathinfo($upload['file'], PATHINFO_FILENAME) . '-150x150.png';
		file_put_contents($size_file, 'png-size');
		wp_update_attachment_metadata($attachment, array('file' => _wp_relative_upload_path($upload['file']), 'sizes' => array('thumbnail' => array('file' => basename($size_file), 'width' => 150, 'height' => 150, 'mime-type' => 'image/png'))));
		$id   = $this->closed_guest(array('_mhmrentiva_receipt_attachment_id' => $attachment));
		$keep = static fn($file) => (basename($file) === basename($size_file)) ? '' : $file;
		add_filter('wp_delete_file', $keep);

		$left = BookingAnonymizer::anonymize($id, 0);

		remove_filter('wp_delete_file', $keep);
		$this->assertContains('receipt', $left);
		$this->assertNotNull(get_post($attachment));
		$this->assertSame((string) $attachment, (string) get_post_meta($id, '_mhmrentiva_receipt_attachment_id', true));
		wp_delete_file($size_file);
		wp_delete_file($upload['file']);
	}

	/**
	 * A second meta row of the same key can hold the person's data while the
	 * first is clean (Codex r6): every row is rewritten and checked.
	 */
	public function test_every_row_of_history_cancellation_and_logs_is_cleaned(): void
	{
		$id = $this->closed_guest();
		add_post_meta($id, '_mhmrentiva_booking_history', array('1-a' => array('type' => 'status_change', 'note' => 'ok', 'status_to' => 'completed', 'user_id' => 0, 'timestamp' => 1)));
		add_post_meta($id, '_mhmrentiva_booking_history', array('2-b' => array('type' => 'note', 'note' => 'Called Ahmet', 'user_id' => 3, 'timestamp' => 2)));
		add_post_meta($id, '_mhmrentiva_cancellation_data', array('cancelled_via' => 'admin'));
		add_post_meta($id, '_mhmrentiva_cancellation_data', array('cancelled_via' => 'admin', 'cancellation_reason' => 'Ahmet asked'));
		add_post_meta($id, '_mhmrentiva_booking_logs', array(array('action' => 'x', 'timestamp' => 't')));
		add_post_meta($id, '_mhmrentiva_booking_logs', array(array('action' => 'y', 'timestamp' => 't', 'data' => array('reason' => 'Ahmet'))));

		$this->assertSame(array(), BookingAnonymizer::anonymize($id, 0));

		$all = (string) wp_json_encode(array(
			get_post_meta($id, '_mhmrentiva_booking_history', false),
			get_post_meta($id, '_mhmrentiva_cancellation_data', false),
			get_post_meta($id, '_mhmrentiva_booking_logs', false),
		));
		$this->assertStringNotContainsString('Ahmet', $all);
	}

	/**
	 * The meta API unslashes what it stores: a status note kept through the
	 * rewrite keeps its backslash (the Pro "Fiat \ Egea" lesson).
	 */
	public function test_a_kept_history_note_keeps_its_backslash(): void
	{
		$id  = $this->closed_guest();
		$row = array('1-a' => array('type' => 'status_change', 'note' => 'Status changed for Fiat \ Egea', 'status_to' => 'completed', 'user_id' => 3, 'timestamp' => 1));
		update_post_meta($id, '_mhmrentiva_booking_history', wp_slash($row));
		$this->assertSame($row, get_post_meta($id, '_mhmrentiva_booking_history', true), 'the fixture keeps the backslash');

		$this->assertSame(array(), BookingAnonymizer::anonymize($id, 0));

		$history = get_post_meta($id, '_mhmrentiva_booking_history', true);
		$this->assertSame(0, $history['1-a']['user_id']);
		$this->assertSame('Status changed for Fiat \ Egea', $history['1-a']['note']);
	}

	/**
	 * An edited image keeps its pre-edit files under _wp_attachment_backup_sizes;
	 * they are the person's receipt too (Codex r6).
	 */
	public function test_a_receipt_edit_backup_left_on_disk_keeps_the_link(): void
	{
		$upload     = wp_upload_bits('receipt-' . wp_generate_password(6, false) . '.png', null, 'png-bytes');
		$attachment = (int) self::factory()->attachment->create_object($upload['file'], 0, array('post_mime_type' => 'image/png'));
		$backup     = dirname($upload['file']) . '/' . pathinfo($upload['file'], PATHINFO_FILENAME) . '-e1700000000.png';
		file_put_contents($backup, 'png-original');
		update_post_meta($attachment, '_wp_attachment_backup_sizes', array('full-orig' => array('file' => basename($backup), 'width' => 10, 'height' => 10)));
		$id   = $this->closed_guest(array('_mhmrentiva_receipt_attachment_id' => $attachment));
		$keep = static fn($file) => (basename($file) === basename($backup)) ? '' : $file;
		add_filter('wp_delete_file', $keep);

		$left = BookingAnonymizer::anonymize($id, 0);

		remove_filter('wp_delete_file', $keep);
		$this->assertContains('receipt', $left);
		$this->assertFileExists($backup);
		$this->assertSame((string) $attachment, (string) get_post_meta($id, '_mhmrentiva_receipt_attachment_id', true));
		wp_delete_file($backup);
		wp_delete_file($upload['file']);
	}

	public function test_every_row_of_an_order_line_snapshot_is_cleaned(): void
	{
		$a     = $this->closed_guest();
		$items = $this->order_with_lines(array($a => $this->snapshot('guest@example.test')));
		wc_add_order_item_meta($items[ $a ], '_mhmrentiva_booking_data', array_merge($this->snapshot('guest@example.test'), array('total_price' => '999.00')));

		$this->assertSame(array(), BookingAnonymizer::anonymize($a, 0));

		$rows = (array) wc_get_order_item_meta($items[ $a ], '_mhmrentiva_booking_data', false);
		$this->assertCount(2, $rows);
		$this->assertStringNotContainsString('guest@example.test', (string) wp_json_encode($rows));
		$this->assertSame(array('300.00', '999.00'), array_column($rows, 'total_price'), 'each row keeps its own amounts');
	}

	public function test_first_erased_timestamp_is_kept(): void
	{
		$id = $this->closed_guest();
		BookingAnonymizer::anonymize($id, 0);
		update_post_meta($id, '_mhmrentiva_personal_data_erased', '2020-01-01T00:00:00+00:00');

		BookingAnonymizer::anonymize($id, 0);

		$this->assertSame('2020-01-01T00:00:00+00:00', get_post_meta($id, '_mhmrentiva_personal_data_erased', true));
	}

	public function test_running_twice_is_harmless(): void
	{
		$id = $this->closed_guest(array('_mhmrentiva_cancellation_data' => array('cancelled_via' => 'admin', 'cancellation_reason' => 'x')));

		$this->assertSame(array(), BookingAnonymizer::anonymize($id, 0));
		$first = get_post_meta($id);
		unset($first['_mhmrentiva_personal_data_erased']);

		$this->assertSame(array(), BookingAnonymizer::anonymize($id, 0));
		$second = get_post_meta($id);
		unset($second['_mhmrentiva_personal_data_erased']);

		$this->assertSame($first, $second);
	}

	/**
	 * Four meta keys have carried the order ID; the resolver knows them all and
	 * the privacy notes must too (OrderIdMetaKeyResolutionTest).
	 */
	public function test_has_linked_order_reads_every_order_id_key(): void
	{
		$legacy = $this->closed_guest(array('_mhmrentiva_woocommerce_order_id' => 321));

		$this->assertTrue(BookingAnonymizer::has_linked_order($legacy));
		$this->assertTrue(\MHMRentiva\Admin\Privacy\BookingMatcher::any_linked_order('guest@example.test'));
	}

	public function test_has_linked_order_for_a_remaining_payment_link_without_snapshot(): void
	{
		$none   = $this->closed_guest();
		$direct = $this->closed_guest(array('_mhmrentiva_wc_order_id' => 123));
		$line   = $this->closed_guest();
		$order  = wc_create_order(array('status' => 'pending'));
		$item   = new \WC_Order_Item_Fee();
		$item->set_name('Remaining payment');
		$item->add_meta_data('_mhmrentiva_booking_id', $line, true);
		$order->add_item($item);
		$order->save();

		$this->assertFalse(BookingAnonymizer::has_linked_order($none));
		$this->assertTrue(BookingAnonymizer::has_linked_order($direct));
		$this->assertTrue(BookingAnonymizer::has_linked_order($line));
	}
}
