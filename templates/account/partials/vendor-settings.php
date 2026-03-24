<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

use MHMRentiva\Admin\Vendor\VendorApplicationManager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Vendor Settings partial — rendered under the Payment Settings tab.
 *
 * Provides:
 *  - Vendor profile settings update form (bio, service areas, phone)
 *  - Payment details update triggering admin review upon change
 *
 * @since 4.21.0
 */

$current_user_id = (int) ($dashboard['user']->ID ?? get_current_user_id());

$form_error = '';
$form_success = '';

// Handle settings form submission.
if (
    isset($_POST['mhm_vendor_settings_nonce']) &&
    wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['mhm_vendor_settings_nonce'])), 'mhm_vendor_settings_' . $current_user_id)
) {
    // 1. Process basic profile information (instantly updated)
    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $new_phone = isset($_POST['vendor_phone']) ? sanitize_text_field(wp_unslash($_POST['vendor_phone'])) : '';
    $new_city  = isset($_POST['vendor_city'])  ? sanitize_text_field(wp_unslash($_POST['vendor_city'])) : '';
    $new_bio   = isset($_POST['vendor_bio'])   ? sanitize_textarea_field(wp_unslash($_POST['vendor_bio'])) : '';
    $new_tax   = isset($_POST['vendor_tax'])   ? sanitize_text_field(wp_unslash($_POST['vendor_tax'])) : '';
    $new_areas = isset($_POST['vendor_service_areas']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['vendor_service_areas'])) : array();
    $new_iban  = isset($_POST['vendor_iban'])  ? sanitize_text_field(wp_unslash($_POST['vendor_iban'])) : '';
    // phpcs:enable

    update_user_meta($current_user_id, '_rentiva_vendor_phone', $new_phone);
    update_user_meta($current_user_id, '_rentiva_vendor_city', $new_city);
    update_user_meta($current_user_id, '_rentiva_vendor_bio', $new_bio);
    update_user_meta($current_user_id, '_rentiva_vendor_service_areas', $new_areas);
    update_user_meta($current_user_id, '_rentiva_vendor_tax_number', $new_tax);

    // 2. Process IBAN change -> triggers approval flow if changed
    $current_encrypted_iban = (string) get_user_meta($current_user_id, '_rentiva_vendor_iban', true);
    $current_raw_iban       = VendorApplicationManager::decrypt_iban($current_encrypted_iban);

    // Remove empty spaces from newly submitted IBAN to compare accurately
    $new_iban_sanitized = str_replace(' ', '', strtoupper($new_iban));
    $current_iban_sanitized = str_replace(' ', '', strtoupper($current_raw_iban));

    if ($new_iban_sanitized !== '' && $new_iban_sanitized !== $current_iban_sanitized) {
        $encrypted_new_iban = VendorApplicationManager::encrypt_iban($new_iban_sanitized);
        if ($encrypted_new_iban !== '') {
            update_user_meta($current_user_id, '_rentiva_pending_iban', $encrypted_new_iban);
            update_user_meta($current_user_id, '_rentiva_iban_change_status', 'pending');
            $form_success = __('Settings updated successfully. Your IBAN change is pending admin review.', 'mhm-rentiva');

            // Add a log entry for auditing
            \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::info(
                sprintf('Vendor #%d requested IBAN change.', $current_user_id),
                array('vendor' => $current_user_id, 'action' => 'iban_change_request')
            );
        } else {
            $form_error = __('Settings updated, but IBAN encryption failed. Please try saving again.', 'mhm-rentiva');
        }
    } else {
        if ($form_error === '') {
            $form_success = __('Settings updated successfully.', 'mhm-rentiva');
        }
    }
}

// Retrieve current data for display
$phone      = (string) get_user_meta($current_user_id, '_rentiva_vendor_phone', true);
$city       = (string) get_user_meta($current_user_id, '_rentiva_vendor_city', true);
$bio        = (string) get_user_meta($current_user_id, '_rentiva_vendor_bio', true);
$tax_number = (string) get_user_meta($current_user_id, '_rentiva_vendor_tax_number', true);
$areas      = (array) get_user_meta($current_user_id, '_rentiva_vendor_service_areas', true);

$raw_iban   = VendorApplicationManager::decrypt_iban((string) get_user_meta($current_user_id, '_rentiva_vendor_iban', true));

$pending_iban_status = (string) get_user_meta($current_user_id, '_rentiva_iban_change_status', true);
$has_pending_iban    = $pending_iban_status === 'pending';

// Global specific options
$stored_cities = get_option('mhm_vendor_service_cities', array());
$all_service_cities = !empty($stored_cities) ? (array) $stored_cities : array('Istanbul', 'Ankara', 'Izmir', 'Antalya', 'Bursa', 'Adana', 'Konya', 'Other');
$bio_max = (int) get_option('mhm_vendor_bio_max_length', 400);

?>

<div class="mhm-rentiva-dashboard__settings">

    <div class="mhm-rentiva-dashboard__section">
        <div class="mhm-rentiva-dashboard__section-head">
            <h3><?php esc_html_e('Payment & Profile Settings', 'mhm-rentiva'); ?></h3>
        </div>

        <div class="mhm-rentiva-dashboard__settings-body">

            <?php if ($form_success !== '') : ?>
                <div class="mhm-rentiva-dashboard__notice is-success">
                    <?php echo esc_html($form_success); ?>
                </div>
            <?php endif; ?>

            <?php if ($form_error !== '') : ?>
                <div class="mhm-rentiva-dashboard__notice is-error">
                    <?php echo esc_html($form_error); ?>
                </div>
            <?php endif; ?>

            <?php if ($has_pending_iban) : ?>
                <div class="mhm-rentiva-dashboard__notice is-warning">
                    <?php esc_html_e('Your recent IBAN update is pending administrator approval. Payouts will continue using your previously approved IBAN until the new one is confirmed.', 'mhm-rentiva'); ?>
                </div>
            <?php endif; ?>

            <form method="post" class="mhm-rentiva-dashboard__form" novalidate>
                <?php wp_nonce_field('mhm_vendor_settings_' . $current_user_id, 'mhm_vendor_settings_nonce'); ?>

                <div class="mhm-rentiva-dashboard__form-row" style="display:flex; gap:20px; margin-bottom:15px;">
                    <div class="mhm-rentiva-dashboard__form-group" style="flex:1;">
                        <label for="vendor_phone" class="mhm-rentiva-dashboard__form-label"><?php esc_html_e('Phone', 'mhm-rentiva'); ?></label>
                        <input type="tel" id="vendor_phone" name="vendor_phone" class="mhm-rentiva-dashboard__form-input" value="<?php echo esc_attr($phone); ?>">
                    </div>
                    <div class="mhm-rentiva-dashboard__form-group" style="flex:1;">
                        <label for="vendor_city" class="mhm-rentiva-dashboard__form-label"><?php esc_html_e('Base City', 'mhm-rentiva'); ?></label>
                        <input type="text" id="vendor_city" name="vendor_city" class="mhm-rentiva-dashboard__form-input" value="<?php echo esc_attr($city); ?>">
                    </div>
                </div>

                <div class="mhm-rentiva-dashboard__form-group" style="margin-bottom:15px;">
                    <label class="mhm-rentiva-dashboard__form-label"><?php esc_html_e('Service Areas', 'mhm-rentiva'); ?></label>
                    <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:5px;">
                        <?php foreach ($all_service_cities as $city_option) : ?>
                            <label style="display:flex; align-items:center; gap:5px; font-size:14px; cursor:pointer;">
                                <input type="checkbox" name="vendor_service_areas[]" value="<?php echo esc_attr($city_option); ?>" <?php checked(in_array($city_option, $areas, true)); ?>>
                                <?php echo esc_html($city_option); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mhm-rentiva-dashboard__form-group" style="margin-bottom:20px;">
                    <label for="vendor_bio" class="mhm-rentiva-dashboard__form-label"><?php esc_html_e('Short Bio', 'mhm-rentiva'); ?></label>
                    <textarea id="vendor_bio" name="vendor_bio" class="mhm-rentiva-dashboard__form-input" rows="4" maxlength="<?php echo esc_attr((string) $bio_max); ?>" style="width:100%; padding:10px; border:1px solid #e1e4eb; border-radius:4px;"><?php echo esc_textarea($bio); ?></textarea>
                </div>

                <hr style="border:0; border-top:1px solid #e1e4eb; margin:25px 0;">

                <h4 style="margin-top:0; margin-bottom:15px; font-size:1.1rem; color:#111827;"><?php esc_html_e('Financial Details', 'mhm-rentiva'); ?></h4>

                <div class="mhm-rentiva-dashboard__form-group" style="margin-bottom:15px;">
                    <label for="vendor_iban" class="mhm-rentiva-dashboard__form-label">
                        <?php esc_html_e('IBAN', 'mhm-rentiva'); ?>
                        <span style="color:#d97706; font-size:0.85em; font-weight:normal; margin-left:5px;">(<?php esc_html_e('Changes require admin approval', 'mhm-rentiva'); ?>)</span>
                    </label>
                    <input type="text" id="vendor_iban" name="vendor_iban" class="mhm-rentiva-dashboard__form-input" value="<?php echo esc_attr($raw_iban); ?>" style="font-family:monospace;">
                </div>

                <div class="mhm-rentiva-dashboard__form-group" style="margin-bottom:20px;">
                    <label for="vendor_tax" class="mhm-rentiva-dashboard__form-label"><?php esc_html_e('Tax Number', 'mhm-rentiva'); ?></label>
                    <input type="text" id="vendor_tax" name="vendor_tax" class="mhm-rentiva-dashboard__form-input" value="<?php echo esc_attr($tax_number); ?>">
                </div>

                <button type="submit" class="mhm-rentiva-dashboard__payout-submit" style="background:#2563eb; color:#fff; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-weight:500;">
                    <?php esc_html_e('Save Settings', 'mhm-rentiva'); ?>
                </button>
            </form>

        </div>
    </div>
</div>