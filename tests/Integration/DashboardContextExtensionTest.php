<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

use MHMRentiva\Core\Dashboard\DashboardContext;

/**
 * Covers the neutral dashboard context extension contract.
 */
final class DashboardContextExtensionTest extends \WP_UnitTestCase
{
    protected function tearDown(): void
    {
        wp_set_current_user(0);
        remove_all_filters('mhmrentiva_dashboard_context');
        parent::tearDown();
    }

    public function test_logged_in_user_resolves_to_customer_by_default(): void
    {
        $user_id = $this->factory()->user->create();
        wp_set_current_user($user_id);

        $this->assertSame('customer', DashboardContext::resolve());
    }

    public function test_subscriber_can_supply_an_external_context(): void
    {
        $user_id = $this->factory()->user->create();
        wp_set_current_user($user_id);

        add_filter('mhmrentiva_dashboard_context', static function (string $context, int $filtered_user_id) use ($user_id): string {
            return $user_id === $filtered_user_id ? 'external' : $context;
        }, 10, 2);

        $this->assertSame('external', DashboardContext::resolve());
    }

    public function test_empty_subscriber_value_falls_back_to_customer(): void
    {
        $user_id = $this->factory()->user->create();
        wp_set_current_user($user_id);
        add_filter('mhmrentiva_dashboard_context', '__return_empty_string');

        $this->assertSame('customer', DashboardContext::resolve());
    }
}
