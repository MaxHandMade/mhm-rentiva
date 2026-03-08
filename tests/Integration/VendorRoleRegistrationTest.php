<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

/**
 * Tests for Plugin::register_vendor_role().
 */
final class VendorRoleRegistrationTest extends \WP_UnitTestCase
{
    public function test_register_vendor_role_creates_role(): void
    {
        remove_role('rentiva_vendor');
        \MHMRentiva\Plugin::register_vendor_role();
        $role = get_role('rentiva_vendor');
        $this->assertNotNull($role);
        $this->assertTrue($role->has_cap('read'));
        $this->assertTrue($role->has_cap('upload_files'));
    }

    public function test_register_vendor_role_is_idempotent(): void
    {
        \MHMRentiva\Plugin::register_vendor_role();
        \MHMRentiva\Plugin::register_vendor_role();
        $this->assertNotNull(get_role('rentiva_vendor'));
    }

    protected function tearDown(): void
    {
        remove_role('rentiva_vendor');
        parent::tearDown();
    }
}
