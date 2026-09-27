<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Frontend;

use MHMRentiva\Admin\Frontend\Shortcodes\ContactForm;
use MHMRentiva\Admin\Frontend\Shortcodes\ContactMessagePostType;
use ReflectionMethod;
use WP_UnitTestCase;

final class ContactFormIngestionTest extends WP_UnitTestCase
{
	/** @return mixed */
	private function call(string $method, array $args)
	{
		$m = new ReflectionMethod(ContactForm::class, $method);
		$m->setAccessible(true);
		return $m->invokeArgs(null, $args);
	}

	private function sanitized(array $overrides): array
	{
		return $this->call('sanitize_contact_form_data', array( array_merge(array(
			'type' => 'general', 'name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Hi',
		), $overrides) ));
	}

	public function test_unknown_type_falls_back_to_general(): void
	{
		$this->assertSame('general', $this->sanitized(array( 'type' => 'evil<b>' ))['type']);
		$this->assertSame('support', $this->sanitized(array( 'type' => 'support' ))['type']);
	}

	public function test_priority_rating_and_date_are_allowlisted(): void
	{
		$d = $this->sanitized(array( 'priority' => 'urgent', 'rating' => 9, 'preferred_date' => 'next friday' ));
		$this->assertSame('', $d['priority']);
		$this->assertSame(5, $d['rating']);
		$this->assertSame('', $d['preferred_date']);

		$ok = $this->sanitized(array( 'priority' => 'high', 'rating' => 3, 'preferred_date' => '2026-09-29' ));
		$this->assertSame(array( 'high', 3, '2026-09-29' ), array( $ok['priority'], $ok['rating'], $ok['preferred_date'] ));
	}

	public function test_vehicle_id_must_point_at_a_vehicle(): void
	{
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
		$vehicle = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle' ));
		$this->assertSame(0, $this->sanitized(array( 'vehicle_id' => $booking ))['vehicle_id']);
		$this->assertSame($vehicle, $this->sanitized(array( 'vehicle_id' => $vehicle ))['vehicle_id']);
	}

	public function test_attachment_text_from_the_request_is_ignored(): void
	{
		$this->assertSame('', $this->sanitized(array( 'attachment' => 'https://example.com/wp-content/uploads/woocommerce_uploads/x.zip' ))['attachment']);
	}

	public function test_upload_over_the_cap_or_with_an_error_is_rejected_with_its_own_message(): void
	{
		// A cap far below PHP's limit, so the plugin's own check -- not
		// wp_max_upload_size() -- is what rejects (before: the same input passes
		// through to wp_handle_upload() and fails with core's own message).
		add_filter('mhmrentiva_contact_attachment_max_bytes', static fn() => 100);
		require_once ABSPATH . 'wp-admin/includes/file.php'; // wp_tempnam(); house pattern VehicleColumnsLayoutTest:70
		$tmp = wp_tempnam('cap');
		file_put_contents($tmp, str_repeat('x', 101));

		try {
			$big = $this->call('handle_file_upload', array( array(
				'name' => 'a.pdf', 'type' => 'application/pdf', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => 101,
			) ));
			$this->assertSame('File size is too large.', $big['message']);

			$err = $this->call('handle_file_upload', array( array(
				'name' => 'a.pdf', 'type' => 'application/pdf', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_PARTIAL, 'size' => 10,
			) ));
			$this->assertSame('The file could not be uploaded.', $err['message']);

			$empty = $this->call('handle_file_upload', array( array(
				'name' => 'a.pdf', 'type' => 'application/pdf', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => 0,
			) ));
			$this->assertSame('The file is empty.', $empty['message']);
		} finally {
			remove_all_filters('mhmrentiva_contact_attachment_max_bytes');
			@unlink($tmp); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- test temp file.
		}
	}

	public function test_the_cap_filter_cannot_exceed_the_php_limit(): void
	{
		add_filter('mhmrentiva_contact_attachment_max_bytes', static fn() => PHP_INT_MAX);
		$m = new ReflectionMethod(ContactForm::class, 'max_attachment_bytes');
		$m->setAccessible(true);
		$this->assertLessThanOrEqual(wp_max_upload_size(), $m->invoke(null));
		remove_all_filters('mhmrentiva_contact_attachment_max_bytes');
	}

	public function test_admin_email_has_a_panel_link_and_no_ip(): void
	{
		$data = $this->sanitized(array());
		$data['ip_address'] = '203.0.113.48';
		$html = $this->call('build_email_message', array( $data, array( 'title' => 'General Contact' ), 4242 ));
		$this->assertStringNotContainsString('203.0.113.48', $html);
		$this->assertStringContainsString(esc_url(ContactMessagePostType::admin_detail_url(4242)), $html);
	}

	public function test_type_label_maps_known_and_unknown_types(): void
	{
		$this->assertSame('Technical Support', ContactMessagePostType::type_label('support'));
		$this->assertSame('General Contact', ContactMessagePostType::type_label('whatever'));
	}
}
