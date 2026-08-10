<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\ListTable;

use MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns;
use WP_UnitTestCase;

/**
 * Faz 1b Task 2 — category chips on the vehicle list. Chips are plain
 * links carrying the taxonomy's own registered query var (same URL the
 * native admin-column term links use), so count/filter parity is native.
 * Empty terms stay out; off-screen renders nothing.
 */
final class VehicleCategoryChipsTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        global $pagenow, $post_type;
        $pagenow   = 'edit.php';
        $post_type = 'mhmrentiva_vehicle';
    }

    public function tearDown(): void
    {
        global $pagenow, $post_type;
        $pagenow   = 'index.php';
        $post_type = null;
        parent::tearDown();
    }

    public function test_chips_render_terms_in_use_and_skip_empty_ones(): void
    {
        $used  = self::factory()->term->create(array('taxonomy' => 'mhmrentiva_vehicle_category', 'name' => 'Ekonomi', 'slug' => 'ekonomi'));
        $empty = self::factory()->term->create(array('taxonomy' => 'mhmrentiva_vehicle_category', 'name' => 'BosKategori', 'slug' => 'bos-kategori'));

        $vehicle = self::factory()->post->create(array('post_type' => 'mhmrentiva_vehicle'));
        wp_set_object_terms($vehicle, array($used), 'mhmrentiva_vehicle_category');

        ob_start();
        VehicleColumns::category_chips();
        $html = ob_get_clean();

        $this->assertStringContainsString('mhmrentiva_vehicle_category=ekonomi', $html);
        $this->assertStringContainsString('rv-vhl-chip__count">1<', $html);
        $this->assertStringNotContainsString('bos-kategori', $html);
        $this->assertMatchesRegularExpression('/rv-vhl-chip[^"]*is-active/', $html);
    }

    public function test_renders_nothing_without_terms_or_off_screen(): void
    {
        ob_start();
        VehicleColumns::category_chips();
        $this->assertSame('', ob_get_clean(), 'No terms in use → no strip at all');

        global $pagenow;
        $pagenow = 'index.php';
        ob_start();
        VehicleColumns::category_chips();
        $this->assertSame('', ob_get_clean());
    }
}
