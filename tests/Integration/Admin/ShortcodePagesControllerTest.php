<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Settings\ShortcodePages\REST\ShortcodePagesController;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

final class ShortcodePagesControllerTest extends WP_UnitTestCase
{
    private static WP_REST_Server $server;
    private int $admin_id  = 0;
    private int $editor_id = 0;

    public function setUp(): void
    {
        parent::setUp();
        ShortcodePagesController::register();
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
        remove_action( 'rest_api_init', array( ShortcodePagesController::class, 'register_routes' ) );
        parent::tearDown();
    }

    // ── Auth ─────────────────────────────────────────────────────────────

    public function test_unauthenticated_get_returns_401(): void
    {
        wp_set_current_user( 0 );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/shortcode-pages' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 401, $response->get_status() );
    }

    public function test_editor_get_returns_403(): void
    {
        wp_set_current_user( $this->editor_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/shortcode-pages' );
        $response = self::$server->dispatch( $request );
        $this->assertSame( 403, $response->get_status() );
    }

    // ── List ─────────────────────────────────────────────────────────────

    public function test_admin_get_list_returns_200_with_26_shortcodes(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/shortcode-pages' );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 200, $response->get_status() );
        $data = (array) $response->get_data();

        $this->assertArrayHasKey( 'shortcodes', $data );
        $this->assertArrayHasKey( 'stats', $data );
        $this->assertCount( 26, $data['shortcodes'] );
        $this->assertSame( 26, (int) $data['stats']['total'] );
    }

    public function test_all_shortcode_slugs_have_rentiva_prefix(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/shortcode-pages' );
        $response = self::$server->dispatch( $request );
        $data     = (array) $response->get_data();

        foreach ( $data['shortcodes'] as $sc ) {
            $this->assertStringStartsWith(
                'rentiva_',
                (string) $sc['slug'],
                "Slug does not start with rentiva_: {$sc['slug']}"
            );
        }
    }

    // ── Create ───────────────────────────────────────────────────────────

    public function test_create_with_unknown_slug_returns_400(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/shortcode-pages/nonexistent_slug/create' );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 400, $response->get_status() );
        $this->assertSame( 'invalid_slug', $response->get_data()['code'] );
    }

    public function test_create_known_slug_returns_200_with_page_id(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/shortcode-pages/rentiva_my_bookings/create' );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 200, $response->get_status() );
        $data = (array) $response->get_data();

        $this->assertArrayHasKey( 'page_id', $data );
        $this->assertIsInt( $data['page_id'] );
        $this->assertGreaterThan( 0, $data['page_id'] );
        $this->assertSame( 'active', $data['status'] );
    }

    // ── Delete ───────────────────────────────────────────────────────────

    public function test_delete_slug_without_page_returns_404(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'DELETE', '/mhm-rentiva/v1/shortcode-pages/rentiva_my_favorites' );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 404, $response->get_status() );
        $this->assertSame( 'page_not_found', $response->get_data()['code'] );
    }

    // ── Clear Cache ──────────────────────────────────────────────────────

    public function test_clear_cache_returns_200_with_cleared_true(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/shortcode-pages/clear-cache' );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 200, $response->get_status() );
        $this->assertTrue( (bool) $response->get_data()['cleared'] );
    }

    // ── Debug ────────────────────────────────────────────────────────────

    public function test_debug_returns_200_with_scanned_pages_and_results(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/shortcode-pages/debug' );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 200, $response->get_status() );
        $data = (array) $response->get_data();

        $this->assertArrayHasKey( 'scanned_pages', $data );
        $this->assertArrayHasKey( 'results', $data );
        $this->assertGreaterThanOrEqual( 0, (int) $data['scanned_pages'] );
        $this->assertCount( 26, $data['results'] );
        $this->assertArrayHasKey( 'slug',     $data['results'][0] );
        $this->assertArrayHasKey( 'found_in', $data['results'][0] );
    }

    // ── Reset ────────────────────────────────────────────────────────────

    public function test_reset_returns_200_with_deleted_count(): void
    {
        wp_set_current_user( $this->admin_id );
        $request  = new WP_REST_Request( 'POST', '/mhm-rentiva/v1/shortcode-pages/reset' );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 200, $response->get_status() );
        $data = (array) $response->get_data();

        $this->assertArrayHasKey( 'deleted_count', $data );
        $this->assertGreaterThanOrEqual( 0, (int) $data['deleted_count'] );
    }
}
