<?php

namespace MHMRentiva\Tests\Admin\Settings\Core;

use MHMRentiva\Admin\Settings\Core\RateLimiter;
use WP_UnitTestCase;

/**
 * Test class for RateLimiter
 */
class RateLimiterTest extends WP_UnitTestCase
{
    private $limiter;

    public function setUp(): void
    {
        parent::setUp();
        $this->limiter = RateLimiter::instance();

        // Reset state before tests
        $reflection = new \ReflectionClass(RateLimiter::class);
        $instance = $reflection->getProperty('instance');
        $instance->setValue(null, null);

        $this->limiter = RateLimiter::instance();

        // Clear potential leftovers
        $this->clear_transients();
    }

    private function clear_transients()
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_mhm_rl_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_timeout_mhm_rl_%'");
    }

    /**
     * @test
     */
    public function it_allows_requests_within_limit()
    {
        $this->limiter->configure(5, 60);

        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($this->limiter->check_limit(), "Hit $i should be allowed");
        }

        $this->assertFalse($this->limiter->check_limit(), "Hit 6 should be blocked");
    }

    /**
     * @test
     */
    public function it_uses_client_ip_from_server_vars()
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $this->limiter->configure(1, 60);

        $this->assertTrue($this->limiter->check_limit());
        $this->assertFalse($this->limiter->check_limit());

        // Change IP
        $_SERVER['REMOTE_ADDR'] = '192.168.1.2';
        $this->assertTrue($this->limiter->check_limit(), "New IP should have its own limit");
    }

    /**
     * @test
     */
    public function it_is_secure_by_default_and_ignores_proxies()
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->limiter->configure(1, 60);
        $this->assertTrue($this->limiter->check_limit());

        // Should block based on 127.0.0.1, NOT 1.2.3.4
        $this->assertFalse($this->limiter->check_limit());

        // Key should be for 127.0.0.1
        $key = 'mhm_rl_' . md5('127.0.0.1');
        $this->assertNotFalse(get_site_transient($key));
    }

    /**
     * @test
     */
    public function it_allows_custom_ip_detection_via_filter()
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        // Add filter to trust proxy
        add_filter('mhm_rentiva_rate_limiter_client_ip', function () {
            return '5.6.7.8';
        });

        $this->limiter->configure(1, 60);
        $this->assertTrue($this->limiter->check_limit());

        // Verify key exists for filtered IP
        $key = 'mhm_rl_' . md5('5.6.7.8');
        $this->assertNotFalse(get_site_transient($key), "Filter should have changed the target IP");

        // Cleanup
        remove_all_filters('mhm_rentiva_rate_limiter_client_ip');
    }

    /**
     * @test
     */
    public function it_cleans_up_expired_limits()
    {
        $this->limiter->configure(10, 60);

        // Manual insert of expired transient
        $transient_key = 'mhm_rl_expired_test';
        set_site_transient($transient_key, 5, -100); // Expired 100s ago

        $this->limiter->cleanup_expired_limits();

        $this->assertFalse(get_site_transient($transient_key), "Expired transient should be removed");
    }
}
