<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\About\REST\AboutController;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

final class AboutControllerTest extends WP_UnitTestCase
{
    private static WP_REST_Server $server;
    private int $admin_id  = 0;
    private int $editor_id = 0;

    public function setUp(): void
    {
        parent::setUp();
        AboutController::register();
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        self::$server   = $wp_rest_server;
        do_action( 'rest_api_init', self::$server );

        $this->admin_id  = (int) $this->factory->user->create( array( 'role' => 'administrator' ) );
        $this->editor_id = (int) $this->factory->user->create( array( 'role' => 'editor' ) );
    }

    public function tearDown(): void
    {
        global $wp_rest_server;
        $wp_rest_server = null;
        wp_set_current_user( 0 );
        remove_action( 'rest_api_init', array( AboutController::class, 'register_route' ) );
        parent::tearDown();
    }

    // ── Auth ─────────────────────────────────────────────────────────────

    public function test_unauthenticated_request_returns_401(): void
    {
        wp_set_current_user( 0 );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/about' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 401, $response->get_status() );
    }

    public function test_editor_request_returns_403(): void
    {
        wp_set_current_user( $this->editor_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/about' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 403, $response->get_status() );
    }

    // ── Response shape ───────────────────────────────────────────────────

    public function test_admin_gets_200_with_required_top_level_keys(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/about' );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 200, $response->get_status() );

        $data = (array) $response->get_data();
        foreach ( array( 'general', 'features', 'system', 'support', 'developer' ) as $key ) {
            $this->assertArrayHasKey( $key, $data, "Missing top-level key: {$key}" );
        }
    }

    public function test_general_stats_vehicles_is_non_negative_integer(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/about' );
        $response = self::$server->dispatch( $request );
        $data     = (array) $response->get_data();

        $stats = $data['general']['stats'] ?? array();
        $vehicles_row = current( array_filter( $stats, fn( $r ) => ( $r['label'] ?? '' ) === 'Total Vehicles' ) );

        $this->assertNotFalse( $vehicles_row, 'Total Vehicles row not found in general.stats' );
        $this->assertIsNumeric( $vehicles_row['value'] );
        $this->assertGreaterThanOrEqual( 0, (int) $vehicles_row['value'] );
    }

    public function test_support_changelog_has_required_fields(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/about' );
        $response = self::$server->dispatch( $request );
        $data     = (array) $response->get_data();

        $changelog = $data['support']['changelog'] ?? array();
        $this->assertNotEmpty( $changelog, 'support.changelog must not be empty' );

        $first = (array) $changelog[0];
        $this->assertArrayHasKey( 'version', $first );
        $this->assertArrayHasKey( 'date',    $first );
        $this->assertArrayHasKey( 'changes', $first );
        $this->assertIsArray( $first['changes'] );
    }

    public function test_system_php_version_matches_php_version_constant(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/about' );
        $response = self::$server->dispatch( $request );
        $data     = (array) $response->get_data();

        $php_rows   = $data['system']['php'] ?? array();
        $version_row = current( array_filter( $php_rows, fn( $r ) => ( $r['label'] ?? '' ) === 'Version' ) );

        $this->assertNotFalse( $version_row, 'PHP Version row not found in system.php' );
        $this->assertSame( PHP_VERSION, $version_row['value'] );
    }
}
