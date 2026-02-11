<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Hooks;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Vehicle\Helpers\RatingHelper;
use MHMRentiva\Admin\Vehicle\Helpers\VerifiedReviewHelper;
use MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList;
use MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid;
use MHMRentiva\Admin\Frontend\Shortcodes\FeaturedVehicles;
use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;

/**
 * Class ReviewNormalization
 *
 * Enforces 'review' comment type for rated comments and handles cache invalidation.
 * Critical for ensuring rating consistency and immediate frontend updates.
 *
 * @package MHMRentiva\Admin\Vehicle\Hooks
 * @since 1.2.0
 */
class ReviewNormalization
{
    /**
     * Register hooks
     */
    public static function register(): void
    {
        // Hook into comment lifecycle events to ensure normalization and cache clearing
        // priority 10 is standard, args 3 for comment_post/transition_comment_status
        add_action('comment_post', array(self::class, 'handle_comment_post'), 10, 3);
        add_action('edit_comment', array(self::class, 'handle_comment_edit'), 10, 2);
        add_action('transition_comment_status', array(self::class, 'handle_status_transition'), 10, 3);

        // Also hook into meta update just in case rating is added AFTER comment creation
        add_action('updated_comment_meta', array(self::class, 'handle_meta_update'), 10, 4);
        add_action('added_comment_meta', array(self::class, 'handle_meta_update'), 10, 4);
    }

    /**
     * Handle new comment creation
     *
     * @param int $comment_ID Comment ID.
     * @param int|string $comment_approved Comment approval status.
     * @param array $commentdata Comment data.
     */
    public static function handle_comment_post(int $comment_ID, $comment_approved, array $commentdata): void
    {
        self::process_comment($comment_ID);
    }

    /**
     * Handle comment edit
     * 
     * @param int $comment_ID Comment ID.
     * @param array $data Comment data.
     */
    public static function handle_comment_edit(int $comment_ID, array $data): void
    {
        self::process_comment($comment_ID);
    }

    /**
     * Handle comment status transition (e.g. approve/unapprove/spam/trash)
     *
     * @param string $new_status New comment status.
     * @param string $old_status Old comment status.
     * @param object $comment Comment object.
     */
    public static function handle_status_transition(string $new_status, string $old_status, object $comment): void
    {
        if ($new_status !== $old_status) {
            self::process_comment((int) $comment->comment_ID);
        }
    }

    /**
     * Handle comment meta updates (specifically mhm_rating)
     * 
     * @param int $meta_id
     * @param int $comment_id
     * @param string $meta_key
     * @param mixed $meta_value
     */
    public static function handle_meta_update(int $meta_id, int $comment_id, string $meta_key, $meta_value): void
    {
        if ($meta_key === 'mhm_rating') {
            self::process_comment($comment_id);
        }
    }

    /**
     * Core Logic: Normalize Type, Recalculate, Invalidate Cache
     *
     * @param int $comment_ID
     */
    private static function process_comment(int $comment_ID): void
    {
        $comment = get_comment($comment_ID);
        if (! $comment) {
            return;
        }

        // 1. Check Context: Must be a vehicle
        $post_id = (int) $comment->comment_post_ID;
        if (get_post_type($post_id) !== 'vehicle') {
            return;
        }

        // 2. Get Meta: Check for rating
        $rating = (int) get_comment_meta($comment_ID, 'mhm_rating', true);

        // 3. Normalize: If rated but not 'review', force 'review'
        if ($rating > 0 && $comment->comment_type !== 'review') {
            global $wpdb;
            // Direct DB update to avoid infinite loops and hook recursion
            $wpdb->update(
                $wpdb->comments,
                array('comment_type' => 'review'),
                array('comment_ID' => $comment_ID),
                array('%s'),
                array('%d')
            );

            // Invalidate comment cache so subsequent get_comment() calls see the change
            clean_comment_cache($comment_ID);

            // Update the object in memory if needed (though we don't use it further)
            $comment->comment_type = 'review';
        }

        // 4. Recalculate: Update vehicle aggregate stats
        // Only if status is 'approve' or we just transitioned, 
        // but RatingHelper::recalculate_and_save queries all approved 'review' comments
        // so it's safe to call it anytime a comment changes to ensure accuracy.
        RatingHelper::recalculate_and_save($post_id);

        // 5. Invalidate verified review cache for this vehicle
        VerifiedReviewHelper::invalidate_cache($post_id);

        // 6. Invalidate Cache: Clear all shortcode caches
        self::invalidate_caches();
    }

    /**
     * Invalidate all relevant shortcode caches
     */
    private static function invalidate_caches(): void
    {
        // List and Grid are the main ones displaying cards with ratings
        if (class_exists(VehiclesList::class)) {
            VehiclesList::cleanup();
        }
        if (class_exists(VehiclesGrid::class)) {
            VehiclesGrid::cleanup();
        }
        if (class_exists(FeaturedVehicles::class)) {
            FeaturedVehicles::cleanup();
        }
        if (class_exists(SearchResults::class)) {
            SearchResults::cleanup();
        }

        // Note: Individual vehicle page cache is handled by Plugin::invalidate_cache_on_meta_update
        // when RatingHelper updates the _mhm_rentiva_rating_* meta.
    }
}
