<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Account;

use MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration;
use WP_UnitTestCase;

/**
 * Task A8a seam inversion: WooCommerceIntegration::add_menu_items() no longer
 * reads \MHMRentiva\Admin\Licensing\Mode at all. Lite contributes only its own
 * three WooCommerce My Account tabs (bookings, favorites, payment_history);
 * the "Messages" tab and the vendor tab(s) ("Become a Vendor" / "Vendor
 * Panel") are re-added exclusively by Pro's
 * mhm-rentiva-pro/src/Pro/Extensions/AccountExtensions.php, subscribed to the
 * neutral `mhm_rentiva_account_nav_items` filter -- the same pattern as
 * Task A7's MenuExtensions.
 *
 * render_vendor_apply()'s access guard is covered here too: it now defers to
 * whether the 'vendor_apply' tab is registered via the same filter, rather
 * than calling Mode::canUseVendorMarketplace() directly.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration
 */
final class AccountNavNoProTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        remove_all_filters('mhm_rentiva_account_nav_items');
    }

    protected function tearDown(): void
    {
        remove_all_filters('mhm_rentiva_account_nav_items');
        parent::tearDown();
    }

    /** @return array<string, string> */
    private function base_wc_items(): array
    {
        return array(
            'dashboard'       => 'Dashboard',
            'orders'          => 'Orders',
            'downloads'       => 'Downloads',
            'edit-address'    => 'Addresses',
            'payment-methods' => 'Payment methods',
            'edit-account'    => 'Account details',
            'customer-logout' => 'Log out',
        );
    }

    public function test_no_subscriber_hides_messages_and_vendor_tabs(): void
    {
        $items = WooCommerceIntegration::add_menu_items($this->base_wc_items());

        $messages_slug = WooCommerceIntegration::get_endpoint_slug('messages');
        $vendor_slug   = WooCommerceIntegration::get_endpoint_slug('vendor_apply');

        $this->assertArrayNotHasKey($messages_slug, $items, 'No Pro subscriber, so the Messages tab must not register.');
        $this->assertArrayNotHasKey($vendor_slug, $items, 'No Pro subscriber, so the vendor tab must not register.');
        $this->assertArrayNotHasKey('vendor-panel', $items, 'No Pro subscriber, so the active-vendor "Vendor Panel" link must not register.');
        $this->assertNotContains('Messages', $items, 'No translated "Messages" label must leak into the nav without a subscriber.');
    }

    /**
     * Positive control: a change that stripped the whole nav-building method
     * down to nothing would satisfy the assertion above while destroying the
     * Lite account nav -- Lite's own tabs and WooCommerce's own items must
     * always survive.
     */
    public function test_own_tabs_and_core_wc_items_still_register_without_a_subscriber(): void
    {
        $items = WooCommerceIntegration::add_menu_items($this->base_wc_items());

        foreach (array( 'bookings', 'favorites', 'payment_history' ) as $key) {
            $slug = WooCommerceIntegration::get_endpoint_slug($key);
            $this->assertArrayHasKey($slug, $items, sprintf('Lite\'s own "%s" tab must always register.', $key));
        }

        $this->assertArrayHasKey('dashboard', $items);
        $this->assertArrayHasKey('orders', $items);
        $this->assertArrayHasKey('customer-logout', $items, 'Logout must survive the splice and stay last.');
        $this->assertSame('customer-logout', array_key_last($items));
    }

    public function test_a_subscriber_can_add_a_nav_item_back(): void
    {
        add_filter('mhm_rentiva_account_nav_items', static function (array $items): array {
            $items['messages'] = array(
                'slug'  => 'rentiva-messages',
                'label' => 'Messages',
            );
            return $items;
        });

        $items = WooCommerceIntegration::add_menu_items($this->base_wc_items());

        $this->assertArrayHasKey('rentiva-messages', $items);
        $this->assertSame('Messages', $items['rentiva-messages']);
    }

    /**
     * A malformed contribution (missing slug/label, or not an array at all)
     * from a filter subscriber must be dropped rather than corrupting
     * WooCommerce's menu items array with a non-string key/value.
     */
    public function test_malformed_subscriber_contribution_is_dropped(): void
    {
        add_filter('mhm_rentiva_account_nav_items', static function (array $items): array {
            $items['broken_one'] = array( 'slug' => 'broken-slug' ); // missing label
            $items['broken_two'] = 'not-an-array';
            return $items;
        });

        $items = WooCommerceIntegration::add_menu_items($this->base_wc_items());

        $this->assertArrayNotHasKey('broken-slug', $items);
    }

    public function test_render_vendor_apply_outputs_nothing_without_a_subscriber(): void
    {
        ob_start();
        WooCommerceIntegration::render_vendor_apply();
        $output = (string) ob_get_clean();

        $this->assertSame('', $output, 'render_vendor_apply() must render nothing when no subscriber registers the vendor tab.');
    }

    /**
     * Grep-clean proof, pinned at the source level too (mirrors
     * MenuNoProSubmenusTest::test_menu_source_names_no_pro_admin_page_class()
     * for Task A7): a future re-introduction of a Mode:: read fails this
     * suite even before the shell grep in CI does.
     */
    public function test_source_names_no_licensing_mode(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 4) . '/src/Admin/Frontend/Account/WooCommerceIntegration.php'
        );

        $this->assertStringNotContainsString('Mode::', $source);
        $this->assertStringNotContainsString('Licensing\\Mode', $source);
    }
}
