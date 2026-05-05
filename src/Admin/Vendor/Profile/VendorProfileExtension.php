<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Profile;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Transfer\Engine\LocationProvider;
use MHMRentiva\Admin\Vehicle\ReliabilityScoreCalculator;



/**
 * Vendor Profile Extension
 * 
 * Adds default location selector to user profile pages for vendors.
 */
final class VendorProfileExtension
{
    /**
     * Register hooks
     */
    public static function register(): void
    {
        add_action('show_user_profile', array(self::class, 'render_location_field'));
        add_action('edit_user_profile', array(self::class, 'render_location_field'));

        add_action('personal_options_update', array(self::class, 'save_location_field'));
        add_action('edit_user_profile_update', array(self::class, 'save_location_field'));

        add_action('admin_enqueue_scripts', array(self::class, 'enqueue_admin_assets'));

        // Wire vendor badge eligibility's completed-bookings filter to the
        // ReliabilityScoreCalculator helper. Without this, the badge defaults
        // to STATUS_NEW for every vendor (filter has no callback in production).
        add_filter(
            'mhm_rentiva_vendor_completed_bookings_count',
            array(ReliabilityScoreCalculator::class, 'count_completed_bookings_for_filter'),
            10,
            2
        );
    }

    /**
     * Render location selector on user profile
     * 
     * @param \WP_User $user
     */
    public static function render_location_field(\WP_User $user): void
    {
        // Only show if user can be a vendor or managed options
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        $current_location = get_user_meta($user->ID, MetaKeys::VENDOR_LOCATION_ID, true);
        $locations = LocationProvider::get_locations('rental');
        ?>
        <h3><?php esc_html_e('MHM Rentiva Vendor Settings', 'mhm-rentiva'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="mhm_rentiva_vendor_location"><?php esc_html_e('Default Branch/Location', 'mhm-rentiva'); ?></label></th>
                <td>
                    <select name="mhm_rentiva_vendor_location_id" id="mhm_rentiva_vendor_location">
                        <option value=""><?php esc_html_e('Use Global Default', 'mhm-rentiva'); ?></option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo esc_attr((string)$loc->id); ?>" <?php selected((string)$current_location, (string)$loc->id); ?>>
                                <?php echo esc_html($loc->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e('This location will be used as the default for all vehicles owned by this user, unless a specific location is set for the vehicle.', 'mhm-rentiva'); ?>
                    </p>
                </td>
            </tr>
            <?php
            $current_avatar = (int) get_user_meta($user->ID, MetaKeys::VENDOR_AVATAR_ID, true);
            $avatar_url = $current_avatar ? wp_get_attachment_image_url($current_avatar, 'thumbnail') : '';
            ?>
            <tr>
                <th><label for="mhm_rentiva_vendor_avatar"><?php esc_html_e('Vendor Avatar', 'mhm-rentiva'); ?></label></th>
                <td>
                    <input type="hidden" name="mhm_rentiva_vendor_avatar_id" id="mhm_rentiva_vendor_avatar_id" value="<?php echo esc_attr((string)$current_avatar); ?>" />
                    <div class="mhm-vendor-avatar-preview" style="margin-bottom:8px">
                        <?php if ($avatar_url): ?>
                            <img src="<?php echo esc_url($avatar_url); ?>" alt="" style="max-width:96px;border-radius:50%" />
                        <?php endif; ?>
                    </div>
                    <button type="button" class="button mhm-vendor-avatar-upload"><?php esc_html_e('Choose image', 'mhm-rentiva'); ?></button>
                    <button type="button" class="button mhm-vendor-avatar-remove" <?php echo $current_avatar ? '' : 'style="display:none"'; ?>><?php esc_html_e('Remove', 'mhm-rentiva'); ?></button>
                    <p class="description"><?php esc_html_e('Custom profile picture shown on the public vendor profile page. Leave empty to use Gravatar.', 'mhm-rentiva'); ?></p>
                </td>
            </tr>
            <?php
            $current_slug = (string) get_user_meta($user->ID, MetaKeys::VENDOR_SLUG, true);
            ?>
            <tr>
                <th><label for="mhm_rentiva_vendor_slug"><?php esc_html_e('Public Profile URL Slug', 'mhm-rentiva'); ?></label></th>
                <td>
                    <input type="text" name="mhm_rentiva_vendor_slug" id="mhm_rentiva_vendor_slug" value="<?php echo esc_attr($current_slug); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e('ASCII-only slug used in the public profile URL. Leave empty to auto-generate from display name. Changing this creates a 301 redirect from the old URL.', 'mhm-rentiva'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save location field
     * 
     * @param int $user_id
     */
    public static function save_location_field(int $user_id): void
    {
        if (!check_admin_referer('update-user_' . $user_id)) {
            return;
        }

        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        if (isset($_POST['mhm_rentiva_vendor_location_id'])) {
            $location_id = absint($_POST['mhm_rentiva_vendor_location_id']);
            if ($location_id > 0) {
                update_user_meta($user_id, MetaKeys::VENDOR_LOCATION_ID, $location_id);
            } else {
                delete_user_meta($user_id, MetaKeys::VENDOR_LOCATION_ID);
            }
        }

        // Save avatar
        if (isset($_POST['mhm_rentiva_vendor_avatar_id'])) {
            $avatar_id = absint($_POST['mhm_rentiva_vendor_avatar_id']);
            if ($avatar_id > 0) {
                update_user_meta($user_id, MetaKeys::VENDOR_AVATAR_ID, $avatar_id);
            } else {
                delete_user_meta($user_id, MetaKeys::VENDOR_AVATAR_ID);
            }
        }

        // Save slug (sanitized via VendorSlugManager later — for now plain ASCII pass)
        if (isset($_POST['mhm_rentiva_vendor_slug'])) {
            $raw_slug = sanitize_text_field(wp_unslash($_POST['mhm_rentiva_vendor_slug']));
            $clean_slug = $raw_slug === '' ? '' : sanitize_title(remove_accents($raw_slug));
            $existing = (string) get_user_meta($user_id, MetaKeys::VENDOR_SLUG, true);
            if ($clean_slug !== $existing) {
                if ($existing !== '') {
                    // Append old slug to history (Phase 2 will handle the helper)
                    $history = (array) get_user_meta($user_id, MetaKeys::VENDOR_SLUG_HISTORY, true);
                    array_unshift($history, $existing);
                    $history = array_slice(array_values(array_unique($history)), 0, 10);
                    update_user_meta($user_id, MetaKeys::VENDOR_SLUG_HISTORY, $history);
                }
                if ($clean_slug !== '') {
                    update_user_meta($user_id, MetaKeys::VENDOR_SLUG, $clean_slug);
                } else {
                    delete_user_meta($user_id, MetaKeys::VENDOR_SLUG);
                }
            }
        }
    }

    /**
     * Enqueue admin assets for vendor avatar media uploader
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_admin_assets(string $hook): void
    {
        if ($hook !== 'profile.php' && $hook !== 'user-edit.php') {
            return;
        }
        wp_enqueue_media();
        wp_add_inline_script('jquery-core', <<<'JS'
            jQuery(function($){
                $('.mhm-vendor-avatar-upload').on('click', function(e){
                    e.preventDefault();
                    var $btn = $(this);
                    var frame = wp.media({title: 'Vendor Avatar', multiple: false, library: {type: 'image'}});
                    frame.on('select', function(){
                        var att = frame.state().get('selection').first().toJSON();
                        $('#mhm_rentiva_vendor_avatar_id').val(att.id);
                        $btn.closest('td').find('.mhm-vendor-avatar-preview').html('<img src="' + att.url + '" style="max-width:96px;border-radius:50%" />');
                        $btn.siblings('.mhm-vendor-avatar-remove').show();
                    });
                    frame.open();
                });
                $('.mhm-vendor-avatar-remove').on('click', function(e){
                    e.preventDefault();
                    var $btn = $(this);
                    $('#mhm_rentiva_vendor_avatar_id').val('');
                    $btn.closest('td').find('.mhm-vendor-avatar-preview').html('');
                    $btn.hide();
                });
            });
        JS);
    }
}
