<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Vendor;

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Licensing\Mode;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * [rentiva_vehicle_submit] Shortcode
 *
 * Renders a vehicle submission form for vendors. Handles AJAX via wp_ajax_mhm_vehicle_submit.
 */
final class VehicleSubmit extends AbstractShortcode
{
    protected static function get_shortcode_tag(): string
    {
        return 'rentiva_vehicle_submit';
    }

    protected static function get_template_path(): string
    {
        return 'vendor/vehicle-submit';
    }

    protected static function get_default_attributes(): array
    {
        return array();
    }

    /**
     * Register the AJAX handler for vehicle submissions.
     */
    protected static function register_ajax_handlers(): void
    {
        add_action('wp_ajax_mhm_vehicle_submit', array(static::class, 'handle_ajax'));
    }

    /**
     * Prepare template data with access control checks.
     *
     * @param array $atts Shortcode attributes.
     * @return array Template data.
     */
    protected static function prepare_template_data(array $atts): array
    {
        if (! Mode::canUseVendorMarketplace()) {
            return array('pro_required' => true);
        }

        if (! is_user_logged_in()) {
            return array(
                'pro_required'   => false,
                'login_required' => true,
            );
        }

        $user    = wp_get_current_user();
        $is_vendor = in_array('rentiva_vendor', (array) $user->roles, true);

        if (! $is_vendor) {
            return array(
                'pro_required'   => false,
                'login_required' => false,
                'vendor_only'    => true,
            );
        }

        return array(
            'pro_required'   => false,
            'login_required' => false,
            'vendor_only'    => false,
            'ajax_url'       => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('mhm_vehicle_submit'),
        );
    }

    /**
     * Render shortcode output directly.
     *
     * @param array       $atts    Shortcode attributes.
     * @param string|null $content Shortcode content.
     * @return string Rendered HTML.
     */
    public static function render(array $atts = array(), ?string $content = null): string
    {
        $data = static::prepare_template_data($atts);

        if (! empty($data['pro_required'])) {
            return '<div class="mhm-rentiva-notice mhm-rentiva-notice--pro">'
                . esc_html__('This feature requires Rentiva Pro.', 'mhm-rentiva')
                . '</div>';
        }

        if (! empty($data['login_required'])) {
            return '<div class="mhm-rentiva-notice mhm-rentiva-notice--login">'
                . esc_html__('You must be logged in to submit a vehicle.', 'mhm-rentiva')
                . '</div>';
        }

        if (! empty($data['vendor_only'])) {
            return '<div class="mhm-rentiva-notice mhm-rentiva-notice--info">'
                . esc_html__('Only approved vendors can submit vehicles.', 'mhm-rentiva')
                . '</div>';
        }

        $ajax_url = esc_url($data['ajax_url']);
        $nonce    = esc_attr($data['nonce']);

        return '<form class="mhm-rentiva-vehicle-submit-form" method="post" action="' . $ajax_url . '">'
            . '<input type="hidden" name="action" value="mhm_vehicle_submit">'
            . '<input type="hidden" name="mhm_vehicle_submit_nonce" value="' . $nonce . '">'
            . '<p><label>' . esc_html__('Make', 'mhm-rentiva') . '<input type="text" name="make"></label></p>'
            . '<p><label>' . esc_html__('Model', 'mhm-rentiva') . '<input type="text" name="model"></label></p>'
            . '<p><label>' . esc_html__('Year', 'mhm-rentiva') . '<input type="number" name="year"></label></p>'
            . '<p><label>' . esc_html__('Price per Day', 'mhm-rentiva') . '<input type="number" step="0.01" name="price_per_day"></label></p>'
            . '<p><label>' . esc_html__('Description', 'mhm-rentiva') . '<textarea name="description"></textarea></label></p>'
            . '<p><label>' . esc_html__('City', 'mhm-rentiva') . '<input type="text" name="city"></label></p>'
            . '<p><label>' . esc_html__('Service Type', 'mhm-rentiva') . '<input type="text" name="service_type"></label></p>'
            . '<p><button type="submit">' . esc_html__('Submit Vehicle', 'mhm-rentiva') . '</button></p>'
            . '</form>';
    }

    /**
     * AJAX handler for vehicle submission.
     */
    public static function handle_ajax(): void
    {
        check_ajax_referer('mhm_vehicle_submit', 'mhm_vehicle_submit_nonce');

        if (! is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'mhm-rentiva')));
        }

        $user      = wp_get_current_user();
        $is_vendor = in_array('rentiva_vendor', (array) $user->roles, true);

        if (! $is_vendor) {
            wp_send_json_error(array('message' => __('Only approved vendors can submit vehicles.', 'mhm-rentiva')));
        }

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitization performed below
        $make          = sanitize_text_field(wp_unslash($_POST['make'] ?? ''));
        $model         = sanitize_text_field(wp_unslash($_POST['model'] ?? ''));
        $year          = (int) ($_POST['year'] ?? 0);
        $price_per_day = (float) ($_POST['price_per_day'] ?? 0.0);
        $description   = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
        $city          = sanitize_text_field(wp_unslash($_POST['city'] ?? ''));
        $service_type  = sanitize_text_field(wp_unslash($_POST['service_type'] ?? ''));
        // phpcs:enable

        $title = trim(implode(' ', array_filter(array($make, $model, (string) $year))));
        if ($title === '') {
            $title = __('Untitled Vehicle', 'mhm-rentiva');
        }

        $post_id = wp_insert_post(array(
            'post_type'    => 'vehicle',
            'post_status'  => 'pending',
            'post_author'  => get_current_user_id(),
            'post_title'   => sanitize_text_field($title),
            'post_content' => $description,
        ), true);

        if (is_wp_error($post_id)) {
            wp_send_json_error(array('message' => $post_id->get_error_message()));
        }

        update_post_meta($post_id, '_vehicle_review_status', 'pending_review');
        update_post_meta($post_id, '_mhm_rentiva_vehicle_make', $make);
        update_post_meta($post_id, '_mhm_rentiva_vehicle_model', $model);
        update_post_meta($post_id, '_mhm_rentiva_vehicle_year', $year);
        update_post_meta($post_id, '_mhm_rentiva_price_per_day', $price_per_day);
        update_post_meta($post_id, '_mhm_rentiva_vehicle_city', $city);
        update_post_meta($post_id, '_mhm_rentiva_service_type', $service_type);

        wp_send_json_success(array('vehicle_id' => $post_id));
    }
}
