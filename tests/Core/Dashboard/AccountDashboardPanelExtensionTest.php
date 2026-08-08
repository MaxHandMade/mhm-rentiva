<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Dashboard;

use MHMRentiva\Core\Dashboard\CustomerDashboard;
use WP_UnitTestCase;

/**
 * Covers the neutral dashboard panel extension contract.
 */
final class AccountDashboardPanelExtensionTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        remove_all_filters('mhmrentiva_dashboard_panels');
    }

    protected function tearDown(): void
    {
        remove_all_filters('mhmrentiva_dashboard_panels');
        parent::tearDown();
    }

    public function test_customer_dashboard_has_no_extension_panel_by_default(): void
    {
        $html = CustomerDashboard::render(array(
            'context'    => 'customer',
            'active_tab' => 'overview',
            'panels'     => apply_filters('mhmrentiva_dashboard_panels', array(), 'customer', 0),
        ));

        $this->assertNotSame('', $html);
        $this->assertStringNotContainsString('stand-in-extension-panel', $html);
    }

    public function test_subscriber_can_add_context_aware_panel(): void
    {
        add_filter('mhmrentiva_dashboard_panels', static function (array $panels, string $context): array {
            if ('external' === $context) {
                $panels['overview'] = '<div class="stand-in-extension-panel">Extension content</div>';
            }
            return $panels;
        }, 10, 2);

        $panels = apply_filters('mhmrentiva_dashboard_panels', array(), 'external', 123);
        $html = CustomerDashboard::render(array(
            'context'    => 'external',
            'active_tab' => 'overview',
            'panels'     => $panels,
        ));

        $this->assertStringContainsString('stand-in-extension-panel', $html);
    }

    public function test_subscriber_panel_is_not_injected_into_another_context(): void
    {
        add_filter('mhmrentiva_dashboard_panels', static function (array $panels, string $context): array {
            if ('external' === $context) {
                $panels['overview'] = '<div class="stand-in-extension-panel">Extension content</div>';
            }
            return $panels;
        }, 10, 2);

        $panels = apply_filters('mhmrentiva_dashboard_panels', array(), 'customer', 123);
        $html = CustomerDashboard::render(array(
            'context'    => 'customer',
            'active_tab' => 'overview',
            'panels'     => $panels,
        ));

        $this->assertStringNotContainsString('stand-in-extension-panel', $html);
    }
}
