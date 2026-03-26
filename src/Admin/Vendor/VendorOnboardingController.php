<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Vendor\PostType\VendorApplication;



/**
 * Handles vendor application approval, rejection, and suspension.
 * Assigns roles, syncs user meta, and fires action hooks for email notifications.
 */
final class VendorOnboardingController
{
    /**
     * Approve a vendor application.
     * Assigns rentiva_vendor role, syncs meta to user, updates post status.
     * Fires: mhm_rentiva_vendor_approved( $user_id, $application_id )
     *
     * @param  int $application_id
     * @return true|\WP_Error
     */
    public static function approve(int $application_id)
    {
        $application = VendorApplicationManager::get_application($application_id);
        if (is_wp_error($application)) {
            return $application;
        }

        $user_id = (int) $application->post_author;
        $user    = get_userdata($user_id);

        if (!$user) {
            return new \WP_Error('invalid_user', __('Associated user not found.', 'mhm-rentiva'));
        }

        $user->add_role('rentiva_vendor');
        update_user_meta($user_id, '_rentiva_vendor_status', 'active');

        // Sync application meta to user meta for fast retrieval.
        $meta_map = array(
            '_vendor_phone'         => '_rentiva_vendor_phone',
            '_vendor_city'          => '_rentiva_vendor_city',
            '_vendor_iban'          => '_rentiva_vendor_iban',
            '_vendor_service_areas' => '_rentiva_vendor_service_areas',
            '_vendor_profile_bio'   => '_rentiva_vendor_bio',
            '_vendor_tax_number'    => '_rentiva_vendor_tax_number',
        );

        foreach ($meta_map as $post_key => $user_key) {
            $value = get_post_meta($application_id, $post_key, true);
            update_user_meta($user_id, $user_key, $value);
        }

        $now = current_time('mysql');
        update_post_meta($application_id, '_vendor_approved_at', $now);
        update_post_meta($application_id, '_vendor_approved_by', get_current_user_id());
        update_post_meta($application_id, '_vendor_status', VendorApplicationManager::STATUS_APPROVED);
        update_user_meta($user_id, '_rentiva_vendor_approved_at', $now);

        $update_result = wp_update_post(array(
            'ID'          => $application_id,
            'post_status' => VendorApplicationManager::STATUS_APPROVED,
        ), true);

        if (is_wp_error($update_result)) {
            return $update_result;
        }

        do_action('mhm_rentiva_vendor_approved', $user_id, $application_id);

        return true;
    }

    /**
     * Reject a vendor application.
     * Stores rejection note, updates status.
     * Fires: mhm_rentiva_vendor_rejected( $user_id, $application_id, $reason )
     *
     * @param  int    $application_id
     * @param  string $reason Admin's rejection note.
     * @return true|\WP_Error
     */
    public static function reject(int $application_id, string $reason)
    {
        $application = VendorApplicationManager::get_application($application_id);
        if (is_wp_error($application)) {
            return $application;
        }

        $user_id = (int) $application->post_author;

        $sanitized_reason = sanitize_textarea_field($reason);
        update_post_meta($application_id, '_vendor_rejection_note', $sanitized_reason);
        update_post_meta($application_id, '_vendor_status', VendorApplicationManager::STATUS_REJECTED);

        $update_result = wp_update_post(array(
            'ID'          => $application_id,
            'post_status' => VendorApplicationManager::STATUS_REJECTED,
        ), true);

        if (is_wp_error($update_result)) {
            return $update_result;
        }

        do_action('mhm_rentiva_vendor_rejected', $user_id, $application_id, $sanitized_reason);

        set_transient('mhm_vendor_reject_cooldown_' . (int) $user_id, true, DAY_IN_SECONDS);

        return true;
    }

    /**
     * Suspend an approved vendor.
     * Removes rentiva_vendor role, sets suspended status in user meta.
     * Fires: mhm_rentiva_vendor_suspended( $user_id )
     *
     * @param  int $user_id
     * @return bool False if user not found.
     */
    public static function suspend(int $user_id): bool
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $user->remove_role('rentiva_vendor');
        $remaining_roles = array_diff((array) $user->roles, ['rentiva_vendor', 'subscriber']);
        if (empty($remaining_roles)) {
            $user->add_role('customer');
        }
        update_user_meta($user_id, '_rentiva_vendor_status', 'suspended');

        do_action('mhm_rentiva_vendor_suspended', $user_id);

        return true;
    }
}
