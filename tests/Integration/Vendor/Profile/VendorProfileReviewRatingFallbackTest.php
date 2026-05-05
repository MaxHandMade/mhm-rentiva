<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Profile;

use MHMRentiva\Admin\Vendor\Profile\VendorProfileProvider;

/**
 * v4.37.1 defensive fallback: review rating resolves through `mhm_rating`
 * (Rentiva canonical) first, then `rating` (WC standard) as a fallback.
 *
 * Reasoning:
 *   `mhm_rating` is the canonical key set by VehicleRatingForm and the
 *   ReviewEnforcer normalization hook. Reviews submitted via that path
 *   continue to surface their stars unchanged. However, sites that import
 *   reviews from external sources (WC product reviews migrated into vehicle
 *   posts, Site Reviews / Customer Reviews for WooCommerce, manual database
 *   seeds, etc.) typically write the WC-standard `rating` meta key. Without
 *   this fallback those imported reviews show empty stars on the public
 *   vendor profile even though the meta is present.
 *
 * @group vendor-profile
 * @group vendor-reviews
 */
final class VendorProfileReviewRatingFallbackTest extends \WP_UnitTestCase
{
    public function test_resolves_canonical_mhm_rating_first(): void
    {
        $data = $this->build_profile_with_review([
            'mhm_rating' => 5,
            'rating'     => 3,
        ]);

        $this->assertNotEmpty($data['reviews']);
        $this->assertSame(5, $data['reviews'][0]['rating']);
    }

    public function test_falls_back_to_rating_meta_when_mhm_rating_absent(): void
    {
        $data = $this->build_profile_with_review([
            'rating' => 4,
        ]);

        $this->assertNotEmpty($data['reviews']);
        $this->assertSame(
            4,
            $data['reviews'][0]['rating'],
            'Provider must fall back to the WC-standard rating meta when mhm_rating is empty.'
        );
    }

    public function test_returns_zero_when_no_rating_meta_is_set(): void
    {
        $data = $this->build_profile_with_review([]);

        $this->assertNotEmpty($data['reviews']);
        $this->assertSame(0, $data['reviews'][0]['rating']);
    }

    /**
     * @param array<string,int> $meta Map of comment-meta key => value.
     * @return array<string,mixed>
     */
    private function build_profile_with_review(array $meta): array
    {
        $vendor_id = self::factory()->user->create();
        update_user_meta($vendor_id, '_rentiva_vendor_status', 'active');
        update_user_meta($vendor_id, '_rentiva_vendor_slug', 'rating-fallback-vendor-' . $vendor_id);

        $vehicle_id = wp_insert_post([
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $vendor_id,
            'post_title'  => 'Rating Fallback Vehicle',
        ]);
        update_post_meta($vehicle_id, '_mhm_vehicle_lifecycle_status', 'active');

        $comment_id = wp_insert_comment([
            'comment_post_ID'  => $vehicle_id,
            'comment_author'   => 'Test Reviewer',
            'comment_content'  => 'Rating fallback fixture review.',
            'comment_type'     => 'review',
            'comment_approved' => 1,
        ]);
        foreach ($meta as $key => $value) {
            update_comment_meta($comment_id, $key, $value);
        }

        VendorProfileProvider::clear_cache_for_user($vendor_id);

        return VendorProfileProvider::get_profile_data($vendor_id);
    }
}
