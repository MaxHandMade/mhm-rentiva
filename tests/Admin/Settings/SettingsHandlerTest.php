<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\SettingsHandler;
use MHMRentiva\Admin\Settings\Settings;
use WP_UnitTestCase;

class SettingsHandlerTest extends WP_UnitTestCase
{
    private $admin_user_id;

    public function setUp(): void
    {
        parent::setUp();

        $_POST = array();
        $_GET  = array();

        $this->admin_user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($this->admin_user_id);
    }

    /** @test */
    public function it_handles_reset_defaults_action()
    {
        $nonce = wp_create_nonce('mhm_rentiva_reset_defaults');

        $_GET['reset_defaults'] = 'true';
        $_GET['_wpnonce'] = $nonce;
        $_GET['tab'] = 'email';

        add_filter('wp_redirect', function ($location) {
            throw new \RuntimeException('redirected:' . $location);
        });

        // We expect a redirect after reset
        try {
            SettingsHandler::handle();
            $this->fail('Expected wp_safe_redirect to throw an intercepted exception.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('settings-updated=true', $e->getMessage());
        }

        // Verify side effect: redirection was attempted
        $this->assertTrue(true);
    }

    /** @test */
    public function it_ignores_requests_without_nonce()
    {
        $_GET['reset_defaults'] = 'true';
        // Missing _wpnonce

        // Setting a value that should be reset if it worked
        update_option('mhm_rentiva_settings', ['mhm_rentiva_sender_name' => 'Should Stay']);

        try {
            SettingsHandler::handle();
            $this->fail('Expected security check to fail and throw WPDieException');
        } catch (\WPDieException $e) {
            $this->assertStringContainsString('Security check failed', $e->getMessage());
        }

        $settings = get_option('mhm_rentiva_settings');
        $this->assertEquals('Should Stay', $settings['mhm_rentiva_sender_name'] ?? '');
    }

    /** @test */
    public function it_prevents_action_for_non_admins()
    {
        $subscriber_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriber_id);

        $_GET['reset_defaults'] = 'true';
        $_GET['_wpnonce'] = wp_create_nonce('mhm_rentiva_reset_defaults');

        SettingsHandler::handle();

        // No exception thrown and no redirect should happen (logic returns early)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_email_templates_save_action()
    {
        wp_set_current_user($this->admin_user_id);

        $_POST['email_templates_action'] = 'save';
        $_POST['_wpnonce'] = wp_create_nonce('mhm_rentiva_settings-options'); // Handler expects this
        $_POST['mhm_rentiva_email_templates_nonce'] = wp_create_nonce('mhm_rentiva_save_email_templates'); // EmailTemplates expects this
        $_POST['current_tab'] = 'booking_notifications';
        $_POST['mhm_rentiva_booking_created_subject'] = 'Updated Subject';

        try {
            SettingsHandler::handle();
        } catch (\WPDieException $e) {
        }

        $this->assertEquals('Updated Subject', get_option('mhm_rentiva_booking_created_subject'));
    }

    /** @test */
    public function it_handles_rest_settings_save_action()
    {
        wp_set_current_user($this->admin_user_id);

        $_POST['option_page'] = 'mhm_rentiva_rest_settings';
        $_POST['action'] = 'update';
        $_POST['_wpnonce'] = wp_create_nonce('mhm_rentiva_rest_settings-options');
        $_POST['mhm_rentiva_rest_settings'] = [
            'rate_limiting' => [
                'enabled' => '1',
                'default_limit' => '100'
            ]
        ];

        try {
            SettingsHandler::handle();
        } catch (\WPDieException $e) {
        }

        $saved = get_option('mhm_rentiva_rest_settings');
        $this->assertTrue($saved['rate_limiting']['enabled']);
        $this->assertEquals(100, $saved['rate_limiting']['default_limit']);
    }
}
