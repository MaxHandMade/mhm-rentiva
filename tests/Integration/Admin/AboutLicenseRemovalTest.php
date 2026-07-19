<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\About\REST\AboutController;
use MHMRentiva\Admin\About\SystemInfo;
use MHMRentiva\Admin\About\Tabs\GeneralTab;
use MHMRentiva\Admin\About\Tabs\SupportTab;
use MHMRentiva\Admin\About\Tabs\SystemTab;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Task A9b — Lite is a standalone free plugin: the About/System/Setup admin
 * surfaces must not render any "Lite Version" / "Pro Active" edition label,
 * license panel, or license status anywhere. This locks that removal down so
 * a future edit can't silently reintroduce a Mode::isPro()/LicenseManager
 * read into these Lite-only screens.
 */
final class AboutLicenseRemovalTest extends WP_UnitTestCase
{
    private static WP_REST_Server $server;
    private int $admin_id = 0;

    public function setUp(): void
    {
        parent::setUp();
        AboutController::register();
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        self::$server   = $wp_rest_server;
        do_action( 'rest_api_init', self::$server );

        $this->admin_id = (int) $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $this->admin_id );
    }

    public function tearDown(): void
    {
        global $wp_rest_server;
        $wp_rest_server = null;
        wp_set_current_user( 0 );
        remove_action( 'rest_api_init', array( AboutController::class, 'register_route' ) );
        parent::tearDown();
    }

    private function forbidden_needles(): array
    {
        return array(
            'License',
            'license',
            'Lite Version',
            'Pro Active',
            'Priority Support',
        );
    }

    // ── PHP-rendered tabs (AbstractTab::render()) ──────────────────────────

    public function test_general_tab_renders_without_fatal_and_without_license_content(): void
    {
        ob_start();
        GeneralTab::render();
        $html = (string) ob_get_clean();

        $this->assertNotSame( '', $html, 'GeneralTab::render() produced no output.' );
        // Non-license content must still be present.
        $this->assertStringContainsString( 'MHM Rentiva', $html );
        $this->assertStringContainsString( 'Developer', $html );
        foreach ( $this->forbidden_needles() as $needle ) {
            $this->assertStringNotContainsString( $needle, $html, "GeneralTab output must not contain '{$needle}'." );
        }
    }

    public function test_system_tab_renders_without_fatal_and_without_license_content(): void
    {
        ob_start();
        SystemTab::render();
        $html = (string) ob_get_clean();

        $this->assertNotSame( '', $html, 'SystemTab::render() produced no output.' );
        $this->assertStringContainsString( 'PHP Information', $html );
        $this->assertStringContainsString( 'Database Information', $html );
        foreach ( $this->forbidden_needles() as $needle ) {
            $this->assertStringNotContainsString( $needle, $html, "SystemTab output must not contain '{$needle}'." );
        }
    }

    public function test_support_tab_renders_without_fatal_and_without_priority_support(): void
    {
        ob_start();
        SupportTab::render();
        $html = (string) ob_get_clean();

        $this->assertNotSame( '', $html, 'SupportTab::render() produced no output.' );
        $this->assertStringContainsString( 'Contact Form', $html );
        $this->assertStringNotContainsString( 'Priority Support', $html, 'Lite must not offer the Pro-only priority support link.' );
    }

    // ── SystemInfo (used by the SystemTab REST payload and dashboard) ─────

    public function test_system_info_plugin_array_has_no_license_status_key(): void
    {
        $info = SystemInfo::get_cached_system_info();

        $this->assertArrayHasKey( 'plugin', $info );
        $this->assertArrayNotHasKey( 'license_status', $info['plugin'] );
        // Non-license plugin data must still be present.
        $this->assertArrayHasKey( 'version', $info['plugin'] );
        $this->assertArrayHasKey( 'install_date', $info['plugin'] );
    }

    // ── About REST endpoint ─────────────────────────────────────────────────

    public function test_rest_response_has_no_is_pro_or_license_fields(): void
    {
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/about' );
        $response = self::$server->dispatch( $request );
        $data     = (array) $response->get_data();

        $this->assertArrayNotHasKey( 'is_pro', (array) ( $data['support'] ?? array() ), 'REST support payload must not expose is_pro.' );

        $general_plugin_info = (array) ( $data['general']['plugin_info'] ?? array() );
        foreach ( $general_plugin_info as $row ) {
            $this->assertNotSame( 'License', $row['label'] ?? '', 'general.plugin_info must not carry a License row.' );
        }

        $general_stats = (array) ( $data['general']['stats'] ?? array() );
        foreach ( $general_stats as $row ) {
            $this->assertNotSame( 'Active License', $row['label'] ?? '', 'general.stats must not carry an Active License row.' );
        }

        $system_plugin = (array) ( $data['system']['plugin'] ?? array() );
        foreach ( $system_plugin as $row ) {
            $this->assertNotContains(
                $row['label'] ?? '',
                array( 'License Status', 'License Expiry' ),
                'system.plugin must not carry License Status/Expiry rows.'
            );
        }
    }
}
