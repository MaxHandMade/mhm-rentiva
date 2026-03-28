<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\EndpointHelperTrait;
use WP_UnitTestCase;

/**
 * Class EndpointHelperTest
 * 
 * Tests the EndpointHelperTrait implementation.
 */
class EndpointHelperTest extends WP_UnitTestCase
{
    /**
     * Dummy class to test trait
     */
    private $tester;

    public function setUp(): void
    {
        parent::setUp();

        $this->tester = new class {
            use EndpointHelperTrait;
        };

        $this->tester::clear_slug_cache();
    }

    /**
     * Test default slug resolution
     */
    public function test_get_endpoint_slug_default()
    {
        $slug = $this->tester::get_endpoint_slug('bookings');
        // Default in map is 'rentiva-bookings'
        $this->assertEquals('rentiva-bookings', $slug);
    }

    /**
     * Test database option priority
     */
    public function test_get_endpoint_slug_db_priority()
    {
        $settings = array(
            'mhm_rentiva_endpoint_bookings' => 'custom-bookings-slug'
        );
        update_option('mhm_rentiva_settings', $settings);

        $this->tester::clear_slug_cache();
        $slug = $this->tester::get_endpoint_slug('bookings');

        $this->assertEquals('custom-bookings-slug', $slug);
    }

    /**
     * Test static caching
     */
    public function test_slug_caching()
    {
        // First call
        $slug1 = $this->tester::get_endpoint_slug('favorites');

        // Change DB option
        $settings = array(
            'mhm_rentiva_endpoint_favorites' => 'new-favorites'
        );
        update_option('mhm_rentiva_settings', $settings);

        // Second call (should still return old value due to cache)
        $slug2 = $this->tester::get_endpoint_slug('favorites');

        $this->assertEquals($slug1, $slug2);

        // Clear cache and call again
        $this->tester::clear_slug_cache();
        $slug3 = $this->tester::get_endpoint_slug('favorites');

        $this->assertEquals('new-favorites', $slug3);
    }

    /**
     * Test sanitization
     */
    public function test_slug_sanitization()
    {
        $settings = array(
            'mhm_rentiva_endpoint_payment_history' => 'Payment History !!!'
        );
        update_option('mhm_rentiva_settings', $settings);

        $this->tester::clear_slug_cache();
        $slug = $this->tester::get_endpoint_slug('payment_history');

        $this->assertEquals('payment-history', $slug);
    }
}
