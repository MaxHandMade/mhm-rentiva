<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes\Account;

use WP_UnitTestCase;

class MyFavoritesTest extends WP_UnitTestCase
{
    public function test_logged_out_returns_login_message()
    {
        wp_set_current_user(0);
        $output = do_shortcode('[rentiva_my_favorites]');

        $this->assertStringContainsString('Please login to view this content.', $output);
    }

    public function test_logged_in_renders_favorites_wrapper()
    {
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $output = do_shortcode('[rentiva_my_favorites]');

        $this->assertStringContainsString('mhm-my-favorites-container', $output);
    }

    public function test_logged_in_renders_favorites_content()
    {
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);

        $output = do_shortcode('[rentiva_my_favorites]');

        $this->assertStringContainsString('rv-my-favorites-wrapper', $output);
    }

    public function tearDown(): void
    {
        wp_set_current_user(0);
        parent::tearDown();
    }
}
