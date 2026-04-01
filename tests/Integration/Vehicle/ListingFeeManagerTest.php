<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vehicle;

use MHMRentiva\Admin\Vehicle\ListingFeeManager;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleManager;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus;
use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Settings\Core\SettingsCore;

/**
 * Tests for ListingFeeManager — paid vehicle listing fee logic.
 *
 * @covers \MHMRentiva\Admin\Vehicle\ListingFeeManager
 */
final class ListingFeeManagerTest extends \WP_UnitTestCase
{
    private int $vendor_id;
    private int $vehicle_id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor_id = $this->factory()->user->create(array('role' => 'subscriber'));
        $user = get_userdata($this->vendor_id);
        $user->add_role('rentiva_vendor');

        $this->vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'pending',
            'post_author' => $this->vendor_id,
            'post_title'  => 'Listing Fee Test Vehicle',
        ));
    }

    protected function tearDown(): void
    {
        delete_option(SettingsCore::OPTION_NAME);
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * Seed the serialized settings option with listing fee values.
     */
    private function seed_settings(bool $enabled, float $amount, string $model = 'one_time'): void
    {
        $settings = get_option(SettingsCore::OPTION_NAME, array());
        $settings['mhm_rentiva_listing_fee_enabled'] = $enabled;
        $settings['mhm_rentiva_listing_fee_amount']  = $amount;
        $settings['mhm_rentiva_listing_fee_model']   = $model;
        update_option(SettingsCore::OPTION_NAME, $settings);
    }

    // ── Task 2: Core Settings Tests ─────────────────────────

    public function test_is_enabled_returns_false_by_default(): void
    {
        $this->assertFalse(ListingFeeManager::is_enabled());
    }

    public function test_is_enabled_returns_true_when_setting_on(): void
    {
        $this->seed_settings(true, 50.0);
        $this->assertTrue(ListingFeeManager::is_enabled());
    }

    public function test_is_enabled_returns_false_when_amount_zero(): void
    {
        $this->seed_settings(true, 0.0);
        $this->assertFalse(ListingFeeManager::is_enabled());
    }

    public function test_get_fee_amount_reads_from_settings(): void
    {
        $this->seed_settings(true, 99.50);
        $this->assertSame(99.50, ListingFeeManager::get_fee_amount());
    }

    public function test_get_fee_model_defaults_to_one_time(): void
    {
        // No settings configured — should return 'one_time'.
        $this->assertSame('one_time', ListingFeeManager::get_fee_model());
    }

    public function test_get_fee_model_validates_against_allowed_values(): void
    {
        $this->seed_settings(true, 50.0, 'invalid_model');
        $this->assertSame('one_time', ListingFeeManager::get_fee_model());
    }

    public function test_get_fee_model_returns_per_listing(): void
    {
        $this->seed_settings(true, 50.0, 'per_listing');
        $this->assertSame('per_listing', ListingFeeManager::get_fee_model());
    }

    public function test_requires_payment_returns_false_when_disabled(): void
    {
        // Default settings — disabled.
        $this->assertFalse(ListingFeeManager::requires_payment('new'));
        $this->assertFalse(ListingFeeManager::requires_payment('renew'));
        $this->assertFalse(ListingFeeManager::requires_payment('relist'));
    }

    public function test_requires_payment_returns_true_for_valid_actions(): void
    {
        $this->seed_settings(true, 50.0);

        $this->assertTrue(ListingFeeManager::requires_payment('new'));
        $this->assertTrue(ListingFeeManager::requires_payment('renew'));
        $this->assertTrue(ListingFeeManager::requires_payment('relist'));
    }

    public function test_requires_payment_returns_false_for_unknown_action(): void
    {
        $this->seed_settings(true, 50.0);
        $this->assertFalse(ListingFeeManager::requires_payment('edit'));
        $this->assertFalse(ListingFeeManager::requires_payment('delete'));
    }

    // ── Task 3: WC Product Tests ────────────────────────────

    public function test_get_or_create_product_creates_hidden_product(): void
    {
        if (! class_exists('WC_Product_Simple')) {
            $this->markTestSkipped('WooCommerce not available.');
        }

        $product_id = ListingFeeManager::get_or_create_product();
        $this->assertGreaterThan(0, $product_id);

        $product = wc_get_product($product_id);
        $this->assertNotFalse($product);
        $this->assertSame(ListingFeeManager::PRODUCT_SKU, $product->get_sku());
        $this->assertTrue($product->is_virtual());
        $this->assertSame('hidden', $product->get_catalog_visibility());
    }

    public function test_get_or_create_product_returns_same_id_on_second_call(): void
    {
        if (! class_exists('WC_Product_Simple')) {
            $this->markTestSkipped('WooCommerce not available.');
        }

        $first_id  = ListingFeeManager::get_or_create_product();
        $second_id = ListingFeeManager::get_or_create_product();

        $this->assertSame($first_id, $second_id);
    }

    // ── Task 4: Order Processing Tests ──────────────────────

    public function test_process_completed_order_transitions_new_vehicle_to_pending_review(): void
    {
        // Vehicle starts as 'pending' post_status, no lifecycle meta.
        ListingFeeManager::process_completed_order($this->vehicle_id, 'new');

        $this->assertSame('pending', get_post_status($this->vehicle_id));
        $this->assertSame(
            VehicleLifecycleStatus::PENDING_REVIEW,
            get_post_meta($this->vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, true)
        );
        $this->assertSame(
            'pending_review',
            get_post_meta($this->vehicle_id, '_vehicle_review_status', true)
        );
    }

    public function test_process_completed_order_fires_action_for_new(): void
    {
        $fired = false;
        add_action('mhm_rentiva_listing_fee_completed', function ($vid, $act) use (&$fired) {
            $fired = true;
        }, 10, 2);

        ListingFeeManager::process_completed_order($this->vehicle_id, 'new');
        $this->assertTrue($fired);
    }

    public function test_process_completed_order_fires_action_for_relist(): void
    {
        ListingFeeManager::process_completed_order($this->vehicle_id, 'relist');

        $this->assertSame(
            VehicleLifecycleStatus::PENDING_REVIEW,
            get_post_meta($this->vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, true)
        );
        $this->assertGreaterThan(0, did_action('mhm_rentiva_listing_fee_completed'));
    }

    public function test_process_completed_order_handles_renew_action(): void
    {
        // Set up vehicle as expired so renew() can work.
        update_post_meta($this->vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::EXPIRED);
        update_post_meta($this->vehicle_id, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, gmdate('Y-m-d H:i:s', strtotime('-1 day')));

        ListingFeeManager::process_completed_order($this->vehicle_id, 'renew');

        // After renew, vehicle should be active.
        $this->assertSame(
            VehicleLifecycleStatus::ACTIVE,
            VehicleLifecycleStatus::get($this->vehicle_id)
        );
    }

    public function test_process_completed_order_ignores_unknown_action(): void
    {
        // Should not change anything for an unknown action.
        ListingFeeManager::process_completed_order($this->vehicle_id, 'unknown');

        // No lifecycle status change expected.
        $this->assertEmpty(get_post_meta($this->vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, true));
    }
}
