<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Directory;

use MHMRentiva\Admin\Vendor\Directory\VendorDirectoryUrlBase;
use WP_UnitTestCase;

/**
 * @covers \MHMRentiva\Admin\Vendor\Directory\VendorDirectoryUrlBase
 */
final class VendorDirectoryUrlBaseTest extends WP_UnitTestCase
{
    public function test_resolve_returns_default_english_slug_when_no_filter(): void
    {
        $base = VendorDirectoryUrlBase::resolve();
        $this->assertSame('vendors', $base);
    }

    public function test_resolve_honors_filter_override(): void
    {
        add_filter('mhm_rentiva_vendor_directory_url_base', static fn(): string => 'dealers');
        $base = VendorDirectoryUrlBase::resolve();
        $this->assertSame('dealers', $base);
        remove_all_filters('mhm_rentiva_vendor_directory_url_base');
    }

    public function test_resolve_falls_back_to_english_when_filter_returns_empty(): void
    {
        add_filter('mhm_rentiva_vendor_directory_url_base', static fn(): string => '');
        $base = VendorDirectoryUrlBase::resolve();
        $this->assertSame('vendors', $base);
        remove_all_filters('mhm_rentiva_vendor_directory_url_base');
    }

    public function test_resolve_sanitizes_non_ascii_filter_return(): void
    {
        add_filter('mhm_rentiva_vendor_directory_url_base', static fn(): string => 'bäyîler');
        $base = VendorDirectoryUrlBase::resolve();
        $this->assertSame('bayiler', $base);
        remove_all_filters('mhm_rentiva_vendor_directory_url_base');
    }

    public function test_resolve_falls_back_when_filter_returns_null(): void
    {
        add_filter('mhm_rentiva_vendor_directory_url_base', static fn() => null);
        $base = VendorDirectoryUrlBase::resolve();
        $this->assertSame('vendors', $base);
        remove_all_filters('mhm_rentiva_vendor_directory_url_base');
    }

    public function test_check_for_locale_change_fires_action_once_on_change(): void
    {
        $fired = 0;
        add_action('mhm_rentiva_vendor_directory_url_base_changed', static function () use (&$fired): void {
            $fired++;
        });

        // First call — no cached value yet, action fires once
        VendorDirectoryUrlBase::check_for_locale_change();
        // Second call — cached value matches current, action must NOT fire
        VendorDirectoryUrlBase::check_for_locale_change();

        $this->assertSame(1, $fired, 'Action must fire exactly once when base does not change between calls.');

        delete_option('mhm_rentiva_vendor_directory_url_base_cached');
        remove_all_actions('mhm_rentiva_vendor_directory_url_base_changed');
    }
}
