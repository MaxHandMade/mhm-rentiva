<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Services;

use MHMRentiva\Admin\Services\CompareService;
use WP_UnitTestCase;

/**
 * r2 M4, m15: the list is pruned in memory. Drawing a page writes nothing;
 * only add() stores the clean list, so a closed vehicle stops taking a seat.
 *
 * @covers \MHMRentiva\Admin\Services\CompareService
 */
final class CompareServicePruneTest extends WP_UnitTestCase
{
	private int $user;

	public function setUp(): void
	{
		parent::setUp();
		$this->user = (int) self::factory()->user->create();
		wp_set_current_user($this->user);
		$this->forget();
	}

	public function tearDown(): void
	{
		unset($_COOKIE['mhmrentiva_compare']);
		wp_set_current_user(0);
		$this->forget();
		parent::tearDown();
	}

	private function forget(): void
	{
		$prop = new \ReflectionProperty(CompareService::class, 'cached_list');
		$prop->setAccessible(true);
		$prop->setValue(null, null);
	}

	private function vehicle(string $status = 'publish', array $meta = array(), array $post = array()): int
	{
		$id = (int) self::factory()->post->create(array_merge(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => $status ), $post));
		foreach ($meta as $key => $value) {
			update_post_meta($id, $key, $value);
		}

		return $id;
	}

	private function store(array $ids): void
	{
		update_user_meta($this->user, 'mhmrentiva_compare', $ids);
		$this->forget();
	}

	public function test_the_list_holds_only_comparable_vehicles(): void
	{
		$ok   = $this->vehicle();
		$list = array(
			$this->vehicle('draft'),
			$this->vehicle('publish', array( '_mhmrentiva_vehicle_status' => 'maintenance' )),
			$ok,
		);
		$this->store($list);
		$this->assertSame(array( $ok ), CompareService::get_list());

		$this->store(array( $this->vehicle('publish', array( '_mhmrentiva_vehicle_service_type' => 'transfer' )) ));
		$this->assertSame(array(), CompareService::get_list(), 'Transfer-only.');

		$this->store(array( $this->vehicle('publish', array(), array( 'post_password' => 'x' )) ));
		$this->assertSame(array(), CompareService::get_list(), 'Not publicly readable.');

		$this->store(array( (int) self::factory()->post->create(array( 'post_type' => 'page', 'post_status' => 'publish' )), 999999 ));
		$this->assertSame(array(), CompareService::get_list(), 'Another type, or nothing at all.');
	}

	public function test_reading_writes_nothing(): void
	{
		$closed = $this->vehicle('draft');
		$ok     = $this->vehicle();
		$this->store(array( $closed, $ok ));

		CompareService::get_list();

		$this->assertSame(array( $closed, $ok ), get_user_meta($this->user, 'mhmrentiva_compare', true));
	}

	public function test_a_guest_page_sets_no_cookie_for_a_pruned_list(): void
	{
		wp_set_current_user(0);
		$closed                       = $this->vehicle('draft');
		$ok                           = $this->vehicle();
		$cookie                       = (string) wp_json_encode(array( $closed, $ok ));
		$_COOKIE['mhmrentiva_compare'] = $cookie;
		$this->forget();

		$this->assertSame(array( $ok ), CompareService::get_list());
		// save_list() is the only writer, and it rewrites this copy before setcookie().
		$this->assertSame($cookie, $_COOKIE['mhmrentiva_compare']);
	}

	public function test_a_closed_vehicle_gives_its_seat_to_the_next_add(): void
	{
		$closed = $this->vehicle('draft');
		$a      = $this->vehicle();
		$b      = $this->vehicle();
		$this->store(array( $closed, $a, $b ));
		$new = $this->vehicle();

		$this->assertTrue(CompareService::add($new));

		$this->assertSame(array( $a, $b, $new ), CompareService::get_list());
		$this->assertSame(array( $a, $b, $new ), get_user_meta($this->user, 'mhmrentiva_compare', true), 'add() stores the clean list.');
	}

	public function test_a_vehicle_that_cannot_be_compared_is_refused_not_reported_added(): void
	{
		$transfer = $this->vehicle('publish', array( '_mhmrentiva_vehicle_service_type' => 'transfer' ));

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('This vehicle cannot be compared.');
		CompareService::add($transfer);
	}

	public function test_a_long_cookie_costs_at_most_the_lists_size(): void
	{
		wp_set_current_user(0);
		$a                             = $this->vehicle();
		$b                             = $this->vehicle();
		$c                             = $this->vehicle();
		$d                             = $this->vehicle();
		$_COOKIE['mhmrentiva_compare'] = (string) wp_json_encode(array( $a, $b, $c, $d ));
		$this->forget();

		$this->assertSame(array( $a, $b, $c ), CompareService::get_list());
	}

	public function test_a_closed_vehicle_does_not_push_a_good_one_past_the_limit(): void
	{
		wp_set_current_user(0);
		$closed                        = $this->vehicle('draft');
		$a                             = $this->vehicle();
		$b                             = $this->vehicle();
		$c                             = $this->vehicle();
		$_COOKIE['mhmrentiva_compare'] = (string) wp_json_encode(array( $closed, $a, $b, $c ));
		$this->forget();

		$this->assertSame(array( $a, $b, $c ), CompareService::get_list());
	}
}
