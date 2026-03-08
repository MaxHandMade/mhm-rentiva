<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor;

class VendorApplicationPostTypeTest extends \WP_UnitTestCase
{
    public function test_post_type_constant_is_correct(): void
    {
        $this->assertSame('mhm_vendor_app', \MHMRentiva\Admin\Vendor\PostType\VendorApplication::POST_TYPE);
    }

    public function test_register_post_type_registers_cpt(): void
    {
        \MHMRentiva\Admin\Vendor\PostType\VendorApplication::register_post_type();
        $this->assertTrue(post_type_exists('mhm_vendor_app'));
    }
}
