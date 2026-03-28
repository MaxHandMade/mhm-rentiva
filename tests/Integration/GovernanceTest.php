<?php

/**
 * Integration Test for Governance Enforcement.
 *
 * @package MHMRentiva
 */

namespace MHMRentiva\Tests\Integration;

use MHMRentiva\Admin\Core\Governance;

/**
 * Class GovernanceTest
 */
class GovernanceTest extends \WP_UnitTestCase
{

    /**
     * Governance instance.
     *
     * @var Governance
     */
    protected $governance;

    /**
     * Setup test environment.
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->governance = new Governance();
    }

    /**
     * Test that forbidden handles are correctly identified as violations.
     */
    public function test_forbidden_handles_are_violations(): void
    {
        $this->assertTrue($this->governance->is_violation('tailwind', 'style.css'));
        $this->assertTrue($this->governance->is_violation('tailwindcss', 'style.css'));
        $this->assertTrue($this->governance->is_violation('tailwind-cdn', 'style.css'));
        $this->assertTrue($this->governance->is_violation('mhm-tailwind-logic', 'style.css'));

        $this->assertFalse($this->governance->is_violation('mhm-rentiva-core', 'style.css'));
        $this->assertFalse($this->governance->is_violation('jquery', 'jquery.js'));
    }

    /**
     * Test that forbidden URLs (Tailwind CDN) are correctly identified as violations.
     */
    public function test_forbidden_urls_are_violations(): void
    {
        $this->assertTrue($this->governance->is_violation('my-style', 'https://cdn.tailwindcss.com'));
        $this->assertTrue($this->governance->is_violation('my-style', 'https://cdn.tailwindcss.com/v3.0.0'));
        $this->assertTrue($this->governance->is_violation('my-style', 'https://unpkg.com/tailwindcss@^2/dist/tailwind.min.css'));

        $this->assertFalse($this->governance->is_violation('my-style', 'https://trusted-cdn.com/style.css'));
        $this->assertFalse($this->governance->is_violation('my-style', home_url('/wp-content/plugins/mhm-rentiva/assets/css/core.css')));
    }

    /**
     * Test that violating styles are correctly dequeued (using fake URLs to avoid HTTP requests).
     */
    public function test_violating_styles_are_dequeued(): void
    {
        // Enforce hooks
        $this->governance->register();

        // 1. Enqueue a valid style (safe local path)
        wp_enqueue_style('mhm-valid-style', '/wp-content/plugins/mhm-rentiva/assets/css/core/core.css');

        // 2. Enqueue a violating style (via forbidden handle - no real HTTP call needed)
        wp_enqueue_style('tailwind-fake', '/wp-content/some-local-tailwind-copy.css');

        // 3. Enqueue a violating style (via URL containing forbidden string - kept as path, no outbound request)
        wp_enqueue_style('external-violation', 'https://cdn.tailwindcss.com/fake-for-test-only.css');

        // Manually invoke the enforcement (mimics the action callback)
        $this->governance->enforce_no_tailwind();

        // Assertions
        $this->assertTrue(wp_style_is('mhm-valid-style', 'enqueued'), 'Valid style should remain enqueued');
        $this->assertFalse(wp_style_is('tailwind-fake', 'enqueued'), 'Violating handle should be dequeued');
        $this->assertFalse(wp_style_is('external-violation', 'enqueued'), 'Violating URL should be dequeued');
    }
}
