<?php

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\APIKeysPage;
use MHMRentiva\Admin\REST\APIKeyManager;

/**
 * Test class for APIKeysPage
 */
class APIKeysPageTest extends \WP_Ajax_UnitTestCase
{
    private $admin_id;

    public function setUp(): void
    {
        parent::setUp();
        $this->admin_id = $this->factory->user->create(['role' => 'administrator']);
        $_POST = [];
        $_REQUEST = [];
        \MHMRentiva\Admin\Settings\APIKeysPage::register();
    }

    /** @test */
    public function it_denies_unauthorized_access()
    {
        wp_set_current_user(0);

        $_POST['nonce'] = wp_create_nonce('mhm_rest_api_keys_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];
        $_POST['action'] = 'mhm_list_api_keys';
        $_REQUEST['action'] = 'mhm_list_api_keys';

        try {
            $this->_handleAjax('mhm_list_api_keys');
            $this->fail('Expected wp_send_json_error to trigger wp_die');
        } catch (\WPAjaxDieContinueException $e) {
            // Expected
        } catch (\WPAjaxDieStopException $e) {
            // Expected
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success'] ?? true);
        $this->assertEquals('Insufficient permissions to perform this action.', $response['data']['message'] ?? '');
    }

    /**
     * @test
     */
    public function it_lists_api_keys_for_admin()
    {
        wp_set_current_user($this->admin_id);

        $_POST['nonce'] = wp_create_nonce('mhm_rest_api_keys_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];
        $_POST['action'] = 'mhm_list_api_keys';
        $_REQUEST['action'] = 'mhm_list_api_keys';

        try {
            $this->_handleAjax('mhm_list_api_keys');
        } catch (\WPAjaxDieContinueException $e) {
            // Expected
        } catch (\WPAjaxDieStopException $e) {
            // Expected
        }

        var_dump("Response is: " . $this->_last_response);
        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success'] ?? false);
        $this->assertArrayHasKey('keys', $response['data']);
        $this->assertArrayHasKey('count', $response['data']);
    }

    /**
     * @test
     */
    public function it_creates_api_key_validly()
    {
        wp_set_current_user($this->admin_id);

        $_POST['nonce'] = wp_create_nonce('mhm_rest_api_keys_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];
        $_POST['action'] = 'mhm_create_api_key';
        $_REQUEST['action'] = 'mhm_create_api_key';
        $_POST['name'] = 'Test Key';
        $_REQUEST['name'] = 'Test Key';
        $_POST['permissions'] = ['read', 'write'];
        $_REQUEST['permissions'] = ['read', 'write'];

        try {
            $this->_handleAjax('mhm_create_api_key');
        } catch (\WPAjaxDieContinueException $e) {
            // Expected
        } catch (\WPAjaxDieStopException $e) {
            // Expected
        }

        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success'], $response['data']['message'] ?? 'Creation failed');
        $this->assertEquals('API key created successfully.', $response['data']['message']);
        $this->assertArrayHasKey('key', $response['data']);
        $this->assertEquals('Test Key', $response['data']['key']['name']);
    }

    /**
     * @test
     */
    public function it_fails_to_create_api_key_without_name()
    {
        wp_set_current_user($this->admin_id);

        $_POST['nonce'] = wp_create_nonce('mhm_rest_api_keys_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];
        $_POST['action'] = 'mhm_create_api_key';
        $_REQUEST['action'] = 'mhm_create_api_key';
        $_POST['name'] = '';
        $_REQUEST['name'] = '';

        try {
            $this->_handleAjax('mhm_create_api_key');
        } catch (\WPAjaxDieContinueException $e) {
            // Expected
        } catch (\WPAjaxDieStopException $e) {
            // Expected
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success'] ?? true);
        $this->assertEquals('API key name is required.', $response['data']['message'] ?? '');
    }
}
