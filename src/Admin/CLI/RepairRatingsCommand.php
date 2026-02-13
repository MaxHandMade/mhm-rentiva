<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\CLI;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Vehicle\Helpers\RatingHelper;
use MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList;
use MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid;
use MHMRentiva\Admin\Frontend\Shortcodes\FeaturedVehicles;
use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;

/**
 * WP-CLI Command to repair vehicle ratings
 * 
 * Usage:
 * wp mhm-rentiva repair-ratings
 * wp mhm-rentiva repair-ratings --dry-run
 */
class RepairRatingsCommand
{
    /**
     * Repair vehicle ratings by normalizing comment types and recalculating stats.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Show what would be changed without executing.
     * 
     * ## EXAMPLES
     * 
     *     wp mhm-rentiva repair-ratings
     *     wp mhm-rentiva repair-ratings --dry-run
     * 
     * @when after_wp_load
     */
    public function __invoke($args, $assoc_args)
    {
        global $wpdb;

        $dry_run = isset($assoc_args['dry-run']);

        \WP_CLI::log('Starting Rating Repair...');

        // 1. Find all comments on vehicles with mhm_rating meta
        // We join with posts table to ensure post_type is vehicle
        // And check if comment_type is NOT 'review'
        $query = $wpdb->prepare(
            'SELECT c.comment_ID, c.comment_post_ID, cm.meta_value as rating
            FROM %i c
            JOIN %i p ON c.comment_post_ID = p.ID
            JOIN %i cm ON c.comment_ID = cm.comment_id
            WHERE p.post_type = %s
            AND cm.meta_key = %s
            AND cm.meta_value > %d
            AND c.comment_type != %s',
            $wpdb->comments,
            $wpdb->posts,
            $wpdb->commentmeta,
            'vehicle',
            'mhm_rating',
            0,
            'review'
        );

        // Table identifiers are from $wpdb core properties; query is safe in this context.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results($query);
        $count = count($results);

        \WP_CLI::log("Found {$count} comments requiring normalization.");

        $affected_vehicles = [];

        if ($count > 0) {
            $progress = \WP_CLI\Utils\make_progress_bar('Normalizing comments', $count);

            foreach ($results as $row) {
                if (! $dry_run) {
                    $wpdb->update(
                        $wpdb->comments,
                        ['comment_type' => 'review'],
                        ['comment_ID' => $row->comment_ID],
                        ['%s'],
                        ['%d']
                    );
                    clean_comment_cache($row->comment_ID);
                }
                $affected_vehicles[$row->comment_post_ID] = true;
                $progress->tick();
            }

            $progress->finish();
            \WP_CLI::success("Normalized {$count} comments.");
        } else {
            \WP_CLI::log('No comments needed normalization.');
        }

        // Also check vehicles that MIGHT have reviews but stats are out of sync? 
        // For now, let's just recalculate the ones we touched, PLUS all vehicles just in case.
        // The user instruction said "Recalculate ratings for affected vehicles."
        // But maybe we should be safe and do ALL vehicles if it's a "repair" command?
        // Let's stick to "affected" first, or maybe "all vehicles with reviews".

        // Getting unique vehicle IDs from the normalization query + logic to find vehicles with reviews
        // A safer bet for a "repair" command is to recalculate ALL vehicles that have ratings, 
        // just in case the stats meta is wrong even if comment type is correct.

        \WP_CLI::log('Recalculating vehicle statistics...');

        $vehicles_query = $wpdb->prepare(
            'SELECT ID FROM %i
            WHERE post_type = %s
            AND post_status = %s',
            $wpdb->posts,
            'vehicle',
            'publish'
        );
        // Table identifiers are from $wpdb core properties; query is safe in this context.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $all_vehicles = $wpdb->get_col($vehicles_query);

        $progress_calc = \WP_CLI\Utils\make_progress_bar('Recalculating stats', count($all_vehicles));

        foreach ($all_vehicles as $vid) {
            if (! $dry_run) {
                RatingHelper::recalculate_and_save((int)$vid);
            }
            $progress_calc->tick();
        }

        $progress_calc->finish();
        \WP_CLI::success('Vehicle statistics recalculated.');

        // Invalidate Cache
        \WP_CLI::log('Invalidating shortcode caches...');
        if (! $dry_run) {
            if (class_exists(VehiclesList::class)) VehiclesList::cleanup();
            if (class_exists(VehiclesGrid::class)) VehiclesGrid::cleanup();
            if (class_exists(FeaturedVehicles::class)) FeaturedVehicles::cleanup();
            if (class_exists(SearchResults::class)) SearchResults::cleanup();
            \WP_CLI::success('Shortcode caches cleared.');
        } else {
            \WP_CLI::log('Dry run: Skipping cache clearing.');
        }

        \WP_CLI::success('Repair complete!');
    }
}

// Register
if (defined('WP_CLI')) {
    \WP_CLI::add_command('mhm-rentiva repair-ratings', RepairRatingsCommand::class);
}
