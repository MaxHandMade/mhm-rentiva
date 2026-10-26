<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\Meta;

use MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox;
use WP_Ajax_UnitTestCase;

/**
 * PR S (M2) -- every writer of the blocked-day list drops what was cached
 * about the vehicles it wrote.
 *
 * Util::check_availability() serves a cached "unavailable" without rechecking
 * it, for up to an hour. Once blocked days count, a day removed by the
 * operator would keep the vehicle "booked" on the detail page and the form
 * for that hour. The fleet occupancy matrix has its own cache and paints the
 * same days.
 *
 * Each writer also announces the write (`mhmrentiva_vehicle_blocked_days_saved`),
 * which the add-on's penalty ledger listens to.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox
 */
final class BlockedDatesCacheTest extends WP_Ajax_UnitTestCase
{
	protected $_last_response;

	private int $source;

	private int $other;

	/** @var list<int> */
	private array $announced = array();

	public function setUp(): void
	{
		parent::setUp();

		BlockedDatesMetaBox::register();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));

		$this->source = $this->vehicle();
		$this->other  = $this->vehicle();

		$this->announced = array();
		add_action(
			'mhmrentiva_vehicle_blocked_days_saved',
			function ($vehicle_id): void {
				$this->announced[] = (int) $vehicle_id;
			}
		);
	}

	public function tearDown(): void
	{
		$_POST = array();
		parent::tearDown();
	}

	private function vehicle(): int
	{
		return (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * A cached availability answer and a cached occupancy figure, in the database.
	 */
	private function seed_caches(int ...$vehicle_ids): void
	{
		foreach ($vehicle_ids as $vehicle_id) {
			set_transient('mhmrentiva_avail_' . $vehicle_id . '_probe', array( 'ok' => false ), HOUR_IN_SECONDS);
		}
		set_transient('mhmrentiva_vehicle_stats_probe', array( 1 ), HOUR_IN_SECONDS);
	}

	private function rows(string $like): int
	{
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				'%' . $wpdb->esc_like($like) . '%'
			)
		);
	}

	private function assert_caches_dropped(int ...$vehicle_ids): void
	{
		foreach ($vehicle_ids as $vehicle_id) {
			$this->assertSame(0, $this->rows('mhmrentiva_avail_' . $vehicle_id . '_'), "Availability cache of vehicle {$vehicle_id}.");
		}
		$this->assertSame(0, $this->rows('mhmrentiva_vehicle_stats_'), 'Occupancy cache.');
	}

	private function dispatch(string $action): void
	{
		try {
			$this->_handleAjax($action);
		} catch (\WPAjaxDieContinueException $e) {
			// Expected exit path for WP_Ajax_UnitTestCase.
		}
	}

	public function test_toggle_drops_the_caches(): void
	{
		$this->seed_caches($this->source);

		BlockedDatesMetaBox::toggle_blocked_date($this->source, '2027-01-04');

		$this->assert_caches_dropped($this->source);
		$this->assertSame(array( $this->source ), $this->announced);
	}

	public function test_save_drops_the_caches(): void
	{
		$this->seed_caches($this->source);
		$_POST = array(
			'mhmrentiva_blocked_dates_nonce' => wp_create_nonce('mhmrentiva_blocked_dates_save'),
			'_mhmrentiva_blocked_dates'      => '["2027-01-04"]',
		);

		BlockedDatesMetaBox::save($this->source);

		$this->assertSame(array( '2027-01-04' ), BlockedDatesMetaBox::get_blocked_dates($this->source));
		$this->assert_caches_dropped($this->source);
		$this->assertSame(array( $this->source ), $this->announced);
	}

	public function test_apply_to_all_drops_every_vehicles_caches(): void
	{
		$this->seed_caches($this->source, $this->other);
		$_POST = array(
			'nonce'      => wp_create_nonce('mhmrentiva_apply_blocked_to_all'),
			'vehicle_id' => (string) $this->source,
			'dates'      => '["2027-01-04"]',
		);

		$this->dispatch('mhmrentiva_apply_blocked_dates_to_all');

		$this->assertSame(array( '2027-01-04' ), BlockedDatesMetaBox::get_blocked_dates($this->other));
		$this->assert_caches_dropped($this->source, $this->other);
		$this->assertContains($this->source, $this->announced);
		$this->assertContains($this->other, $this->announced);
	}

	public function test_remove_from_all_drops_every_vehicles_caches(): void
	{
		update_post_meta($this->other, '_mhmrentiva_blocked_dates', '["2027-01-04","2027-01-05"]');
		$this->seed_caches($this->source, $this->other);
		$_POST = array(
			'nonce'      => wp_create_nonce('mhmrentiva_remove_blocked_from_all'),
			'vehicle_id' => (string) $this->source,
			'dates'      => '["2027-01-04"]',
		);

		$this->dispatch('mhmrentiva_remove_blocked_dates_from_all');

		$this->assertSame(array( '2027-01-05' ), BlockedDatesMetaBox::get_blocked_dates($this->other));
		$this->assert_caches_dropped($this->other);
		$this->assertContains($this->other, $this->announced);
	}

	/**
	 * A filter that adds 2027-02-02 and drops 2027-01-05, on every vehicle.
	 */
	private function filter_adds_and_drops(): void
	{
		add_filter(
			'mhmrentiva_vehicle_blocked_days',
			static function ($days) {
				$days   = array_values(array_diff((array) $days, array( '2027-01-05' )));
				$days[] = '2027-02-02';
				return $days;
			}
		);
	}

	private function stored(int $vehicle_id): array
	{
		return (array) json_decode((string) get_post_meta($vehicle_id, '_mhmrentiva_blocked_dates', true), true);
	}

	/**
	 * Review I1: the filter shapes what availability reads, never what is stored.
	 */
	public function test_toggle_stores_the_unfiltered_list(): void
	{
		update_post_meta($this->source, '_mhmrentiva_blocked_dates', '["2027-01-05"]');
		$this->filter_adds_and_drops();

		BlockedDatesMetaBox::toggle_blocked_date($this->source, '2027-01-04');

		$this->assertSame(array( '2027-01-04', '2027-01-05' ), $this->stored($this->source));
	}

	public function test_the_meta_box_field_carries_the_unfiltered_list(): void
	{
		update_post_meta($this->source, '_mhmrentiva_blocked_dates', '["2027-01-05"]');
		$this->filter_adds_and_drops();

		ob_start();
		BlockedDatesMetaBox::render(get_post($this->source));
		$html = (string) ob_get_clean();

		$this->assertMatchesRegularExpression('/id="mhmrentiva_blocked_dates_value"\s+value="\[&quot;2027-01-05&quot;\]"/', $html);
	}

	/**
	 * The field shows the stored list, but only after the read filters have run
	 * once: a subscriber that settles the stored list on read (the add-on lifts
	 * an expired penalty there) must have done so before the box is drawn, or
	 * the operator would save the stale days back.
	 */
	public function test_the_meta_box_lets_the_read_filters_settle_before_drawing(): void
	{
		update_post_meta($this->source, '_mhmrentiva_blocked_dates', '["2027-01-04","2027-01-05"]');
		add_filter(
			'mhmrentiva_vehicle_blocked_days',
			static function ($days, $vehicle_id) {
				update_post_meta((int) $vehicle_id, '_mhmrentiva_blocked_dates', '["2027-01-04"]');
				return array_values(array_diff((array) $days, array( '2027-01-05' )));
			},
			10,
			2
		);

		ob_start();
		BlockedDatesMetaBox::render(get_post($this->source));
		$html = (string) ob_get_clean();

		$this->assertMatchesRegularExpression('/id="mhmrentiva_blocked_dates_value"\s+value="\[&quot;2027-01-04&quot;\]"/', $html);
	}

	public function test_remove_from_all_stores_the_unfiltered_list(): void
	{
		update_post_meta($this->other, '_mhmrentiva_blocked_dates', '["2027-01-05","2027-01-06"]');
		$this->filter_adds_and_drops();
		$_POST = array(
			'nonce'      => wp_create_nonce('mhmrentiva_remove_blocked_from_all'),
			'vehicle_id' => (string) $this->source,
			'dates'      => '["2027-01-06"]',
		);

		$this->dispatch('mhmrentiva_remove_blocked_dates_from_all');

		$this->assertSame(array( '2027-01-05' ), $this->stored($this->other));
	}

	public function test_apply_to_all_without_payload_copies_the_stored_list(): void
	{
		update_post_meta($this->source, '_mhmrentiva_blocked_dates', '["2027-01-05"]');
		$this->filter_adds_and_drops();
		$_POST = array(
			'nonce'      => wp_create_nonce('mhmrentiva_apply_blocked_to_all'),
			'vehicle_id' => (string) $this->source,
		);

		$this->dispatch('mhmrentiva_apply_blocked_dates_to_all');

		$this->assertSame(array( '2027-01-05' ), $this->stored($this->other));
		$this->assertSame(array( '2027-01-05' ), $this->stored($this->source));
	}

	public function test_apply_to_all_does_not_copy_active_penalty_days(): void
	{
		// The add-on names the source vehicle's penalty days; they are that
		// vehicle's, not the fleet's.
		add_filter(
			'mhmrentiva_vehicle_penalty_days',
			fn($days, $vehicle_id) => $vehicle_id === $this->source ? array( '2027-01-05' ) : $days,
			10,
			2
		);
		$_POST = array(
			'nonce'      => wp_create_nonce('mhmrentiva_apply_blocked_to_all'),
			'vehicle_id' => (string) $this->source,
			'dates'      => '["2027-01-04","2027-01-05"]',
		);

		$this->dispatch('mhmrentiva_apply_blocked_dates_to_all');

		$this->assertSame(array( '2027-01-04' ), BlockedDatesMetaBox::get_blocked_dates($this->other));
		$this->assertSame(array( '2027-01-04', '2027-01-05' ), BlockedDatesMetaBox::get_blocked_dates($this->source), 'The source keeps its own.');
	}

	/**
	 * Review m5: "Apply to all" replaces each target's list with the copied
	 * days, but the target's own active penalty days (and their notes) stay.
	 * A fleet-wide copy must not lift another vehicle's penalty.
	 */
	public function test_apply_to_all_keeps_each_targets_own_penalty_days(): void
	{
		update_post_meta($this->other, '_mhmrentiva_blocked_dates', '["2027-03-01","2027-03-09"]');
		update_post_meta($this->other, '_mhmrentiva_blocked_dates_notes', '{"2027-03-01":"own note","2027-03-09":"manual"}');
		add_filter(
			'mhmrentiva_vehicle_penalty_days',
			fn($days, $vehicle_id) => $vehicle_id === $this->other ? array( '2027-03-01' ) : $days,
			10,
			2
		);
		$_POST = array(
			'nonce'      => wp_create_nonce('mhmrentiva_apply_blocked_to_all'),
			'vehicle_id' => (string) $this->source,
			'dates'      => '["2027-01-04"]',
		);

		$this->dispatch('mhmrentiva_apply_blocked_dates_to_all');

		$this->assertSame(array( '2027-01-04', '2027-03-01' ), $this->stored($this->other), 'Copied days plus the target vehicle\'s own penalty day; its manual day is replaced as before.');
		$notes = (array) json_decode((string) get_post_meta($this->other, '_mhmrentiva_blocked_dates_notes', true), true);
		$this->assertSame(array( '2027-03-01' => 'own note' ), $notes);
	}

	/**
	 * "Remove from all" does not lift another vehicle's active penalty day
	 * either; it removes the day where it is not a penalty.
	 */
	public function test_remove_from_all_keeps_each_targets_own_penalty_days(): void
	{
		update_post_meta($this->other, '_mhmrentiva_blocked_dates', '["2027-03-01","2027-03-02"]');
		add_filter(
			'mhmrentiva_vehicle_penalty_days',
			fn($days, $vehicle_id) => $vehicle_id === $this->other ? array( '2027-03-01' ) : $days,
			10,
			2
		);
		$_POST = array(
			'nonce'      => wp_create_nonce('mhmrentiva_remove_blocked_from_all'),
			'vehicle_id' => (string) $this->source,
			'dates'      => '["2027-03-01","2027-03-02"]',
		);

		$this->dispatch('mhmrentiva_remove_blocked_dates_from_all');

		$this->assertSame(array( '2027-03-01' ), $this->stored($this->other));
	}
}
