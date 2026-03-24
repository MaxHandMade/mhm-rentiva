<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Profile;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Transfer\Engine\LocationProvider;



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
    }
}
