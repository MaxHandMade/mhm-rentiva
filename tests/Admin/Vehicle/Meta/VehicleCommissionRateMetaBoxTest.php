<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\Meta;

use MHMRentiva\Admin\Vehicle\Meta\VehicleCommissionRateMetaBox;
use WP_UnitTestCase;

class VehicleCommissionRateMetaBoxTest extends WP_UnitTestCase
{
    private int $vehicle_id;
    private int $admin_id;

    public function setUp(): void
    {
        parent::setUp();
        $this->admin_id   = self::factory()->user->create(array('role' => 'administrator'));
        $this->vehicle_id = self::factory()->post->create(array('post_type' => 'vehicle'));
        wp_set_current_user($this->admin_id);
    }

    public function test_get_post_type_is_vehicle(): void
    {
        $reflection = new \ReflectionMethod(VehicleCommissionRateMetaBox::class, 'get_post_type');
        $reflection->setAccessible(true);
        $this->assertSame('vehicle', $reflection->invoke(null));
    }

    public function test_saving_the_field_persists_the_rate(): void
    {
        $nonce_name = 'vehicle_mhmrentiva_vehicle_commission_rate_nonce';

        $_POST[ $nonce_name ]                          = wp_create_nonce($nonce_name);
        $_POST['_mhmrentiva_vendor_commission_rate']          = '9.5';

        VehicleCommissionRateMetaBox::save_meta($this->vehicle_id, get_post($this->vehicle_id));

        $this->assertSame('9.5', get_post_meta($this->vehicle_id, '_mhmrentiva_vendor_commission_rate', true));

        unset($_POST[ $nonce_name ], $_POST['_mhmrentiva_vendor_commission_rate']);
    }

    public function test_saving_an_out_of_range_high_value_is_clamped_to_100(): void
    {
        $nonce_name = 'vehicle_mhmrentiva_vehicle_commission_rate_nonce';

        $_POST[ $nonce_name ]                 = wp_create_nonce($nonce_name);
        $_POST['_mhmrentiva_vendor_commission_rate'] = '500';

        VehicleCommissionRateMetaBox::save_meta($this->vehicle_id, get_post($this->vehicle_id));

        $this->assertSame('100', get_post_meta($this->vehicle_id, '_mhmrentiva_vendor_commission_rate', true));

        unset($_POST[ $nonce_name ], $_POST['_mhmrentiva_vendor_commission_rate']);
    }

    public function test_saving_a_negative_value_is_clamped_to_0(): void
    {
        $nonce_name = 'vehicle_mhmrentiva_vehicle_commission_rate_nonce';

        $_POST[ $nonce_name ]                 = wp_create_nonce($nonce_name);
        $_POST['_mhmrentiva_vendor_commission_rate'] = '-10';

        VehicleCommissionRateMetaBox::save_meta($this->vehicle_id, get_post($this->vehicle_id));

        $this->assertSame('0', get_post_meta($this->vehicle_id, '_mhmrentiva_vendor_commission_rate', true));

        unset($_POST[ $nonce_name ], $_POST['_mhmrentiva_vendor_commission_rate']);
    }

    public function test_saving_a_non_numeric_value_stores_an_empty_string(): void
    {
        $nonce_name = 'vehicle_mhmrentiva_vehicle_commission_rate_nonce';

        $_POST[ $nonce_name ]                 = wp_create_nonce($nonce_name);
        $_POST['_mhmrentiva_vendor_commission_rate'] = 'abc';

        VehicleCommissionRateMetaBox::save_meta($this->vehicle_id, get_post($this->vehicle_id));

        $this->assertSame('', get_post_meta($this->vehicle_id, '_mhmrentiva_vendor_commission_rate', true));

        unset($_POST[ $nonce_name ], $_POST['_mhmrentiva_vendor_commission_rate']);
    }
}
