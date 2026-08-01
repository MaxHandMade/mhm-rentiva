<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Dashboard;

use MHMRentiva\Core\Dashboard\CustomerDashboard;
use WP_UnitTestCase;

/**
 * Task A8a seam inversion, bug B2 (the fatal).
 *
 * templates/account/user-dashboard.php used to call
 * \MHMRentiva\Admin\Licensing\Mode::canUseVendorMarketplace() directly inside
 * its vendor "Contact Administrator" panel branch. That template is
 * `include`d by Lite's own CustomerDashboard::render() -- a plain Lite code
 * path, never behind a class_exists() guard -- so once the licensing Mode
 * router class is deleted (a later task), that call would have fatalled
 * every Lite site the moment a 'vendor' context reached the dashboard.
 *
 * The fix replaces the direct call with
 * `apply_filters('mhmrentiva_account_vendor_panel', '', $context)`. Pro's
 * AccountExtensions is the only thing left that can return non-empty markup
 * for it; Lite's own (and this filter's default) contribution is always ''.
 *
 * @covers \MHMRentiva\Core\Dashboard\CustomerDashboard
 */
final class AccountVendorPanelSeamTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        remove_all_filters('mhmrentiva_account_vendor_panel');
    }

    protected function tearDown(): void
    {
        remove_all_filters('mhmrentiva_account_vendor_panel');
        parent::tearDown();
    }

    public function test_filter_default_is_empty_string_without_a_subscriber(): void
    {
        $this->assertSame('', apply_filters('mhmrentiva_account_vendor_panel', '', 'vendor'));
    }

    /**
     * The actual B2 regression: rendering the customer dashboard template
     * with a 'vendor' context must not fatal and must not print the vendor
     * panel when no Pro subscriber is present -- exercising the real
     * CustomerDashboard::render() -> template `include` path, not just the
     * filter in isolation.
     */
    public function test_vendor_context_dashboard_renders_without_fatal_and_without_panel(): void
    {
        $html = CustomerDashboard::render(array( 'context' => 'vendor' ));

        $this->assertNotSame('', $html, 'Premise: the dashboard must render something, or the assertions below are vacuous.');
        $this->assertStringNotContainsString('Contact Administrator', $html, 'The vendor panel must not render without a Pro subscriber.');
        $this->assertStringNotContainsString('mhm-rentiva-dashboard__contact-admin', $html);
    }

    /**
     * Positive control: a subscriber (standing in for Pro's AccountExtensions)
     * must still be able to add the panel back for a 'vendor' context.
     */
    public function test_a_subscriber_can_add_the_vendor_panel_back(): void
    {
        add_filter('mhmrentiva_account_vendor_panel', static function (string $html, string $context): string {
            return 'vendor' === $context ? '<div class="stand-in-vendor-panel">Contact Administrator</div>' : $html;
        }, 10, 2);

        $html = CustomerDashboard::render(array( 'context' => 'vendor' ));

        $this->assertStringContainsString('stand-in-vendor-panel', $html);
    }

    /**
     * The filter must be context-aware: a 'customer' context must never
     * receive vendor-only markup, mirroring the template's own
     * `$context === 'vendor'` guard that used to gate the Mode:: call.
     */
    public function test_customer_context_never_receives_the_vendor_panel(): void
    {
        add_filter('mhmrentiva_account_vendor_panel', static function (string $html, string $context): string {
            return 'vendor' === $context ? '<div class="stand-in-vendor-panel">Contact Administrator</div>' : $html;
        }, 10, 2);

        $html = CustomerDashboard::render(array( 'context' => 'customer' ));

        $this->assertStringNotContainsString('stand-in-vendor-panel', $html);
    }

    /**
     * Grep-clean proof, pinned at the source level too: a future
     * re-introduction of a Mode:: read in the template fails this suite even
     * before the shell grep in CI does.
     */
    public function test_template_source_names_no_licensing_mode(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 3) . '/templates/account/user-dashboard.php'
        );

        $this->assertStringNotContainsString('Mode::', $source);
        $this->assertStringNotContainsString('Licensing\\Mode', $source);
    }

    /**
     * Same proof for UserDashboard.php's now-removed AnalyticsController
     * registration block (the other half of B2's task description).
     */
    public function test_user_dashboard_shortcode_source_names_no_licensing_mode(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 3) . '/src/Admin/Frontend/Shortcodes/Account/UserDashboard.php'
        );

        $this->assertStringNotContainsString('Mode::', $source);
        $this->assertStringNotContainsString('Licensing\\Mode', $source);
    }
}
