<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Customers\CustomerIdentity;
use MHMRentiva\Admin\Frontend\Shortcodes\ContactForm;
use WP_UnitTestCase;

/**
 * The contact form links a booking number only when it is the signed-in
 * user's own booking. 6.2.1: the lookup no longer runs through WP_Query with
 * `suppress_filters => true` (Plugin Check ERROR
 * WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters);
 * it reads the one post and its two ownership meta keys directly, which keeps
 * the property suppress_filters gave it: no query filter can widen the answer.
 */
final class ContactFormOwnBookingTest extends WP_UnitTestCase
{
	private int $owner_id = 0;
	private string $owner_email = 'renter.owner@example.test';

	public function setUp(): void
	{
		parent::setUp();
		$this->owner_id = (int) self::factory()->user->create(array( 'user_email' => $this->owner_email ));
	}

	public function tearDown(): void
	{
		remove_all_filters('pre_get_posts');
		remove_all_filters('posts_where');
		remove_all_filters('posts_pre_query');
		wp_set_current_user(0);
		parent::tearDown();
	}

	private function booking(string $status = 'publish', array $meta = array()): int
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => $status ));
		foreach ($meta as $key => $value) {
			update_post_meta($id, $key, $value);
		}
		return $id;
	}

	private function lookup(int $booking_id): int
	{
		$m = new \ReflectionMethod(ContactForm::class, 'own_booking_id');
		$m->setAccessible(true);
		return (int) $m->invoke(null, $booking_id);
	}

	public function test_owner_by_user_id_is_found(): void
	{
		$id = $this->booking('publish', array( MetaKeys::BOOKING_CUSTOMER_USER_ID => (string) $this->owner_id ));
		wp_set_current_user($this->owner_id);
		$this->assertSame($id, $this->lookup($id));
		$this->assertTrue(CustomerIdentity::booking_is_owned_by($id, $this->owner_id, $this->owner_email));
	}

	public function test_owner_by_account_email_is_found_case_insensitively(): void
	{
		$id = $this->booking('publish', array( MetaKeys::BOOKING_CUSTOMER_EMAIL => $this->owner_email ));
		wp_set_current_user($this->owner_id);
		$this->assertSame($id, $this->lookup($id));

		// The old meta_query compared under the table's _ci collation.
		$upper = $this->booking('publish', array( MetaKeys::BOOKING_CUSTOMER_EMAIL => strtoupper($this->owner_email) ));
		$this->assertSame($upper, $this->lookup($upper));
	}

	public function test_another_users_booking_is_not_found(): void
	{
		$other = (int) self::factory()->user->create(array( 'user_email' => 'someone.else@example.test' ));
		$id    = $this->booking('publish', array(
			MetaKeys::BOOKING_CUSTOMER_USER_ID => (string) $other,
			MetaKeys::BOOKING_CUSTOMER_EMAIL   => 'someone.else@example.test',
		));
		wp_set_current_user($this->owner_id);
		$this->assertSame(0, $this->lookup($id));
		$this->assertFalse(CustomerIdentity::booking_is_owned_by($id, $this->owner_id, $this->owner_email));
	}

	public function test_non_booking_post_is_not_found(): void
	{
		$post = (int) self::factory()->post->create(array( 'post_type' => 'post' ));
		update_post_meta($post, MetaKeys::BOOKING_CUSTOMER_USER_ID, (string) $this->owner_id);
		wp_set_current_user($this->owner_id);
		$this->assertSame(0, $this->lookup($post));
		$this->assertSame(0, $this->lookup(999999));
	}

	public function test_guest_and_zero_are_not_found(): void
	{
		$id = $this->booking('publish', array( MetaKeys::BOOKING_CUSTOMER_USER_ID => (string) $this->owner_id ));
		$this->assertSame(0, $this->lookup($id), 'Guests are never linked.');
		wp_set_current_user($this->owner_id);
		$this->assertSame(0, $this->lookup(0));
	}

	public function test_status_any_semantics_are_kept(): void
	{
		wp_set_current_user($this->owner_id);
		foreach (array( 'draft', 'pending', 'private', 'future' ) as $status) {
			$id = $this->booking($status, array( MetaKeys::BOOKING_CUSTOMER_USER_ID => (string) $this->owner_id ));
			$this->assertSame($id, $this->lookup($id), "post_status 'any' included {$status}.");
		}
		// WP_Query's 'any' excludes exclude_from_search statuses (trash, auto-draft).
		$trashed = $this->booking('trash', array( MetaKeys::BOOKING_CUSTOMER_USER_ID => (string) $this->owner_id ));
		$this->assertSame(0, $this->lookup($trashed), 'A binned booking was never linked.');
	}

	public function test_query_filters_cannot_widen_the_answer(): void
	{
		$other = (int) self::factory()->user->create();
		$id    = $this->booking('publish', array( MetaKeys::BOOKING_CUSTOMER_USER_ID => (string) $other ));

		add_action('pre_get_posts', static function ($q): void {
			$q->set('meta_query', array());
			$q->set('p', 0);
		});
		add_filter('posts_where', static fn(): string => ' AND 1=1 ');
		add_filter('posts_pre_query', static fn() => array( $id ));

		wp_set_current_user($this->owner_id);
		$this->assertSame(0, $this->lookup($id), 'A third-party query filter must not turn a foreign booking into an owned one.');
	}

	public function test_the_lookup_has_no_suppress_filters_shape(): void
	{
		$src = (string) file_get_contents(dirname(__DIR__, 3) . '/src/Admin/Frontend/Shortcodes/ContactForm.php');
		$this->assertSame(0, substr_count($src, 'suppress_filters'), 'Plugin Check reports suppress_filters as an ERROR.');
	}
}
