<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Frontend\Shortcodes\Account\UserDashboard;

/**
 * The shortcode, the block and the Elementor widget all render this stub.
 * It stays registered because sites in the wild have it embedded; what it
 * renders is a pointer to My Account, not a dashboard.
 */
final class RetiredUserDashboardStubTest extends \WP_UnitTestCase {

    public function test_a_guest_sees_nothing(): void
    {
        wp_set_current_user(0);

        $this->assertSame('', UserDashboard::render());
    }

    public function test_a_customer_sees_one_line_and_a_link_to_my_account(): void
    {
        wp_set_current_user($this->factory->user->create(array( 'role' => 'customer' )));

        $html = UserDashboard::render();

        $this->assertStringContainsString('mhm-rentiva-retired-dashboard', $html);
        $this->assertStringContainsString(esc_url(wc_get_page_permalink('myaccount')), $html);
    }

    public function test_a_customer_does_not_see_the_administrator_notice(): void
    {
        wp_set_current_user($this->factory->user->create(array( 'role' => 'customer' )));

        $this->assertStringNotContainsString('deprecated', strtolower(UserDashboard::render()));
    }

    public function test_an_administrator_also_sees_the_deprecation_notice(): void
    {
        wp_set_current_user($this->factory->user->create(array( 'role' => 'administrator' )));

        $this->assertStringContainsString('mhm-rentiva-retired-dashboard__admin', UserDashboard::render());
    }

    public function test_the_output_is_filterable(): void
    {
        wp_set_current_user($this->factory->user->create(array( 'role' => 'customer' )));
        add_filter(
            'mhmrentiva_retired_dashboard_notice',
            static fn (string $html, string $role): string => $html . "<!-- $role -->",
            10,
            2
        );

        $this->assertStringContainsString('<!-- customer -->', UserDashboard::render());
    }

    /** The dashboard pipeline is gone; the stub must not reach for it. */
    public function test_the_stub_builds_no_dashboard_data(): void
    {
        wp_set_current_user($this->factory->user->create(array( 'role' => 'customer' )));
        $called = false;
        add_filter(
            'mhmrentiva_dashboard_data',
            static function ($data) use (&$called) {
                $called = true;
                return $data;
            }
        );

        UserDashboard::render();

        $this->assertFalse($called, 'The stub still runs the retired dashboard pipeline.');
    }

    public function test_register_wires_only_the_panel_guard(): void
    {
        UserDashboard::register();

        $this->assertNotFalse(has_action('template_redirect', array( UserDashboard::class, 'guard_panel_access' )));
        $this->assertFalse(has_action('wp_enqueue_scripts', array( UserDashboard::class, 'enqueue_assets' )));
        $this->assertFalse(has_filter('body_class', array( UserDashboard::class, 'add_body_class' )));
    }
}
