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
 *  - Vendor profile settings update form (bio, phone, city)
 *  - Payment / banking details with admin review on IBAN change
 *
 * @since 4.21.0
 * @since 4.24.0 Redesigned with mhm-vendor-form classes; removed service-areas; added account_holder & tax_office.
 */

$current_user_id = (int) ( $dashboard['user']->ID ?? get_current_user_id() );

$form_error   = '';
$form_success = '';

// Handle settings form submission.
if (
    isset($_POST['mhm_vendor_settings_nonce']) &&
    wp_verify_nonce(sanitize_text_field(wp_unslash( (string) $_POST['mhm_vendor_settings_nonce'])), 'mhm_vendor_settings_' . $current_user_id)
) {
    // 1. Process basic profile information (instantly updated)
    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $new_phone          = isset($_POST['vendor_phone'])          ? sanitize_text_field(wp_unslash($_POST['vendor_phone'])) : '';
    $new_city           = isset($_POST['vendor_city'])           ? sanitize_text_field(wp_unslash($_POST['vendor_city'])) : '';
    $new_bio            = isset($_POST['vendor_bio'])            ? sanitize_textarea_field(wp_unslash($_POST['vendor_bio'])) : '';
    $new_account_holder = isset($_POST['vendor_account_holder']) ? sanitize_text_field(wp_unslash($_POST['vendor_account_holder'])) : '';
    $new_tax_office     = isset($_POST['vendor_tax_office'])     ? sanitize_text_field(wp_unslash($_POST['vendor_tax_office'])) : '';
    $new_tax            = isset($_POST['vendor_tax'])            ? sanitize_text_field(wp_unslash($_POST['vendor_tax'])) : '';
    $new_iban           = isset($_POST['vendor_iban'])           ? sanitize_text_field(wp_unslash($_POST['vendor_iban'])) : '';
    // phpcs:enable

    update_user_meta($current_user_id, '_rentiva_vendor_phone', $new_phone);
    update_user_meta($current_user_id, '_rentiva_vendor_city', $new_city);
    update_user_meta($current_user_id, '_rentiva_vendor_bio', $new_bio);
    update_user_meta($current_user_id, '_rentiva_vendor_account_holder', $new_account_holder);
    update_user_meta($current_user_id, '_rentiva_vendor_tax_office', $new_tax_office);
    update_user_meta($current_user_id, '_rentiva_vendor_tax_number', $new_tax);

    // 2. Process IBAN change -> triggers approval flow if changed
    $current_encrypted_iban = (string) get_user_meta($current_user_id, '_rentiva_vendor_iban', true);
    $current_raw_iban       = VendorApplicationManager::decrypt_iban($current_encrypted_iban);

    // Remove empty spaces from newly submitted IBAN to compare accurately
    $new_iban_sanitized     = str_replace(' ', '', strtoupper($new_iban));
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
                array(
					'vendor' => $current_user_id,
					'action' => 'iban_change_request',
				)
            );
        } else {
            $form_error = __('Settings updated, but IBAN encryption failed. Please try saving again.', 'mhm-rentiva');
        }
    } elseif ($form_error === '') {
            $form_success = __('Settings updated successfully.', 'mhm-rentiva');
    }
}

// Retrieve current data for display
$phone          = (string) get_user_meta($current_user_id, '_rentiva_vendor_phone', true);
$city           = (string) get_user_meta($current_user_id, '_rentiva_vendor_city', true);
$bio            = (string) get_user_meta($current_user_id, '_rentiva_vendor_bio', true);
$account_holder = (string) get_user_meta($current_user_id, '_rentiva_vendor_account_holder', true);
$tax_office     = (string) get_user_meta($current_user_id, '_rentiva_vendor_tax_office', true);
$tax_number     = (string) get_user_meta($current_user_id, '_rentiva_vendor_tax_number', true);

$raw_iban = VendorApplicationManager::decrypt_iban( (string) get_user_meta($current_user_id, '_rentiva_vendor_iban', true));

$pending_iban_status = (string) get_user_meta($current_user_id, '_rentiva_iban_change_status', true);
$has_pending_iban    = $pending_iban_status === 'pending';

$bio_max = (int) get_option('mhm_vendor_bio_max_length', 400);

?>

<div class="mhm-vendor-apply-wrap">

    <?php if ($form_success !== '') : ?>
        <div class="mhm-vendor-notice mhm-vendor-notice--success">
            <?php echo esc_html($form_success); ?>
        </div>
    <?php endif; ?>

    <?php if ($form_error !== '') : ?>
        <div class="mhm-vendor-notice mhm-vendor-notice--error">
            <?php echo esc_html($form_error); ?>
        </div>
    <?php endif; ?>

    <?php if ($has_pending_iban) : ?>
        <div class="mhm-vendor-notice mhm-vendor-notice--warn">
            <?php esc_html_e('Your recent IBAN update is pending administrator approval. Payouts will continue using your previously approved IBAN until the new one is confirmed.', 'mhm-rentiva'); ?>
        </div>
    <?php endif; ?>

    <form method="post" class="mhm-vendor-form" novalidate>
        <?php wp_nonce_field('mhm_vendor_settings_' . $current_user_id, 'mhm_vendor_settings_nonce'); ?>

        <!-- Profile Section -->
        <div class="mhm-vendor-form__section">
            <h3><?php esc_html_e('Profile Information', 'mhm-rentiva'); ?></h3>

            <div class="mhm-vendor-form__row">
                <div class="mhm-vendor-form__field">
                    <label for="vendor_phone"><?php esc_html_e('Phone', 'mhm-rentiva'); ?></label>
                    <input type="tel" id="vendor_phone" name="vendor_phone" value="<?php echo esc_attr($phone); ?>">
                </div>
                <div class="mhm-vendor-form__field">
                    <label for="vendor_city"><?php esc_html_e('Base City', 'mhm-rentiva'); ?></label>
                    <?php echo \MHMRentiva\Admin\Core\Utilities\CityHelper::render_select('vendor_city', 'vendor_city', $city); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>

            <div class="mhm-vendor-form__field mhm-vendor-form__field--wide">
                <label for="vendor_bio"><?php esc_html_e('Short Bio', 'mhm-rentiva'); ?></label>
                <textarea id="vendor_bio" name="vendor_bio" rows="4" maxlength="<?php echo esc_attr( (string) $bio_max); ?>"><?php echo esc_textarea($bio); ?></textarea>
                <?php /* translators: %d: maximum allowed character count for the vendor bio field */ ?>
                <small><?php echo esc_html(sprintf(__('Max %d characters', 'mhm-rentiva'), $bio_max)); ?></small>
            </div>
        </div>

        <!-- Financial Details Section -->
        <div class="mhm-vendor-form__section">
            <h3><?php esc_html_e('Financial Details', 'mhm-rentiva'); ?></h3>

            <div class="mhm-vendor-form__field mhm-vendor-form__field--wide">
                <label for="vendor_account_holder"><?php esc_html_e('Account Holder Name', 'mhm-rentiva'); ?></label>
                <input type="text" id="vendor_account_holder" name="vendor_account_holder" value="<?php echo esc_attr($account_holder); ?>">
                <small><?php esc_html_e('Full name as it appears on the bank account.', 'mhm-rentiva'); ?></small>
            </div>

            <div class="mhm-vendor-form__field mhm-vendor-form__field--wide">
                <label for="vendor_iban">
                    <?php esc_html_e('IBAN', 'mhm-rentiva'); ?>
                    <span class="optional">(<?php esc_html_e('Changes require admin approval', 'mhm-rentiva'); ?>)</span>
                </label>
                <input type="text" id="vendor_iban" name="vendor_iban" value="<?php echo esc_attr($raw_iban); ?>" style="font-family:monospace;">
                <small><?php esc_html_e('e.g. TR33 0006 1005 1978 6457 8413 26', 'mhm-rentiva'); ?></small>
            </div>

            <div class="mhm-vendor-form__row">
                <div class="mhm-vendor-form__field">
                    <label for="vendor_tax_office"><?php esc_html_e('Tax Office', 'mhm-rentiva'); ?></label>
                    <input type="text" id="vendor_tax_office" name="vendor_tax_office" value="<?php echo esc_attr($tax_office); ?>">
                </div>
                <div class="mhm-vendor-form__field">
                    <label for="vendor_tax"><?php esc_html_e('Tax Number', 'mhm-rentiva'); ?></label>
                    <input type="text" id="vendor_tax" name="vendor_tax" value="<?php echo esc_attr($tax_number); ?>">
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="mhm-vendor-form__submit">
            <button type="submit" class="mhm-vendor-form__btn mhm-vendor-form__btn--primary">
                <?php esc_html_e('Save Settings', 'mhm-rentiva'); ?>
            </button>
        </div>

    </form>

</div>
