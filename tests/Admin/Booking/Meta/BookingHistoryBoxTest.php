<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use WP_Ajax_UnitTestCase;

/**
 * The booking history (spec PR 3a): written but never registered, so the
 * edit screen had no history. PR 3a put it in the side column; PR 3b-1
 * (canvas v135) draws it inside the Payment Management box instead. Its note field
 * is no longer `required` (inside the post form that blocked Update), an
 * empty note is refused only by "Add note", and the box says the notes travel
 * with the customer's personal data export (K4).
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta
 */
final class BookingHistoryBoxTest extends WP_Ajax_UnitTestCase
{
	/** @var string */
	protected $_last_response;

	private int $booking;

	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		$this->booking = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		// Not BookingMeta::register(): its static guard runs it once per
		// process, while the suite restores hooks after every test.
		add_action('wp_ajax_mhmrentiva_add_booking_history_note', array( BookingMeta::class, 'ajax_add_booking_history_note' ));
	}

	public function tearDown(): void
	{
		global $wp_meta_boxes, $post, $pagenow;
		$wp_meta_boxes = array();
		$post          = null;
		$pagenow       = 'index.php';
		$_POST         = array();
		parent::tearDown();
	}

	private function boxes_on(string $screen_page): array
	{
		global $wp_meta_boxes, $post, $pagenow;
		$wp_meta_boxes = array();
		$post          = get_post($this->booking);
		$pagenow       = $screen_page;
		BookingMeta::add_meta_boxes();
		return $wp_meta_boxes['mhmrentiva_booking']['side'] ?? array();
	}

	private function html(): string
	{
		ob_start();
		BookingMeta::render_booking_history_box(get_post($this->booking));
		return (string) ob_get_clean();
	}

	public function test_history_is_not_a_box_of_its_own_on_the_edit_screen(): void
	{
		$side = $this->boxes_on('post.php');
		$this->assertArrayNotHasKey('mhmrentiva_booking_history_box', $side['default'] ?? array());
	}

	public function test_history_is_drawn_inside_the_payment_box_after_the_timeline_before_the_actions(): void
	{
		foreach (array(
			'_mhmrentiva_payment_type'   => 'deposit',
			'_mhmrentiva_payment_status' => 'pending',
			'_mhmrentiva_status'         => 'pending',
			'_mhmrentiva_total_price'    => '5850',
			'_mhmrentiva_deposit_amount' => '585',
		) as $key => $value) {
			update_post_meta($this->booking, $key, $value);
		}
		ob_start();
		\MHMRentiva\Admin\Booking\Meta\BookingDepositMetaBox::render_deposit_management(get_post($this->booking));
		$html = (string) ob_get_clean();

		$history  = strpos($html, 'mhm-add-history-btn');
		$timeline = strpos($html, 'deposit-timeline');
		$action   = strpos($html, 'id="approve-payment"');
		$this->assertIsInt($history);
		$this->assertIsInt($timeline);
		$this->assertIsInt($action);
		$this->assertGreaterThan($timeline, $history);
		$this->assertLessThan($action, $history);
	}

	public function test_a_booking_without_payment_type_keeps_its_history(): void
	{
		// The payment box returns before its payment sections on a pre-deposit
		// booking; the history (a box of its own until PR 3b-1) must not go
		// with them (Codex plan audit 3b-1, finding 1).
		delete_post_meta($this->booking, '_mhmrentiva_payment_type');
		ob_start();
		\MHMRentiva\Admin\Booking\Meta\BookingDepositMetaBox::render_deposit_management(get_post($this->booking));
		$html = (string) ob_get_clean();

		$this->assertStringContainsString('mhm-add-history-btn', $html);
		$this->assertStringContainsString('mhmrentiva_history_nonce', $html);
		$this->assertStringContainsString('personal data export', $html);
		$this->assertStringNotContainsString('id="approve-payment"', $html);
	}

	public function test_note_type_select_offers_note_and_customer_contact_only(): void
	{
		preg_match_all('/<option value="([a-z_]+)"/', $this->html(), $m);
		$this->assertSame(array( 'note', 'customer_contact' ), $m[1]);
	}

	public function test_a_system_note_type_is_refused(): void
	{
		// Status, payment and system rows are Rentiva's own; a hand-made one
		// would read as if the system had written it.
		$this->assertFalse($this->add_note('Fake', 'system')['success'] ?? true);
		$this->assertFalse(metadata_exists('post', $this->booking, '_mhmrentiva_booking_history'));
	}

	public function test_history_box_is_not_offered_on_a_new_booking(): void
	{
		$side = $this->boxes_on('post-new.php');
		$this->assertArrayNotHasKey('mhmrentiva_booking_history_box', $side['default'] ?? array());
	}

	public function test_note_field_is_not_required(): void
	{
		$html = $this->html();
		$this->assertMatchesRegularExpression('/<textarea[^>]*id="note_content"/', $html);
		$this->assertDoesNotMatchRegularExpression('/<textarea[^>]*id="note_content"[^>]*required/', $html);
	}

	public function test_a_single_add_note_container_carries_the_booking_id(): void
	{
		$html = $this->html();
		$this->assertSame(1, preg_match_all('/class="mhm-add-history-note"/', $html));
		$this->assertStringContainsString('class="mhm-add-history-note" data-booking-id="' . $this->booking . '"', $html);
	}

	public function test_privacy_line_is_shown(): void
	{
		$this->assertStringContainsString(
			"These notes are included in the customer&#039;s personal data export.",
			$this->html()
		);
	}

	private function add_note(string $content, string $type = 'note'): array
	{
		$_POST = wp_slash(array(
			'mhmrentiva_history_nonce' => wp_create_nonce('mhmrentiva_add_history_note'),
			'booking_id'               => (string) $this->booking,
			'note_content'             => $content,
			'note_type'                => $type,
		));
		try {
			$this->_handleAjax('mhmrentiva_add_booking_history_note');
		} catch (\WPAjaxDieContinueException $e) {
			unset($e);
		}
		$response             = json_decode((string) $this->_last_response, true);
		$this->_last_response = '';
		return is_array($response) ? $response : array();
	}

	public function test_empty_note_is_refused_by_add_note(): void
	{
		$this->assertFalse($this->add_note('   ')['success'] ?? true);
		$this->assertFalse(metadata_exists('post', $this->booking, '_mhmrentiva_booking_history'));
	}

	public function test_a_note_is_added_by_add_note(): void
	{
		$this->assertTrue($this->add_note('Called the customer')['success'] ?? false);
		$history = (array) get_post_meta($this->booking, '_mhmrentiva_booking_history', true);
		$this->assertCount(1, $history);
		$this->assertSame('Called the customer', array_values($history)[0]['note']);
	}
}
