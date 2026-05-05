<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Profile;

use MHMRentiva\Admin\Vendor\Profile\VendorProfileSeo;
use MHMRentiva\Admin\Vendor\Profile\VendorProfileRewrite;

/**
 * v4.37.2 regression: vendor profile SEO defaults (page title + meta
 * description) are emitted only when no real SEO plugin is active and
 * may be globally disabled with a filter.
 *
 * @group vendor-profile
 * @group vendor-seo
 */
final class VendorProfileSeoTest extends \WP_UnitTestCase
{
    public function test_word_boundary_trim_keeps_short_string_unchanged(): void
    {
        $this->assertSame(
            'Antalya merkezli filo.',
            VendorProfileSeo::trim_at_word_boundary('Antalya merkezli filo.', 155)
        );
    }

    public function test_word_boundary_trim_clips_long_string_at_space_with_ellipsis(): void
    {
        $bio     = str_repeat('Antalya merkezli filo, havalimanı transferleri uzmanı. ', 5);
        $clipped = VendorProfileSeo::trim_at_word_boundary($bio, 80);

        $this->assertStringEndsWith('…', $clipped);
        $this->assertLessThanOrEqual(85, mb_strlen($clipped, 'UTF-8'), 'Length must respect the requested limit (+ trailing ellipsis).');
        // The trim must not break a word in half: the last alphabetic run
        // before the ellipsis must be a real word from the source. Strip
        // the ellipsis and any trailing punctuation, then confirm the final
        // word appears verbatim somewhere in the source.
        $stripped = rtrim($clipped, "…,;:.\"' \t");
        $words    = preg_split('/\s+/u', trim($stripped));
        $last     = (string) end($words);
        $this->assertStringContainsString($last, $bio, 'Final word must exist verbatim in the source string.');
    }

    public function test_word_boundary_trim_collapses_whitespace(): void
    {
        $bio = "  Birinci  satır.\n\nİkinci   satır.  ";
        $clipped = VendorProfileSeo::trim_at_word_boundary($bio, 200);
        $this->assertSame('Birinci satır. İkinci satır.', $clipped);
    }

    public function test_seo_filter_disable_skips_registration(): void
    {
        add_filter('mhm_rentiva_vendor_profile_seo_disable', '__return_true');

        VendorProfileSeo::register();

        $this->assertFalse(has_filter('document_title_parts', [VendorProfileSeo::class, 'filter_title']));
        $this->assertFalse(has_action('wp_head', [VendorProfileSeo::class, 'emit_meta_description']));
    }

    public function test_filter_title_replaces_title_when_vendor_query_var_is_set(): void
    {
        $vendor_id = self::factory()->user->create([
            'display_name' => 'Akif Yıldız',
            'role'         => 'rentiva_vendor',
        ]);
        update_user_meta($vendor_id, '_rentiva_vendor_status', 'active');
        update_user_meta($vendor_id, '_rentiva_vendor_slug', 'akif-yildiz');

        set_query_var(VendorProfileRewrite::QUERY_VAR, 'akif-yildiz');
        \MHMRentiva\Admin\Vendor\Profile\VendorProfileProvider::clear_cache_for_user($vendor_id);

        $parts = ['title' => 'Default Theme Title', 'site' => 'Test Site'];
        $out   = VendorProfileSeo::filter_title($parts);

        $this->assertSame('Akif Yıldız', $out['title']);
        $this->assertSame('Test Site', $out['site']);

        set_query_var(VendorProfileRewrite::QUERY_VAR, '');
    }

    public function test_filter_title_passthrough_when_no_vendor_query_var(): void
    {
        set_query_var(VendorProfileRewrite::QUERY_VAR, '');
        $parts = ['title' => 'Default Theme Title'];
        $out   = VendorProfileSeo::filter_title($parts);
        $this->assertSame('Default Theme Title', $out['title']);
    }
}
