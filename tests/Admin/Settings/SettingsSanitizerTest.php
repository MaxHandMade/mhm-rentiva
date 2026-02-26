<?php

namespace MHMRentiva\Tests\Admin\Settings\Core;

use MHMRentiva\Admin\Core\Helpers\Sanitizer;
use WP_UnitTestCase;

class SettingsSanitizerTest extends WP_UnitTestCase
{
    /**
     * @test
     */
    public function it_sanitizes_text_field_safely()
    {
        $dirty = "  Test<script>alert('x')</script>  ";
        $clean = Sanitizer::text_field_safe($dirty);

        $this->assertEquals('Test', $clean);
    }

    /**
     * @test
     */
    public function it_returns_empty_string_for_null_values()
    {
        $result = Sanitizer::text_field_safe(null);
        $this->assertEquals('', $result);
    }
}
