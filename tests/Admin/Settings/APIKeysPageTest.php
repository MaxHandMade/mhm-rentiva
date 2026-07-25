<?php

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\APIKeysPage;

/**
 * The dispatcher behind the Integration tab's AJAX actions.
 *
 * This file used to cover four API-key handlers. That surface is gone: the
 * Integration tab issued credentials labelled READ / WRITE / ADMIN ("Full
 * system control") while `APIKeyManager::verify_api_key()` had no caller
 * anywhere — every REST route authenticates through `AuthHelper::verifyAuth()`,
 * which accepts only a WordPress nonce. A key generated there opened nothing,
 * so the screen handed the administrator a secret and misdescribed what it did.
 *
 * Two actions remain — the endpoint reference list and the settings reset — and
 * what still has to hold is the dispatcher's own gate, which is what these
 * tests pin.
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
        APIKeysPage::register();
    }

    /**
     * @test
     */
    public function it_denies_unauthorized_access()
    {
        wp_set_current_user(0);

        $_POST['nonce'] = wp_create_nonce('mhm_rest_api_keys_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];
        $_POST['action'] = 'mhm_rentiva_list_endpoints';
        $_REQUEST['action'] = 'mhm_rentiva_list_endpoints';

        try {
            $this->_handleAjax('mhm_rentiva_list_endpoints');
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
    public function it_rejects_a_bad_nonce()
    {
        wp_set_current_user($this->admin_id);

        $_POST['nonce'] = 'not-a-real-nonce';
        $_REQUEST['nonce'] = $_POST['nonce'];
        $_POST['action'] = 'mhm_rentiva_list_endpoints';
        $_REQUEST['action'] = 'mhm_rentiva_list_endpoints';

        try {
            $this->_handleAjax('mhm_rentiva_list_endpoints');
            $this->fail('Expected wp_send_json_error to trigger wp_die');
        } catch (\WPAjaxDieContinueException $e) {
            // Expected
        } catch (\WPAjaxDieStopException $e) {
            // Expected
        }

        $response = json_decode($this->_last_response, true);
        $this->assertFalse($response['success'] ?? true);
        $this->assertEquals('Invalid security nonce.', $response['data']['message'] ?? '');
    }

    /**
     * @test
     */
    public function it_lists_endpoints_for_admin()
    {
        wp_set_current_user($this->admin_id);

        $_POST['nonce'] = wp_create_nonce('mhm_rest_api_keys_nonce');
        $_REQUEST['nonce'] = $_POST['nonce'];
        $_POST['action'] = 'mhm_rentiva_list_endpoints';
        $_REQUEST['action'] = 'mhm_rentiva_list_endpoints';

        try {
            $this->_handleAjax('mhm_rentiva_list_endpoints');
        } catch (\WPAjaxDieContinueException $e) {
            // Expected
        } catch (\WPAjaxDieStopException $e) {
            // Expected
        }

        $response = json_decode($this->_last_response, true);
        $this->assertTrue($response['success'] ?? false, $response['data']['message'] ?? 'Listing failed');
    }

    /**
     * The removed handlers must not answer any more. Leaving one registered
     * would keep the credential machinery reachable from the browser after the
     * UI that drove it was taken away.
     *
     * @test
     */
    public function the_removed_api_key_actions_are_no_longer_registered()
    {
        foreach ([
            'mhm_rentiva_create_api_key',
            'mhm_rentiva_list_api_keys',
            'mhm_rentiva_revoke_api_key',
            'mhm_rentiva_delete_api_key',
        ] as $action) {
            $this->assertFalse(
                has_action('wp_ajax_' . $action),
                $action . ' is still registered; the API-key surface is still reachable.'
            );
        }
    }
}
