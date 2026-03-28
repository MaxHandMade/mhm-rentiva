<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * Tests for SettingsSanitizer public static methods.
 * These are pure unit tests — no DB interaction.
 */
class SettingsSanitizerPublicTest extends WP_UnitTestCase
{
    // -------------------------------------------------------------------------
    // sanitize_dark_mode_option
    // -------------------------------------------------------------------------

    public function test_dark_mode_auto_is_default_for_empty_value()
    {
        $this->assertSame('auto', SettingsSanitizer::sanitize_dark_mode_option(''));
    }

    public function test_dark_mode_returns_auto_for_unknown_value()
    {
        $this->assertSame('auto', SettingsSanitizer::sanitize_dark_mode_option('rainbow'));
    }

    public function test_dark_mode_truthy_values_map_to_dark()
    {
        foreach (['1', 'on', 'yes', 'true', 'dark'] as $value) {
            $this->assertSame('dark', SettingsSanitizer::sanitize_dark_mode_option($value), "Failed for value: $value");
        }
    }

    public function test_dark_mode_falsy_values_map_to_light()
    {
        foreach (['0', 'off', 'no', 'false', 'light'] as $value) {
            $this->assertSame('light', SettingsSanitizer::sanitize_dark_mode_option($value), "Failed for value: $value");
        }
    }

    public function test_dark_mode_auto_maps_to_auto()
    {
        $this->assertSame('auto', SettingsSanitizer::sanitize_dark_mode_option('auto'));
    }

    public function test_dark_mode_custom_default_returned_for_invalid_input()
    {
        $this->assertSame('light', SettingsSanitizer::sanitize_dark_mode_option('', 'light'));
    }

    // -------------------------------------------------------------------------
    // safe_text
    // -------------------------------------------------------------------------

    public function test_safe_text_strips_html_tags()
    {
        $result = SettingsSanitizer::safe_text('<script>alert("xss")</script>Hello');
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('Hello', $result);
    }

    public function test_safe_text_returns_empty_string_for_null()
    {
        $this->assertSame('', SettingsSanitizer::safe_text(null));
    }

    public function test_safe_text_returns_empty_string_for_array()
    {
        $this->assertSame('', SettingsSanitizer::safe_text(['foo', 'bar']));
    }

    public function test_safe_text_trims_whitespace()
    {
        $this->assertSame('hello', SettingsSanitizer::safe_text('  hello  '));
    }

    public function test_safe_text_returns_empty_for_empty_string()
    {
        $this->assertSame('', SettingsSanitizer::safe_text(''));
    }
}
