<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Vendor;

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Vendor\VendorApplicationManager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * [rentiva_vendor_apply] Shortcode
 *
 * Renders a vendor application form. Handles AJAX submission via wp_ajax_mhm_vendor_apply.
 */
final class VendorApply extends AbstractShortcode
{
    protected static function get_shortcode_tag(): string
    {
        return 'rentiva_vendor_apply';
    }

    protected static function get_template_path(): string
    {
        return 'vendor/vendor-apply';
    }

    protected static function get_default_attributes(): array
    {
        return array();
    }

    /**
     * Register the AJAX handler for vendor application submissions.
     */
    protected static function register_ajax_handlers(): void
    {
        add_action('wp_ajax_mhm_vendor_apply', array(static::class, 'handle_ajax'));
    }

    /**
     * Prepare template data, applying access control checks.
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

        $user_id = get_current_user_id();

        if (! VendorApplicationManager::can_apply($user_id)) {
            return array(
                'pro_required'    => false,
                'login_required'  => false,
                'already_applied' => true,
            );
        }

        return array(
            'pro_required'    => false,
            'login_required'  => false,
            'already_applied' => false,
            'ajax_url'        => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce('mhm_vendor_apply'),
        );
    }

    /**
     * Render shortcode output directly (no template file required).
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
                . esc_html__('You must be logged in to apply as a vendor.', 'mhm-rentiva')
                . '</div>';
        }

        if (! empty($data['already_applied'])) {
            return '<div class="mhm-rentiva-notice mhm-rentiva-notice--info">'
                . esc_html__('You have already applied or are already a vendor.', 'mhm-rentiva')
                . '</div>';
        }

        $ajax_url = esc_url($data['ajax_url']);
        $nonce    = esc_attr($data['nonce']);

        return '<form class="mhm-rentiva-vendor-apply-form" method="post" action="' . $ajax_url . '">'
            . '<input type="hidden" name="action" value="mhm_vendor_apply">'
            . '<input type="hidden" name="mhm_vendor_apply_nonce" value="' . $nonce . '">'
            . '<p><label>' . esc_html__('Phone', 'mhm-rentiva') . '<input type="text" name="phone"></label></p>'
            . '<p><label>' . esc_html__('City', 'mhm-rentiva') . '<input type="text" name="city"></label></p>'
            . '<p><label>' . esc_html__('IBAN', 'mhm-rentiva') . '<input type="text" name="iban"></label></p>'
            . '<p><label>' . esc_html__('Service Areas', 'mhm-rentiva') . '<input type="text" name="service_areas[]"></label></p>'
            . '<p><label>' . esc_html__('Bio', 'mhm-rentiva') . '<textarea name="bio"></textarea></label></p>'
            . '<p><label>' . esc_html__('Tax Number', 'mhm-rentiva') . '<input type="text" name="tax_number"></label></p>'
            . '<p><label>' . esc_html__('ID Document (attachment ID)', 'mhm-rentiva') . '<input type="number" name="doc_id" value="0"></label></p>'
            . '<p><label>' . esc_html__('License Document (attachment ID)', 'mhm-rentiva') . '<input type="number" name="doc_license" value="0"></label></p>'
            . '<p><label>' . esc_html__('Address Document (attachment ID)', 'mhm-rentiva') . '<input type="number" name="doc_address" value="0"></label></p>'
            . '<p><label>' . esc_html__('Insurance Document (attachment ID)', 'mhm-rentiva') . '<input type="number" name="doc_insurance" value="0"></label></p>'
            . '<p><button type="submit">' . esc_html__('Submit Application', 'mhm-rentiva') . '</button></p>'
            . '</form>';
    }

    /**
     * AJAX handler for vendor application form submission.
     */
    public static function handle_ajax(): void
    {
        check_ajax_referer('mhm_vendor_apply', 'mhm_vendor_apply_nonce');

        if (! is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'mhm-rentiva')));
        }

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitization performed below
        $data = array(
            'phone'         => sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
            'city'          => sanitize_text_field(wp_unslash($_POST['city'] ?? '')),
            'iban'          => sanitize_text_field(wp_unslash($_POST['iban'] ?? '')),
            'service_areas' => array_map('sanitize_text_field', (array) (isset($_POST['service_areas']) ? wp_unslash($_POST['service_areas']) : array())),
            'bio'           => sanitize_textarea_field(wp_unslash($_POST['bio'] ?? '')),
            'tax_number'    => sanitize_text_field(wp_unslash($_POST['tax_number'] ?? '')),
            'doc_id'        => (int) ($_POST['doc_id'] ?? 0),
            'doc_license'   => (int) ($_POST['doc_license'] ?? 0),
            'doc_address'   => (int) ($_POST['doc_address'] ?? 0),
            'doc_insurance' => (int) ($_POST['doc_insurance'] ?? 0),
        );
        // phpcs:enable

        $result = VendorApplicationManager::create_application(get_current_user_id(), $data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('application_id' => $result));
    }
}
