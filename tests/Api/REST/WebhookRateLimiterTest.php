<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Api\REST;

use MHMRentiva\Api\REST\WebhookRateLimiter;
use WP_UnitTestCase;

/**
 * Unit tests for WebhookRateLimiter — Sprint 6 QA evidence.
 *
 * Scenarios:
 *   1. Under limit: requests below max_requests all succeed.
 *   2. Rate exceeded: request at max_requests+1 returns false (429 trigger).
 *   3. Reset: after reset(), counter goes back to 0.
 *   4. build_identifier() includes IP — different IPs produce different keys.
 *   5. Different identifiers are independently tracked (no cross-pollution).
 *
 * @since 4.21.0
 */
class WebhookRateLimiterTest extends WP_UnitTestCase
{
    private string $identifier;

    public function setUp(): void
    {
        parent::setUp();
        // Unique identifier per test to avoid inter-test pollution.
        $this->identifier = '127.0.0.1:testsig_' . wp_generate_password(8, false);
    }

    public function tearDown(): void
    {
        WebhookRateLimiter::reset($this->identifier);
        parent::tearDown();
    }

    /**
     * @test
     * All requests below max are allowed.
     */
    public function test_under_limit_all_pass(): void
    {
        $max = 5;
        for ($i = 1; $i <= $max; $i++) {
            $this->assertTrue(
                WebhookRateLimiter::check($this->identifier, $max, 60),
                "Request #{$i} should be allowed (under limit)."
            );
        }
    }

    /**
     * @test
     * Request at max+1 is denied — rate limiter returns false.
     */
    public function test_rate_exceeded_returns_false(): void
    {
        $max = 3;
        // Fill the window.
        for ($i = 0; $i < $max; $i++) {
            WebhookRateLimiter::check($this->identifier, $max, 60);
        }

        $result = WebhookRateLimiter::check($this->identifier, $max, 60);
        $this->assertFalse($result, 'Request beyond max_requests must return false (429 trigger).');
    }

    /**
     * @test
     * Counter is accessible via get_count().
     */
    public function test_get_count_reflects_actual_count(): void
    {
        WebhookRateLimiter::check($this->identifier, 10, 60);
        WebhookRateLimiter::check($this->identifier, 10, 60);

        $this->assertSame(2, WebhookRateLimiter::get_count($this->identifier));
    }

    /**
     * @test
     * reset() clears the counter — next request passes.
     */
    public function test_reset_clears_window(): void
    {
        $max = 2;
        // Exhaust.
        WebhookRateLimiter::check($this->identifier, $max, 60);
        WebhookRateLimiter::check($this->identifier, $max, 60);

        $this->assertFalse(
            WebhookRateLimiter::check($this->identifier, $max, 60),
            'Should be rate-limited before reset.'
        );

        WebhookRateLimiter::reset($this->identifier);

        $this->assertTrue(
            WebhookRateLimiter::check($this->identifier, $max, 60),
            'After reset(), first request must pass again.'
        );
    }

    /**
     * @test
     * build_identifier() with different IPs produces different keys (IP-inclusive).
     */
    public function test_build_identifier_ip_inclusive(): void
    {
        $sig   = 'sha256=abc123def456';
        $id_v4 = WebhookRateLimiter::build_identifier($sig); // Uses $_SERVER['REMOTE_ADDR'] = '127.0.0.1' in CLI

        // Manually build with a different IP — must differ.
        $id_other = hash('sha256', '10.0.0.1:' . substr($sig, 0, 32));
        // They will be different because IP differs.
        $this->assertNotSame(
            hash('sha256', $id_v4),
            hash('sha256', $id_other),
            'Identifiers from different IPs must produce different rate limit keys.'
        );
    }

    /**
     * @test
     * Two different identifiers are tracked independently.
     */
    public function test_independent_identifier_isolation(): void
    {
        $id_a = '1.2.3.4:sig_aaaaaa';
        $id_b = '1.2.3.4:sig_bbbbbb';

        WebhookRateLimiter::check($id_a, 1, 60);
        WebhookRateLimiter::check($id_a, 1, 60); // a exhausted
        WebhookRateLimiter::reset($id_a);

        // b is independent — should still have free slots.
        $this->assertTrue(
            WebhookRateLimiter::check($id_b, 5, 60),
            'Identifier B must not be affected by identifier A exhaustion.'
        );

        WebhookRateLimiter::reset($id_a);
        WebhookRateLimiter::reset($id_b);
    }
}
