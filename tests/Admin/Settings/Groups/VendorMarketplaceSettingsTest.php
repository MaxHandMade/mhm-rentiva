<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings\Groups;

use MHMRentiva\Admin\Settings\Groups\VendorMarketplaceSettings;
use WP_UnitTestCase;

class VendorMarketplaceSettingsTest extends WP_UnitTestCase
{
    public function test_default_settings_include_listing_fee_fields(): void
    {
        $defaults = VendorMarketplaceSettings::get_default_settings();

        $this->assertArrayHasKey('mhm_rentiva_listing_fee_enabled', $defaults);
        $this->assertArrayHasKey('mhm_rentiva_listing_fee_model', $defaults);
        $this->assertArrayHasKey('mhm_rentiva_listing_fee_amount', $defaults);
        $this->assertFalse($defaults['mhm_rentiva_listing_fee_enabled']);
        $this->assertSame('one_time', $defaults['mhm_rentiva_listing_fee_model']);
        $this->assertSame(0.0, $defaults['mhm_rentiva_listing_fee_amount']);
    }
}
