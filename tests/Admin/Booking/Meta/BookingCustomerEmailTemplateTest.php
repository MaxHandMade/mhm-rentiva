<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use WP_Ajax_UnitTestCase;

/**
 * "Send email to customer" on the booking edit screen: every type in the
 * select has a default subject and message, so choosing one fills both and
 * sending it works. "Booking cancelled" had a message but no subject: the
 * template came back with `subject: null` and sending it ended in a fatal
 * TypeError (Templates::wrapWithLayout(), string $subject) -- found by the
 * booking edit screen's functional sweep (H1, 2026-11-04).
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

	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		$this->booking = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		update_post_meta($this->booking, '_mhmrentiva_customer_email', 'fonk@example.test');
		update_post_meta($this->booking, '_mhmrentiva_customer_name', 'Ayşe Yılmaz');
		add_action('wp_ajax_mhmrentiva_get_email_template', array( BookingMeta::class, 'ajax_get_email_template' ));
		add_action('wp_ajax_mhmrentiva_send_customer_email', array( BookingMeta::class, 'ajax_send_customer_email' ));
		// Nothing leaves the test: record the message, answer "sent".
		add_filter('pre_wp_mail', function ($short, array $atts) {
			unset($short);
			$this->sent[] = $atts;
			return true;
		}, 10, 2);
	}

	public function tearDown(): void
	{
		$_POST = array();
		parent::tearDown();
	}

	/** @return array<string, mixed> */
	private function ajax(string $action, array $post): array
	{
		$_POST = $post;
		try {
			$this->_handleAjax($action);
		} catch (\WPAjaxDieContinueException $e) {
			unset($e);
		}
		$response             = json_decode((string) $this->_last_response, true);
		$this->_last_response = '';
		return is_array($response) ? $response : array( 'raw' => 'no json' );
	}

	/** @return iterable<string, array{0: string}> */
	public static function types(): iterable
	{
		foreach (array( 'booking_confirmation', 'payment_reminder', 'booking_reminder', 'booking_cancelled', 'custom' ) as $type) {
			yield $type => array( $type );
		}
	}

	/**
	 * @dataProvider types
	 */
	public function test_every_type_fills_a_subject_and_a_message(string $type): void
	{
		$response = $this->ajax('mhmrentiva_get_email_template', array(
			'nonce'      => wp_create_nonce('mhmrentiva_send_email'),
			'booking_id' => (string) $this->booking,
			'email_type' => $type,
		));

		$this->assertTrue($response['success'] ?? false, wp_json_encode($response));
		$this->assertIsString($response['data']['subject'] ?? null);
		$this->assertNotSame('', trim((string) $response['data']['subject']));
		$this->assertNotSame('', trim((string) ( $response['data']['message'] ?? '' )));
	}

	public function test_a_cancellation_email_can_be_sent(): void
	{
		$response = $this->ajax('mhmrentiva_send_customer_email', array(
			'mhmrentiva_email_nonce' => wp_create_nonce('mhmrentiva_send_email'),
			'booking_id'             => (string) $this->booking,
			'email_type'             => 'booking_cancelled',
			'email_subject'          => '',
			'email_message'          => '',
		));

		$this->assertTrue($response['success'] ?? false, wp_json_encode($response));
		$this->assertCount(1, $this->sent);
		$this->assertNotSame('', trim((string) $this->sent[0]['subject']));
	}

	public function test_an_unknown_type_is_refused_not_a_server_error(): void
	{
		$response = $this->ajax('mhmrentiva_get_email_template', array(
			'nonce'      => wp_create_nonce('mhmrentiva_send_email'),
			'booking_id' => (string) $this->booking,
			'email_type' => 'no_such_type',
		));

		$this->assertFalse($response['success'] ?? true, wp_json_encode($response));
	}
}
