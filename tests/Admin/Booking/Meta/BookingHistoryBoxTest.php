<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use WP_Ajax_UnitTestCase;

/**
 * The booking history box (spec PR 3a): written but never registered, so the
 * edit screen had no history. It now sits in the side column; its note field
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

	public function test_history_box_is_registered_on_the_edit_screen_side(): void
	{
		$side = $this->boxes_on('post.php');
		$this->assertArrayHasKey('mhmrentiva_booking_history_box', $side['default'] ?? array());
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

	private function add_note(string $content): array
	{
		$_POST = wp_slash(array(
			'mhmrentiva_history_nonce' => wp_create_nonce('mhmrentiva_add_history_note'),
			'booking_id'               => (string) $this->booking,
			'note_content'             => $content,
			'note_type'                => 'note',
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
