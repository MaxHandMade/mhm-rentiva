<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor;

use MHMRentiva\Admin\Vendor\VendorApplicationManager;

class VendorApplicationManagerTest extends \WP_UnitTestCase
{
    public function test_can_apply_returns_false_if_already_vendor(): void
    {
        $user_id = $this->factory()->user->create();
        $user = new \WP_User($user_id);
        $user->add_role('rentiva_vendor');
        $this->assertFalse(VendorApplicationManager::can_apply($user_id));
    }

    public function test_can_apply_returns_true_for_plain_user(): void
    {
        $user_id = $this->factory()->user->create();
        $this->assertTrue(VendorApplicationManager::can_apply($user_id));
    }

    public function test_can_apply_returns_false_if_pending_application_exists(): void
    {
        $user_id = $this->factory()->user->create();
        wp_insert_post(array(
            'post_type'   => \MHMRentiva\Admin\Vendor\PostType\VendorApplication::POST_TYPE,
            'post_author' => $user_id,
            'post_status' => 'pending',
            'post_title'  => 'Test Application',
        ));
        $this->assertFalse(VendorApplicationManager::can_apply($user_id));
    }

    public function test_create_application_returns_post_id(): void
    {
        $user_id = $this->factory()->user->create();
        $data = array(
            'phone'         => '+90 555 123 4567',
            'city'          => 'Istanbul',
            'iban'          => 'TR123456789012345678901234',
            'service_areas' => array('Istanbul'),
            'doc_id'        => 0,
            'doc_license'   => 0,
            'doc_address'   => 0,
            'doc_insurance' => 0,
        );
        $result = VendorApplicationManager::create_application($user_id, $data);
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function test_create_application_returns_error_if_cannot_apply(): void
    {
        $user_id = $this->factory()->user->create();
        $user = new \WP_User($user_id);
        $user->add_role('rentiva_vendor');
        $result = VendorApplicationManager::create_application($user_id, array());
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('cannot_apply', $result->get_error_code());
    }

    public function test_encrypt_decrypt_iban_roundtrip(): void
    {
        $plain = 'TR330006100519786457841326';
        $encrypted = VendorApplicationManager::encrypt_iban($plain);
        $this->assertNotSame($plain, $encrypted);
        $decrypted = VendorApplicationManager::decrypt_iban($encrypted);
        $this->assertSame($plain, $decrypted);
    }
}
