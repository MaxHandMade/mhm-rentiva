<?php
declare(strict_types=1);

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Core\Utilities\CityHelper;
use MHMRentiva\Admin\Vendor\Profile\VendorProfileSettingsSave;
use MHMRentiva\Admin\Vendor\Profile\VendorProfileUrlBase;

if (!defined('ABSPATH')) {
    exit;
}

$current_user_id = (int) ( $dashboard['user']->ID ?? get_current_user_id() );
$form_error      = '';
$form_success    = '';

if (
    isset($_POST['mhm_vendor_profile_nonce']) &&
    wp_verify_nonce(sanitize_text_field(wp_unslash( (string) $_POST['mhm_vendor_profile_nonce'])), 'mhm_vendor_profile_' . $current_user_id)
) {
    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $post_data = [
        'phone' => isset($_POST['vendor_phone']) ? sanitize_text_field(wp_unslash($_POST['vendor_phone'])) : '',
        'city'  => isset($_POST['vendor_city'])  ? sanitize_text_field(wp_unslash($_POST['vendor_city']))  : '',
        'bio'   => isset($_POST['vendor_bio'])   ? sanitize_textarea_field(wp_unslash($_POST['vendor_bio'])) : '',
        'slug'  => isset($_POST['vendor_slug'])  ? sanitize_text_field(wp_unslash($_POST['vendor_slug']))  : '',
    ];
    // phpcs:enable

    $file = ( isset( $_FILES['vendor_avatar']['size'] ) && (int) $_FILES['vendor_avatar']['size'] > 0 )
        ? $_FILES['vendor_avatar'] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised inside VendorProfileSettingsSave::handle()
        : null;

    $result = VendorProfileSettingsSave::handle($current_user_id, $post_data, $file);
    if ($result['success']) {
        $form_success = __('Profile updated successfully.', 'mhm-rentiva');
    } else {
        $form_error = $result['error'];
    }
}

// Fetch current values for display
$current_avatar_id  = (int) get_user_meta($current_user_id, MetaKeys::VENDOR_AVATAR_ID, true);
$current_avatar_url = $current_avatar_id > 0
    ? (string) wp_get_attachment_image_url($current_avatar_id, 'thumbnail')
    : '';
$current_slug       = (string) get_user_meta($current_user_id, MetaKeys::VENDOR_SLUG, true);
$current_phone      = (string) get_user_meta($current_user_id, '_rentiva_vendor_phone', true);
$current_city       = (string) get_user_meta($current_user_id, '_rentiva_vendor_city', true);
$current_bio        = (string) get_user_meta($current_user_id, '_rentiva_vendor_bio', true);
$bio_max            = (int) get_option('mhm_vendor_bio_max_length', 400);
$url_base           = home_url('/' . VendorProfileUrlBase::resolve() . '/');
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

    <form method="post" enctype="multipart/form-data" class="mhm-vendor-form" novalidate>
        <?php wp_nonce_field('mhm_vendor_profile_' . $current_user_id, 'mhm_vendor_profile_nonce'); ?>

        <!-- Profile Photo -->
        <div class="mhm-vendor-form__section">
            <h3><?php esc_html_e('Profile Photo', 'mhm-rentiva'); ?></h3>
            <div class="mhm-vendor-form__field">
                <div class="mhm-vendor-avatar-preview" id="mhm-avatar-preview" style="margin-bottom:10px">
                    <?php if ($current_avatar_url !== '') : ?>
                        <img src="<?php echo esc_url($current_avatar_url); ?>" alt="" style="width:96px;height:96px;border-radius:50%;object-fit:cover" />
                    <?php else : ?>
                        <div style="width:96px;height:96px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:2rem">&#128100;</div>
                    <?php endif; ?>
                </div>
                <input type="file" id="vendor_avatar" name="vendor_avatar" accept="image/jpeg,image/png" />
                <p class="description">
                    <?php esc_html_e('JPG or PNG only. Max 2 MB.', 'mhm-rentiva'); ?>
                </p>
            </div>
        </div>

        <!-- Public URL Slug -->
        <div class="mhm-vendor-form__section">
            <h3><?php esc_html_e('Profile URL', 'mhm-rentiva'); ?></h3>
            <div class="mhm-vendor-form__field mhm-vendor-form__field--wide">
                <label for="vendor_slug"><?php esc_html_e('Public URL Slug', 'mhm-rentiva'); ?></label>
                <div style="display:flex;align-items:center;gap:4px">
                    <span style="color:#6b7280;white-space:nowrap"><?php echo esc_html($url_base); ?></span>
                    <input type="text" id="vendor_slug" name="vendor_slug" value="<?php echo esc_attr($current_slug); ?>" style="width:200px" />
                </div>
                <small><?php esc_html_e('ASCII characters only. Changing creates a 301 redirect from the old URL.', 'mhm-rentiva'); ?></small>
            </div>
        </div>

        <!-- Profile Info -->
        <div class="mhm-vendor-form__section">
            <h3><?php esc_html_e('Profile Information', 'mhm-rentiva'); ?></h3>
            <div class="mhm-vendor-form__row">
                <div class="mhm-vendor-form__field">
                    <label for="vendor_phone"><?php esc_html_e('Phone', 'mhm-rentiva'); ?></label>
                    <input type="tel" id="vendor_phone" name="vendor_phone" value="<?php echo esc_attr($current_phone); ?>" />
                </div>
                <div class="mhm-vendor-form__field">
                    <label for="vendor_city"><?php esc_html_e('Base City', 'mhm-rentiva'); ?></label>
                    <?php echo CityHelper::render_select('vendor_city', 'vendor_city', $current_city); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
            <div class="mhm-vendor-form__field mhm-vendor-form__field--wide">
                <label for="vendor_bio"><?php esc_html_e('Short Bio', 'mhm-rentiva'); ?></label>
                <textarea id="vendor_bio" name="vendor_bio" rows="4" maxlength="<?php echo esc_attr( (string) $bio_max); ?>"><?php echo esc_textarea($current_bio); ?></textarea>
                <?php /* translators: %d: maximum character count */ ?>
                <small><?php echo esc_html(sprintf(__('Max %d characters', 'mhm-rentiva'), $bio_max)); ?></small>
            </div>
        </div>

        <div class="mhm-vendor-form__submit">
            <button type="submit" class="mhm-vendor-form__btn mhm-vendor-form__btn--primary">
                <?php esc_html_e('Save', 'mhm-rentiva'); ?>
            </button>
        </div>
    </form>
</div>

<script>
(function() {
    var input = document.getElementById('vendor_avatar');
    var preview = document.getElementById('mhm-avatar-preview');
    if (!input || !preview) return;
    input.addEventListener('change', function() {
        var file = input.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = '<img src="' + e.target.result + '" alt="" style="width:96px;height:96px;border-radius:50%;object-fit:cover" />';
        };
        reader.readAsDataURL(file);
    });
}());
</script>
