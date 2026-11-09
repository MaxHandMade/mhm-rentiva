<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Privacy;

use MHMRentiva\Admin\Privacy\BookingEraser;
use MHMRentiva\Admin\Privacy\BookingMatcher;
use MHMRentiva\Admin\Privacy\ErasureMarker;
use WP_UnitTestCase;

/**
 * The booking eraser's paging contract (5a spec T4): closed bookings are
 * anonymised fifty per call, active ones kept with one message, failures
 * marked so the queue moves on, and a later run retries them.
 *
 * @covers \MHMRentiva\Admin\Privacy\BookingEraser
 */
final class BookingEraserTest extends WP_UnitTestCase
{
	private const EMAIL = 'guest@example.test';

	/** @var callable|null */
	private $block = null;

	public function tearDown(): void
	{
		if (null !== $this->block) {
			remove_filter('delete_post_metadata', $this->block, 10);
			remove_filter('update_post_metadata', $this->block, 10);
			remove_filter('add_post_metadata', $this->block, 10);
		}
		parent::tearDown();
	}

	private function guest(string $status = 'completed', string $email = self::EMAIL): int
	{
		$id = (int) self::factory()->post->create(array('post_type' => 'mhmrentiva_booking', 'post_status' => 'publish'));
		update_post_meta($id, '_mhmrentiva_customer_user_id', 0);
		update_post_meta($id, '_mhmrentiva_customer_email', $email);
		update_post_meta($id, '_mhmrentiva_customer_phone', '+90 555');
		update_post_meta($id, '_mhmrentiva_status', $status);

		return $id;
	}

	/**
	 * Make the phone of these bookings undeletable (and optionally the marker unwritable).
	 *
	 * @param int[] $ids
	 */
	private function block_phone(array $ids, bool $block_marker = false): void
	{
		$this->block = static function ($check, $object_id, $meta_key) use ($ids, $block_marker) {
			if (! in_array((int) $object_id, $ids, true)) {
				return $check;
			}
			if ('_mhmrentiva_customer_phone' === $meta_key) {
				return false;
			}
			if ($block_marker && ErasureMarker::KEY === $meta_key) {
				return false;
			}

			return $check;
		};
		add_filter('delete_post_metadata', $this->block, 10, 3);
		add_filter('update_post_metadata', $this->block, 10, 3);
		add_filter('add_post_metadata', $this->block, 10, 3);
	}

	public function test_return_has_the_four_core_keys(): void
	{
		$result = BookingEraser::erase('nobody@example.test', 1);

		$this->assertSame(array('items_removed', 'items_retained', 'messages', 'done'), array_keys($result));
		$this->assertTrue($result['done']);
	}

	public function test_closed_anonymised_active_kept_with_one_message(): void
	{
		$closed = $this->guest('completed');
		$active = $this->guest('confirmed');

		$result = BookingEraser::erase(self::EMAIL, 1);

		$this->assertTrue($result['items_removed']);
		$this->assertTrue($result['items_retained']);
		$this->assertTrue($result['done']);
		$this->assertFalse(BookingMatcher::matches($closed, self::EMAIL));
		$this->assertTrue(BookingMatcher::matches($active, self::EMAIL));
		$this->assertCount(1, array_filter($result['messages'], static fn(string $m): bool => false !== strpos($m, '#' . $active)));
	}

	public function test_second_run_does_nothing_to_closed(): void
	{
		$this->guest('completed');
		BookingEraser::erase(self::EMAIL, 1);

		$result = BookingEraser::erase(self::EMAIL, 1);

		$this->assertFalse($result['items_removed']);
		$this->assertTrue($result['done']);
	}

	public function test_sixty_with_first_fifty_failing_still_reaches_the_last_ten(): void
	{
		$ids = array();
		for ($i = 0; $i < 60; $i++) {
			$ids[] = $this->guest();
		}
		$this->block_phone(array_slice($ids, 0, 50));

		$page1 = BookingEraser::erase(self::EMAIL, 1);
		$this->assertFalse($page1['done']);
		$this->assertTrue($page1['items_retained']);
		foreach (array_slice($ids, 0, 50) as $id) {
			$this->assertTrue(BookingMatcher::matches($id, self::EMAIL));
			$this->assertNotSame('', get_post_meta($id, ErasureMarker::KEY, true));
		}

		$page2 = BookingEraser::erase(self::EMAIL, 2);
		$this->assertTrue($page2['done']);
		$this->assertTrue($page2['items_removed']);
		foreach (array_slice($ids, 50) as $id) {
			$this->assertFalse(BookingMatcher::matches($id, self::EMAIL));
		}
	}

	public function test_an_immediate_rerun_skips_the_marked_and_says_so(): void
	{
		$id = $this->guest();
		$this->block_phone(array($id));
		BookingEraser::erase(self::EMAIL, 1);

		$rerun = BookingEraser::erase(self::EMAIL, 1);

		$this->assertTrue($rerun['done']);
		$this->assertFalse($rerun['items_removed']);
		$this->assertTrue($rerun['items_retained']);
		$this->assertNotEmpty(array_filter($rerun['messages'], static fn(string $m): bool => false !== strpos($m, '15')));
	}

	public function test_after_the_window_a_new_run_retries_and_finishes(): void
	{
		$id = $this->guest();
		$this->block_phone(array($id));
		BookingEraser::erase(self::EMAIL, 1);
		remove_filter('delete_post_metadata', $this->block, 10);
		remove_filter('update_post_metadata', $this->block, 10);
		remove_filter('add_post_metadata', $this->block, 10);
		$this->block = null;
		update_post_meta($id, ErasureMarker::KEY, time() - ErasureMarker::WINDOW - 1);

		$result = BookingEraser::erase(self::EMAIL, 1);

		$this->assertTrue($result['items_removed']);
		$this->assertFalse(BookingMatcher::matches($id, self::EMAIL));
		$this->assertSame('', get_post_meta($id, ErasureMarker::KEY, true));
	}

	public function test_cache_flush_between_pages_does_not_reset_progress(): void
	{
		$ids = array();
		for ($i = 0; $i < 55; $i++) {
			$ids[] = $this->guest();
		}
		$this->block_phone(array_slice($ids, 0, 50));

		BookingEraser::erase(self::EMAIL, 1);
		wp_cache_flush();
		$page2 = BookingEraser::erase(self::EMAIL, 2);

		$this->assertTrue($page2['done']);
		foreach (array_slice($ids, 50) as $id) {
			$this->assertFalse(BookingMatcher::matches($id, self::EMAIL));
		}
	}

	public function test_two_interleaved_runs_reach_the_same_end_state(): void
	{
		$ids = array();
		for ($i = 0; $i < 70; $i++) {
			$ids[] = $this->guest();
		}

		BookingEraser::erase(self::EMAIL, 1); // run A
		BookingEraser::erase(self::EMAIL, 1); // run B
		$a2 = BookingEraser::erase(self::EMAIL, 2);
		$b2 = BookingEraser::erase(self::EMAIL, 2);

		$this->assertTrue($a2['done']);
		$this->assertTrue($b2['done']);
		foreach ($ids as $id) {
			$this->assertFalse(BookingMatcher::matches($id, self::EMAIL));
		}
	}

	public function test_unmarkable_failure_stops_with_done(): void
	{
		$ids = array($this->guest(), $this->guest());
		$this->block_phone($ids, true);

		$result = BookingEraser::erase(self::EMAIL, 1);

		$this->assertTrue($result['done']);
		$this->assertTrue($result['items_retained']);
		// Stopped at the first booking: the second one was not attempted.
		$this->assertSame('+90 555', get_post_meta($ids[1], '_mhmrentiva_customer_phone', true));
	}

	public function test_account_and_order_messages_on_page_one(): void
	{
		self::factory()->user->create(array('user_email' => self::EMAIL));
		$id = $this->guest();
		update_post_meta($id, '_mhmrentiva_wc_order_id', 55);

		$messages = implode("\n", BookingEraser::erase(self::EMAIL, 1)['messages']);

		$this->assertStringContainsString('WooCommerce', $messages);
		$this->assertStringContainsString('Users', $messages);
	}
}
