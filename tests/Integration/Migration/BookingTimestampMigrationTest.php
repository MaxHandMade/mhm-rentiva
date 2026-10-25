<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Migration;

use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use MHMRentiva\Admin\PostTypes\Logs\PostType as LogPostType;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Tests\Support\ForgetsMigrationLock;
use MHMRentiva\Tests\Support\SiteClock;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * 4.8.0: every stored booking timestamp becomes the real instant its local
 * date and time name.
 *
 * WooCommerce checkouts used to store `strtotime("$date $time")` under
 * WordPress's UTC PHP zone -- the local wall time read as UTC -- while the
 * booking form stored the real instant. The step rebuilds both stamps from
 * the stored dates and times, which stay the source of truth.
 */
final class BookingTimestampMigrationTest extends WP_UnitTestCase
{
	use ForgetsMigrationLock;
	use SiteClock;

	private const VEHICLE_ID = 8801;

	private const OTHER_VEHICLE_ID = 8802;

	public function setUp(): void
	{
		parent::setUp();
		delete_option(DatabaseMigrator::BOOKING_TS_DONE_OPTION);
		self::forget_migration_lock();
	}

	public function tearDown(): void
	{
		delete_option(DatabaseMigrator::BOOKING_TS_DONE_OPTION);
		parent::tearDown();
		self::forget_migration_lock();
	}

	/**
	 * @return array<string, int>
	 */
	private function step(): array
	{
		$m = new ReflectionMethod(DatabaseMigrator::class, 'normalise_booking_timestamps_480');
		$m->setAccessible(true);

		return (array) $m->invoke(null);
	}

	private function pin_zone(string $zone): void
	{
		if ('+03:00' === $zone) {
			$this->pin_site_offset(3);
			return;
		}
		$this->pin_site_timezone($zone);
	}

	/**
	 * The four site clocks with their UTC offset in May 2027 (New York on
	 * EDT, Auckland on NZST). Expected instants are derived from these
	 * literals, never from the code under test.
	 *
	 * @return array<string, array{0: string, 1: int}>
	 */
	public static function zones(): array
	{
		return array(
			'Istanbul'     => array( 'Europe/Istanbul', 3 ),
			'New York'     => array( 'America/New_York', -4 ),
			'Auckland'     => array( 'Pacific/Auckland', 12 ),
			'fixed +03:00' => array( '+03:00', 3 ),
		);
	}

	/**
	 * Local `Y-m-d H:i` at a fixed offset (hours) as a Unix timestamp.
	 */
	private static function local_at(string $local, int $offset_hours): int
	{
		return (int) strtotime($local . ' UTC') - $offset_hours * HOUR_IN_SECONDS;
	}

	/**
	 * @param array<string, string|int> $meta
	 */
	private function booking(array $meta, int $vehicle_id = self::VEHICLE_ID): int
	{
		$id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		update_post_meta($id, '_mhmrentiva_vehicle_id', $vehicle_id);
		update_post_meta($id, '_mhmrentiva_status', 'confirmed');
		foreach ($meta as $key => $value) {
			update_post_meta($id, '_mhmrentiva_' . $key, $value);
		}

		return $id;
	}

	private function without_buffer(): void
	{
		add_filter(
			'option_' . SettingsCore::OPTION_NAME,
			static function ($settings): array {
				$settings = is_array($settings) ? $settings : array();
				$settings['mhmrentiva_booking_buffer_time'] = '0';

				return $settings;
			}
		);
	}

	private function log_at_info(): void
	{
		add_filter(
			'option_' . SettingsCore::OPTION_NAME,
			static function ($settings): array {
				$settings = is_array($settings) ? $settings : array();
				$settings['mhmrentiva_log_level'] = 'info';

				return $settings;
			}
		);
	}

	private static function ts(int $id, string $which): string
	{
		return (string) get_post_meta($id, '_mhmrentiva_' . $which . '_ts', true);
	}

	/**
	 * A WooCommerce-era row (wall time read as UTC) and a form row (real
	 * instant) both end up as the real instant, and the overlap check then
	 * sees the WAU row where it actually is.
	 *
	 * @dataProvider zones
	 */
	public function test_migration_normalises_mixed_rows_and_overlap_sees_them(string $zone, int $offset): void
	{
		$this->pin_zone($zone);
		$this->without_buffer();

		$a = $this->booking(array(
			'pickup_date'  => '2027-05-10',
			'start_time'   => '10:00',
			'dropoff_date' => '2027-05-12',
			'end_time'     => '10:00',
			'start_ts'     => (int) strtotime('2027-05-10 10:00'),
			'end_ts'       => (int) strtotime('2027-05-12 10:00'),
		));
		$b = $this->booking(
			array(
				'pickup_date'  => '2027-06-01',
				'start_time'   => '16:00',
				'dropoff_date' => '2027-06-03',
				'end_time'     => '16:00',
				'start_ts'     => self::local_at('2027-06-01 16:00', $offset),
				'end_ts'       => self::local_at('2027-06-03 16:00', $offset),
			),
			self::OTHER_VEHICLE_ID
		);

		$counts = $this->step();

		$a_start = self::local_at('2027-05-10 10:00', $offset);
		$a_end   = self::local_at('2027-05-12 10:00', $offset);
		$this->assertSame((string) $a_start, self::ts($a, 'start'));
		$this->assertSame((string) $a_end, self::ts($a, 'end'));
		$this->assertSame((string) self::local_at('2027-06-01 16:00', $offset), self::ts($b, 'start'));
		$this->assertSame((string) self::local_at('2027-06-03 16:00', $offset), self::ts($b, 'end'));
		$this->assertSame(1, $counts['changed']);
		$this->assertSame(1, $counts['unchanged']);

		$this->assertTrue(Util::has_overlap_locked(self::VEHICLE_ID, $a_start, $a_end), 'A\'s own window must overlap A.');
		$this->assertFalse(
			Util::has_overlap_locked(self::VEHICLE_ID, $a_end + MINUTE_IN_SECONDS, $a_end + DAY_IN_SECONDS),
			'A request starting one minute after A ends must not overlap A.'
		);
	}

	public function test_migration_skips_inverted_rows(): void
	{
		$this->pin_site_timezone('Europe/Istanbul');
		$this->log_at_info();

		$id = $this->booking(array(
			'pickup_date'  => '2027-05-12',
			'start_time'   => '10:00',
			'dropoff_date' => '2027-05-10',
			'end_time'     => '10:00',
			'start_ts'     => 1111,
			'end_ts'       => 2222,
		));

		$counts = $this->step();

		$this->assertSame('1111', self::ts($id, 'start'));
		$this->assertSame('2222', self::ts($id, 'end'));
		$this->assertSame(1, $counts['inverted']);
		$this->assertSame(0, $counts['changed']);
		$this->assertSame('1', get_option(DatabaseMigrator::BOOKING_TS_DONE_OPTION));

		$logs = get_posts(array(
			'post_type'      => LogPostType::TYPE,
			'posts_per_page' => 1,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		));
		$this->assertNotEmpty($logs, 'The step logs one info line with its counts.');
		$this->assertStringContainsString('4.8.0', $logs[0]->post_title);
		$this->assertStringContainsString('"inverted": 1', $logs[0]->post_content);
		$this->assertStringContainsString('"dateless": 0', $logs[0]->post_content);
	}

	/**
	 * @dataProvider zones
	 */
	public function test_migration_reads_pickup_time_alias_and_default(string $zone, int $offset): void
	{
		$this->pin_zone($zone);

		$alias = $this->booking(array(
			'pickup_date'  => '2027-05-10',
			'pickup_time'  => '14:00',
			'dropoff_date' => '2027-05-12',
			'dropoff_time' => '15:30',
		));
		$timeless = $this->booking(array(
			'pickup_date'  => '2027-05-10',
			'dropoff_date' => '2027-05-12',
		));

		$this->step();

		$this->assertSame((string) self::local_at('2027-05-10 14:00', $offset), self::ts($alias, 'start'));
		$this->assertSame((string) self::local_at('2027-05-12 15:30', $offset), self::ts($alias, 'end'));
		$this->assertSame((string) self::local_at('2027-05-10 10:00', $offset), self::ts($timeless, 'start'));
		$this->assertSame((string) self::local_at('2027-05-12 10:00', $offset), self::ts($timeless, 'end'));

		// The same default parse_datetimes() reads, through the same filters.
		delete_option(DatabaseMigrator::BOOKING_TS_DONE_OPTION);
		add_filter('mhmrentiva_default_pickup_time', static fn (): string => '09:00');
		add_filter('mhmrentiva_default_dropoff_time', static fn (): string => '08:15');
		$this->step();

		$this->assertSame((string) self::local_at('2027-05-10 09:00', $offset), self::ts($timeless, 'start'));
		$this->assertSame((string) self::local_at('2027-05-12 08:15', $offset), self::ts($timeless, 'end'));
		$this->assertSame((string) self::local_at('2027-05-10 14:00', $offset), self::ts($alias, 'start'), 'A stored time beats the default.');
	}

	public function test_start_time_wins_over_pickup_time(): void
	{
		$this->pin_site_timezone('Europe/Istanbul');

		$id = $this->booking(array(
			'pickup_date'  => '2027-05-10',
			'start_time'   => '11:00',
			'pickup_time'  => '14:00',
			'dropoff_date' => '2027-05-12',
			'end_time'     => '12:00',
			'dropoff_time' => '15:00',
		));

		$this->step();

		$this->assertSame((string) self::local_at('2027-05-10 11:00', 3), self::ts($id, 'start'));
		$this->assertSame((string) self::local_at('2027-05-12 12:00', 3), self::ts($id, 'end'));
	}

	public function test_migration_is_idempotent_and_skips_dateless_rows(): void
	{
		$this->pin_site_timezone('Europe/Istanbul');

		$wau      = $this->booking(array(
			'pickup_date'  => '2027-05-10',
			'start_time'   => '10:00',
			'dropoff_date' => '2027-05-12',
			'end_time'     => '10:00',
			'start_ts'     => (int) strtotime('2027-05-10 10:00'),
			'end_ts'       => (int) strtotime('2027-05-12 10:00'),
		));
		$no_ts    = $this->booking(array(
			'pickup_date'  => '2027-05-20',
			'dropoff_date' => '2027-05-22',
		));
		$dateless = $this->booking(array(
			'start_ts' => 3333,
			'end_ts'   => 4444,
		));
		$half     = $this->booking(array(
			'pickup_date' => '2027-05-20',
			'start_ts'    => 5555,
			'end_ts'      => 6666,
		));
		set_transient('mhmrentiva_avail_' . self::VEHICLE_ID . '_1_2_abc', array( 'x' ), HOUR_IN_SECONDS);

		$first = $this->step();

		$this->assertSame(2, $first['changed']);
		$this->assertSame(2, $first['dateless']);
		$this->assertSame((string) self::local_at('2027-05-20 10:00', 3), self::ts($no_ts, 'start'), 'A dated row without stamps gets them.');
		$this->assertSame('3333', self::ts($dateless, 'start'));
		$this->assertSame('4444', self::ts($dateless, 'end'));
		$this->assertSame('5555', self::ts($half, 'start'), 'A row missing its return date is dateless.');
		// Cache::clearAll() deletes the rows directly, so read the table, not the
		// request's options cache.
		global $wpdb;
		$this->assertSame(
			'0',
			(string) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", '_transient_mhmrentiva_avail_' . self::VEHICLE_ID . '_1_2_abc')),
			'The availability cache is cleared at the end.'
		);

		$writes = 0;
		$count  = static function ($meta_id, $object_id, $meta_key) use (&$writes): void {
			unset($meta_id, $object_id);
			if (in_array($meta_key, array( '_mhmrentiva_start_ts', '_mhmrentiva_end_ts' ), true)) {
				++$writes;
			}
		};
		add_action('updated_post_meta', $count, 10, 3);
		add_action('added_post_meta', $count, 10, 3);

		// With the flag set the step does not run at all...
		$this->step();
		$this->assertSame(0, $writes);

		// ...and a forced rerun finds nothing left to change.
		delete_option(DatabaseMigrator::BOOKING_TS_DONE_OPTION);
		$second = $this->step();
		$this->assertSame(0, $writes, 'A second run must not write any stamp.');
		$this->assertSame(0, $second['changed']);
		$this->assertSame(2, $second['unchanged']);
		$this->assertSame((string) self::local_at('2027-05-10 10:00', 3), self::ts($wau, 'start'));
	}

	public function test_migration_batches_and_flags_last(): void
	{
		$this->pin_site_timezone('Europe/Istanbul');

		$ids = array();
		for ($i = 0; $i < 250; $i++) {
			$ids[] = $this->booking(array(
				'pickup_date'  => '2027-05-10',
				'dropoff_date' => '2027-05-12',
				'start_ts'     => (int) strtotime('2027-05-10 10:00'),
				'end_ts'       => (int) strtotime('2027-05-12 10:00'),
			));
		}

		$pages = array();
		add_action(
			'pre_get_posts',
			static function (\WP_Query $q) use (&$pages): void {
				if ('mhmrentiva_booking' === $q->get('post_type')) {
					$pages[] = array( (int) $q->get('paged'), (int) $q->get('posts_per_page'), $q->get('orderby'), $q->get('order'), $q->get('fields') );
				}
			}
		);

		$written = 0;
		add_action(
			'updated_post_meta',
			static function ($meta_id, $object_id, $meta_key) use (&$written): void {
				unset($meta_id, $object_id);
				if ('_mhmrentiva_start_ts' === $meta_key) {
					++$written;
				}
			},
			10,
			3
		);

		$written_at_flag = null;
		add_filter(
			'pre_update_option_' . DatabaseMigrator::BOOKING_TS_DONE_OPTION,
			static function ($value) use (&$written, &$written_at_flag) {
				$written_at_flag = $written;
				return $value;
			}
		);

		$counts = $this->step();

		$this->assertSame(250, $counts['changed']);
		$this->assertSame(250, $written_at_flag, 'The flag is written only after the last batch.');
		$this->assertSame('1', get_option(DatabaseMigrator::BOOKING_TS_DONE_OPTION));
		$this->assertSame(array( 1, 100, 'ID', 'ASC', 'ids' ), $pages[0]);
		$this->assertSame(array( 1, 2, 3 ), array_slice(array_column($pages, 0), 0, 3));
		foreach ($ids as $id) {
			$this->assertSame((string) self::local_at('2027-05-10 10:00', 3), self::ts($id, 'start'));
		}
	}

	public function test_an_interrupted_run_leaves_no_flag(): void
	{
		$this->pin_site_timezone('Europe/Istanbul');

		for ($i = 0; $i < 150; $i++) {
			$this->booking(array(
				'pickup_date'  => '2027-05-10',
				'dropoff_date' => '2027-05-12',
				'start_ts'     => (int) strtotime('2027-05-10 10:00'),
				'end_ts'       => (int) strtotime('2027-05-12 10:00'),
			));
		}

		$written = 0;
		add_action(
			'updated_post_meta',
			static function ($meta_id, $object_id, $meta_key) use (&$written): void {
				unset($meta_id, $object_id);
				if ('_mhmrentiva_end_ts' === $meta_key && ++$written === 120) {
					throw new \RuntimeException('request died mid-run');
				}
			},
			10,
			3
		);
		$flag_writes = 0;
		add_filter(
			'pre_update_option_' . DatabaseMigrator::BOOKING_TS_DONE_OPTION,
			static function ($value) use (&$flag_writes) {
				++$flag_writes;
				return $value;
			}
		);

		try {
			$this->step();
			$this->fail('The simulated interruption did not fire.');
		} catch (\RuntimeException $e) {
			$this->assertSame('request died mid-run', $e->getMessage());
		}

		$this->assertSame(0, $flag_writes);
		$this->assertFalse(get_option(DatabaseMigrator::BOOKING_TS_DONE_OPTION), 'A partial run must not leave the flag Pro gates on.');
	}

	public function test_full_migration_stamps_480_and_runs_the_step(): void
	{
		$this->pin_site_timezone('Europe/Istanbul');

		$id = $this->booking(array(
			'pickup_date'  => '2027-05-10',
			'dropoff_date' => '2027-05-12',
			'start_ts'     => (int) strtotime('2027-05-10 10:00'),
			'end_ts'       => (int) strtotime('2027-05-12 10:00'),
		));
		update_option('mhmrentiva_db_version', '4.7.0');

		DatabaseMigrator::run_migrations();

		$this->assertSame('4.8.0', get_option('mhmrentiva_db_version'));
		$this->assertSame('1', get_option(DatabaseMigrator::BOOKING_TS_DONE_OPTION));
		$this->assertSame((string) self::local_at('2027-05-10 10:00', 3), self::ts($id, 'start'));
	}
}
