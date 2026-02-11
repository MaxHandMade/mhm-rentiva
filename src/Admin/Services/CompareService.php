<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Services;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Compare Service
 * 
 * Handles logic for vehicle comparison list.
 * Supports both User Meta (for logged-in users) and Cookies (for guests).
 * 
 * @since 1.3.3
 */
class CompareService
{
    /**
     * Meta key/Cookie name
     */
    private const STORAGE_KEY = 'mhm_rentiva_compare';

    /**
     * Max items in compare list
     */
    private const MAX_ITEMS = 3;

    /**
     * Cookie expiry (30 days)
     */
    private const COOKIE_EXPIRY = 2592000;

    /**
     * Register service actions
     */
    public static function register(): void
    {
        add_action('wp_ajax_mhm_rentiva_toggle_compare', array(self::class, 'ajax_toggle_compare'));
        add_action('wp_ajax_nopriv_mhm_rentiva_toggle_compare', array(self::class, 'ajax_toggle_compare'));
    }

    /**
     * Get compare list
     * 
     * @return array<int> list of vehicle IDs
     */
    public static function get_list(): array
    {
        $list = array();

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $meta = get_user_meta($user_id, self::STORAGE_KEY, true);
            if (! empty($meta) && is_array($meta)) {
                $list = $meta;
            }
        } else {
            $cookie_val = filter_input(INPUT_COOKIE, self::STORAGE_KEY);
            if ($cookie_val) {
                $decoded = json_decode(stripslashes($cookie_val), true);
                if (is_array($decoded)) {
                    $list = $decoded;
                }
            }
        }

        return array_map('intval', array_unique($list));
    }

    /**
     * Save compare list
     * 
     * @param array $list
     * @return bool
     */
    private static function save_list(array $list): bool
    {
        // Enforce limit
        if (count($list) > self::MAX_ITEMS) {
            $list = array_slice($list, 0, self::MAX_ITEMS);
        }

        $list = array_values(array_unique(array_map('intval', $list)));

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            return (bool) update_user_meta($user_id, self::STORAGE_KEY, $list);
        } else {
            // Set cookie
            // Note: This relies on headers not being sent yet.
            // In AJAX context, this is usually fine.
            $json = wp_json_encode($list);
            return setcookie(self::STORAGE_KEY, $json, time() + self::COOKIE_EXPIRY, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        }
    }

    /**
     * Add to compare
     * 
     * @param int $vehicle_id
     * @return bool
     * @throws \Exception if max limit reached
     */
    public static function add(int $vehicle_id): bool
    {
        if ($vehicle_id <= 0) {
            return false;
        }

        $list = self::get_list();

        if (in_array($vehicle_id, $list, true)) {
            return true; // Already added
        }

        if (count($list) >= self::MAX_ITEMS) {
            throw new \Exception(sprintf(__('You can compare up to %d vehicles.', 'mhm-rentiva'), self::MAX_ITEMS));
        }

        $list[] = $vehicle_id;
        return self::save_list($list);
    }

    /**
     * Remove from compare
     * 
     * @param int $vehicle_id
     * @return bool
     */
    public static function remove(int $vehicle_id): bool
    {
        $list = self::get_list();
        $key = array_search($vehicle_id, $list, true);

        if ($key === false) {
            return true; // Already removed
        }

        unset($list[$key]);
        return self::save_list($list);
    }

    /**
     * Check if in compare list
     * 
     * @param int $vehicle_id
     * @return bool
     */
    public static function is_in_compare(int $vehicle_id): bool
    {
        return in_array($vehicle_id, self::get_list(), true);
    }

    /**
     * AJAX: Toggle Compare
     */
    public static function ajax_toggle_compare(): void
    {
        try {
            // 1. Verify Nonce
            $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
            if (empty($nonce) || (
                ! wp_verify_nonce($nonce, 'mhm_rentiva_toggle_compare') &&
                ! wp_verify_nonce($nonce, 'mhm_rentiva_vehicles_list')
            )) {
                throw new \Exception(__('Security check failed', 'mhm-rentiva'));
            }

            // 2. Input
            $vehicle_id = isset($_POST['vehicle_id']) ? intval(wp_unslash($_POST['vehicle_id'])) : 0;
            if ($vehicle_id <= 0) {
                throw new \Exception(__('Invalid vehicle ID', 'mhm-rentiva'));
            }

            // 3. Logic
            $action = '';
            $message = '';
            $list = self::get_list();

            if (in_array($vehicle_id, $list, true)) {
                self::remove($vehicle_id);
                $action = 'removed';
                $message = __('Removed from comparison', 'mhm-rentiva');
            } else {
                self::add($vehicle_id);
                $action = 'added';
                $message = __('Added to comparison', 'mhm-rentiva');
            }

            // 4. Response
            wp_send_json_success(array(
                'vehicle_id' => $vehicle_id,
                'action' => $action,
                'message' => $message,
                'is_in_compare' => ($action === 'added'),
                'count' => count(self::get_list())
            ));
        } catch (\Exception $e) {
            // Rethrow test exception
            if (strpos(get_class($e), 'WPAjaxDie') !== false) {
                throw $e;
            }
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}
