<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vendor;

use MHMRentiva\Admin\Vendor\VendorApplicationManager;
use WP_UnitTestCase;

class VendorApplicationTermsMetaTest extends WP_UnitTestCase
{
    private function applicant(): int
    {
        $uid = (int) self::factory()->user->create(array('role' => 'customer'));
        wp_set_current_user($uid);
        return $uid;
    }

    /** @test */
    public function test_stores_terms_proof_when_present(): void
    {
        $uid = $this->applicant();
        $id  = VendorApplicationManager::create_application($uid, array(
            'phone'             => '+90 555 000 0000',
            'city'              => 'istanbul',
            'iban'              => 'TR000000000000000000000000',
            'account_holder'    => 'Test Vendor',
            'terms_accepted_at' => '2026-06-23 10:00:00',
            'terms_version'     => str_repeat('a', 64),
        ));

        $this->assertIsInt($id);
        $this->assertSame('2026-06-23 10:00:00', get_post_meta($id, '_vendor_terms_accepted_at', true));
        $this->assertSame(str_repeat('a', 64), get_post_meta($id, '_vendor_terms_version', true));
    }

    /** @test */
    public function test_no_terms_meta_when_absent(): void
    {
        $uid = $this->applicant();
        $id  = VendorApplicationManager::create_application($uid, array(
            'phone'          => '+90 555 000 0000',
            'city'           => 'istanbul',
            'iban'           => 'TR000000000000000000000000',
            'account_holder' => 'Test Vendor',
        ));

        $this->assertIsInt($id);
        $this->assertSame('', get_post_meta($id, '_vendor_terms_accepted_at', true));
        $this->assertSame('', get_post_meta($id, '_vendor_terms_version', true));
    }
}
