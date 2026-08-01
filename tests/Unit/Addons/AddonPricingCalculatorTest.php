<?php
// tests/Unit/Addons/AddonPricingCalculatorTest.php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Addons;

use MHMRentiva\Admin\Addons\AddonPricingCalculator;
use WP_UnitTestCase;

final class AddonPricingCalculatorTest extends WP_UnitTestCase {

    private function make_addon( float $price, string $type ): int {
        $id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_addon' ) );
        update_post_meta( $id, 'mhmrentiva_addon_price', (string) $price );
        update_post_meta( $id, '_mhmrentiva_addon_pricing_type', $type );
        return $id;
    }

    public function test_per_booking_returns_flat_price(): void {
        $id = $this->make_addon( 50.0, 'per_booking' );
        $this->assertSame(
            50.0,
            AddonPricingCalculator::calculate( $id, array( 'rental_days' => 5, 'adults' => 3 ) )
        );
    }

    public function test_per_day_multiplies_by_rental_days(): void {
        $id = $this->make_addon( 15.0, 'per_day' );
        $this->assertSame(
            75.0,
            AddonPricingCalculator::calculate( $id, array( 'rental_days' => 5 ) )
        );
    }

    public function test_per_day_treats_zero_days_as_one(): void {
        $id = $this->make_addon( 15.0, 'per_day' );
        $this->assertSame(
            15.0,
            AddonPricingCalculator::calculate( $id, array( 'rental_days' => 0 ) )
        );
    }

    public function test_per_passenger_sums_adults_and_children(): void {
        $id = $this->make_addon( 20.0, 'per_passenger' );
        $this->assertSame(
            80.0,
            AddonPricingCalculator::calculate( $id, array( 'adults' => 2, 'children' => 2 ) )
        );
    }

    public function test_per_passenger_treats_zero_pax_as_one(): void {
        $id = $this->make_addon( 20.0, 'per_passenger' );
        $this->assertSame(
            20.0,
            AddonPricingCalculator::calculate( $id, array( 'adults' => 0, 'children' => 0 ) )
        );
    }

    public function test_unknown_pricing_type_falls_back_to_per_booking(): void {
        $id = $this->make_addon( 30.0, 'per_galaxy' );
        $this->assertSame(
            30.0,
            AddonPricingCalculator::calculate( $id, array( 'rental_days' => 5, 'adults' => 3 ) )
        );
    }

    public function test_missing_context_uses_safe_defaults(): void {
        $id = $this->make_addon( 10.0, 'per_day' );
        $this->assertSame(
            10.0,
            AddonPricingCalculator::calculate( $id, array() )
        );
    }

    public function test_negative_price_is_clamped_to_zero(): void {
        $id = $this->make_addon( -5.0, 'per_booking' );
        $this->assertSame(
            0.0,
            AddonPricingCalculator::calculate( $id, array() )
        );
    }

    public function test_multiplier_per_booking_returns_one(): void {
        $this->assertSame(
            1,
            AddonPricingCalculator::multiplier( 'per_booking', array( 'rental_days' => 5, 'adults' => 3 ) )
        );
    }

    public function test_multiplier_per_day_returns_rental_days(): void {
        $this->assertSame(
            5,
            AddonPricingCalculator::multiplier( 'per_day', array( 'rental_days' => 5 ) )
        );
    }

    public function test_multiplier_per_passenger_sums_pax(): void {
        $this->assertSame(
            4,
            AddonPricingCalculator::multiplier( 'per_passenger', array( 'adults' => 2, 'children' => 2 ) )
        );
    }

    public function test_multiplier_per_passenger_treats_zero_pax_as_one(): void {
        $this->assertSame(
            1,
            AddonPricingCalculator::multiplier( 'per_passenger', array( 'adults' => 0, 'children' => 0 ) )
        );
    }
}
