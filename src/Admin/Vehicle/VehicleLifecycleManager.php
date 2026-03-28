<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\MetaKeys;

/**
 * Manages vehicle lifecycle state transitions with validation and side effects.
 *
 * All state changes go through this manager to enforce:
 * - Transition rules (VehicleLifecycleStatus FSM)
 * - Business rules (cooldown, pause limits, active bookings)
 * - Side effects (post_status change, meta updates, hooks)
 *
 * @since 4.24.0
 */
final class VehicleLifecycleManager
{
    /**
     * Register hooks that trigger lifecycle transitions.
     */
    public static function register(): void
    {
        // When a vehicle is approved (admin or auto-publish), activate its lifecycle.
        add_action('mhm_rentiva_vehicle_approved', array(self::class, 'on_vehicle_approved'), 10, 2);
    }

    /**
     * Callback for vehicle approval — start the listing lifecycle.
     *
     * @param int $vehicle_id Vehicle post ID.
     * @param int $vendor_id  Vendor user ID.
     */
    public static function on_vehicle_approved(int $vehicle_id, int $vendor_id): void
    {
        $current = VehicleLifecycleStatus::get($vehicle_id);

        // Only activate if the vehicle is in pending_review or has no lifecycle meta yet.
        // Skip if already active (e.g. re-publish of an already active vehicle).
        if ($current === VehicleLifecycleStatus::ACTIVE) {
            return;
        }

        // Set to pending_review first if not already set, so the FSM transition is valid.
        if ($current !== VehicleLifecycleStatus::PENDING_REVIEW) {
            update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::PENDING_REVIEW);
        }

        self::activate($vehicle_id);
    }

    /**
     * Activate a vehicle (admin approval or renewal).
     * Sets lifecycle to active, starts listing timer.
     *
     * @param int  $vehicle_id Vehicle post ID.
     * @param bool $reset_timer Whether to (re)set the 90-day listing timer.
     * @return true|\WP_Error
     */
    public static function activate(int $vehicle_id, bool $reset_timer = true)
    {
        $current = VehicleLifecycleStatus::get($vehicle_id);

        if ($current === VehicleLifecycleStatus::ACTIVE) {
            return new \WP_Error('already_active', __('Vehicle is already active.', 'mhm-rentiva'));
        }

        if (! VehicleLifecycleStatus::can_transition($current, VehicleLifecycleStatus::ACTIVE)) {
            return new \WP_Error(
                'invalid_transition',
                sprintf(__('Cannot activate vehicle from "%s" state.', 'mhm-rentiva'), $current)
            );
        }

        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::ACTIVE);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_STATUS, 'active');

        if ($reset_timer) {
            $now     = gmdate('Y-m-d H:i:s');
            $expires = gmdate('Y-m-d H:i:s', strtotime('+' . VehicleLifecycleStatus::LISTING_DURATION_DAYS . ' days'));
            update_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_STARTED_AT, $now);
            update_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, $expires);
        }

        // Ensure post is published.
        $post = get_post($vehicle_id);
        if ($post && $post->post_status !== 'publish') {
            wp_update_post(array(
                'ID'          => $vehicle_id,
                'post_status' => 'publish',
            ));
        }

        $vendor_id = (int) get_post_field('post_author', $vehicle_id);
        $old_status = $current;

        do_action('mhm_rentiva_vehicle_lifecycle_changed', $vehicle_id, $old_status, VehicleLifecycleStatus::ACTIVE);

        return true;
    }

    /**
     * Pause a vehicle (vendor self-service).
     *
     * @param int $vehicle_id Vehicle post ID.
     * @param int $vendor_id  Vendor user ID (for ownership verification).
     * @return true|\WP_Error
     */
    public static function pause(int $vehicle_id, int $vendor_id)
    {
        $ownership = self::verify_ownership($vehicle_id, $vendor_id);
        if (is_wp_error($ownership)) {
            return $ownership;
        }

        $current = VehicleLifecycleStatus::get($vehicle_id);

        if (! VehicleLifecycleStatus::can_transition($current, VehicleLifecycleStatus::PAUSED)) {
            return new \WP_Error(
                'invalid_transition',
                sprintf(__('Cannot pause vehicle from "%s" state.', 'mhm-rentiva'), $current)
            );
        }

        // Check monthly pause limit.
        $pause_limit = self::check_pause_limit($vehicle_id);
        if (is_wp_error($pause_limit)) {
            return $pause_limit;
        }

        // Check for active bookings.
        $booking_check = self::check_active_bookings($vehicle_id);
        if (is_wp_error($booking_check)) {
            return $booking_check;
        }

        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::PAUSED);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_STATUS, 'inactive');
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_PAUSED_AT, gmdate('Y-m-d H:i:s'));
        self::increment_pause_count($vehicle_id);

        do_action('mhm_rentiva_vehicle_paused', $vehicle_id, $vendor_id);
        do_action('mhm_rentiva_vehicle_lifecycle_changed', $vehicle_id, $current, VehicleLifecycleStatus::PAUSED);

        return true;
    }

    /**
     * Resume a paused vehicle (vendor self-service).
     *
     * @param int $vehicle_id Vehicle post ID.
     * @param int $vendor_id  Vendor user ID.
     * @return true|\WP_Error
     */
    public static function resume(int $vehicle_id, int $vendor_id)
    {
        $ownership = self::verify_ownership($vehicle_id, $vendor_id);
        if (is_wp_error($ownership)) {
            return $ownership;
        }

        $current = VehicleLifecycleStatus::get($vehicle_id);

        if ($current !== VehicleLifecycleStatus::PAUSED) {
            return new \WP_Error('not_paused', __('Vehicle is not paused.', 'mhm-rentiva'));
        }

        // Check max pause duration.
        $paused_at = get_post_meta($vehicle_id, MetaKeys::VEHICLE_PAUSED_AT, true);
        if ($paused_at) {
            $paused_days = (int) ((time() - strtotime($paused_at)) / DAY_IN_SECONDS);
            if ($paused_days > VehicleLifecycleStatus::MAX_PAUSE_DURATION_DAYS) {
                return new \WP_Error(
                    'pause_expired',
                    __('Pause duration exceeded. Please withdraw and relist.', 'mhm-rentiva')
                );
            }
        }

        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::ACTIVE);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_STATUS, 'active');
        delete_post_meta($vehicle_id, MetaKeys::VEHICLE_PAUSED_AT);

        do_action('mhm_rentiva_vehicle_resumed', $vehicle_id, $vendor_id);
        do_action('mhm_rentiva_vehicle_lifecycle_changed', $vehicle_id, VehicleLifecycleStatus::PAUSED, VehicleLifecycleStatus::ACTIVE);

        return true;
    }

    /**
     * Withdraw a vehicle (vendor self-service, permanent removal).
     *
     * @param int $vehicle_id Vehicle post ID.
     * @param int $vendor_id  Vendor user ID.
     * @return true|\WP_Error
     */
    public static function withdraw(int $vehicle_id, int $vendor_id)
    {
        $ownership = self::verify_ownership($vehicle_id, $vendor_id);
        if (is_wp_error($ownership)) {
            return $ownership;
        }

        $current = VehicleLifecycleStatus::get($vehicle_id);

        if (! VehicleLifecycleStatus::can_transition($current, VehicleLifecycleStatus::WITHDRAWN)) {
            return new \WP_Error(
                'invalid_transition',
                sprintf(__('Cannot withdraw vehicle from "%s" state.', 'mhm-rentiva'), $current)
            );
        }

        // Block if vehicle has confirmed/in-progress bookings.
        $booking_check = self::check_active_bookings($vehicle_id);
        if (is_wp_error($booking_check)) {
            return $booking_check;
        }

        $now          = gmdate('Y-m-d H:i:s');
        $cooldown_end = gmdate('Y-m-d H:i:s', strtotime('+' . VehicleLifecycleStatus::WITHDRAWAL_COOLDOWN_DAYS . ' days'));

        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::WITHDRAWN);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_STATUS, 'inactive');
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_WITHDRAWN_AT, $now);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_COOLDOWN_ENDS_AT, $cooldown_end);

        // Set post to draft.
        wp_update_post(array(
            'ID'          => $vehicle_id,
            'post_status' => 'draft',
        ));

        // Calculate progressive penalty based on withdrawal history.
        $penalty = PenaltyCalculator::calculate_withdrawal_penalty($vehicle_id, $vendor_id);
        do_action('mhm_rentiva_vehicle_withdrawn', $vehicle_id, $vendor_id, $penalty);
        do_action('mhm_rentiva_vehicle_lifecycle_changed', $vehicle_id, $current, VehicleLifecycleStatus::WITHDRAWN);

        return true;
    }

    /**
     * Expire a vehicle (called by cron when listing duration exceeded).
     *
     * @param int $vehicle_id Vehicle post ID.
     * @return true|\WP_Error
     */
    public static function expire(int $vehicle_id)
    {
        $current = VehicleLifecycleStatus::get($vehicle_id);

        if (! VehicleLifecycleStatus::can_transition($current, VehicleLifecycleStatus::EXPIRED)) {
            return new \WP_Error(
                'invalid_transition',
                sprintf(__('Cannot expire vehicle from "%s" state.', 'mhm-rentiva'), $current)
            );
        }

        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::EXPIRED);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_STATUS, 'inactive');

        $vendor_id = (int) get_post_field('post_author', $vehicle_id);

        do_action('mhm_rentiva_vehicle_expired', $vehicle_id, $vendor_id);
        do_action('mhm_rentiva_vehicle_lifecycle_changed', $vehicle_id, $current, VehicleLifecycleStatus::EXPIRED);

        return true;
    }

    /**
     * Renew an expired vehicle (vendor action, extends listing by 90 days).
     *
     * @param int $vehicle_id Vehicle post ID.
     * @param int $vendor_id  Vendor user ID.
     * @return true|\WP_Error
     */
    public static function renew(int $vehicle_id, int $vendor_id)
    {
        $ownership = self::verify_ownership($vehicle_id, $vendor_id);
        if (is_wp_error($ownership)) {
            return $ownership;
        }

        $current = VehicleLifecycleStatus::get($vehicle_id);

        if ($current !== VehicleLifecycleStatus::EXPIRED) {
            return new \WP_Error('not_expired', __('Only expired listings can be renewed.', 'mhm-rentiva'));
        }

        // Check if within grace period.
        $expires_at = get_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, true);
        if ($expires_at) {
            $days_since_expiry = (int) ((time() - strtotime($expires_at)) / DAY_IN_SECONDS);
            if ($days_since_expiry > VehicleLifecycleStatus::EXPIRY_GRACE_DAYS) {
                return new \WP_Error(
                    'grace_period_expired',
                    __('Renewal grace period has passed. Please relist the vehicle.', 'mhm-rentiva')
                );
            }
        }

        $now     = gmdate('Y-m-d H:i:s');
        $expires = gmdate('Y-m-d H:i:s', strtotime('+' . VehicleLifecycleStatus::LISTING_DURATION_DAYS . ' days'));

        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::ACTIVE);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_STATUS, 'active');
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_RENEWED_AT, $now);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_EXPIRES_AT, $expires);

        $count = (int) get_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_RENEWAL_CNT, true);
        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LISTING_RENEWAL_CNT, $count + 1);

        do_action('mhm_rentiva_vehicle_renewed', $vehicle_id);
        do_action('mhm_rentiva_vehicle_lifecycle_changed', $vehicle_id, VehicleLifecycleStatus::EXPIRED, VehicleLifecycleStatus::ACTIVE);

        return true;
    }

    /**
     * Relist a withdrawn vehicle (after cooldown, goes to pending review).
     *
     * @param int $vehicle_id Vehicle post ID.
     * @param int $vendor_id  Vendor user ID.
     * @return true|\WP_Error
     */
    public static function relist(int $vehicle_id, int $vendor_id)
    {
        $ownership = self::verify_ownership($vehicle_id, $vendor_id);
        if (is_wp_error($ownership)) {
            return $ownership;
        }

        $current = VehicleLifecycleStatus::get($vehicle_id);

        if ($current !== VehicleLifecycleStatus::WITHDRAWN) {
            return new \WP_Error('not_withdrawn', __('Only withdrawn vehicles can be relisted.', 'mhm-rentiva'));
        }

        // Cooldown check.
        $cooldown_ends = get_post_meta($vehicle_id, MetaKeys::VEHICLE_COOLDOWN_ENDS_AT, true);
        if ($cooldown_ends && strtotime($cooldown_ends) > time()) {
            $remaining = (int) ceil((strtotime($cooldown_ends) - time()) / DAY_IN_SECONDS);
            return new \WP_Error(
                'cooldown_active',
                sprintf(__('Cooldown period active. %d day(s) remaining.', 'mhm-rentiva'), $remaining)
            );
        }

        update_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::PENDING_REVIEW);
        update_post_meta($vehicle_id, '_vehicle_review_status', 'pending_review');

        wp_update_post(array(
            'ID'          => $vehicle_id,
            'post_status' => 'pending',
        ));

        // Clean up withdrawal meta.
        delete_post_meta($vehicle_id, MetaKeys::VEHICLE_WITHDRAWN_AT);
        delete_post_meta($vehicle_id, MetaKeys::VEHICLE_COOLDOWN_ENDS_AT);

        do_action('mhm_rentiva_vehicle_relist', $vehicle_id, $vendor_id);
        do_action('mhm_rentiva_vehicle_lifecycle_changed', $vehicle_id, VehicleLifecycleStatus::WITHDRAWN, VehicleLifecycleStatus::PENDING_REVIEW);

        return true;
    }

    /**
     * Verify that the vendor owns the vehicle.
     *
     * @return true|\WP_Error
     */
    private static function verify_ownership(int $vehicle_id, int $vendor_id)
    {
        $post = get_post($vehicle_id);
        if (! $post || $post->post_type !== 'vehicle') {
            return new \WP_Error('invalid_vehicle', __('Vehicle not found.', 'mhm-rentiva'));
        }

        if ((int) $post->post_author !== $vendor_id) {
            return new \WP_Error('not_owner', __('You do not own this vehicle.', 'mhm-rentiva'));
        }

        return true;
    }

    /**
     * Check if vehicle has active (confirmed/in-progress) bookings.
     *
     * @return true|\WP_Error True if no active bookings.
     */
    private static function check_active_bookings(int $vehicle_id)
    {
        $active_bookings = get_posts(array(
            'post_type'      => 'vehicle_booking',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'   => '_mhm_vehicle_id',
                    'value' => $vehicle_id,
                ),
                array(
                    'key'     => '_mhm_status',
                    'value'   => array('confirmed', 'in_progress'),
                    'compare' => 'IN',
                ),
            ),
        ));

        if (! empty($active_bookings)) {
            return new \WP_Error(
                'active_bookings_exist',
                __('Cannot change vehicle status while it has active bookings.', 'mhm-rentiva')
            );
        }

        return true;
    }

    /**
     * Check monthly pause limit for a vehicle.
     *
     * @return true|\WP_Error
     */
    private static function check_pause_limit(int $vehicle_id)
    {
        $current_month = gmdate('Y-m');
        $stored = get_post_meta($vehicle_id, '_mhm_vehicle_pause_count_month', true);

        if (is_string($stored) && strpos($stored, $current_month . ':') === 0) {
            $count = (int) substr($stored, strlen($current_month) + 1);
            if ($count >= VehicleLifecycleStatus::MAX_PAUSES_PER_MONTH) {
                return new \WP_Error(
                    'pause_limit_reached',
                    sprintf(
                        __('Monthly pause limit reached (%d/%d). Try again next month.', 'mhm-rentiva'),
                        $count,
                        VehicleLifecycleStatus::MAX_PAUSES_PER_MONTH
                    )
                );
            }
        }

        return true;
    }

    /**
     * Increment the monthly pause counter for a vehicle.
     */
    private static function increment_pause_count(int $vehicle_id): void
    {
        $current_month = gmdate('Y-m');
        $stored = get_post_meta($vehicle_id, '_mhm_vehicle_pause_count_month', true);
        $count = 0;

        if (is_string($stored) && strpos($stored, $current_month . ':') === 0) {
            $count = (int) substr($stored, strlen($current_month) + 1);
        }

        update_post_meta($vehicle_id, '_mhm_vehicle_pause_count_month', $current_month . ':' . ($count + 1));
    }
}
