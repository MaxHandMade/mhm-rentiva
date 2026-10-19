<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vehicle;

use MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox;
use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar;
use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * A vehicle's service type (`rental` / `transfer` / `both`) is written by the
 * add-on's transfer meta box, but Lite had no reader: a "transfer only" vehicle
 * was listed, offered and bookable on every rental surface.
 *
 * This file pins the single source of truth (the helper) and the VISIBILITY half
 * of the fix: a transfer-only vehicle must not appear on a rental listing, while
 * a vehicle with no service type at all -- every vehicle on a Lite-only site --
 * stays exactly as visible as before.
 *
 * Each exclusion is paired with an inclusion of the `both` / untyped vehicle, so
 * a regression that empties a listing outright cannot pass as "hidden".
 *
 * @covers \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_service_type
 * @covers \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::allows_service
 * @covers \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::service_meta_query
 * @covers \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::restrict_query_to_service
 */
final class VehicleServiceTypeVisibilityTest extends WP_UnitTestCase
{
	/** Legacy (pre-6.0.0) spelling the add-on wrote before the prefix rename. */
	private const LEGACY_KEY = MetaKeys::VEHICLE_SERVICE_TYPE_LEGACY;

	private int $untyped_id;
	private int $rental_id;
	private int $both_id;
	private int $transfer_id;
	private int $legacy_transfer_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->untyped_id         = $this->make_vehicle('Untyped Sedan', null, null);
		$this->rental_id          = $this->make_vehicle('Rental Hatchback', 'rental', null);
		$this->both_id            = $this->make_vehicle('Both Minivan', 'both', null);
		$this->transfer_id        = $this->make_vehicle('Transfer Only Vito', 'transfer', null);
		$this->legacy_transfer_id = $this->make_vehicle('Legacy Transfer Sprinter', null, 'transfer');

		// Role-agnostic list caches outlive a test's rolled-back rows.
		\MHMRentiva\Admin\Core\PerformanceHelper::cache_invalidate_tags(array( 'vehicles', 'availability_calendar' ));
	}

	public function tearDown(): void
	{
		\MHMRentiva\Admin\Core\PerformanceHelper::cache_invalidate_tags(array( 'vehicles', 'availability_calendar' ));
		parent::tearDown();
	}

	// ------------------------------------------------------------ the helper

	public function test_meta_key_is_a_named_constant(): void
	{
		$this->assertSame('_mhmrentiva_vehicle_service_type', MetaKeys::VEHICLE_SERVICE_TYPE);

		// The legacy key is the old key the 6.0.0 migration merged into the new
		// one -- the add-on's transfer meta box wrote it before the rename.
		$this->assertSame(
			\MHMRentiva\Admin\Core\Utilities\PrefixMigrationMap::POSTMETA_MERGE_WINNERS[ MetaKeys::VEHICLE_SERVICE_TYPE ],
			MetaKeys::VEHICLE_SERVICE_TYPE_LEGACY
		);
		$this->assertNotSame(MetaKeys::VEHICLE_SERVICE_TYPE, MetaKeys::VEHICLE_SERVICE_TYPE_LEGACY);
		$this->assertStringEndsWith('_vehicle_service_type', MetaKeys::VEHICLE_SERVICE_TYPE_LEGACY);
	}

	public function test_a_vehicle_without_a_service_type_is_a_rental_vehicle(): void
	{
		$this->assertSame('rental', VehicleDataHelper::get_service_type($this->untyped_id));
		$this->assertTrue(VehicleDataHelper::allows_service($this->untyped_id, 'rental'));
		$this->assertFalse(VehicleDataHelper::allows_service($this->untyped_id, 'transfer'));
	}

	public function test_service_type_matrix(): void
	{
		$this->assertSame('rental', VehicleDataHelper::get_service_type($this->rental_id));
		$this->assertSame('both', VehicleDataHelper::get_service_type($this->both_id));
		$this->assertSame('transfer', VehicleDataHelper::get_service_type($this->transfer_id));

		$this->assertTrue(VehicleDataHelper::allows_service($this->rental_id, 'rental'));
		$this->assertFalse(VehicleDataHelper::allows_service($this->rental_id, 'transfer'));
		$this->assertTrue(VehicleDataHelper::allows_service($this->both_id, 'rental'));
		$this->assertTrue(VehicleDataHelper::allows_service($this->both_id, 'transfer'));
		$this->assertFalse(VehicleDataHelper::allows_service($this->transfer_id, 'rental'));
		$this->assertTrue(VehicleDataHelper::allows_service($this->transfer_id, 'transfer'));
	}

	public function test_legacy_key_is_read_only_when_the_current_key_is_absent(): void
	{
		$this->assertSame('transfer', VehicleDataHelper::get_service_type($this->legacy_transfer_id));
		$this->assertFalse(VehicleDataHelper::allows_service($this->legacy_transfer_id, 'rental'));

		// The current key wins over a stale legacy row.
		$mixed = $this->make_vehicle('Mixed Keys', 'rental', 'transfer');
		$this->assertSame('rental', VehicleDataHelper::get_service_type($mixed));

		// An empty current value falls back to the legacy row.
		$empty_current = $this->make_vehicle('Empty Current', '', 'transfer');
		$this->assertSame('transfer', VehicleDataHelper::get_service_type($empty_current));
	}

	public function test_unknown_values_and_unknown_services_fail_safe(): void
	{
		$garbage = $this->make_vehicle('Garbage Type', 'spaceship', null);

		$this->assertSame('rental', VehicleDataHelper::get_service_type($garbage));
		$this->assertTrue(VehicleDataHelper::allows_service($garbage, 'rental'));
		$this->assertFalse(VehicleDataHelper::allows_service($this->both_id, 'teleport'));
	}

	public function test_rental_meta_query_matches_the_helper_on_every_shape(): void
	{
		$mixed         = $this->make_vehicle('Mixed Keys', 'rental', 'transfer');
		$empty_current = $this->make_vehicle('Empty Current', '', 'transfer');
		$garbage       = $this->make_vehicle('Garbage Type', 'spaceship', null);

		$ids = get_posts(
			VehicleDataHelper::restrict_query_to_service(
				array(
					'post_type'      => 'mhmrentiva_vehicle',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				),
				'rental'
			)
		);
		$ids = array_map('intval', $ids);
		sort($ids);

		$expected = array();
		foreach (array( $this->untyped_id, $this->rental_id, $this->both_id, $this->transfer_id, $this->legacy_transfer_id, $mixed, $empty_current, $garbage ) as $id) {
			if (VehicleDataHelper::allows_service($id, 'rental')) {
				$expected[] = $id;
			}
		}
		sort($expected);

		$this->assertSame($expected, $ids, 'The SQL clause and the PHP helper must agree on every meta shape.');
		$this->assertNotContains($this->transfer_id, $ids);
		$this->assertContains($this->untyped_id, $ids);
	}

	public function test_restrict_keeps_an_existing_or_meta_query_intact(): void
	{
		$args = VehicleDataHelper::restrict_query_to_service(
			array(
				'post_type'      => 'mhmrentiva_vehicle',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'   => '_mhmrentiva_price_per_day',
						'value' => '100',
					),
					array(
						'key'   => '_mhmrentiva_price_per_day',
						'value' => '200',
					),
				),
			),
			'rental'
		);

		$ids = array_map('intval', get_posts($args));

		// All five fixtures cost 100; the OR must still select them and the
		// service clause must still remove the transfer-only ones.
		$this->assertContains($this->both_id, $ids);
		$this->assertNotContains($this->transfer_id, $ids);
		$this->assertNotContains($this->legacy_transfer_id, $ids);
	}

	// ------------------------------------------------------------ listings

	public function test_vehicles_grid_hides_transfer_only_vehicles(): void
	{
		$this->assert_listing('[rentiva_vehicles_grid limit="50"]');
	}

	public function test_vehicles_list_hides_transfer_only_vehicles(): void
	{
		$this->assert_listing('[rentiva_vehicles_list limit="50"]');
	}

	public function test_featured_vehicles_hide_transfer_only_vehicles(): void
	{
		foreach (array( $this->untyped_id, $this->both_id, $this->transfer_id, $this->legacy_transfer_id ) as $id) {
			update_post_meta($id, '_mhmrentiva_featured', '1');
		}

		$this->assert_listing('[rentiva_featured_vehicles limit="50"]', array( 'Untyped Sedan', 'Both Minivan' ));
	}

	public function test_vehicles_list_runs_the_shared_query_filter(): void
	{
		$contexts = array();
		$spy      = static function (array $args, string $context) use (&$contexts): array {
			$contexts[] = $context;
			return $args;
		};
		add_filter('mhmrentiva_vehicle_query_args', $spy, 10, 2);

		do_shortcode('[rentiva_vehicles_list]');

		remove_filter('mhmrentiva_vehicle_query_args', $spy, 10);
		$this->assertContains('vehicles_list', $contexts);
	}

	public function test_search_results_hide_transfer_only_vehicles(): void
	{
		$method = new ReflectionMethod(\MHMRentiva\Admin\Frontend\Shortcodes\SearchResults::class, 'perform_search');
		$method->setAccessible(true);

		$result = $method->invoke(
			null,
			array(
				'page'        => 1,
				'pickup_date' => gmdate('Y-m-d', strtotime('+10 days')),
				'return_date' => gmdate('Y-m-d', strtotime('+12 days')),
				'min_price'   => 0,
				'max_price'   => 0,
				'year_min'    => 0,
				'year_max'    => 0,
				'mileage_max' => 0,
				'sort'        => 'name_asc',
			),
			array( 'results_per_page' => 50 )
		);

		$ids = $this->ids_in($result);
		$this->assertContains($this->untyped_id, $ids, 'Positive control: an untyped vehicle is a rental vehicle.');
		$this->assertContains($this->both_id, $ids);
		$this->assertNotContains($this->transfer_id, $ids);
		$this->assertNotContains($this->legacy_transfer_id, $ids);
	}

	// ---------------------------------------------------------- booking form

	public function test_booking_form_vehicle_dropdown_omits_transfer_only_vehicles(): void
	{
		$method = new ReflectionMethod(BookingForm::class, 'get_available_vehicles');
		$method->setAccessible(true);

		$ids = array_map('intval', wp_list_pluck($method->invoke(null), 'id'));

		$this->assertContains($this->untyped_id, $ids);
		$this->assertContains($this->both_id, $ids);
		$this->assertNotContains($this->transfer_id, $ids);
		$this->assertNotContains($this->legacy_transfer_id, $ids);
	}

	public function test_booking_form_refuses_to_preselect_a_transfer_only_vehicle(): void
	{
		$method = new ReflectionMethod(BookingForm::class, 'prepare_selected_vehicle');
		$method->setAccessible(true);

		$this->assertNull($method->invoke(null, $this->transfer_id, array()));
		$this->assertNotNull($method->invoke(null, $this->both_id, array()), 'Positive control: a both-type vehicle is preselected.');
	}

	// -------------------------------------------------------- vehicle details

	public function test_vehicle_details_hides_the_booking_button_for_transfer_only(): void
	{
		$transfer = do_shortcode('[rentiva_vehicle_details vehicle_id="' . $this->transfer_id . '"]');
		$both     = do_shortcode('[rentiva_vehicle_details vehicle_id="' . $this->both_id . '"]');

		$this->assertStringContainsString('rv-vehicle-details-wrapper', $transfer, 'The details page itself still renders.');
		$this->assertStringNotContainsString('rv-cta-container', $transfer);
		$this->assertStringContainsString('rv-cta-container', $both, 'Positive control: a both-type vehicle keeps its booking button.');
	}

	public function test_vehicle_card_drops_the_booking_button_for_transfer_only(): void
	{
		// The card partial also renders outside the filtered listings -- the
		// favourites page, the Elementor single-card widget -- so the card
		// itself must not offer a rental booking for a transfer-only vehicle.
		$render = static function (int $id): string {
			return (string) \MHMRentiva\Admin\Core\Utilities\Templates::render(
				'partials/vehicle-card',
				array(
					'vehicle' => \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_vehicle_data_for_shortcode($id, array()),
					'layout'  => 'grid',
					'atts'    => array( 'show_booking_button' => '1' ),
				),
				true
			);
		};

		$this->assertStringNotContainsString('vehicle-book-btn', $render($this->transfer_id));
		$this->assertStringContainsString('vehicle-book-btn', $render($this->both_id), 'Positive control: a both-type card keeps its button.');
	}

	// ------------------------------------------------------ calendar + admin

	public function test_availability_calendar_switcher_omits_transfer_only_vehicles(): void
	{
		$method = new ReflectionMethod(AvailabilityCalendar::class, 'get_vehicles_list');
		$method->setAccessible(true);

		$ids = array_map('intval', wp_list_pluck($method->invoke(null), 'id'));

		$this->assertContains($this->untyped_id, $ids);
		$this->assertNotContains($this->transfer_id, $ids);
		$this->assertNotContains($this->legacy_transfer_id, $ids);
	}

	public function test_manual_booking_vehicle_select_omits_transfer_only_vehicles(): void
	{
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		$booking_id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));

		ob_start();
		ManualBookingMetaBox::render(get_post($booking_id));
		$markup = (string) ob_get_clean();

		$this->assertStringContainsString('<option value="' . $this->both_id . '"', $markup);
		$this->assertStringContainsString('<option value="' . $this->untyped_id . '"', $markup);
		$this->assertStringNotContainsString('<option value="' . $this->transfer_id . '"', $markup);
		$this->assertStringNotContainsString('<option value="' . $this->legacy_transfer_id . '"', $markup);
	}

	// ------------------------------------------------------------- fixtures

	private function make_vehicle(string $title, ?string $current, ?string $legacy): int
	{
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
		update_post_meta($id, '_mhmrentiva_price_per_day', '100');
		update_post_meta($id, '_mhmrentiva_vehicle_status', 'active');

		if (null !== $current) {
			update_post_meta($id, '_mhmrentiva_vehicle_service_type', $current);
		}
		if (null !== $legacy) {
			update_post_meta($id, self::LEGACY_KEY, $legacy);
		}

		return $id;
	}

	/**
	 * @param array<int, string>|null $visible Titles that must appear; defaults to the untyped and both vehicles.
	 */
	private function assert_listing(string $shortcode, ?array $visible = null): void
	{
		$output = do_shortcode($shortcode);

		foreach ($visible ?? array( 'Untyped Sedan', 'Rental Hatchback', 'Both Minivan' ) as $title) {
			$this->assertStringContainsString($title, $output, "Positive control: {$title} must stay listed.");
		}
		$this->assertStringNotContainsString('Transfer Only Vito', $output);
		$this->assertStringNotContainsString('Legacy Transfer Sprinter', $output);
	}

	/**
	 * @param mixed $result
	 * @return array<int, int>
	 */
	private function ids_in($result): array
	{
		$rows = is_array($result) ? ( $result['vehicles'] ?? $result ) : array();
		$ids  = array();
		foreach ((array) $rows as $row) {
			if (is_array($row) && isset($row['id'])) {
				$ids[] = (int) $row['id'];
			} elseif ($row instanceof \WP_Post) {
				$ids[] = (int) $row->ID;
			} elseif (is_numeric($row)) {
				$ids[] = (int) $row;
			}
		}
		return $ids;
	}
}
