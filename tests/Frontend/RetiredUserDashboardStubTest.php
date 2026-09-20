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

    /**
     * The dashboard pipeline is gone; the stub must not reach for it.
     *
     * This used to hang a callback on `mhmrentiva_dashboard_data` and assert it
     * never fired. That worked while the pipeline still existed. Once the
     * pipeline was deleted (2026-09-20) no apply_filters() for that name was
     * left in Lite, so the callback could not have fired whatever the stub did
     * -- the test would have gone on passing over a stub that had been rewritten
     * to rebuild the whole dashboard by hand. A test that cannot fail is not a
     * test.
     *
     * The claim is the same one, pinned at the source instead: the stub's own
     * file must not name any of the nine classes this retirement deleted. That
     * fails the moment someone writes
     * `use MHMRentiva\Core\Dashboard\DashboardContext;` back into it, which is
     * the actual regression being guarded against -- a reference to a class
     * that no longer exists is a fatal on a customer's page, and
     * bin/check-guarded-refs.php does not see an unguarded one.
     *
     * Narrow on purpose, and the narrowing is the point: this reads ONE file's
     * bytes. A dashboard rebuilt under new class names, or in a new file, would
     * not trip it. test_register_wires_only_the_panel_guard() is the
     * behavioural half of the pair.
     */
    public function test_the_stub_references_no_deleted_pipeline_class(): void
    {
        $path   = dirname(__DIR__, 2) . '/src/Admin/Frontend/Shortcodes/Account/UserDashboard.php';
        $source = (string) file_get_contents($path);

        // Positive control: a path typo or a moved file would otherwise let
        // every assertion below pass against an empty string.
        $this->assertStringContainsString(
            'class UserDashboard',
            $source,
            'Positive control: the stub source was not read.'
        );

        foreach (
            array(
                'DashboardContext',
                'DashboardDataProvider',
                'DashboardNavigation',
                'DashboardConfig',
                'CustomerDashboard',
                'MetricRegistry',
                'TrendService',
                'TotalBookingsMetric',
                'UpcomingPickupsMetric',
            ) as $retired_class
        ) {
            $this->assertStringNotContainsString(
                $retired_class,
                $source,
                "The stub reaches for the retired dashboard pipeline again: {$retired_class}."
            );
        }
    }

    public function test_register_wires_only_the_panel_guard(): void
    {
        UserDashboard::register();

        $this->assertNotFalse(has_action('template_redirect', array( UserDashboard::class, 'guard_panel_access' )));
        $this->assertFalse(has_action('wp_enqueue_scripts', array( UserDashboard::class, 'enqueue_assets' )));
        $this->assertFalse(has_filter('body_class', array( UserDashboard::class, 'add_body_class' )));
    }
}
