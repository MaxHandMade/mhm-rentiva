<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Profile;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Vendor\Profile\VendorProfileProvider;

/**
 * @group vendor-profile
 * @group vendor-provider
 */
final class VendorProfileProviderTest extends \WP_UnitTestCase
{
    private function make_active_vendor(string $slug, array $extra = []): int
    {
        $user_id = self::factory()->user->create($extra);
        update_user_meta($user_id, MetaKeys::VENDOR_SLUG, $slug);
        update_user_meta($user_id, '_rentiva_vendor_status', 'active');
        update_user_meta($user_id, '_rentiva_vendor_approved_at', gmdate('Y-m-d H:i:s'));
        return $user_id;
    }

    // ── Task 4.1: lookup_by_slug ─────────────────────────────────

    public function test_lookup_by_slug_returns_active_vendor_id(): void
    {
        $user_id = $this->make_active_vendor('akif-otomotiv');

        $found = VendorProfileProvider::lookup_by_slug('akif-otomotiv');

        $this->assertSame($user_id, $found);
    }

    public function test_lookup_returns_zero_when_slug_not_found(): void
    {
        $this->assertSame(0, VendorProfileProvider::lookup_by_slug('nonexistent'));
    }

    public function test_lookup_returns_zero_when_status_not_active(): void
    {
        $user_id = $this->make_active_vendor('suspended-vendor');
        update_user_meta($user_id, '_rentiva_vendor_status', 'suspended');

        $this->assertSame(0, VendorProfileProvider::lookup_by_slug('suspended-vendor'));
    }

    public function test_lookup_returns_zero_for_empty_slug(): void
    {
        $this->assertSame(0, VendorProfileProvider::lookup_by_slug(''));
    }

    public function test_lookup_returns_zero_when_status_meta_missing(): void
    {
        $user_id = self::factory()->user->create();
        update_user_meta($user_id, MetaKeys::VENDOR_SLUG, 'orphan');
        // No _rentiva_vendor_status meta

        $this->assertSame(0, VendorProfileProvider::lookup_by_slug('orphan'));
    }

    // ── Task 4.2: get_profile_data ──────────────────────────────

    public function test_get_profile_data_returns_identity_fields(): void
    {
        $user_id = $this->make_active_vendor('akif-otomotiv', ['display_name' => 'Akif Otomotiv']);
        update_user_meta($user_id, '_rentiva_vendor_city', 'Antalya');
        update_user_meta($user_id, '_rentiva_vendor_bio', 'Trusted local rental, est. 2018.');

        $data = VendorProfileProvider::get_profile_data($user_id);

        $this->assertSame($user_id, $data['user_id']);
        $this->assertSame('Akif Otomotiv', $data['display_name']);
        $this->assertSame('akif-otomotiv', $data['slug']);
        $this->assertSame('Antalya', $data['city']);
        $this->assertSame('Trusted local rental, est. 2018.', $data['bio']);
    }

    public function test_get_profile_data_excludes_sensitive_fields(): void
    {
        $user_id = $this->make_active_vendor('akif-sensitive');
        update_user_meta($user_id, '_rentiva_vendor_phone', '+90-555-0000');
        update_user_meta($user_id, '_rentiva_vendor_iban', 'TR00111122223333');
        update_user_meta($user_id, '_rentiva_vendor_tax_number', '12345678901');

        $data       = VendorProfileProvider::get_profile_data($user_id);
        $serialized = wp_json_encode($data);

        $this->assertStringNotContainsString('+90-555-0000', $serialized);
        $this->assertStringNotContainsString('TR00111122223333', $serialized);
        $this->assertStringNotContainsString('12345678901', $serialized);
    }

    public function test_get_profile_data_caches_in_transient(): void
    {
        $user_id = $this->make_active_vendor('akif-cached');

        // Default cache key now includes max_vehicles/max_reviews so per-shortcode
        // caps don't pollute each other (defensive fix following Phase 6 review).
        $key = VendorProfileProvider::TRANSIENT_PREFIX . $user_id . '_6_10';
        delete_transient($key);

        VendorProfileProvider::get_profile_data($user_id);

        $cached = get_transient($key);
        $this->assertIsArray($cached);
        $this->assertSame($user_id, $cached['user_id']);
    }

    public function test_get_profile_data_returns_empty_array_for_inactive_vendor(): void
    {
        $user_id = $this->make_active_vendor('inactive-vendor');
        update_user_meta($user_id, '_rentiva_vendor_status', 'suspended');

        $data = VendorProfileProvider::get_profile_data($user_id);

        $this->assertSame([], $data);
    }

    // ── Task 4.3: vehicles + aggregate_rating + reviews ─────────

    public function test_collect_vehicles_only_active_lifecycle(): void
    {
        $user_id = $this->make_active_vendor('akif-fleet');

        $active = self::factory()->post->create([
            'post_type'   => 'vehicle',
            'post_author' => $user_id,
            'post_status' => 'publish',
        ]);
        update_post_meta($active, '_mhm_vehicle_lifecycle_status', 'active');
        update_post_meta($active, '_mhm_rentiva_rating_average', 4.5);

        $withdrawn = self::factory()->post->create([
            'post_type'   => 'vehicle',
            'post_author' => $user_id,
            'post_status' => 'publish',
        ]);
        update_post_meta($withdrawn, '_mhm_vehicle_lifecycle_status', 'withdrawn');

        $data         = VendorProfileProvider::get_profile_data($user_id);
        $vehicle_ids  = array_column($data['vehicles'], 'id');

        $this->assertContains($active, $vehicle_ids);
        $this->assertNotContains($withdrawn, $vehicle_ids);
    }

    public function test_aggregate_rating_weighted_by_review_count(): void
    {
        $user_id = $this->make_active_vendor('akif-rated');

        $v1 = self::factory()->post->create([
            'post_type'   => 'vehicle',
            'post_author' => $user_id,
            'post_status' => 'publish',
        ]);
        update_post_meta($v1, '_mhm_vehicle_lifecycle_status', 'active');
        update_post_meta($v1, '_mhm_rentiva_rating_average', 5.0);
        update_post_meta($v1, '_mhm_rentiva_rating_count', 10);

        $v2 = self::factory()->post->create([
            'post_type'   => 'vehicle',
            'post_author' => $user_id,
            'post_status' => 'publish',
        ]);
        update_post_meta($v2, '_mhm_vehicle_lifecycle_status', 'active');
        update_post_meta($v2, '_mhm_rentiva_rating_average', 3.0);
        update_post_meta($v2, '_mhm_rentiva_rating_count', 30);

        $data = VendorProfileProvider::get_profile_data($user_id);

        // Weighted: (5*10 + 3*30) / 40 = 140/40 = 3.5
        $this->assertEqualsWithDelta(3.5, $data['rating']['average'], 0.01);
        $this->assertSame(40, $data['rating']['count']);
    }

    // ── Reviewer-driven defansif tests ───────────────────────────

    /**
     * Reviewer Y-1: mb_substr on raw HTML cut mid-tag, leaving broken markup
     * (`...aaa<stro`). Fix strips tags before truncating long bios.
     */
    public function test_long_bio_truncation_does_not_emit_unclosed_tags(): void
    {
        $user_id = $this->make_active_vendor('akif-long-bio');
        // 495 chars of plain padding + a strong tag whose opener crosses the
        // 500-char boundary. mb_substr would land mid-tag.
        $padding = str_repeat('a', 495);
        $bio     = $padding . '<strong>kapanmamis-tag</strong>';
        update_user_meta($user_id, '_rentiva_vendor_bio', $bio);

        $data = VendorProfileProvider::get_profile_data($user_id);

        $this->assertStringNotContainsString('<stro', $data['bio']);
        $this->assertStringEndsWith('…', $data['bio']);
        // Plaintext fallback drops formatting tags entirely on overflow.
        $this->assertStringNotContainsString('<strong>', $data['bio']);
    }

    /**
     * Reviewer O-3: collect_vehicles must honor its limit even when the
     * vendor owns more active vehicles than the cap.
     *
     * Lite mode caps total vehicle creation at 5; this test temporarily
     * raises that cap so the provider's own `posts_per_page = 6` can be
     * exercised against an oversized fleet.
     */
    public function test_collect_vehicles_caps_at_six_active_vehicles(): void
    {
        add_filter('mhm_rentiva_lite_max_vehicles', static fn() => 100);

        $user_id = $this->make_active_vendor('akif-large-fleet');

        for ($i = 0; $i < 8; $i++) {
            $vid = self::factory()->post->create([
                'post_type'   => 'vehicle',
                'post_author' => $user_id,
                'post_status' => 'publish',
            ]);
            update_post_meta($vid, '_mhm_vehicle_lifecycle_status', 'active');
            // Rating meta required because collect_vehicles orders by
            // meta_value_num against this key (implicit "has rating" JOIN).
            update_post_meta($vid, '_mhm_rentiva_rating_average', 4.0);
        }

        $data = VendorProfileProvider::get_profile_data($user_id);

        $this->assertCount(6, $data['vehicles']);
    }

    /**
     * Reviewer O-5: get_profile_data must short-circuit on $user_id <= 0
     * without hitting the database.
     */
    public function test_get_profile_data_returns_empty_for_zero_user_id(): void
    {
        $this->assertSame([], VendorProfileProvider::get_profile_data(0));
        $this->assertSame([], VendorProfileProvider::get_profile_data(-5));
    }

    public function test_collect_reviews_returns_at_most_10_with_vehicle_attribution(): void
    {
        $user_id = $this->make_active_vendor('akif-reviewed');

        $vehicle_id = self::factory()->post->create([
            'post_type'   => 'vehicle',
            'post_author' => $user_id,
            'post_status' => 'publish',
            'post_title'  => 'Mercedes Vito',
        ]);
        update_post_meta($vehicle_id, '_mhm_vehicle_lifecycle_status', 'active');

        for ($i = 0; $i < 12; $i++) {
            self::factory()->comment->create([
                'comment_post_ID'  => $vehicle_id,
                'comment_type'     => 'review',
                'comment_approved' => 1,
                'comment_content'  => 'Review ' . $i,
            ]);
        }

        $data = VendorProfileProvider::get_profile_data($user_id);

        $this->assertCount(10, $data['reviews']);
        $this->assertSame($vehicle_id, $data['reviews'][0]['vehicle_id']);
        $this->assertSame('Mercedes Vito', $data['reviews'][0]['vehicle_title']);
    }
}
