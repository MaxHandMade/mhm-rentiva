<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Transfer;

if (!defined('ABSPATH')) {
    exit;
}

final class TransferAdmin
{
    /**
     * Register hooks
     */
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'add_menu_pages']);
        // Form handlers
        add_action('admin_post_mhm_save_location', [self::class, 'handle_save_location']);
        add_action('admin_post_mhm_delete_location', [self::class, 'handle_delete_location']);
        add_action('admin_post_mhm_save_route', [self::class, 'handle_save_route']);
        add_action('admin_post_mhm_delete_route', [self::class, 'handle_delete_route']);
        add_action('admin_post_mhm_save_transfer_settings', [self::class, 'handle_save_transfer_settings']);

        // Register Meta Box
        VehicleTransferMetaBox::register();

        // Register Integration
        Integration\TransferCartIntegration::register();
        Integration\TransferBookingHandler::register();
    }

    /**
     * Add menu pages
     */
    public static function add_menu_pages(): void
    {
        add_submenu_page(
            'mhm-rentiva',
            __('Transfer Locations', 'mhm-rentiva'),
            __('Transfer Locations', 'mhm-rentiva'),
            'manage_options',
            'mhm-rentiva-transfer-locations',
            [self::class, 'render_locations_page']
        );

        add_submenu_page(
            'mhm-rentiva',
            __('Transfer Routes', 'mhm-rentiva'),
            __('Transfer Routes', 'mhm-rentiva'),
            'manage_options',
            'mhm-rentiva-transfer-routes',
            [self::class, 'render_routes_page']
        );

        add_submenu_page(
            'mhm-rentiva',
            __('Transfer Settings', 'mhm-rentiva'),
            __('Transfer Settings', 'mhm-rentiva'),
            'manage_options',
            'mhm-rentiva-transfer-settings',
            [self::class, 'render_settings_page']
        );
    }

    /**
     * Render Settings Page
     */
    public static function render_settings_page(): void
    {
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'mhm-rentiva') . '</p></div>';
        }

        $deposit_type = get_option('mhm_transfer_deposit_type', 'full_payment');
        $deposit_rate = get_option('mhm_transfer_deposit_rate', '20');
?>
        <div class="wrap">
            <h1><?php echo esc_html__('Transfer Settings', 'mhm-rentiva'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mhm_save_transfer_settings">
                <?php wp_nonce_field('mhm_save_transfer_settings_nonce', 'mhm_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="mhm_transfer_deposit_type"><?php echo esc_html__('Payment Type', 'mhm-rentiva'); ?></label></th>
                        <td>
                            <select name="mhm_transfer_deposit_type" id="mhm_transfer_deposit_type">
                                <option value="full_payment" <?php selected($deposit_type, 'full_payment'); ?>><?php echo esc_html__('Full Payment Required', 'mhm-rentiva'); ?></option>
                                <option value="percentage" <?php selected($deposit_type, 'percentage'); ?>><?php echo esc_html__('Deposit (Percentage)', 'mhm-rentiva'); ?></option>
                            </select>
                            <p class="description"><?php echo esc_html__('Select how customers should pay for transfers.', 'mhm-rentiva'); ?></p>
                        </td>
                    </tr>
                    <tr id="deposit_rate_row" style="<?php echo ($deposit_type === 'percentage') ? '' : 'display:none;'; ?>">
                        <th scope="row"><label for="mhm_transfer_deposit_rate"><?php echo esc_html__('Deposit Rate (%)', 'mhm-rentiva'); ?></label></th>
                        <td>
                            <input name="mhm_transfer_deposit_rate" type="number" id="mhm_transfer_deposit_rate" value="<?php echo esc_attr($deposit_rate); ?>" class="regular-text" min="1" max="100" step="1">
                            <p class="description"><?php echo esc_html__('Percentage of total price to be paid as deposit.', 'mhm-rentiva'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
            <script>
                jQuery(document).ready(function($) {
                    $('#mhm_transfer_deposit_type').on('change', function() {
                        if ($(this).val() === 'percentage') {
                            $('#deposit_rate_row').show();
                        } else {
                            $('#deposit_rate_row').hide();
                        }
                    });
                });
            </script>
        </div>
    <?php
    }

    /**
     * Handle save transfer settings
     */
    public static function handle_save_transfer_settings(): void
    {
        if (!current_user_can('manage_options') || !isset($_POST['mhm_nonce']) || !wp_verify_nonce($_POST['mhm_nonce'], 'mhm_save_transfer_settings_nonce')) {
            wp_die('Unauthorized');
        }

        update_option('mhm_transfer_deposit_type', sanitize_text_field($_POST['mhm_transfer_deposit_type']));
        update_option('mhm_transfer_deposit_rate', intval($_POST['mhm_transfer_deposit_rate']));

        wp_redirect(admin_url('admin.php?page=mhm-rentiva-transfer-settings&updated=true'));
        exit;
    }

    // ... existing list render methods ...

    /**
     * Render Locations Page
     */
    public static function render_locations_page(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
        $locations = $wpdb->get_results("SELECT * FROM $table_name ORDER BY priority ASC, name ASC");
        $edit_location = null;

        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
            $edit_location = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['id'])));
        }

    ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Transfer Locations', 'mhm-rentiva'); ?></h1>
            <hr class="wp-header-end">

            <div id="col-container" class="wp-clearfix">
                <!-- Add/Edit Form -->
                <div id="col-left">
                    <div class="col-wrap">
                        <div class="form-wrap">
                            <h2><?php echo $edit_location ? esc_html__('Edit Location', 'mhm-rentiva') : esc_html__('Add New Location', 'mhm-rentiva'); ?></h2>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="mhm_save_location">
                                <?php wp_nonce_field('mhm_save_location_nonce', 'mhm_nonce'); ?>
                                <?php if ($edit_location): ?>
                                    <input type="hidden" name="id" value="<?php echo esc_attr($edit_location->id); ?>">
                                <?php endif; ?>

                                <div class="form-field">
                                    <label for="name"><?php echo esc_html__('Name', 'mhm-rentiva'); ?></label>
                                    <input name="name" id="name" type="text" value="<?php echo $edit_location ? esc_attr($edit_location->name) : ''; ?>" required>
                                </div>

                                <div class="form-field">
                                    <label for="type"><?php echo esc_html__('Type', 'mhm-rentiva'); ?></label>
                                    <select name="type" id="type">
                                        <?php
                                        $types = ['airport' => 'Airport', 'hotel' => 'Hotel', 'generic' => 'Generic'];
                                        foreach ($types as $key => $label) {
                                            echo '<option value="' . esc_attr($key) . '" ' . selected($edit_location ? $edit_location->type : '', $key, false) . '>' . esc_html($label) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="form-field">
                                    <label for="priority"><?php echo esc_html__('Priority', 'mhm-rentiva'); ?></label>
                                    <input name="priority" id="priority" type="number" value="<?php echo $edit_location ? esc_attr($edit_location->priority) : '0'; ?>">
                                </div>

                                <?php submit_button($edit_location ? __('Update Location', 'mhm-rentiva') : __('Add Location', 'mhm-rentiva')); ?>
                                <?php if ($edit_location): ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-transfer-locations')); ?>" class="button"><?php echo esc_html__('Cancel', 'mhm-rentiva'); ?></a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- List Table -->
                <div id="col-right">
                    <div class="col-wrap">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Name', 'mhm-rentiva'); ?></th>
                                    <th><?php echo esc_html__('Type', 'mhm-rentiva'); ?></th>
                                    <th><?php echo esc_html__('Priority', 'mhm-rentiva'); ?></th>
                                    <th><?php echo esc_html__('Actions', 'mhm-rentiva'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($locations)): ?>
                                    <?php foreach ($locations as $location): ?>
                                        <tr>
                                            <td><strong><?php echo esc_html($location->name); ?></strong></td>
                                            <td><?php echo esc_html(ucfirst($location->type)); ?></td>
                                            <td><?php echo esc_html($location->priority); ?></td>
                                            <td>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-transfer-locations&action=edit&id=' . $location->id)); ?>"><?php echo esc_html__('Edit', 'mhm-rentiva'); ?></a> |
                                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mhm_delete_location&id=' . $location->id), 'mhm_delete_location_nonce')); ?>" onclick="return confirm('<?php echo esc_js(__('Are you sure?', 'mhm-rentiva')); ?>');" style="color: #a00;"><?php echo esc_html__('Delete', 'mhm-rentiva'); ?></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4"><?php echo esc_html__('No locations found.', 'mhm-rentiva'); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Render Routes Page
     */
    public static function render_routes_page(): void
    {
        global $wpdb;
        $table_routes = $wpdb->prefix . 'mhm_rentiva_transfer_routes';
        $table_locations = $wpdb->prefix . 'mhm_rentiva_transfer_locations';

        $routes = $wpdb->get_results("
            SELECT r.*, 
                   l1.name as origin_name, 
                   l2.name as dest_name 
            FROM $table_routes r
            LEFT JOIN $table_locations l1 ON r.origin_id = l1.id
            LEFT JOIN $table_locations l2 ON r.destination_id = l2.id
            ORDER BY r.id DESC
        ");

        $locations = $wpdb->get_results("SELECT id, name FROM $table_locations ORDER BY name ASC");

        $edit_route = null;
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
            $edit_route = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_routes WHERE id = %d", intval($_GET['id'])));
        }
    ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Transfer Routes', 'mhm-rentiva'); ?></h1>
            <hr class="wp-header-end">

            <div id="col-container" class="wp-clearfix">
                <!-- Add/Edit Form -->
                <div id="col-left">
                    <div class="col-wrap">
                        <div class="form-wrap">
                            <h2><?php echo $edit_route ? esc_html__('Edit Route', 'mhm-rentiva') : esc_html__('Add New Route', 'mhm-rentiva'); ?></h2>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="mhm_save_route">
                                <?php wp_nonce_field('mhm_save_route_nonce', 'mhm_nonce'); ?>
                                <?php if ($edit_route): ?>
                                    <input type="hidden" name="id" value="<?php echo esc_attr($edit_route->id); ?>">
                                <?php endif; ?>

                                <!-- Origin -->
                                <div class="form-field">
                                    <label for="origin_id"><?php echo esc_html__('Origin', 'mhm-rentiva'); ?></label>
                                    <select name="origin_id" id="origin_id" required>
                                        <option value=""><?php echo esc_html__('Select Origin', 'mhm-rentiva'); ?></option>
                                        <?php foreach ($locations as $loc): ?>
                                            <option value="<?php echo esc_attr($loc->id); ?>" <?php selected($edit_route ? $edit_route->origin_id : '', $loc->id); ?>><?php echo esc_html($loc->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Destination -->
                                <div class="form-field">
                                    <label for="destination_id"><?php echo esc_html__('Destination', 'mhm-rentiva'); ?></label>
                                    <select name="destination_id" id="destination_id" required>
                                        <option value=""><?php echo esc_html__('Select Destination', 'mhm-rentiva'); ?></option>
                                        <?php foreach ($locations as $loc): ?>
                                            <option value="<?php echo esc_attr($loc->id); ?>" <?php selected($edit_route ? $edit_route->destination_id : '', $loc->id); ?>><?php echo esc_html($loc->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Distance & Duration -->
                                <div class="form-field">
                                    <label for="distance_km"><?php echo esc_html__('Distance (KM)', 'mhm-rentiva'); ?></label>
                                    <input name="distance_km" id="distance_km" type="number" step="0.1" value="<?php echo $edit_route ? esc_attr($edit_route->distance_km) : ''; ?>" required>
                                </div>
                                <div class="form-field">
                                    <label for="duration_min"><?php echo esc_html__('Duration (Minutes)', 'mhm-rentiva'); ?></label>
                                    <input name="duration_min" id="duration_min" type="number" value="<?php echo $edit_route ? esc_attr($edit_route->duration_min) : ''; ?>" required>
                                </div>

                                <!-- Pricing Method -->
                                <div class="form-field">
                                    <label for="pricing_method"><?php echo esc_html__('Pricing Method', 'mhm-rentiva'); ?></label>
                                    <select name="pricing_method" id="pricing_method" onchange="togglePricingFields(this.value)">
                                        <option value="fixed" <?php selected($edit_route ? $edit_route->pricing_method : '', 'fixed'); ?>><?php echo esc_html__('Fixed Price', 'mhm-rentiva'); ?></option>
                                        <option value="calculated" <?php selected($edit_route ? $edit_route->pricing_method : '', 'calculated'); ?>><?php echo esc_html__('Distance Based (KM)', 'mhm-rentiva'); ?></option>
                                    </select>
                                </div>

                                <!-- Pricing Fields -->
                                <div class="form-field">
                                    <label for="base_price"><span id="base_price_label"><?php echo esc_html__('Price', 'mhm-rentiva'); ?></span></label>
                                    <input name="base_price" id="base_price" type="number" step="0.01" value="<?php echo $edit_route ? esc_attr($edit_route->base_price) : ''; ?>" required>
                                </div>

                                <div class="form-field" id="min_price_field" style="<?php echo ($edit_route && $edit_route->pricing_method === 'calculated') ? '' : 'display:none;'; ?>">
                                    <label for="min_price"><?php echo esc_html__('Minimum Price', 'mhm-rentiva'); ?></label>
                                    <input name="min_price" id="min_price" type="number" step="0.01" value="<?php echo $edit_route ? esc_attr($edit_route->min_price) : ''; ?>">
                                </div>

                                <?php submit_button($edit_route ? __('Update Route', 'mhm-rentiva') : __('Add Route', 'mhm-rentiva')); ?>
                                <?php if ($edit_route): ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-transfer-routes')); ?>" class="button"><?php echo esc_html__('Cancel', 'mhm-rentiva'); ?></a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- List Table -->
                <div id="col-right">
                    <div class="col-wrap">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Route', 'mhm-rentiva'); ?></th>
                                    <th><?php echo esc_html__('Distance/Time', 'mhm-rentiva'); ?></th>
                                    <th><?php echo esc_html__('Pricing', 'mhm-rentiva'); ?></th>
                                    <th><?php echo esc_html__('Actions', 'mhm-rentiva'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($routes)): ?>
                                    <?php foreach ($routes as $route): ?>
                                        <tr>
                                            <td>
                                                <?php echo esc_html($route->origin_name); ?> &rarr; <?php echo esc_html($route->dest_name); ?>
                                            </td>
                                            <td>
                                                <?php echo esc_html($route->distance_km); ?> km <br>
                                                <small><?php echo esc_html($route->duration_min); ?> min</small>
                                            </td>
                                            <td>
                                                <?php if ($route->pricing_method === 'fixed'): ?>
                                                    <span class="badge badge-primary">Fixed</span>
                                                    <strong><?php echo wc_price($route->base_price); ?></strong>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">KM</span>
                                                    <?php echo wc_price($route->base_price); ?> / km <br>
                                                    Min: <?php echo wc_price($route->min_price); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-transfer-routes&action=edit&id=' . $route->id)); ?>"><?php echo esc_html__('Edit', 'mhm-rentiva'); ?></a> |
                                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mhm_delete_route&id=' . $route->id), 'mhm_delete_route_nonce')); ?>" onclick="return confirm('<?php echo esc_js(__('Are you sure?', 'mhm-rentiva')); ?>');" style="color: #a00;"><?php echo esc_html__('Delete', 'mhm-rentiva'); ?></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4"><?php echo esc_html__('No routes found.', 'mhm-rentiva'); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <script>
                function togglePricingFields(method) {
                    const label = document.getElementById('base_price_label');
                    const minField = document.getElementById('min_price_field');
                    if (method === 'calculated') {
                        label.textContent = '<?php echo esc_js(__('Price per KM', 'mhm-rentiva')); ?>';
                        minField.style.display = 'block';
                    } else {
                        label.textContent = '<?php echo esc_js(__('Total Price', 'mhm-rentiva')); ?>';
                        minField.style.display = 'none';
                    }
                }
            </script>
        </div>
<?php
    }

    // --- FORM HANDLERS ---

    public static function handle_save_location(): void
    {
        if (!current_user_can('manage_options') || !isset($_POST['mhm_nonce']) || !wp_verify_nonce($_POST['mhm_nonce'], 'mhm_save_location_nonce')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mhm_rentiva_transfer_locations';

        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'type' => sanitize_text_field($_POST['type']),
            'priority' => intval($_POST['priority']),
        ];

        if (!empty($_POST['id'])) {
            $wpdb->update($table_name, $data, ['id' => intval($_POST['id'])]);
        } else {
            $wpdb->insert($table_name, $data);
        }

        wp_redirect(admin_url('admin.php?page=mhm-rentiva-transfer-locations&updated=true'));
        exit;
    }

    public static function handle_delete_location(): void
    {
        if (!current_user_can('manage_options') || !isset($_GET['mhm_delete_location_nonce']) || !wp_verify_nonce($_GET['mhm_delete_location_nonce'], 'mhm_delete_location_nonce')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
        $wpdb->delete($table_name, ['id' => intval($_GET['id'])]);

        wp_redirect(admin_url('admin.php?page=mhm-rentiva-transfer-locations&deleted=true'));
        exit;
    }

    public static function handle_save_route(): void
    {
        if (!current_user_can('manage_options') || !isset($_POST['mhm_nonce']) || !wp_verify_nonce($_POST['mhm_nonce'], 'mhm_save_route_nonce')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mhm_rentiva_transfer_routes';

        $data = [
            'origin_id' => intval($_POST['origin_id']),
            'destination_id' => intval($_POST['destination_id']),
            'distance_km' => floatval($_POST['distance_km']),
            'duration_min' => intval($_POST['duration_min']),
            'pricing_method' => sanitize_text_field($_POST['pricing_method']),
            'base_price' => floatval($_POST['base_price']),
            'min_price' => floatval($_POST['min_price']),
        ];

        if (!empty($_POST['id'])) {
            $wpdb->update($table_name, $data, ['id' => intval($_POST['id'])]);
        } else {
            $wpdb->insert($table_name, $data);
        }

        wp_redirect(admin_url('admin.php?page=mhm-rentiva-transfer-routes&updated=true'));
        exit;
    }

    public static function handle_delete_route(): void
    {
        if (!current_user_can('manage_options') || !isset($_GET['mhm_delete_route_nonce']) || !wp_verify_nonce($_GET['mhm_delete_route_nonce'], 'mhm_delete_route_nonce')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mhm_rentiva_transfer_routes';
        $wpdb->delete($table_name, ['id' => intval($_GET['id'])]);

        wp_redirect(admin_url('admin.php?page=mhm-rentiva-transfer-routes&deleted=true'));
        exit;
    }
}
