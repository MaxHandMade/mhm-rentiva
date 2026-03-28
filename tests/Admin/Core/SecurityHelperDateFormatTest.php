<?php

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\SecurityHelper;
use WP_UnitTestCase;

class SecurityHelperDateFormatTest extends WP_UnitTestCase
{

    public function setUp(): void
    {
        parent::setUp();
        // Veritabanındaki tarih formatını d/m/Y olarak ayarla (Test ortamında)
        update_option('date_format', 'd/m/Y');
    }

    /** @test */
    public function it_validates_iso_date_regardless_of_wp_format()
    {
        // WordPress d/m/Y olsa bile ISO formatını tanımalı
        $input = '2026-01-28';
        $result = SecurityHelper::validate_date($input);

        $this->assertEquals('2026-01-28', $result);
    }

    /** @test */
    public function it_validates_and_converts_wp_localized_date_to_iso()
    {
        // WordPress formatı d/m/Y iken bu formatta gelen tarihi ISO'ya çevirmeli
        $input = '28/01/2026';
        $result = SecurityHelper::validate_date($input);

        $this->assertEquals('2026-01-28', $result);
    }

    /** @test */
    public function it_fails_on_invalid_date_format()
    {
        $this->expectException(\InvalidArgumentException::class);
        SecurityHelper::validate_date('invalid-date');
    }

    /** @test */
    public function it_handles_different_separators_if_strtotime_allows()
    {
        // strtotime fallback testi
        $input = '2026.01.28';
        $result = SecurityHelper::validate_date($input);

        $this->assertEquals('2026-01-28', $result);
    }
}
