<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Privacy;

use MHMRentiva\Admin\Privacy\BookingState;
use MHMRentiva\Admin\Privacy\EmailMatch;
use MHMRentiva\Admin\Privacy\ErasureMarker;
use MHMRentiva\Admin\Privacy\PostQueue;
use WP_UnitTestCase;

/**
 * The shared pieces every privacy exporter and eraser leans on (5a spec T2, T3, T7).
 *
 * @covers \MHMRentiva\Admin\Privacy\EmailMatch
 * @covers \MHMRentiva\Admin\Privacy\BookingState::sql_closed
 */
final class PrivacyPrimitivesTest extends WP_UnitTestCase
{
	public function test_normalise_lowercases_and_trims_and_rejects_non_emails(): void
	{
		$this->assertSame('a@example.test', EmailMatch::normalise(' A@Example.TEST '));
		$this->assertSame('', EmailMatch::normalise('x'));
		$this->assertSame('', EmailMatch::normalise(''));
	}

	public function test_needle_is_the_quoted_json_string(): void
	{
		global $wpdb;

		// wp_json_encode() escapes "/" as "\/"; esc_like() then escapes the backslash.
		$expected = '%' . $wpdb->esc_like('"a\/b@example.test"') . '%';

		$this->assertSame($expected, EmailMatch::json_like_needle('A/B@example.test'));
		$this->assertSame('', EmailMatch::json_like_needle('not an address'));
	}

	/**
	 * 'closed' or 'active' for one booking, through the SQL predicate itself.
	 */
	private function state(int $id): string
	{
		return array($id) === $this->sql_closed_ids(array($id)) ? 'closed' : 'active';
	}

	private function booking(array $meta): int
	{
		$id = (int) self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));
		foreach ($meta as $key => $value) {
			update_post_meta($id, $key, $value);
		}

		return $id;
	}

	public function test_state_prefers_a_valid_current_key(): void
	{
		$this->assertSame('active', $this->state($this->booking(array('_mhmrentiva_status' => 'confirmed', '_mhmrentiva_booking_status' => 'completed'))));
		$this->assertSame('closed', $this->state($this->booking(array('_mhmrentiva_status' => 'no_show'))));
	}

	public function test_state_falls_back_past_an_invalid_current_value(): void
	{
		$this->assertSame('closed', $this->state($this->booking(array('_mhmrentiva_status' => 'garbage', '_mhmrentiva_booking_status' => 'completed'))));
	}

	/**
	 * @return int[] Booking IDs the SQL predicate calls closed, among $ids.
	 */
	private function sql_closed_ids(array $ids): array
	{
		global $wpdb;

		$in  = implode(',', array_map('intval', $ids));
		$sql = "SELECT p.ID FROM {$wpdb->posts} p WHERE p.ID IN ({$in}) AND " . BookingState::sql_closed('p') . ' ORDER BY p.ID';

		return array_map('intval', $wpdb->get_col($sql)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- test fixture IDs.
	}

	public function test_sql_closed_agrees_with_of_for_every_fixture(): void
	{
		$fixtures = array(
			array('_mhmrentiva_status' => 'confirmed', '_mhmrentiva_booking_status' => 'completed'),
			array('_mhmrentiva_status' => 'no_show'),
			array('_mhmrentiva_status' => 'refunded'),
			array('_mhmrentiva_status' => 'cancelled'),
			array('_mhmrentiva_status' => 'garbage', '_mhmrentiva_booking_status' => 'completed'),
			array('_mhmrentiva_booking_status' => 'cancelled'),
			array('_mhmrentiva_status' => '', '_mhmrentiva_booking_status' => 'refunded'),
			array(),
			array('_mhmrentiva_status' => 'garbage'),
			array('_mhmrentiva_status' => 'draft'),
			array('_mhmrentiva_status' => 'pending', '_mhmrentiva_booking_status' => 'garbage'),
		);

		$ids = array();
		foreach ($fixtures as $meta) {
			$ids[] = $this->booking($meta);
		}

		// Written out, not derived: completed/no_show/refunded/cancelled on the
		// current key, garbage + legacy completed, legacy cancelled alone,
		// empty current + legacy refunded.
		$expected = array($ids[1], $ids[2], $ids[3], $ids[4], $ids[5], $ids[6]);

		$this->assertSame($expected, $this->sql_closed_ids($ids));
	}

	/**
	 * Conflicting rows: an active row wins over a closed one, whatever the
	 * row order. Expected values are written out, not derived from of().
	 */
	public function test_conflicting_status_rows_are_never_closed(): void
	{
		$both_new = $this->booking(array());
		add_post_meta($both_new, '_mhmrentiva_status', 'confirmed');
		add_post_meta($both_new, '_mhmrentiva_status', 'completed');

		$both_new_reversed = $this->booking(array());
		add_post_meta($both_new_reversed, '_mhmrentiva_status', 'completed');
		add_post_meta($both_new_reversed, '_mhmrentiva_status', 'confirmed');

		$both_old = $this->booking(array());
		add_post_meta($both_old, '_mhmrentiva_booking_status', 'confirmed');
		add_post_meta($both_old, '_mhmrentiva_booking_status', 'completed');

		$upper  = $this->booking(array('_mhmrentiva_status' => 'COMPLETED'));
		$spaced = $this->booking(array('_mhmrentiva_status' => 'completed '));
		$closed = $this->booking(array('_mhmrentiva_status' => 'completed'));

		$this->assertSame('active', $this->state($both_new));
		$this->assertSame('active', $this->state($both_new_reversed));
		$this->assertSame('active', $this->state($both_old));
		$this->assertSame('active', $this->state($upper));
		$this->assertSame('active', $this->state($spaced));
		$this->assertSame('closed', $this->state($closed));
		$this->assertSame(array($closed), $this->sql_closed_ids(array($both_new, $both_new_reversed, $both_old, $upper, $spaced, $closed)));
	}

	public function test_marker_hides_a_post_inside_the_window_only(): void
	{
		global $wpdb;

		$id = $this->booking(array());
		$visible = static function () use ($wpdb, $id): bool {
			$sql = "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.ID = " . (int) $id . ' AND ' . ErasureMarker::sql_not_recently_marked('p');

			return '1' === (string) $wpdb->get_var($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- test fixture ID.
		};

		$this->assertTrue($visible());
		$this->assertTrue(ErasureMarker::mark($id));
		$this->assertFalse($visible());

		update_post_meta($id, ErasureMarker::KEY, time() - ErasureMarker::WINDOW - 1);
		$this->assertTrue($visible());

		ErasureMarker::mark($id);
		ErasureMarker::clear($id);
		$this->assertTrue($visible());
	}

	public function test_cleaner_keeps_the_two_privacy_keys(): void
	{
		$keys = \MHMRentiva\Admin\Core\Utilities\DatabaseCleaner::valid_meta_keys();

		$this->assertContains('_mhmrentiva_privacy_erase_failed', $keys);
		$this->assertContains('_mhmrentiva_personal_data_erased', $keys);
		$this->assertContains('_mhmrentiva_privacy_subject', $keys);
	}

	public function test_unknown_and_draft_are_kept(): void
	{
		$this->assertSame('active', $this->state($this->booking(array())));
		$this->assertSame('active', $this->state($this->booking(array('_mhmrentiva_status' => 'garbage'))));
		$this->assertSame('active', $this->state($this->booking(array('_mhmrentiva_status' => 'draft'))));
	}

	private function post_meta_rows(int $post_id): int
	{
		global $wpdb;

		return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d", $post_id));
	}

	/**
	 * wp_delete_post() can drop the post and leave meta rows behind -- a
	 * filter or a failed DELETE in delete_metadata_by_mid(). Nothing would
	 * reach them again (the queues read posts), so they are deleted here
	 * (Codex r6 on 5a B4).
	 */
	public function test_meta_rows_left_by_wp_delete_post_are_deleted(): void
	{
		$id    = (int) self::factory()->post->create(array('meta_input' => array('_mhmrentiva_contact_email' => 'guest@example.test')));
		$block = static fn() => false;
		add_filter('delete_post_metadata_by_mid', $block);

		$left = PostQueue::delete_post($id);

		remove_filter('delete_post_metadata_by_mid', $block);
		$this->assertNull($left);
		$this->assertNull(get_post($id));
		$this->assertSame(0, $this->post_meta_rows($id));
	}

	public function test_meta_rows_that_cannot_be_deleted_are_reported(): void
	{
		global $wpdb;

		$id    = (int) self::factory()->post->create(array('meta_input' => array('_mhmrentiva_contact_email' => 'guest@example.test')));
		$block = static fn() => false;
		$fail  = static fn(string $sql): string => (0 === stripos($sql, 'DELETE FROM `' . $wpdb->postmeta . '`')) ? 'SELECT 1 FROM no_such_table' : $sql;
		add_filter('delete_post_metadata_by_mid', $block);
		add_filter('query', $fail);
		$suppress = $wpdb->suppress_errors(true);
		$before   = $this->post_meta_rows($id);

		$left = PostQueue::delete_post($id);

		$wpdb->suppress_errors($suppress);
		remove_filter('query', $fail);
		remove_filter('delete_post_metadata_by_mid', $block);
		$this->assertSame('meta', $left);
		$this->assertGreaterThan(0, $before);
		$this->assertSame($before, $this->post_meta_rows($id));
	}
}
