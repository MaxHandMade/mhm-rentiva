<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes\Account;

use WP_UnitTestCase;

class MyBookingsTest extends WP_UnitTestCase
{
    public function test_logged_out_returns_login_message()
    {
        wp_set_current_user(0);
        $output = do_shortcode('[rentiva_my_bookings]');

        $this->assertStringContainsString('Please login to view this content.', $output);
    }

    public function test_logged_in_renders_bookings_page()
    {
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $output = do_shortcode('[rentiva_my_bookings]');

        $this->assertStringContainsString('rv-bookings-page', $output);
    }

    public function test_logged_in_renders_account_wrapper()
    {
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $output = do_shortcode('[rentiva_my_bookings]');

        $this->assertStringContainsString('mhm-rentiva-account-page', $output);
    }

    public function tearDown(): void
    {
        wp_set_current_user(0);
        parent::tearDown();
    }
}
