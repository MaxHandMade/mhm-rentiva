<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\LicenseAdmin;
use WP_UnitTestCase;

/**
 * v4.32.0 — state-driven emphasis on the "Manage Subscription" button.
 *
 * Threshold table (matches `compute_emphasis_class()` in LicenseAdmin):
 *
 *   - null          → '' (no expiry data — default styling)
 *   - >= 31 days    → '' (plenty of runway)
 *   - 8..30 days    → 'mhm-rentiva-license-warning' (yellow)
 *   - 0..7 days     → 'mhm-rentiva-license-urgent' (amber + glow)
 *
 * @covers \MHMRentiva\Admin\Licensing\LicenseAdmin::compute_emphasis_class
 */
final class EmphasisClassTest extends WP_UnitTestCase
{
    public function test_null_days_returns_empty(): void
    {
        $this->assertSame('', LicenseAdmin::compute_emphasis_class(null));
    }

    public function test_60_days_returns_empty(): void
    {
        $this->assertSame('', LicenseAdmin::compute_emphasis_class(60));
    }

    public function test_25_days_returns_warning(): void
    {
        $this->assertSame('mhm-rentiva-license-warning', LicenseAdmin::compute_emphasis_class(25));
    }

    public function test_5_days_returns_urgent(): void
    {
        $this->assertSame('mhm-rentiva-license-urgent', LicenseAdmin::compute_emphasis_class(5));
    }

    public function test_0_days_returns_urgent(): void
    {
        $this->assertSame('mhm-rentiva-license-urgent', LicenseAdmin::compute_emphasis_class(0));
    }
}
