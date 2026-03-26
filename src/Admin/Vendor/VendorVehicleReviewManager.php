<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages vehicle review lifecycle: pending_review → approved | rejected.
 * Differentiates critical fields (require re-review) from minor fields (immediate update).
 */
final class VendorVehicleReviewManager
{
    /**
     * Fields that require admin re-review when changed on a published vehicle.
     */
    private const CRITICAL_FIELDS = array(
        'price_per_day',
        'service_type',
        'city',
        'year',
    );

    /**
     * Register hooks.
     */
    public static function register(): void
    {
        add_action('save_post_vehicle', array(self::class, 'handle_save_post'), 10, 1);
        add_action('transition_post_status', array(self::class, 'sync_review_on_publish'), 10, 3);
    }

    /**
     * When an admin publishes a pending vehicle, auto-set review status to approved.
     *
     * @param string   $new_status
     * @param string   $old_status
     * @param \WP_Post $post
     */
    public static function sync_review_on_publish(string $new_status, string $old_status, \WP_Post $post): void
    {
        if ($post->post_type !== 'vehicle') {
            return;
        }

        if ($new_status === 'publish' && $old_status !== 'publish') {
            $current = (string) get_post_meta($post->ID, '_vehicle_review_status', true);
            if ($current === 'pending_review' || $current === '') {
                update_post_meta($post->ID, '_vehicle_review_status', 'approved');
                delete_post_meta($post->ID, '_vehicle_rejection_note');
                do_action('mhm_rentiva_vehicle_approved', $post->ID, (int) $post->post_author);
            }
        }
    }

    /**
     * save_post_vehicle callback. Guards against autosave, non-vendor users, and non-owners.
     * Builds changed_fields from POST data and delegates to handle_vendor_edit().
     *
     * @param int $post_id
     */
    public static function handle_save_post(int $post_id): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Skip when the AJAX vehicle-update handler manages re-review itself.
        if (wp_doing_ajax()) {
            return;
        }

        if (!in_array('rentiva_vendor', (array) wp_get_current_user()->roles, true)) {
            return;
        }

        if ((int) get_post_field('post_author', $post_id) !== get_current_user_id()) {
            return;
        }

        // Build changed_fields from submitted POST keys that match known vehicle meta.
        $submitted = array_intersect_key(
            array_map('sanitize_text_field', (array) wp_unslash($_POST)), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            array_flip(self::CRITICAL_FIELDS)
        );

        self::handle_vendor_edit($post_id, $submitted);
    }

    /**
     * Approve a vehicle: publish it and mark as approved.
     *
     * @param  int $vehicle_id
     * @return true|\WP_Error
     */
    public static function approve(int $vehicle_id)
    {
        $post = get_post($vehicle_id);
        if (!$post || $post->post_type !== 'vehicle') {
            return new \WP_Error('invalid_vehicle', __('Invalid vehicle ID.', 'mhm-rentiva'));
        }

        $result = wp_update_post(array('ID' => $vehicle_id, 'post_status' => 'publish'), true);
        if (is_wp_error($result)) {
            return $result;
        }

        update_post_meta($vehicle_id, '_vehicle_review_status', 'approved');
        delete_post_meta($vehicle_id, '_vehicle_rejection_note');

        do_action('mhm_rentiva_vehicle_approved', $vehicle_id, (int) $post->post_author);

        return true;
    }

    /**
     * Reject a vehicle with a reason.
     *
     * @param  int    $vehicle_id
     * @param  string $reason
     * @return true|\WP_Error
     */
    public static function reject(int $vehicle_id, string $reason)
    {
        $post = get_post($vehicle_id);
        if (!$post || $post->post_type !== 'vehicle') {
            return new \WP_Error('invalid_vehicle', __('Invalid vehicle ID.', 'mhm-rentiva'));
        }

        $sanitized_reason = sanitize_textarea_field($reason);
        update_post_meta($vehicle_id, '_vehicle_review_status', 'rejected');
        update_post_meta($vehicle_id, '_vehicle_rejection_note', $sanitized_reason);

        do_action('mhm_rentiva_vehicle_rejected', $vehicle_id, (int) $post->post_author, $sanitized_reason);

        return true;
    }

    /**
     * Handle a vendor edit on a published vehicle.
     * Critical fields → pending_review; minor fields → immediate update.
     *
     * @param int   $vehicle_id
     * @param array $changed_fields Keys of fields being changed.
     */
    public static function handle_vendor_edit(int $vehicle_id, array $changed_fields): void
    {
        $post = get_post($vehicle_id);
        if (!$post || $post->post_status !== 'publish') {
            return;
        }

        foreach (array_keys($changed_fields) as $field) {
            if (self::is_critical_field($field)) {
                update_post_meta($vehicle_id, '_vehicle_review_status', 'pending_review');
                do_action('mhm_rentiva_vehicle_needs_rereview', $vehicle_id);
                return;
            }
        }
    }

    /**
     * Check if a field requires admin re-review when changed.
     */
    public static function is_critical_field(string $field_name): bool
    {
        return in_array($field_name, self::CRITICAL_FIELDS, true);
    }
}
