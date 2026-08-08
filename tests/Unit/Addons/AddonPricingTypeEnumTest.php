<?php
// tests/Unit/Addons/AddonPricingTypeEnumTest.php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Addons;

use MHMRentiva\Admin\Addons\AddonPricingType;
use PHPUnit\Framework\TestCase;

final class AddonPricingTypeEnumTest extends TestCase {

    public function test_constants_match_spec(): void {
        $this->assertSame( 'per_booking', AddonPricingType::PER_BOOKING );
        $this->assertSame( 'per_day', AddonPricingType::PER_DAY );
        $this->assertSame( 'per_passenger', AddonPricingType::PER_PASSENGER );
    }

    public function test_sanitize_falls_back_to_per_booking_for_unknown_value(): void {
        $this->assertSame( 'per_booking', AddonPricingType::sanitize( 'per_km' ) );
        $this->assertSame( 'per_booking', AddonPricingType::sanitize( '' ) );
        $this->assertSame( 'per_booking', AddonPricingType::sanitize( 'PER_DAY' ) ); // case-sensitive
        $this->assertSame( 'per_booking', AddonPricingType::sanitize( null ) );
    }

    public function test_sanitize_preserves_valid_values(): void {
        $this->assertSame( 'per_booking', AddonPricingType::sanitize( 'per_booking' ) );
        $this->assertSame( 'per_day', AddonPricingType::sanitize( 'per_day' ) );
        $this->assertSame( 'per_passenger', AddonPricingType::sanitize( 'per_passenger' ) );
    }

    public function test_label_returns_non_empty_string_for_all_types(): void {
        foreach ( AddonPricingType::all() as $type ) {
            $label = AddonPricingType::label( $type );
            $this->assertIsString( $label );
            $this->assertNotEmpty( $label, "label() returned empty for valid type '$type'" );
        }
        // Unknown type also gets a non-empty label (defaults to per_booking copy).
        $this->assertNotEmpty( AddonPricingType::label( 'galaxy' ) );
    }
}
