<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class Util
{
    /**
     * Converts date/time strings to timestamps in site timezone
     */
    public static function parse_datetimes(string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time): array|\WP_Error
    {
        try {
            $timezone = wp_timezone();
            
            // Date format check
            // Use default times if time values are empty
            if (empty($pickup_time)) {
                $pickup_time = apply_filters('mhm_rentiva_default_pickup_time', '10:00');
            }
            if (empty($dropoff_time)) {
                $dropoff_time = apply_filters('mhm_rentiva_default_dropoff_time', '10:00');
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $pickup_date) || 
                !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dropoff_date) ||
                !preg_match('/^\d{2}:\d{2}$/', $pickup_time) || 
                !preg_match('/^\d{2}:\d{2}$/', $dropoff_time)) {
                throw new \InvalidArgumentException(__('Invalid date/time format.', 'mhm-rentiva'));
            }
            
            // Create DateTime
            $pickup_string = $pickup_date . ' ' . $pickup_time;
            $dropoff_string = $dropoff_date . ' ' . $dropoff_time;
            
            $pickup_datetime = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $pickup_string, $timezone);
            $dropoff_datetime = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $dropoff_string, $timezone);
            
            if (!$pickup_datetime || !$dropoff_datetime) {
                throw new \InvalidArgumentException(__('Invalid date/time format.', 'mhm-rentiva'));
            }
            
            $start_ts = $pickup_datetime->getTimestamp();
            $end_ts = $dropoff_datetime->getTimestamp();
            
            // Date validation
            if ($end_ts <= $start_ts) {
                throw new \InvalidArgumentException(__('End date must be after start date.', 'mhm-rentiva'));
            }
            
            return [
                'start_ts' => $start_ts,
                'end_ts' => $end_ts,
            ];
            
        } catch (\Exception $e) {
            return new \WP_Error('invalid_datetime', __('Invalid date/time format.', 'mhm-rentiva'));
        }
    }

    /**
     * Calculates rental days
     */
    public static function rental_days(int $start_ts, int $end_ts): int
    {
        // Convert Unix timestamps to DateTime objects
        $start_date = new \DateTime();
        $start_date->setTimestamp($start_ts);
        
        $end_date = new \DateTime();
        $end_date->setTimestamp($end_ts);
        
        // Calculate date difference
        $interval = $start_date->diff($end_date);
        
        // Get number of days (only days, ignore hours)
        $days = $interval->days;
        
        // Minimum 1 day
        return max(1, $days);
    }

    /**
     * Calculates total price
     */
    public static function total_price(int $vehicle_id, int $days): float
    {
        $price_per_day = (float) get_post_meta($vehicle_id, '_mhm_rentiva_price_per_day', true);
        
        if ($price_per_day <= 0) {
            return 0.0;
        }
        
        return $price_per_day * $days;
    }

    /**
     * Checks for overlap in the specified date range for the vehicle
     */
    public static function has_overlap(int $vehicle_id, int $start_ts, int $end_ts): bool
    {
        // ⚡ Optimized: direct SQL query for faster checks
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_mhm_vehicle_id'
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_mhm_status'
            INNER JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_mhm_start_ts'
            INNER JOIN {$wpdb->postmeta} pm4 ON p.ID = pm4.post_id AND pm4.meta_key = '_mhm_end_ts'
            WHERE p.post_type = 'vehicle_booking' 
            AND p.post_status = 'publish'
            AND pm1.meta_value = %d
            AND pm2.meta_value IN ('pending', 'confirmed', 'in_progress')
            AND (
                (pm3.meta_value <= %d AND pm4.meta_value > %d) OR
                (pm3.meta_value < %d AND pm4.meta_value >= %d) OR
                (pm3.meta_value >= %d AND pm4.meta_value <= %d)
            )
        ", $vehicle_id, $start_ts, $start_ts, $end_ts, $end_ts, $start_ts, $end_ts));
        
        return (int) $result > 0;
    }

    /**
     * Atomic overlap check (with database lock)
     */
    public static function has_overlap_locked(int $vehicle_id, int $start_ts, int $end_ts): bool
    {
        global $wpdb;

        // Lock vehicle's postmeta records
        $wpdb->get_results($wpdb->prepare(
            "SELECT meta_id FROM {$wpdb->postmeta}
             WHERE post_id = %d AND meta_key LIKE %s
             FOR UPDATE",
            $vehicle_id,
            $wpdb->esc_like('_mhm_') . '%'
        ));

        // Conflict check with accurate date interval handling
        $overlap_query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm1
             INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
             INNER JOIN {$wpdb->postmeta} pm3 ON pm1.post_id = pm3.post_id
             INNER JOIN {$wpdb->postmeta} pm4 ON pm1.post_id = pm4.post_id
             INNER JOIN {$wpdb->posts} p ON pm1.post_id = p.ID
             WHERE p.post_type = 'vehicle_booking'
             AND p.post_status = 'publish'
             AND pm1.meta_key = '_mhm_vehicle_id' AND pm1.meta_value = %d
             AND pm2.meta_key = '_mhm_status' AND pm2.meta_value IN ('pending', 'confirmed', 'in_progress')
             AND pm3.meta_key = '_mhm_start_ts' AND pm4.meta_key = '_mhm_end_ts'
             AND (
                 (pm3.meta_value <= %d AND pm4.meta_value > %d) OR
                 (pm3.meta_value < %d AND pm4.meta_value >= %d) OR
                 (pm3.meta_value >= %d AND pm4.meta_value <= %d)
             )",
            $vehicle_id, $start_ts, $start_ts, $end_ts, $end_ts, $start_ts, $end_ts
        );

        $count = (int) $wpdb->get_var($overlap_query);
        return $count > 0;
    }

    /**
     * Checks vehicle availability status
     */
    public static function is_vehicle_available(int $vehicle_id): bool
    {
        // STANDART META KEY: _mhm_vehicle_availability
        $available = get_post_meta($vehicle_id, '_mhm_vehicle_availability', true);
        return $available === 'active';
    }

    /**
     * Checks availability and returns result (with caching)
     */
    public static function check_availability(int $vehicle_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time): array
    {
        // Validate vehicle existence
        if (get_post_type($vehicle_id) !== 'vehicle') {
            return [
                'ok' => false,
                'code' => 'vehicle_not_found',
                'message' => __('Selected vehicle not found. Please select a valid vehicle.', 'mhm-rentiva'),
            ];
        }

        // Validate vehicle availability status
        if (!self::is_vehicle_available($vehicle_id)) {
            return [
                'ok' => false,
                'code' => 'vehicle_unavailable',
                'message' => __('This vehicle is currently not available for rental. Please select another vehicle.', 'mhm-rentiva'),
            ];
        }

        // Parse date/time
        $datetime_result = self::parse_datetimes($pickup_date, $pickup_time, $dropoff_date, $dropoff_time);

        if (is_wp_error($datetime_result)) {
            return [
                'ok' => false,
                'code' => 'invalid_input',
                'message' => __('Invalid date selection. Please check your pickup and return dates.', 'mhm-rentiva'),
            ];
        }

        $start_ts = $datetime_result['start_ts'];
        $end_ts = $datetime_result['end_ts'];

        // Check from cache
        $cached_result = \MHMRentiva\Admin\Booking\Helpers\Cache::getAvailability($vehicle_id, $start_ts, $end_ts);
        if ($cached_result !== null) {
            return $cached_result;
        }

        // Overlap detection
        if (self::has_overlap($vehicle_id, $start_ts, $end_ts)) {
            $result = [
                'ok' => false,
                'code' => 'unavailable',
                'message' => __('This vehicle is already booked for the selected dates. Please choose different dates or select another vehicle.', 'mhm-rentiva'),
            ];
        } else {
            // Calculate days and pricing
            $days = self::rental_days($start_ts, $end_ts);
            $price_per_day = (float) get_post_meta($vehicle_id, '_mhm_rentiva_price_per_day', true);
            $total_price = self::total_price($vehicle_id, $days);

            $result = [
                'ok' => true,
                'code' => 'ok',
                'message' => __('✅ Great! This vehicle is available for your selected dates.', 'mhm-rentiva'),
                'days' => $days,
                'price_per_day' => $price_per_day,
                'total_price' => $total_price,
                'start_ts' => $start_ts,
                'end_ts' => $end_ts,
            ];
        }

        // Save result to cache
        \MHMRentiva\Admin\Booking\Helpers\Cache::setAvailability($vehicle_id, $start_ts, $end_ts, $result);

        return $result;
    }

    /**
     * Get alternative vehicle suggestions
     */
    public static function get_alternative_vehicles(int $original_vehicle_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time, int $limit = 2): array
    {
        try {
            // Parse date/time
            $datetime_result = self::parse_datetimes($pickup_date, $pickup_time, $dropoff_date, $dropoff_time);
            
            // WP_Error check
            if (is_wp_error($datetime_result)) {
                return [];
            }
            
            $start_ts = $datetime_result['start_ts'];
            $end_ts = $datetime_result['end_ts'];
        } catch (\InvalidArgumentException $e) {
            // Return empty array on date parse error
            return [];
        }

        // Get original vehicle information
        $original_vehicle = get_post($original_vehicle_id);
        if (!$original_vehicle) {
            return [];
        }

        $original_price = (float) get_post_meta($original_vehicle_id, '_mhm_rentiva_price_per_day', true);
        $original_features = get_post_meta($original_vehicle_id, '_mhm_rentiva_features', true);
        $original_features = is_array($original_features) ? $original_features : [];

        // Find available vehicles
        
        // ⚡ Optimized: fetch only active vehicles with a sane limit
        $all_vehicles = get_posts([
            'post_type' => 'vehicle',
            'post_status' => 'publish',
            'posts_per_page' => 20, // Limit to at most 20 vehicles
            'exclude' => [$original_vehicle_id],
            'meta_query' => [
                [
                    'key' => '_mhm_vehicle_availability',
                    'value' => 'active',
                    'compare' => '='
                ],
                [
                    'key' => '_mhm_rentiva_price_per_day',
                    'value' => 0,
                    'compare' => '>'
                ]
            ]
        ]);
        
        
        // ⚡ Optimized: meta query already filtered – use directly
        $available_vehicles = $all_vehicles;

        $alternatives = [];
        $days = self::rental_days($start_ts, $end_ts);

        // ⚡ Optimized: batch meta fetch to avoid N+1 queries
        $vehicle_ids = array_map(function($v) { return $v->ID; }, $available_vehicles);
        $vehicle_meta = [];
        
        if (!empty($vehicle_ids)) {
            global $wpdb;
            $ids_placeholder = implode(',', array_fill(0, count($vehicle_ids), '%d'));
            $meta_results = $wpdb->get_results($wpdb->prepare(
                "SELECT post_id, meta_key, meta_value 
                 FROM {$wpdb->postmeta} 
                 WHERE post_id IN ({$ids_placeholder})
                 AND meta_key IN ('_mhm_rentiva_price_per_day', '_mhm_rentiva_features')",
                $vehicle_ids
            ), ARRAY_A);
            
            // Organize meta
            foreach ($meta_results as $meta) {
                $vehicle_meta[$meta['post_id']][$meta['meta_key']] = $meta['meta_value'];
            }
        }

        foreach ($available_vehicles as $vehicle) {
            // Availability check for this vehicle
            $has_overlap = self::has_overlap($vehicle->ID, $start_ts, $end_ts);
            
            if (!$has_overlap) {
                // ⚡ Optimized: reuse batch meta result
                $price_per_day = (float) ($vehicle_meta[$vehicle->ID]['_mhm_rentiva_price_per_day'] ?? 0);
                $total_price = $price_per_day * $days;
                
                // Extract vehicle features from batch results
                $features_raw = $vehicle_meta[$vehicle->ID]['_mhm_rentiva_features'] ?? '';
                $features = [];
                
                if (is_array($features_raw)) {
                    $features = $features_raw;
                } elseif (is_string($features_raw) && !empty($features_raw)) {
                    // Unserialize if stored as serialized string
                    $unserialized = maybe_unserialize($features_raw);
                    $features = is_array($unserialized) ? $unserialized : [];
                }

                // Calculate similarity score
                $similarity_score = self::calculate_vehicle_similarity(
                    $original_features,
                    $features,
                    $original_price,
                    $price_per_day
                );

                $alternatives[] = [
                    'id' => $vehicle->ID,
                    'title' => $vehicle->post_title,
                    'excerpt' => $vehicle->post_excerpt,
                    'price_per_day' => $price_per_day,
                    'total_price' => $total_price,
                    'days' => $days,
                    'features' => $features,
                    'similarity_score' => $similarity_score,
                    'image' => get_the_post_thumbnail_url($vehicle->ID, 'medium'),
                    'currency_symbol' => \MHMRentiva\Admin\Reports\Reports::get_currency_symbol(),
                ];
            }
        }

        // Sort by similarity score (high to low)
        usort($alternatives, function($a, $b) {
            return $b['similarity_score'] <=> $a['similarity_score'];
        });

        // ⚡ Optimized: apply limit as early as possible
        return array_slice($alternatives, 0, min($limit, 5)); // Maksimum 5 alternatif
    }

    /**
     * Calculate vehicle similarity score
     */
    private static function calculate_vehicle_similarity(array $original_features, array $alternative_features, float $original_price, float $alternative_price): float
    {
        $score = 0;
        $max_score = 100;

        // Price similarity (40%)
        $price_diff = abs($original_price - $alternative_price);
        $price_score = max(0, 40 - ($price_diff / $original_price * 40));
        $score += $price_score;

        // Feature similarity (60%)
        if (!empty($original_features) && !empty($alternative_features)) {
            $common_features = array_intersect($original_features, $alternative_features);
            $feature_score = (count($common_features) / count($original_features)) * 60;
            $score += $feature_score;
        } else {
            $score += 30; // Default score
        }

        return min($score, $max_score);
    }

    /**
     * Advanced availability check (with alternative suggestions)
     */
    public static function check_availability_with_alternatives(int $vehicle_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time): array
    {
        // Normal availability check
        $availability_result = self::check_availability($vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time);

        // If vehicle is not available, add alternative suggestions
        
        if (!$availability_result['ok']) {
            
            $alternatives = self::get_alternative_vehicles($vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time);
            
            if (!empty($alternatives)) {
                $availability_result['alternatives'] = $alternatives;
                $availability_result['message'] = __('❌ Selected vehicle is not available, but we found similar vehicles for you:', 'mhm-rentiva');
            } else {
                $availability_result['message'] = __('❌ Sorry, no vehicles are available for the selected dates. Please try different dates.', 'mhm-rentiva');
            }
        }

        return $availability_result;
    }

    /**
     * Atomic availability check (with locking).
     */
    public static function check_availability_locked(int $vehicle_id, string $pickup_date, string $pickup_time, string $dropoff_date, string $dropoff_time): array
    {
        return \MHMRentiva\Admin\Booking\Helpers\Locker::withLock($vehicle_id, function() use ($vehicle_id, $pickup_date, $pickup_time, $dropoff_date, $dropoff_time) {
            // Validate vehicle existence
            if (get_post_type($vehicle_id) !== 'vehicle') {
                return [
                    'ok' => false,
                    'code' => 'vehicle_not_found',
                    'message' => __('Vehicle not found.', 'mhm-rentiva'),
                ];
            }

            // Validate vehicle availability status
            if (!self::is_vehicle_available($vehicle_id)) {
                return [
                    'ok' => false,
                    'code' => 'vehicle_unavailable',
                    'message' => __('Vehicle is currently not available for rental.', 'mhm-rentiva'),
                ];
            }

            // Parse date/time
            $datetime_result = self::parse_datetimes($pickup_date, $pickup_time, $dropoff_date, $dropoff_time);

            if (is_wp_error($datetime_result)) {
                return [
                    'ok' => false,
                    'code' => 'invalid_input',
                    'message' => $datetime_result->get_error_message(),
                ];
            }

            $start_ts = $datetime_result['start_ts'];
            $end_ts = $datetime_result['end_ts'];

            // Atomic overlap detection
            if (self::has_overlap_locked($vehicle_id, $start_ts, $end_ts)) {
                return [
                    'ok' => false,
                    'code' => 'unavailable',
                    'message' => __('Vehicle is not available in the selected date range.', 'mhm-rentiva'),
                ];
            }

            // Calculate rental days and pricing
            $days = self::rental_days($start_ts, $end_ts);
            $price_per_day = (float) get_post_meta($vehicle_id, '_mhm_rentiva_price_per_day', true);
            $total_price = self::total_price($vehicle_id, $days);

            return [
                'ok' => true,
                'code' => 'ok',
                'message' => __('Vehicle is available on selected dates.', 'mhm-rentiva'),
                'days' => $days,
                'price_per_day' => $price_per_day,
                'total_price' => $total_price,
                'start_ts' => $start_ts,
                'end_ts' => $end_ts,
            ];
        });
    }
}
