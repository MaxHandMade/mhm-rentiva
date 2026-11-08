<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Emails\Core\Templates;
use WP_Ajax_UnitTestCase;

/**
 * "Send email to customer" on the booking edit screen (PR 3b-3): the types are
 * the Settings templates the customer also receives automatically -- booking
 * confirmation, booking reminder, cancellation -- plus a custom message that
 * starts empty. A template fills the box from the same context and the same
 * Settings text as the automatic e-mail; the operator may edit it for this one
 * send. "Payment reminder" left the box (user decision 2026-11-08): the payment
 * link goes out from the Payment management button only.
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta
 */
final class BookingCustomerEmailTemplateTest extends WP_Ajax_UnitTestCase
{
	/** @var string */
	protected $_last_response;

	private int $booking;

	/** @var list<array<string, mixed>> */
	private array $sent = array();

	/** @var list<array<int, mixed>> */
	private array $logged = array();

	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		$this->booking = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
			'meta_input'  => array(
				'_mhmrentiva_customer_email'      => 'fonk@example.test',
				'_mhmrentiva_customer_first_name' => 'Ayşe',
				'_mhmrentiva_customer_last_name'  => 'Yılmaz',
				'_mhmrentiva_pickup_date'         => '2099-05-10',
				'_mhmrentiva_dropoff_date'        => '2099-05-12',
			),
		));
		add_action('wp_ajax_mhmrentiva_get_email_template', array( BookingMeta::class, 'ajax_get_email_template' ));
		add_action('wp_ajax_mhmrentiva_send_customer_email', array( BookingMeta::class, 'ajax_send_customer_email' ));
		add_filter('pre_wp_mail', function ($short, array $atts) {
			unset($short);
			$this->sent[] = $atts;
			return true;
		}, 10, 2);
		add_action('mhmrentiva_email_sent', function (...$args) {
			$this->logged[] = $args;
		}, 10, 5);
	}

	public function tearDown(): void
	{
		$_POST = array();
		delete_option('mhmrentiva_booking_cancelled_body');
		parent::tearDown();
	}

	/** @return array<string, mixed> */
	private function ajax(string $action, array $post): array
	{
		$_POST = wp_slash($post);
		try {
			$this->_handleAjax($action);
		} catch (\WPAjaxDieContinueException $e) {
			unset($e);
		}
		$response             = json_decode((string) $this->_last_response, true);
		$this->_last_response = '';
		return is_array($response) ? $response : array( 'raw' => 'no json' );
	}

	private function fetch(string $type): array
	{
		return $this->ajax('mhmrentiva_get_email_template', array(
			'nonce'      => wp_create_nonce('mhmrentiva_send_email'),
			'booking_id' => (string) $this->booking,
			'email_type' => $type,
		));
	}

	private function send(string $type, string $subject = '', string $message = ''): array
	{
		return $this->ajax('mhmrentiva_send_customer_email', array(
			'mhmrentiva_email_nonce' => wp_create_nonce('mhmrentiva_send_email'),
			'booking_id'             => (string) $this->booking,
			'email_type'             => $type,
			'email_subject'          => $subject,
			'email_message'          => $message,
		));
	}

	/** @return iterable<string, array{0: string}> */
	public static function templates(): iterable
	{
		foreach (array( 'booking_created_customer', 'booking_reminder_customer', 'booking_cancelled' ) as $type) {
			yield $type => array( $type );
		}
	}

	/** @dataProvider templates */
	public function test_a_template_fills_the_box_with_the_automatic_texts(string $type): void
	{
		$response = $this->fetch($type);

		$this->assertTrue($response['success'] ?? false, wp_json_encode($response));
		$ctx = Mailer::getBookingContext($this->booking);
		// Codex #178 M4: not empty on its own, not only equal to the helper.
		$this->assertNotSame('', trim((string) $response['data']['subject']));
		$this->assertSame(Templates::compile_subject($type, $ctx), $response['data']['subject']);
		$this->assertStringNotContainsString('<html', (string) $response['data']['message']);
		$this->assertStringContainsString('Ayşe Yılmaz', (string) $response['data']['message']);
	}

	public function test_the_custom_message_starts_empty_and_is_refused_empty(): void
	{
		$response = $this->fetch('custom');
		$this->assertTrue($response['success'] ?? false);
		$this->assertSame('', $response['data']['subject']);
		$this->assertSame('', $response['data']['message']);

		$this->assertFalse($this->send('custom')['success'] ?? true);
		$this->assertSame(array(), $this->sent);
	}

	public function test_a_custom_message_goes_out_wrapped_once(): void
	{
		$response = $this->send('custom', 'Anahtar teslimi', '<p>Anahtarınız ofiste.</p>');

		$this->assertTrue($response['success'] ?? false, wp_json_encode($response));
		$this->assertSame('Anahtar teslimi', $this->sent[0]['subject']);
		$this->assertSame(1, substr_count(strtolower((string) $this->sent[0]['message']), '<html'));
		$this->assertStringContainsString('Anahtarınız ofiste.', (string) $this->sent[0]['message']);
		$this->assertSame('manual_customer', $this->logged[0][0] ?? null, 'The e-mail log records it.');
	}

	/** @return iterable<string, array{0: string}> */
	public static function refused_types(): iterable
	{
		foreach (array( 'payment_reminder', 'booking_confirmation', 'booking_reminder', 'remaining_payment_link_customer', 'no_such_type' ) as $type) {
			yield $type => array( $type );
		}
	}

	/** @dataProvider refused_types */
	public function test_other_types_are_refused(string $type): void
	{
		$this->assertFalse($this->fetch($type)['success'] ?? true);
		$this->assertFalse($this->send($type, 'x', '<p>x</p>')['success'] ?? true);
		$this->assertSame(array(), $this->sent);
	}

	public function test_a_cancellation_reads_the_reason_like_the_automatic_e_mail(): void
	{
		update_option('mhmrentiva_booking_cancelled_body', '<p>Neden: {{cancellation_reason}}</p>');
		update_post_meta($this->booking, '_mhmrentiva_cancellation_data', array( 'cancellation_reason' => 'Uçuş iptal' ));

		$this->assertStringContainsString('Neden: Uçuş iptal', (string) $this->fetch('booking_cancelled')['data']['message']);
		$this->assertTrue($this->send('booking_cancelled')['success'] ?? false);
		$this->assertStringContainsString('Neden: Uçuş iptal', (string) $this->sent[0]['message']);
	}

	/** Fable #178 D2: a cancellation e-mail for a booking not cancelled yet still reads the reason. */
	public function test_a_cancellation_e_mail_for_a_confirmed_booking_reads_the_reason(): void
	{
		update_post_meta($this->booking, '_mhmrentiva_status', 'confirmed');
		update_option('mhmrentiva_booking_cancelled_body', '<p>Neden: {{cancellation_reason}}</p>');
		update_post_meta($this->booking, '_mhmrentiva_cancellation_data', array( 'cancellation_reason' => 'Uçuş iptal' ));

		$this->assertStringContainsString('Neden: Uçuş iptal', (string) $this->fetch('booking_cancelled')['data']['message']);
	}

	public function test_an_unedited_template_sends_what_the_automatic_e_mail_sends(): void
	{
		$this->assertTrue($this->send('booking_created_customer')['success'] ?? false);

		list( $subject, $body ) = Mailer::render_in_site_locale('booking_created_customer', Mailer::getBookingContext($this->booking));
		$this->assertSame($subject, $this->sent[0]['subject']);
		$this->assertSame($body, $this->sent[0]['message']);
	}

	public function test_an_edited_message_goes_out(): void
	{
		$this->assertTrue($this->send('booking_reminder_customer', 'Yarın görüşürüz', '<p>Düzenlenmiş metin</p>')['success'] ?? false);

		$this->assertSame('Yarın görüşürüz', $this->sent[0]['subject']);
		$this->assertStringContainsString('Düzenlenmiş metin', (string) $this->sent[0]['message']);
		$this->assertSame('fonk@example.test', $this->sent[0]['to']);
	}

	/** Codex bot #178: the heading inside the e-mail is the subject that went out. */
	public function test_an_edited_subject_is_also_the_heading_inside_the_e_mail(): void
	{
		$this->assertTrue($this->send('booking_created_customer', 'Elle yazılan konu', '')['success'] ?? false);

		$this->assertSame('Elle yazılan konu', $this->sent[0]['subject']);
		$this->assertStringContainsString('Elle yazılan konu', (string) $this->sent[0]['message']);
		$ctx = Mailer::getBookingContext($this->booking);
		$this->assertStringNotContainsString(esc_html(Templates::compile_subject('booking_created_customer', $ctx)), (string) $this->sent[0]['message']);
	}

	public function test_a_sent_email_is_written_to_the_history(): void
	{
		$this->assertTrue($this->send('booking_cancelled')['success'] ?? false);

		$history = (array) get_post_meta($this->booking, '_mhmrentiva_booking_history', true);
		$last    = end($history);
		$this->assertSame('customer_contact', $last['type'] ?? null);
		$this->assertSame('E-mail sent to the customer: ' . $this->sent[0]['subject'], $last['note'] ?? null);
	}

	public function test_a_customer_who_turned_off_notifications_still_gets_it_and_the_history_says_so(): void
	{
		$user = (int) self::factory()->user->create(array( 'user_email' => 'fonk@example.test' ));
		update_user_meta($user, 'mhmrentiva_booking_notifications', '0');

		$this->assertTrue($this->send('booking_reminder_customer')['success'] ?? false);
		$this->assertCount(1, $this->sent);

		$history = (array) get_post_meta($this->booking, '_mhmrentiva_booking_history', true);
		$this->assertStringContainsString('turned off', (string) ( end($history)['note'] ?? '' ));
	}

	public function test_the_admin_post_twin_is_gone(): void
	{
		BookingMeta::register();

		$this->assertFalse(has_action('admin_post_mhmrentiva_send_customer_email'));
		$this->assertFalse(method_exists(BookingMeta::class, 'handle_send_customer_email'));
	}
}
