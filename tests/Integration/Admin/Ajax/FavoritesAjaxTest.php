<?php

namespace MHMRentiva\Admin\Tests\Integration\Ajax;

use MHMRentiva\Admin\Services\FavoritesService;
use WP_Ajax_UnitTestCase;

class FavoritesAjaxTest extends WP_Ajax_UnitTestCase
{
    private static bool $service_registered = false;

    private $user_id;
    /**
     * @var string
     */
    protected $_last_response;

    private $vehicle_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->user_id = (int) $this->factory->user->create();
        // Favorites service only validates positive numeric ID; post creation is not required here.
        $this->vehicle_id = 12345;

        wp_set_current_user($this->user_id);
        if (! self::$service_registered) {
            FavoritesService::register();
            self::$service_registered = true;
        }
        delete_user_meta($this->user_id, 'mhm_rentiva_favorites');
    }

    public function tearDown(): void
    {
        parent::tearDown();
        wp_logout();
    }

    public function test_ajax_add_favorite()
    {
        $_POST['action'] = 'mhm_rentiva_toggle_favorite';
        $_POST['nonce'] = wp_create_nonce('mhm_rentiva_toggle_favorite');
        $_POST['vehicle_id'] = $this->vehicle_id;

        try {
            $this->_handleAjax('mhm_rentiva_toggle_favorite');
        } catch (\WPAjaxDieContinueException $e) {
            // Good
        }

        $response = json_decode(trim($this->_last_response), true);

        $this->assertTrue($response['success']);
        $this->assertEquals('added', $response['data']['action']);

        $this->assertTrue(FavoritesService::is_favorite($this->user_id, $this->vehicle_id));
    }

    public function test_ajax_remove_favorite()
    {
        FavoritesService::add($this->user_id, $this->vehicle_id);

        $_POST['action'] = 'mhm_rentiva_toggle_favorite';
        $_POST['nonce'] = wp_create_nonce('mhm_rentiva_toggle_favorite');
        $_POST['vehicle_id'] = $this->vehicle_id;

        try {
            $this->_handleAjax('mhm_rentiva_toggle_favorite');
        } catch (\WPAjaxDieContinueException $e) {
            // Success
        }

        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success']);
        $this->assertEquals('removed', $response['data']['action']);

        $this->assertFalse(FavoritesService::is_favorite($this->user_id, $this->vehicle_id));
    }

    public function test_ajax_invalid_nonce()
    {
        $_POST['action'] = 'mhm_rentiva_toggle_favorite';
        $_POST['nonce'] = 'invalid_nonce';
        $_POST['vehicle_id'] = $this->vehicle_id;

        try {
            $this->_handleAjax('mhm_rentiva_toggle_favorite');
        } catch (\WPAjaxDieContinueException $e) {
            // Failure
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Security check failed', $response['data']['message']);
    }

    public function test_ajax_not_logged_in()
    {
        wp_logout();

        $_POST['action'] = 'mhm_rentiva_toggle_favorite';
        $_POST['nonce'] = wp_create_nonce('mhm_rentiva_toggle_favorite');
        $_POST['vehicle_id'] = $this->vehicle_id;

        try {
            $this->_handleAjax('mhm_rentiva_toggle_favorite');
        } catch (\WPAjaxDieContinueException $e) {
            // Failure
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('logged in', $response['data']['message']);
    }
}
