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
        'service_areas',
        'vehicle_year',
    );

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
