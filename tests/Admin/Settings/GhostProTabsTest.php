<?php

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Emails\Core\EmailTemplates;
use MHMRentiva\Admin\Settings\View\TabRendererRegistry;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Lite must not offer admin tabs for features it does not ship.
 *
 * Neither tab fatals -- they are worse than that in review terms: they are named,
 * clickable surfaces advertising features this build cannot deliver.
 *
 * @package MHMRentiva\Tests\Admin\Settings
 */
class GhostProTabsTest extends WP_UnitTestCase
{

    /**
     * Guards the premise for both tabs below.
     */
    public function test_backing_pro_classes_are_absent_from_lite(): void
    {
        $this->assertFalse(
            class_exists('MHMRentiva\Admin\Settings\Groups\VendorMarketplaceSettings'),
            'Premise: the Vendor Marketplace settings group must be carved out.'
        );
        $this->assertFalse(
            class_exists('MHMRentiva\Admin\Messages\Notifications\MessageNotifications'),
            'Premise: the Messages module must be carved out.'
        );
    }

    /**
     * The Vendor Marketplace tab's whole content came from the carved-out settings
     * group, so in Lite it rendered as an empty shell.
     */
    public function test_settings_has_no_vendor_marketplace_tab_in_lite(): void
    {
        $slugs = $this->get_registered_tab_slugs();

        $this->assertNotContains('vendor-marketplace', $slugs);
    }

    /**
     * Surgical: the tabs Lite does own must survive. This also proves the registry
     * populated at all, so the assertion above is not vacuous.
     */
    public function test_core_settings_tabs_survive_in_lite(): void
    {
        $slugs = $this->get_registered_tab_slugs();

        $this->assertContains('vehicle', $slugs);
        $this->assertContains('booking', $slugs);
        $this->assertContains('frontend', $slugs);
    }

    /**
     * MessageEmails (the form renderer) ships in Lite, but the Messages module that
     * SENDS the mail does not -- so the tab configured notifications that could
     * never leave the site.
     */
    public function test_email_templates_has_no_message_notifications_tab_in_lite(): void
    {
        $tabs = $this->get_email_type_tabs();

        $this->assertArrayNotHasKey('message_emails', $tabs);
        $this->assertArrayNotHasKey('vendor_emails', $tabs, 'Vendor email tab must stay gated too.');
    }

    public function test_core_email_template_tabs_survive_in_lite(): void
    {
        $tabs = $this->get_email_type_tabs();

        $this->assertSame(
            array( 'booking_notifications', 'refund_emails', 'preview' ),
            array_keys($tabs)
        );
    }

    /**
     * @return array<int, string>
     */
    private function get_registered_tab_slugs(): array
    {
        $registry = new TabRendererRegistry();

        $slugs = array();
        foreach ($registry->get_all() as $renderer) {
            $slugs[] = $renderer->get_slug();
        }

        return $slugs;
    }

    /**
     * @return array<string, string>
     */
    private function get_email_type_tabs(): array
    {
        $method = new ReflectionMethod(EmailTemplates::class, 'get_email_type_tabs');
        $method->setAccessible(true);

        return (array) $method->invoke(null);
    }
}
