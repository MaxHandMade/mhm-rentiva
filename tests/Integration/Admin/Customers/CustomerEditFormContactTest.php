<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Customers;

use MHMRentiva\Admin\Customers\CustomerIdentity;
use MHMRentiva\Admin\Customers\CustomersPage;
use MHMRentiva\Tests\Support\UserManagementCapabilities;
use WP_UnitTestCase;

/**
 * The customer edit form shows the phone the customer has, and saving it
 * does not turn an inherited value into a copy.
 *
 * Found on the dev site: a customer with a WooCommerce billing phone opened an
 * edit form whose required Phone field was empty, so changing the name meant
 * inventing a phone. Pre-filling from CustomerContact fixes that; writing the
 * untouched pre-fill back into `mhmrentiva_phone` would then freeze a copy of
 * the billing phone that no longer follows billing.
 *
 * @covers \MHMRentiva\Admin\Customers\CustomersPage
 */
final class CustomerEditFormContactTest extends WP_UnitTestCase
{
    use UserManagementCapabilities;

    private int $customer = 0;

    public function setUp(): void
    {
        parent::setUp();
        CustomerIdentity::flush_memo();
        $actor = (int) self::factory()->user->create(array('role' => 'administrator'));
        $this->grant_user_management_privilege($actor);
        wp_set_current_user($actor);

        $this->customer = (int) self::factory()->user->create(array('role' => 'customer'));
        update_user_meta($this->customer, 'billing_phone', '+90555 555 55 55');
        update_user_meta($this->customer, 'billing_address_1', 'Test Sokak 1');
        update_user_meta($this->customer, 'billing_city', 'Ulus');
    }

    public function tearDown(): void
    {
        unset($_POST['mhmrentiva_edit_customer_nonce'], $_POST['customer_name'], $_POST['customer_email'], $_POST['customer_phone'], $_POST['customer_address'], $_GET['customer_id']);
        wp_set_current_user(0);
        parent::tearDown();
    }

    private function renderEdit(): string
    {
        $_GET['customer_id'] = (string) $this->customer;
        $method = new \ReflectionMethod(CustomersPage::class, 'render_customer_edit');
        $method->setAccessible(true);

        ob_start();
        $method->invoke(new CustomersPage());

        return (string) ob_get_clean();
    }

    private function submit(string $phone, string $address): void
    {
        $_POST['mhmrentiva_edit_customer_nonce'] = wp_create_nonce('mhmrentiva_edit_customer');
        $_POST['customer_name']                  = 'Renamed Customer';
        $_POST['customer_email']                 = get_userdata($this->customer)->user_email;
        $_POST['customer_phone']                 = $phone;
        $_POST['customer_address']               = $address;
        $this->renderEdit();
    }

    public function test_the_form_is_prefilled_with_the_phone_and_address_the_customer_has(): void
    {
        $html = $this->renderEdit();

        $this->assertStringContainsString('value="+90555 555 55 55"', $html);
        $this->assertStringContainsString('>Test Sokak 1, Ulus</textarea>', $html);
    }

    public function test_saving_an_untouched_prefill_copies_nothing(): void
    {
        $this->submit('+90555 555 55 55', 'Test Sokak 1, Ulus');

        $this->assertSame('Renamed Customer', get_userdata($this->customer)->display_name, 'Precondition: the save ran.');
        $this->assertSame('', (string) get_user_meta($this->customer, 'mhmrentiva_phone', true));
        $this->assertSame('', (string) get_user_meta($this->customer, 'mhmrentiva_address', true));
    }

    public function test_a_changed_phone_is_saved_as_the_customers_own(): void
    {
        $this->submit('+90 111 111 11 11', 'Test Sokak 1, Ulus');

        $this->assertSame('+90 111 111 11 11', (string) get_user_meta($this->customer, 'mhmrentiva_phone', true));
        $this->assertSame('', (string) get_user_meta($this->customer, 'mhmrentiva_address', true), 'The untouched address stays inherited.');
    }

    public function test_an_existing_own_phone_is_always_saved(): void
    {
        update_user_meta($this->customer, 'mhmrentiva_phone', '+90 777');

        $this->submit('+90 777', 'Test Sokak 1, Ulus');

        $this->assertSame('+90 777', (string) get_user_meta($this->customer, 'mhmrentiva_phone', true));
    }

    public function test_whitespace_collapsed_by_sanitising_is_not_a_change(): void
    {
        update_user_meta($this->customer, 'billing_phone', '+90  555  555');

        $this->submit('+90  555  555', 'Test Sokak 1, Ulus');

        $this->assertSame('', (string) get_user_meta($this->customer, 'mhmrentiva_phone', true));
    }
}
