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
            return '<div class="mhm-vendor-notice mhm-vendor-notice--pro"><p>'
                . esc_html__('This feature requires Rentiva Pro.', 'mhm-rentiva')
                . '</p></div>';
        }

        if (! empty($data['login_required'])) {
            return '<div class="mhm-vendor-notice mhm-vendor-notice--warn"><p>'
                . esc_html__('Please log in to apply as a vendor.', 'mhm-rentiva')
                . ' <a href="' . esc_url(wp_login_url(get_permalink())) . '">' . esc_html__('Log In', 'mhm-rentiva') . '</a>'
                . '</p></div>';
        }

        if (! empty($data['already_applied'])) {
            return '<div class="mhm-vendor-notice mhm-vendor-notice--info"><p>'
                . esc_html__('Your application is under review or you are already an active vendor.', 'mhm-rentiva')
                . '</p></div>';
        }

        static::enqueue_assets();

        $current_user = wp_get_current_user();
        $cities = array('Istanbul', 'Ankara', 'Izmir', 'Antalya', 'Bursa', 'Adana', 'Konya', 'Other');

        ob_start();
        ?>
        <div class="mhm-vendor-apply-wrap">
            <div id="mhm-vendor-apply-msg" class="mhm-vendor-notice" style="display:none"></div>
            <form id="mhm-vendor-apply-form" class="mhm-vendor-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="mhm_vendor_apply">
                <input type="hidden" name="mhm_vendor_apply_nonce" value="<?php echo esc_attr($data['nonce']); ?>">

                <div class="mhm-vendor-form__section">
                    <h3><?php esc_html_e('Personal Information', 'mhm-rentiva'); ?></h3>
                    <div class="mhm-vendor-form__row">
                        <div class="mhm-vendor-form__field">
                            <label><?php esc_html_e('Full Name', 'mhm-rentiva'); ?></label>
                            <input type="text" value="<?php echo esc_attr($current_user->display_name); ?>" readonly class="mhm-vendor-form__readonly">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label><?php esc_html_e('Email', 'mhm-rentiva'); ?></label>
                            <input type="email" value="<?php echo esc_attr($current_user->user_email); ?>" readonly class="mhm-vendor-form__readonly">
                        </div>
                    </div>
                    <div class="mhm-vendor-form__row">
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-phone"><?php esc_html_e('Phone', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <input type="tel" id="mhm-phone" name="phone" required placeholder="+90 555 000 0000">
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-city"><?php esc_html_e('Base City', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <input type="text" id="mhm-city" name="city" required placeholder="<?php esc_attr_e('e.g. Istanbul', 'mhm-rentiva'); ?>">
                        </div>
                    </div>
                </div>

                <div class="mhm-vendor-form__section">
                    <h3><?php esc_html_e('Service Areas', 'mhm-rentiva'); ?></h3>
                    <p class="mhm-vendor-form__hint"><?php esc_html_e('Select all cities where you offer services.', 'mhm-rentiva'); ?></p>
                    <div class="mhm-vendor-form__checkboxes">
                        <?php foreach ($cities as $city_option) : ?>
                            <label class="mhm-vendor-form__checkbox">
                                <input type="checkbox" name="service_areas[]" value="<?php echo esc_attr($city_option); ?>">
                                <?php echo esc_html($city_option); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mhm-vendor-form__section">
                    <h3><?php esc_html_e('Financial Information', 'mhm-rentiva'); ?></h3>
                    <div class="mhm-vendor-form__row">
                        <div class="mhm-vendor-form__field mhm-vendor-form__field--wide">
                            <label for="mhm-iban"><?php esc_html_e('IBAN', 'mhm-rentiva'); ?> <span class="required">*</span></label>
                            <input type="text" id="mhm-iban" name="iban" required placeholder="TR00 0000 0000 0000 0000 0000 00" maxlength="32" autocomplete="off">
                            <small><?php esc_html_e('Your payout earnings will be sent to this account.', 'mhm-rentiva'); ?></small>
                        </div>
                        <div class="mhm-vendor-form__field">
                            <label for="mhm-tax"><?php esc_html_e('Tax Number', 'mhm-rentiva'); ?> <span class="optional">(<?php esc_html_e('optional', 'mhm-rentiva'); ?>)</span></label>
                            <input type="text" id="mhm-tax" name="tax_number" placeholder="<?php esc_attr_e('10 or 11 digits', 'mhm-rentiva'); ?>">
                        </div>
                    </div>
                </div>

                <div class="mhm-vendor-form__section">
                    <h3><?php esc_html_e('About You', 'mhm-rentiva'); ?></h3>
                    <div class="mhm-vendor-form__field">
                        <label for="mhm-bio"><?php esc_html_e('Short Bio', 'mhm-rentiva'); ?> <span class="optional">(<?php esc_html_e('optional', 'mhm-rentiva'); ?>)</span></label>
                        <textarea id="mhm-bio" name="bio" rows="4" maxlength="400" placeholder="<?php esc_attr_e('Tell customers about yourself and your services...', 'mhm-rentiva'); ?>"></textarea>
                    </div>
                </div>

                <div class="mhm-vendor-form__section">
                    <h3><?php esc_html_e('Required Documents', 'mhm-rentiva'); ?></h3>
                    <p class="mhm-vendor-form__hint"><?php esc_html_e('Upload clear photos or scans. Accepted formats: JPG, PNG, PDF (max 5MB each).', 'mhm-rentiva'); ?></p>
                    <div class="mhm-vendor-form__docs">
                        <?php
                        $doc_fields = array(
                            'doc_id'        => __('ID Document', 'mhm-rentiva'),
                            'doc_license'   => __("Driver's License", 'mhm-rentiva'),
                            'doc_address'   => __('Address Document', 'mhm-rentiva'),
                            'doc_insurance' => __('Vehicle Insurance', 'mhm-rentiva'),
                        );
                        foreach ($doc_fields as $field_name => $field_label) :
                        ?>
                            <div class="mhm-vendor-form__doc-field">
                                <label for="mhm-<?php echo esc_attr($field_name); ?>">
                                    <?php echo esc_html($field_label); ?> <span class="required">*</span>
                                </label>
                                <input type="file"
                                    id="mhm-<?php echo esc_attr($field_name); ?>"
                                    name="<?php echo esc_attr($field_name); ?>"
                                    accept="image/*,application/pdf"
                                    required>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mhm-vendor-form__submit">
                    <button type="submit" class="mhm-vendor-form__btn mhm-vendor-form__btn--primary" id="mhm-vendor-apply-submit">
                        <?php esc_html_e('Submit Application', 'mhm-rentiva'); ?>
                    </button>
                    <span class="mhm-vendor-form__spinner" id="mhm-vendor-apply-spinner" style="display:none">
                        <?php esc_html_e('Submitting...', 'mhm-rentiva'); ?>
                    </span>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue CSS and JS assets for the vendor apply form.
     *
     * @param array $atts Shortcode attributes (unused).
     */
    protected static function enqueue_assets(array $atts = array()): void
    {
        wp_enqueue_style(
            'mhm-rentiva-vendor-forms',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/vendor-forms.css',
            array(),
            MHM_RENTIVA_VERSION
        );
        wp_enqueue_script(
            'mhm-rentiva-vendor-apply',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/vendor-apply.js',
            array('jquery'),
            MHM_RENTIVA_VERSION,
            true
        );
        wp_localize_script('mhm-rentiva-vendor-apply', 'mhmVendorApply', array(
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'successMsg'  => __('Your application has been submitted! We will review it and notify you by email.', 'mhm-rentiva'),
            'errorMsg'    => __('Something went wrong. Please try again.', 'mhm-rentiva'),
        ));
    }

    /**
     * AJAX handler for vendor application form submission with file upload support.
     */
    public static function handle_ajax(): void
    {
        check_ajax_referer('mhm_vendor_apply', 'mhm_vendor_apply_nonce');

        if (! is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', 'mhm-rentiva')));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $doc_ids    = array();
        $doc_fields = array('doc_id', 'doc_license', 'doc_address', 'doc_insurance');

        foreach ($doc_fields as $field) {
            if (! empty($_FILES[ $field ]['name'])) {
                $attachment_id = media_handle_upload($field, 0);
                if (is_wp_error($attachment_id)) {
                    wp_send_json_error(array('message' => sprintf(
                        /* translators: 1: field name 2: error message */
                        __('File upload failed for %1$s: %2$s', 'mhm-rentiva'),
                        $field,
                        $attachment_id->get_error_message()
                    )));
                }
                $doc_ids[ $field ] = $attachment_id;
            } else {
                // fallback: accept pre-uploaded attachment ID
                $doc_ids[ $field ] = isset($_POST[ $field ]) ? (int) $_POST[ $field ] : 0;
            }
        }

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $data = array(
            'phone'         => sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
            'city'          => sanitize_text_field(wp_unslash($_POST['city'] ?? '')),
            'iban'          => sanitize_text_field(wp_unslash($_POST['iban'] ?? '')),
            'service_areas' => array_map('sanitize_text_field', (array) (isset($_POST['service_areas']) ? wp_unslash($_POST['service_areas']) : array())),
            'bio'           => sanitize_textarea_field(wp_unslash($_POST['bio'] ?? '')),
            'tax_number'    => sanitize_text_field(wp_unslash($_POST['tax_number'] ?? '')),
            'doc_id'        => $doc_ids['doc_id'],
            'doc_license'   => $doc_ids['doc_license'],
            'doc_address'   => $doc_ids['doc_address'],
            'doc_insurance' => $doc_ids['doc_insurance'],
        );
        // phpcs:enable

        $result = VendorApplicationManager::create_application(get_current_user_id(), $data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Trigger email notifications: confirmation to applicant + alert to admin.
        do_action('mhm_rentiva_vendor_application_submitted', get_current_user_id());

        // Build redirect URL — WC My Account endpoint if available, else current page.
        $redirect_url = '';
        if (function_exists('wc_get_account_endpoint_url')) {
            $endpoint     = apply_filters('mhm_rentiva_vendor_apply_endpoint_slug', 'vendor-apply');
            $redirect_url = \wc_get_account_endpoint_url($endpoint) . '?applied=1';
        }

        wp_send_json_success(array(
            'application_id' => $result,
            'redirect_url'   => $redirect_url,
        ));
    }
}
