<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use WP_UnitTestCase;

/**
 * WP.org T8 Görev 10b: the Section-A dead-endpoint hook tags identified by
 * `.superpowers/sdd/2026-08-03-t8-duzeltme-turu/task-10a-endpoint-table.md`
 * (rows A1-A12) must no longer be registered in $wp_filter after plugin init.
 *
 * Every one of the 12 tags below had, at the table's measurement (Lite HEAD
 * 7c626e3c): zero shipped nonce producer AND zero consumer (no form, no JS,
 * no Pro reference) in either repo — confirmed independently for this task
 * via fresh grep across both `mhm-rentiva` and `mhm-rentiva-pro` before any
 * deletion (see task-10b-report.md for the per-row evidence).
 *
 * RED (pre-fix): every assertion in test_dead_endpoint_hook_tag_is_not_registered
 * fails — the original tags were still registered, even though nothing in
 * either repo can ever produce a valid nonce for them or POST to them.
 * GREEN (post-fix): each dead hook registration + its dead callback was
 * deleted; live siblings that share a class or file with a dead callback
 * (e.g. DashboardPage::clear_dashboard_cache(), AddCustomerPage::render(),
 * Handler::get_cancellation_policy()/get_payment_deadline(),
 * Uninstaller::uninstall_direct()) were preserved and are asserted
 * still-present below as positive controls. Actions::refund_booking() was
 * one of these at A6 measurement time (its nonce producer still existed),
 * but Task 9 (slice 5, 2026-08-22) later deleted BookingRefundMetaBox's
 * nonce-producing form (a5c35a61) and, once that made the endpoint provably
 * unreachable, the endpoint itself, its registration, notices() and the
 * whole Actions class -- see NoUnguardedRefundEntryPointTest and this row's
 * move from the "still exists" list to the dead-hook list below.
 *
 * Görev 10c-A addition: `wp_ajax_mhmrentiva_create_default_addons`
 * (task-10a-endpoint-table.md row C1). Unlike A1-A12, this one had a real
 * nonce producer AND a real button that posted to it — it was DECISION-NEEDED,
 * not an auto-delete, because the surrounding `AddonSettings::render_page()`
 * page was simply unreachable (no `add_submenu_page()` anywhere), not because
 * the AJAX handler itself lacked wiring. The controller's K5-C1 ruling
 * (task-10c-A-brief.md) chose deletion of the whole render_page()/
 * enqueue_scripts()/ajax_create_default_addons() cluster over building the
 * missing doorway. `AddonSettings::class` (still surviving — its
 * `register_settings()`/`defaults()`/`get()`/`sanitize()` remain live via
 * `SettingsCore`'s central settings lifecycle) is added to the setUp()
 * registrar loop below so this tag's pre-deletion RED state is real, not
 * vacuous, for the same `is_admin()`-gated-bootstrap reason documented on
 * the loop itself.
 *
 * @covers \MHMRentiva\Admin\Booking\Core\Handler
 * @covers \MHMRentiva\Admin\Vehicle\Deposit\DepositAjax
 * @covers \MHMRentiva\Admin\Emails\Core\EmailTemplates
 * @covers \MHMRentiva\Admin\Utilities\Uninstall\UninstallPage
 * @covers \MHMRentiva\Admin\Utilities\Uninstall\Uninstaller
 * @covers \MHMRentiva\Admin\Utilities\Dashboard\DashboardPage
 * @covers \MHMRentiva\Admin\Customers\AddCustomerPage
 * @covers \MHMRentiva\Admin\Addons\AddonSettings
 */
final class Task10bDeadEndpointsRemovedTest extends WP_UnitTestCase
{
    /**
     * Every Section-A registrar lives inside `Plugin::initialize_admin_services()`
     * (or, for UninstallPage, an equally `is_admin()`-gated block), which only
     * runs when `is_admin()` is true AT THE MOMENT `Plugin::initialize_services()`
     * fires -- once, early in the shared PHPUnit bootstrap (`muplugins_loaded`),
     * before any test gets a chance to raise `is_admin()`. In this suite that
     * moment always has `is_admin() === false`, so relying on the plugin's own
     * bootstrap would make every assertion below about an admin-gated hook
     * vacuously pass whether or not the dead callback still exists.
     *
     * Each still-existing registrar is therefore invoked directly here, the
     * same pattern `CoreAdminPagesTest::test_menu_shows_setup_and_about_submenus_by_default()`
     * uses for `Menu::add_menu()`. `method_exists()` (not `class_exists()`)
     * guards each call because two rows (A1/A2, A12) delete `register()` itself
     * -- or empty it out -- while the surrounding class survives; a class-only
     * guard would fatal on those once the method is gone. `Handler::class` /
     * `DepositAjax::class` etc. never trigger autoloading on their own (the
     * `::class` constant is resolved at compile time), so this is safe to call
     * even after A3/A4/A7/A8/F2 delete the file entirely.
     */
    public function setUp(): void
    {
        parent::setUp();

        foreach (
            array(
                \MHMRentiva\Admin\Booking\Core\Handler::class,
                \MHMRentiva\Admin\Vehicle\Deposit\DepositAjax::class,
                \MHMRentiva\Admin\Emails\Core\EmailTemplates::class,
                \MHMRentiva\Admin\Utilities\Uninstall\UninstallPage::class,
                \MHMRentiva\Admin\Utilities\Dashboard\DashboardPage::class,
                // CustomersPage, not AddCustomerPage directly: production fires
                // AddCustomerPage::register() THROUGH CustomersPage::register()
                // (CustomersPage.php:59), and this also exercises the sibling
                // admin_post_mhmrentiva_export_customers hook used as a live
                // positive control below.
                \MHMRentiva\Admin\Customers\CustomersPage::class,
                // Görev 10c-A / row C1: same is_admin()-gated-bootstrap reason.
                \MHMRentiva\Admin\Addons\AddonSettings::class,
            ) as $registrar
        ) {
            if (method_exists($registrar, 'register')) {
                $registrar::register();
            }
        }
    }

    /**
     * The 12 Section-A hook tags, per task-10a-endpoint-table.md rows A1-A12
     * (nopriv rows included as their own row, matching the table's own count).
     *
     * Görev 10c-A added C1 for the same reason (a page that was reachable by
     * nothing). Task 9 (slice 5, 2026-08-22) adds T9:
     * `admin_post_mhmrentiva_refund_booking` was a genuine positive control
     * here at A6 measurement time -- BookingRefundMetaBox still produced its
     * nonce -- but a5c35a61 later deleted that producer (fixing a nested-form
     * regression), leaving the endpoint unreachable rather than unprotected.
     * Task 9 deleted the endpoint, its registration, notices() (whose own
     * nonce had no producer left once notice_url() -- its only caller was
     * refund_booking() -- went with it) and the whole Actions class, so this
     * row moves from "still exists" (below) to "now dead" (here). Fix round 1
     * (F5): that same deletion took row A6's own live apparatus with it --
     * `Actions::class` was A6's registrar in setUp()'s loop and
     * `admin_post_mhmrentiva_refund_booking` was its paired "still exists"
     * control, both removed above -- so A6 is now a vacuous assertion
     * (`has_action('admin_post_mhmrentiva_purge_logs')` was already false
     * before this test process registers anything, with or without a
     * registrar call); left as-is rather than rebuilt, since there is no
     * class left to call register() on.
     *
     * @return array<string, array{0: string}>
     */
    public static function deadHookTagProvider(): array
    {
        return array(
            'A1  admin_post_mhmrentiva_booking'              => array( 'admin_post_mhmrentiva_booking' ),
            'A2  admin_post_nopriv_mhmrentiva_booking'        => array( 'admin_post_nopriv_mhmrentiva_booking' ),
            'A3  wp_ajax_mhmrentiva_calculate_deposit'        => array( 'wp_ajax_mhmrentiva_calculate_deposit' ),
            'A4  wp_ajax_nopriv_mhmrentiva_calculate_deposit' => array( 'wp_ajax_nopriv_mhmrentiva_calculate_deposit' ),
            'A5  admin_post_mhmrentiva_email_send_test'       => array( 'admin_post_mhmrentiva_email_send_test' ),
            'A5b admin_post_mhmrentiva_email_preview'         => array( 'admin_post_mhmrentiva_email_preview' ),
            'A6  admin_post_mhmrentiva_purge_logs'            => array( 'admin_post_mhmrentiva_purge_logs' ),
            'A7  wp_ajax_mhmrentiva_get_uninstall_stats'      => array( 'wp_ajax_mhmrentiva_get_uninstall_stats' ),
            'A8  wp_ajax_mhmrentiva_uninstall_plugin'         => array( 'wp_ajax_mhmrentiva_uninstall_plugin' ),
            'A9  wp_ajax_mhmrentiva_clear_dashboard_cache'    => array( 'wp_ajax_mhmrentiva_clear_dashboard_cache' ),
            'A10 wp_ajax_mhmrentiva_save_dashboard_order'     => array( 'wp_ajax_mhmrentiva_save_dashboard_order' ),
            'A11 wp_ajax_mhmrentiva_reset_dashboard_layout'   => array( 'wp_ajax_mhmrentiva_reset_dashboard_layout' ),
            'A12 wp_ajax_mhmrentiva_add_customer'             => array( 'wp_ajax_mhmrentiva_add_customer' ),
            'C1  wp_ajax_mhmrentiva_create_default_addons'    => array( 'wp_ajax_mhmrentiva_create_default_addons' ),
            'T9  admin_post_mhmrentiva_refund_booking'        => array( 'admin_post_mhmrentiva_refund_booking' ),
        );
    }

    /**
     * @dataProvider deadHookTagProvider
     */
    public function test_dead_endpoint_hook_tag_is_not_registered(string $hook_tag): void
    {
        $this->assertFalse(
            has_action($hook_tag),
            "Dead endpoint '{$hook_tag}' must not be registered in \$wp_filter after plugin init."
        );
    }

    // -- Positive controls: live siblings on the same classes/files must survive --

    public function test_live_sibling_hooks_on_the_same_classes_are_still_registered(): void
    {
        $this->assertNotFalse(
            has_action('wp_ajax_mhmrentiva_preview_email_ajax'),
            'The live nonce/capability-protected AJAX email preview must survive removal of the dead admin-post stub.'
        );
        $this->assertNotFalse(
            has_action('admin_post_mhmrentiva_save_email_templates'),
            'EmailTemplates::handle_save_templates() must survive the A5 handle_send() deletion.'
        );
        $this->assertNotFalse(
            has_action('save_post_mhmrentiva_booking'),
            'DashboardPage::clear_cache_on_booking_change() wiring must survive the A9/A10/A11 AJAX-wrapper deletions.'
        );
        $this->assertNotFalse(
            has_action('admin_post_mhmrentiva_export_customers'),
            'CustomersPage::register() must still wire CustomerExporter::handle() after AddCustomerPage::register() was emptied out by A12.'
        );
    }

    public function test_preserved_public_methods_still_exist(): void
    {
        $this->assertTrue(
            method_exists(\MHMRentiva\Admin\Booking\Core\Handler::class, 'get_cancellation_policy'),
            'Handler::get_cancellation_policy() is called live from WooCommerceBridge::get_cancellation_policy() -- must survive A1/A2.'
        );
        $this->assertTrue(
            method_exists(\MHMRentiva\Admin\Booking\Core\Handler::class, 'get_payment_deadline'),
            'Handler::get_payment_deadline() is called live from WooCommerceBridge::get_payment_deadline() -- must survive A1/A2.'
        );
        $this->assertTrue(
            method_exists(\MHMRentiva\Admin\Utilities\Uninstall\Uninstaller::class, 'uninstall_direct'),
            'Uninstaller::uninstall_direct() is the real uninstall.php:90 path -- must survive A7/A8.'
        );
        $this->assertTrue(
            method_exists(\MHMRentiva\Admin\Customers\AddCustomerPage::class, 'render'),
            'AddCustomerPage::render() is the live inline-POST admin page -- must survive A12.'
        );
        $this->assertTrue(
            method_exists(\MHMRentiva\Admin\Utilities\Dashboard\DashboardPage::class, 'clear_dashboard_cache'),
            'DashboardPage::clear_dashboard_cache() has 6 other live hook callers -- must survive A9.'
        );
        $this->assertTrue(
            method_exists(\MHMRentiva\Admin\Emails\Core\EmailTemplates::class, 'build_context'),
            'EmailTemplates::build_context() is live via EmailAjaxHandler -- must survive A5/D7.'
        );
        $this->assertTrue(
            method_exists(\MHMRentiva\Admin\Emails\Core\EmailTemplates::class, 'render_content_only'),
            'EmailTemplates::render_content_only() is live via TabRendererRegistry -- must survive D7.'
        );
        $this->assertTrue(
            method_exists(\MHMRentiva\Admin\Addons\AddonSettings::class, 'register_settings'),
            'AddonSettings::register_settings() (admin_init) must survive C1 -- it is the whole class\'s only remaining register() line.'
        );
        $this->assertTrue(
            method_exists(\MHMRentiva\Admin\Addons\AddonSettings::class, 'defaults'),
            'AddonSettings::defaults() is live via SettingsCore.php + SettingsSanitizer.php -- must survive C1.'
        );
    }

    public function test_dead_classes_no_longer_exist(): void
    {
        $this->assertFalse(
            class_exists(\MHMRentiva\Admin\Vehicle\Deposit\DepositAjax::class),
            'DepositAjax was fully dead (A3/A4, zero consumer/producer) -- the whole class must be gone.'
        );
        $this->assertFalse(
            class_exists(\MHMRentiva\Admin\Utilities\Uninstall\UninstallPage::class),
            'UninstallPage was fully dead (A7/A8, no rendering surface at all) -- the whole class must be gone.'
        );
        $this->assertFalse(
            class_exists('MHMRentiva\Admin\Settings\Groups\CustomerManagementSettings'),
            'CustomerManagementSettings was a hollow stub (F2: 0 fields, 0 keys) -- the whole class must be gone.'
        );
    }

    public function test_uninstaller_thin_wrapper_and_stats_method_are_gone(): void
    {
        $this->assertFalse(
            method_exists(\MHMRentiva\Admin\Utilities\Uninstall\Uninstaller::class, 'uninstall'),
            'Uninstaller::uninstall() was only ever called from the now-deleted UninstallPage AJAX handler (A8) -- must be gone.'
        );
        $this->assertFalse(
            method_exists(\MHMRentiva\Admin\Utilities\Uninstall\Uninstaller::class, 'get_uninstall_stats'),
            'Uninstaller::get_uninstall_stats() was only ever called from the now-deleted UninstallPage AJAX handler (A7) -- must be gone.'
        );
    }

    public function test_addonsettings_dead_render_and_ajax_surface_is_gone(): void
    {
        $this->assertFalse(
            method_exists(\MHMRentiva\Admin\Addons\AddonSettings::class, 'render_page'),
            'AddonSettings::render_page() had zero callers in either repo (task-10a-endpoint-table.md Section C) -- must be gone (K5-C1).'
        );
        $this->assertFalse(
            method_exists(\MHMRentiva\Admin\Addons\AddonSettings::class, 'enqueue_scripts'),
            'AddonSettings::enqueue_scripts() gated on a screen id no registration ever produces -- must be gone (K5-C1).'
        );
        $this->assertFalse(
            method_exists(\MHMRentiva\Admin\Addons\AddonSettings::class, 'ajax_create_default_addons'),
            'AddonSettings::ajax_create_default_addons() is row C1 -- must be gone (K5-C1).'
        );
        // Fix round 1 (review task-10cA-review.md, Important finding): get() and
        // sanitize() were framed as "kept, live survivors" in the original report,
        // but neither has a caller anywhere in either repo -- sanitize() predates
        // this task (register_settings() always wired
        // SettingsSanitizer::sanitize_addon_settings_option directly, never
        // self::sanitize()); get()'s only callers were the 4 self::get(...) calls
        // inside the now-deleted render_page(), so this task's own K5-C1 deletion
        // orphaned it. Both deleted; asserted gone here rather than left as
        // (incorrectly) "preserved" above.
        $this->assertFalse(
            method_exists(\MHMRentiva\Admin\Addons\AddonSettings::class, 'get'),
            'AddonSettings::get() had zero callers anywhere once render_page() (K5-C1) removed its only 4 call sites -- must be gone.'
        );
        $this->assertFalse(
            method_exists(\MHMRentiva\Admin\Addons\AddonSettings::class, 'sanitize'),
            'AddonSettings::sanitize() had zero callers even before this task -- register_settings() always wired SettingsSanitizer::sanitize_addon_settings_option directly -- must be gone.'
        );
    }
}
