<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Privacy;

use MHMRentiva\Admin\Privacy\BookingMatcher;
use MHMRentiva\Admin\Privacy\ErasureMarker;
use WP_UnitTestCase;

/**
 * Which bookings belong to the person a privacy request names (5a spec T2, T4).
 *
 * @covers \MHMRentiva\Admin\Privacy\BookingMatcher
 */
final class BookingMatcherTest extends WP_UnitTestCase
{
	private function booking(array $meta, string $post_type = 'mhmrentiva_booking', string $status = 'publish'): int
	{
		$id = (int) self::factory()->post->create(array('post_type' => $post_type, 'post_status' => $status));
		foreach ($meta as $key => $value) {
			update_post_meta($id, $key, $value);
		}

		return $id;
	}

	private function guest(string $email, string $status = 'completed'): int
	{
		return $this->booking(array(
			'_mhmrentiva_customer_user_id' => 0,
			'_mhmrentiva_customer_email'   => $email,
			'_mhmrentiva_status'           => $status,
		));
	}

	public function test_guest_by_email_case_and_space_insensitive(): void
	{
		$id = $this->guest(' Guest@Example.TEST');

		$this->assertSame(array($id), BookingMatcher::ids('guest@example.test', 'all', 50));
		$this->assertSame(array($id), BookingMatcher::ids('GUEST@example.test ', 'all', 50));
	}

	public function test_two_guests_never_mix(): void
	{
		$a = $this->guest('a@example.test');
		$this->guest('b@example.test');

		$this->assertSame(array($a), BookingMatcher::ids('a@example.test', 'all', 50));
		$this->assertSame(array(), BookingMatcher::ids('c@example.test', 'all', 50));
	}

	public function test_account_booking_with_a_different_email_is_found_by_id(): void
	{
		$user = (int) self::factory()->user->create(array('user_email' => 'now@example.test'));
		$id   = $this->booking(array(
			'_mhmrentiva_customer_user_id' => $user,
			'_mhmrentiva_customer_email'   => 'then@example.test',
			'_mhmrentiva_status'           => 'completed',
		));

		$this->assertSame(array($id), BookingMatcher::ids('now@example.test', 'all', 50));
		$this->assertTrue(BookingMatcher::matches($id, 'now@example.test'));
	}

	public function test_booking_without_user_id_meta_is_found_by_email(): void
	{
		$id = $this->booking(array('_mhmrentiva_customer_email' => 'x@example.test'));

		$this->assertSame(array($id), BookingMatcher::ids('x@example.test', 'all', 50));
	}

	public function test_legacy_email_keys_match_on_bookings_only(): void
	{
		$legacy_a = $this->booking(array('_mhmrentiva_booking_customer_email' => 'l@example.test'));
		$legacy_b = $this->booking(array('_mhmrentiva_contact_email' => 'l@example.test'));
		$this->booking(array('_mhmrentiva_contact_email' => 'l@example.test'), 'mhmrentiva_contact', 'private');

		$this->assertSame(array($legacy_a, $legacy_b), BookingMatcher::ids('l@example.test', 'all', 50));
	}

	public function test_trash_and_auto_draft_are_ignored(): void
	{
		$kept = $this->guest('t@example.test');
		$this->booking(array('_mhmrentiva_customer_email' => 't@example.test'), 'mhmrentiva_booking', 'trash');
		$this->booking(array('_mhmrentiva_customer_email' => 't@example.test'), 'mhmrentiva_booking', 'auto-draft');

		$this->assertSame(array($kept), BookingMatcher::ids('t@example.test', 'all', 50));
	}

	public function test_queue_skips_active_unknown_and_recently_marked(): void
	{
		$closed   = $this->guest('q@example.test', 'completed');
		$this->guest('q@example.test', 'confirmed');
		$this->booking(array('_mhmrentiva_customer_email' => 'q@example.test'));
		$marked   = $this->guest('q@example.test', 'cancelled');
		ErasureMarker::mark($marked);

		$this->assertSame(array($closed), BookingMatcher::ids('q@example.test', 'queue', 50));
		$this->assertSame(2, BookingMatcher::count('q@example.test', 'active'));
		$this->assertSame(array($marked), BookingMatcher::ids('q@example.test', 'recently_marked', 50));
	}

	public function test_queue_is_one_bounded_query_behind_a_long_active_prefix(): void
	{
		global $wpdb;

		for ($i = 0; $i < 210; $i++) {
			$this->guest('long@example.test', 'confirmed');
		}
		$closed = $this->guest('long@example.test', 'completed');

		$before = $wpdb->num_queries;
		$ids    = BookingMatcher::ids('long@example.test', 'queue', 50);

		$this->assertSame(array($closed), $ids);
		$this->assertLessThanOrEqual(2, $wpdb->num_queries - $before);
	}

	public function test_queue_returns_at_most_its_limit(): void
	{
		for ($i = 0; $i < 60; $i++) {
			$this->guest('cap@example.test', 'completed');
		}

		$this->assertCount(50, BookingMatcher::ids('cap@example.test', 'queue', 50));
		$this->assertSame(60, BookingMatcher::count('cap@example.test', 'all'));
	}

	public function test_invalid_email_runs_no_query_and_matches_nothing(): void
	{
		global $wpdb;

		$this->guest('a@example.test');
		$before = $wpdb->num_queries;

		$this->assertSame(array(), BookingMatcher::ids('', 'all', 50));
		$this->assertSame(0, BookingMatcher::count('not-an-address', 'active'));
		$this->assertSame(0, $wpdb->num_queries - $before);
	}

	public function test_offset_pages_through_distinct_ids(): void
	{
		$ids = array();
		for ($i = 0; $i < 7; $i++) {
			$ids[] = $this->guest('p@example.test');
		}
		// A second e-mail row on one booking must not duplicate it.
		add_post_meta($ids[0], '_mhmrentiva_booking_customer_email', 'p@example.test');

		$this->assertSame(array_slice($ids, 0, 3), BookingMatcher::ids('p@example.test', 'all', 3, 0));
		$this->assertSame(array_slice($ids, 3, 3), BookingMatcher::ids('p@example.test', 'all', 3, 3));
		$this->assertSame(array_slice($ids, 6, 1), BookingMatcher::ids('p@example.test', 'all', 3, 6));
	}
}
