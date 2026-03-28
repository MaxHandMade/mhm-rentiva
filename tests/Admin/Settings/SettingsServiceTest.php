<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Services\SettingsService;
use MHMRentiva\Admin\REST\Settings\RESTSettings;
use MHMRentiva\Admin\Settings\Groups\EmailSettings;
use WP_UnitTestCase;

class SettingsServiceTest extends WP_UnitTestCase
{
    private $admin_user_id;

    public function setUp(): void
    {
        parent::setUp();

        // Create an admin user for permission checks
        $this->admin_user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($this->admin_user_id);
    }

    /** @test */
    public function it_resets_email_templates_correctly()
    {
        // 1. Manually set some custom template values
        update_option('mhm_rentiva_booking_created_subject', 'Custom Subject');
        update_option('mhm_rentiva_booking_created_body', 'Custom Body');

        // 2. Mock defaults
        $defaults = EmailSettings::get_default_settings();
        $expected_subject = $defaults['mhm_rentiva_booking_created_subject'] ?? '';

        // 3. Trigger Reset
        $result = SettingsService::reset_defaults('email-templates');

        // 4. Assertions
        $this->assertTrue($result);
        $this->assertEquals($expected_subject, get_option('mhm_rentiva_booking_created_subject'));
    }

    /** @test */
    public function it_resets_general_email_settings_correctly()
    {
        // 1. Setup master option with custom value
        $settings = ['mhm_rentiva_sender_name' => 'Custom Provider'];
        update_option('mhm_rentiva_settings', $settings);

        // Standalone legacy option
        update_option('mhm_rentiva_sender_name', 'Legacy Provider');

        // 2. Trigger Reset
        $result = SettingsService::reset_defaults('email');

        // 3. Assertions
        $this->assertTrue($result);

        $master_settings = get_option('mhm_rentiva_settings');
        $defaults = EmailSettings::get_default_settings();

        $this->assertArrayHasKey('mhm_rentiva_email_header_image', $defaults);

        // Legacy option should be deleted
        $this->assertFalse(get_option('mhm_rentiva_sender_name'));
    }

    /** @test */
    public function it_saves_rest_settings_correctly()
    {
        $input_data = [
            'rate_limiting' => [
                'enabled' => '1',
                'default_limit' => '50'
            ]
        ];

        $result = SettingsService::save_rest_settings($input_data);

        $this->assertTrue($result);

        $saved_settings = get_option(RESTSettings::OPTION_NAME);
        $this->assertTrue($saved_settings['rate_limiting']['enabled']);
        $this->assertEquals(50, $saved_settings['rate_limiting']['default_limit']);
    }

    /** @test */
    public function it_prevents_unauthorized_resets()
    {
        // Set user to subscriber (no permissions)
        $subscriber_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriber_id);

        $result = SettingsService::reset_defaults('general');

        $this->assertFalse($result);
    }
}
