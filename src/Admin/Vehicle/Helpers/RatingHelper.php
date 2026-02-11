<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class RatingHelper
 * 
 * Central/Canonical logic for Vehicle Ratings.
 * Standardizes meta keys:
 * - _mhm_rentiva_rating_average (float)
 * - _mhm_rentiva_rating_count (int)
 * 
 * Source of Truth for calculation:
 * - wp_comments (type='review', status='approve')
 * - comment_meta (key='mhm_rating')
 * 
 * @package MHMRentiva\Admin\Vehicle\Helpers
 */
class RatingHelper
{
    /**
     * Get the rating data for a vehicle from the cache (post meta).
     * 
     * @param int $vehicle_id
     * @return array { average: float, count: int, stars: string }
     */
    public static function get_rating(int $vehicle_id): array
    {
        $average    = (float) get_post_meta($vehicle_id, '_mhm_rentiva_rating_average', true);
        $count      = (int) get_post_meta($vehicle_id, '_mhm_rentiva_rating_count', true);
        $confidence = RatingConfidenceHelper::from_count($count);

        return array(
            'average'            => $average,
            'count'              => $count,
            'stars'              => self::get_star_html($average),
            'confidence_key'     => $confidence['key'],
            'confidence_label'   => $confidence['label'],
            'confidence_tooltip' => $confidence['tooltip'],
        );
    }

    /**
     * Recalculate average from approved comments and update post meta.
     * This is the "Writer" source of truth.
     * 
     * @param int $vehicle_id
     * @return void
     */
    public static function recalculate_and_save(int $vehicle_id): void
    {
        $comments = get_comments(array(
            'post_id' => $vehicle_id,
            'type'    => 'review',
            'status'  => 'approve',
        ));

        $total_rating = 0;
        $count        = count($comments);

        if ($count > 0) {
            foreach ($comments as $comment) {
                $rating = get_comment_meta($comment->comment_ID, 'mhm_rating', true);
                if ($rating) {
                    $total_rating += (float) $rating;
                }
            }
            $average = $total_rating / $count;
        } else {
            $average = 0;
        }

        $average = round($average, 1);

        // Update the Standard Canonical Keys
        update_post_meta($vehicle_id, '_mhm_rentiva_rating_average', $average);
        update_post_meta($vehicle_id, '_mhm_rentiva_rating_count', $count);
        update_post_meta(
            $vehicle_id,
            '_mhm_rentiva_confidence_score',
            RatingSortHelper::compute_score((float) $average, $count)
        );

        // Clean up legacy/typo keys if they exist
        delete_post_meta($vehicle_id, '_mhm_rentiva_average_rating');
    }

    /**
     * Generate Star HTML (Yellow, standard).
     * 
     * @param float $rating
     * @return string HTML
     */
    public static function get_star_html(float $rating): string
    {
        $stars = '';
        $rounded = round($rating);
        for ($i = 1; $i <= 5; $i++) {
            $class = ($i <= $rounded) ? 'rv-star filled' : 'rv-star';
            $stars .= '<span class="' . $class . '"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" /></svg></span>';
        }
        return $stars;
    }

    /**
     * Check if a user has already rated a vehicle.
     * 
     * @param int $vehicle_id
     * @param int $user_id
     * @return array|null Comment data if exists
     */
    public static function get_user_review(int $vehicle_id, int $user_id): ?array
    {
        if (! $user_id) {
            return null;
        }

        $comments = get_comments(array(
            'post_id' => $vehicle_id,
            'user_id' => $user_id, // This works for logged in users
            'type'    => 'review',
            'number'  => 1
        ));

        if (empty($comments)) {
            return null;
        }

        $c = $comments[0];
        return array(
            'rating'     => get_comment_meta($c->comment_ID, 'mhm_rating', true),
            'comment'    => $c->comment_content,
            'comment_id' => $c->comment_ID,
            'status'     => $c->comment_approved
        );
    }
}
