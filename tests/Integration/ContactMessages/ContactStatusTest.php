<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\ContactMessages;

use MHMRentiva\Admin\ContactMessages\ContactStatus;
use WP_UnitTestCase;

final class ContactStatusTest extends WP_UnitTestCase
{
	private function contact(): int
	{
		return (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_contact', 'post_status' => 'private' ));
	}

	public function test_normalize_keeps_known_values_and_reads_the_rest(): void
	{
		$this->assertSame('new', ContactStatus::normalize('new'));
		$this->assertSame('replied', ContactStatus::normalize('replied'));
		$this->assertSame('read', ContactStatus::normalize(''));
		$this->assertSame('read', ContactStatus::normalize('bogus'));
		$this->assertSame('read', ContactStatus::normalize(null));
	}

	public function test_get_without_meta_is_read(): void
	{
		$this->assertSame('read', ContactStatus::get($this->contact()));
	}

	public function test_set_rejects_unknown_values_and_other_post_types(): void
	{
		$id = $this->contact();
		$this->assertFalse(ContactStatus::set($id, 'urgent'));
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
		$this->assertFalse(ContactStatus::set($booking, 'new'));
		$this->assertTrue(ContactStatus::set($id, 'replied'));
		$this->assertSame('replied', get_post_meta($id, ContactStatus::META_KEY, true));
	}

	public function test_set_forgets_the_badge_count(): void
	{
		set_transient(ContactStatus::BADGE_TRANSIENT, 9, 300);
		ContactStatus::set($this->contact(), 'new');
		$this->assertFalse(get_transient(ContactStatus::BADGE_TRANSIENT));
	}

	/**
	 * set() used to discard update_post_meta()'s outcome and
	 * always returned true. A short-circuited write (another plugin, a full
	 * options/meta table, a lease race) must be reported as a failure, not as
	 * a status change that never actually happened.
	 */
	public function test_set_returns_false_when_the_meta_write_is_short_circuited(): void
	{
		$id = $this->contact();

		$block = static function ($check, $object_id, $meta_key) {
			return ContactStatus::META_KEY === $meta_key ? false : $check;
		};
		add_filter('update_post_metadata', $block, 10, 3);

		try {
			$this->assertFalse(ContactStatus::set($id, 'replied'));
		} finally {
			remove_filter('update_post_metadata', $block, 10);
		}

		$this->assertSame('read', ContactStatus::get($id), 'the stored status must not have changed when the write was blocked');
	}
}
