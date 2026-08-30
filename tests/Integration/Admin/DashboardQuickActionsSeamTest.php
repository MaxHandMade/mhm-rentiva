<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Utilities\Dashboard\DashboardPage;
use WP_UnitTestCase;

/**
 * The dashboard's quick-action extension point, and the scrubbing behind it.
 *
 * WHY THE SEAM EXISTS AT ALL
 *
 * Five shortcuts left this widget on 2026-08-08 with the WordPress.org carve --
 * Transfer, Reports, Vendors, Messages, Export -- correctly, because they lead
 * to paid destinations. What went with them was the `caps` prop, and with it any
 * way for Pro to put them back. A Pro customer kept the pages in their menu and
 * lost the shortcuts, which is a downgrade they paid to avoid.
 *
 * So Lite opens a filter whose default is EMPTY. Lite must not know that a paid
 * destination exists -- naming one here even to hide it would put tier awareness
 * in the free plugin, which is the thing the carve-out forbids.
 *
 * WHY THE SCRUBBING IS TESTED AND NOT JUST WRITTEN
 *
 * A filter accepts input from any plugin installed on the site, and the result
 * is localized straight into the admin page. That makes this a trust boundary,
 * not a convenience. Each assertion below names the mutation that breaks it:
 *
 *   - drop the protocol allow-list  -> mailto:/tel: become dashboard buttons
 *   - drop the dashicon pattern     -> arbitrary text lands in a class attribute
 *   - drop the empty-field checks   -> the grid renders empty boxes
 *   - drop the is_array() guards    -> a malformed contribution fatals the page
 */
final class DashboardQuickActionsSeamTest extends WP_UnitTestCase
{
    private const FILTER = 'mhmrentiva_dashboard_quick_actions';

    protected function tearDown(): void
    {
        remove_all_filters(self::FILTER);
        parent::tearDown();
    }

    /**
     * The default has to be empty, not "empty in practice".
     *
     * If Lite ever shipped a non-empty default here it would be naming
     * destinations it is not allowed to know about.
     */
    public function test_without_a_contributor_the_seam_is_empty(): void
    {
        $this->assertSame(array(), DashboardPage::get_extra_quick_actions());
    }

    public function test_a_well_formed_contribution_survives_intact(): void
    {
        add_filter(self::FILTER, static fn (): array => array(
            array(
                'label' => 'Reports',
                'href'  => 'https://example.test/wp-admin/admin.php?page=reports',
                'icon'  => 'dashicons-chart-bar',
            ),
        ));

        $this->assertSame(
            array(
                array(
                    'label' => 'Reports',
                    'href'  => 'https://example.test/wp-admin/admin.php?page=reports',
                    'icon'  => 'dashicons-chart-bar',
                ),
            ),
            DashboardPage::get_extra_quick_actions()
        );
    }

    /**
     * Core refuses this one on its own -- `javascript` is not in
     * wp_allowed_protocols() -- so this assertion measures WordPress, not the
     * allow-list passed to esc_url_raw(). Kept anyway, because the guarantee is
     * worth stating and would silently disappear if someone replaced
     * esc_url_raw() with something laxer. The allow-list itself is measured by
     * the mailto test below; removing it leaves this one green, which is how
     * that gap was found.
     */
    public function test_a_javascript_url_is_refused(): void
    {
        add_filter(self::FILTER, static fn (): array => array(
            array(
                'label' => 'Innocent',
                'href'  => 'javascript:alert(document.cookie)',
                'icon'  => 'dashicons-admin-generic',
            ),
        ));

        $this->assertSame(
            array(),
            DashboardPage::get_extra_quick_actions(),
            'A non-http(s) href must take the whole entry with it, not arrive with an empty link.'
        );
    }

    /**
     * This is what the explicit http/https allow-list buys.
     *
     * Core's default protocol list is generous -- mailto, tel, sms, feed and a
     * dozen more are all legitimate URLs and all pass esc_url_raw() unrestricted.
     * None of them is a dashboard shortcut: this widget links to admin screens,
     * and a button that opens a mail client is a contributor doing something
     * the seam is not for.
     *
     * Mutation: drop `array( 'http', 'https' )` from the esc_url_raw() call and
     * this test fails while every other test in the class stays green.
     */
    public function test_a_url_core_allows_but_a_shortcut_should_not_use_is_refused(): void
    {
        add_filter(self::FILTER, static fn (): array => array(
            array(
                'label' => 'Mail us',
                'href'  => 'mailto:someone@example.test',
                'icon'  => 'dashicons-email',
            ),
        ));

        $this->assertSame(
            array(),
            DashboardPage::get_extra_quick_actions(),
            'A quick action links to a screen. mailto: passes core but is not a shortcut.'
        );
    }

    /**
     * The icon is interpolated into a class attribute, so it cannot be free text.
     */
    public function test_an_icon_that_is_not_a_dashicon_is_replaced(): void
    {
        add_filter(self::FILTER, static fn (): array => array(
            array(
                'label' => 'Vendors',
                'href'  => 'https://example.test/x',
                'icon'  => 'notice notice-error" onmouseover="x',
            ),
        ));

        $actions = DashboardPage::get_extra_quick_actions();

        $this->assertCount(1, $actions);
        $this->assertSame('dashicons-admin-generic', $actions[0]['icon']);
    }

    /**
     * @dataProvider unusable_entry_provider
     *
     * @param array<string, mixed> $entry
     */
    public function test_an_unusable_entry_is_dropped(array $entry, string $why): void
    {
        add_filter(self::FILTER, static fn (): array => array($entry));

        $this->assertSame(array(), DashboardPage::get_extra_quick_actions(), $why);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function unusable_entry_provider(): array
    {
        return array(
            'no label'    => array(
                array('href' => 'https://example.test/x', 'icon' => 'dashicons-groups'),
                'An entry with no label would render as an empty box.',
            ),
            'empty label' => array(
                array('label' => '   ', 'href' => 'https://example.test/x'),
                'Whitespace is not a label.',
            ),
            'no href'     => array(
                array('label' => 'Nowhere', 'icon' => 'dashicons-groups'),
                'A shortcut that leads nowhere is not a shortcut.',
            ),
        );
    }

    /**
     * A filter is a public surface; a badly behaved contributor must not be able
     * to take the dashboard down with it.
     *
     * @dataProvider malformed_return_provider
     *
     * @param mixed $returned
     */
    public function test_a_malformed_contribution_cannot_break_the_page($returned): void
    {
        add_filter(self::FILTER, static fn () => $returned);

        $this->assertSame(array(), DashboardPage::get_extra_quick_actions());
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function malformed_return_provider(): array
    {
        return array(
            'a string'          => array('not an array'),
            'null'              => array(null),
            'a list of scalars' => array(array('one', 2, true)),
        );
    }

    /**
     * Order is part of the contract: Lite's own shortcuts come first, and
     * contributions appear in the order they were contributed. A widget whose
     * buttons move between page loads is worse than one with fewer buttons.
     */
    public function test_contributions_keep_the_order_they_were_added_in(): void
    {
        add_filter(self::FILTER, static fn (): array => array(
            array('label' => 'First',  'href' => 'https://example.test/1', 'icon' => 'dashicons-groups'),
            array('label' => 'Second', 'href' => 'https://example.test/2', 'icon' => 'dashicons-groups'),
        ));

        $this->assertSame(
            array('First', 'Second'),
            array_column(DashboardPage::get_extra_quick_actions(), 'label')
        );
    }
}
