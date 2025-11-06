<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

use MHMRentiva\Admin\Licensing\Mode;

if (!defined('ABSPATH')) {
    exit;
}

final class AddonManager
{
    /**
     * Maximum number of addons allowed in Lite version
     */
    public const MAX_ADDONS_LITE = 4;

    /**
     * Safe sanitize text field that handles null values
     */
    public static function sanitize_text_field_safe($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        return sanitize_text_field((string) $value);
    }

    public static function register(): void
    {
        add_action('init', [self::class, 'init']);
        add_action('admin_init', [self::class, 'admin_init']);

        // Hook into booking system
        add_filter('mhm_rentiva_booking_data', [self::class, 'process_booking_addons'], 10, 2);
        add_filter('mhm_rentiva_booking_total', [self::class, 'calculate_addon_total'], 10, 2);
        add_action('mhm_rentiva_booking_created', [self::class, 'save_booking_addons'], 10, 2);

        // Admin hooks
        if (is_admin()) {
            // add_action('admin_menu', [self::class, 'add_admin_menu']);        // Removed - was creating menu duplication
            add_filter('mhm_rentiva_admin_submenu_order', [self::class, 'admin_menu_order']);
            
            // AJAX handlers
            add_action('wp_ajax_mhm_bulk_addon_action', [self::class, 'handle_bulk_action']);
            add_action('wp_ajax_mhm_update_addon_price', [self::class, 'handle_update_price']);
        }
    }

    public static function init(): void
    {
        // Register addon post type
        AddonPostType::register();

        // Load textdomain for translations
        load_plugin_textdomain('mhm-rentiva', false, dirname(plugin_basename(__FILE__)) . '/../languages');
    }

    public static function admin_init(): void
    {
        // Register meta boxes
        AddonMeta::register();
        
        // Add price column to WordPress post list
        add_filter('manage_vehicle_addon_posts_columns', [self::class, 'add_price_column']);
        add_action('manage_vehicle_addon_posts_custom_column', [self::class, 'render_price_column'], 10, 2);
        add_filter('manage_edit-vehicle_addon_sortable_columns', [self::class, 'make_price_sortable']);
        
        // Enqueue script and style
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_addon_scripts']);
        
        // Register list table for enhanced functionality
        if (class_exists(AddonListTable::class)) {
            new AddonListTable();
        }
    }

    public static function add_admin_menu(): void
    {
        // WordPress automatically adds post type menus
        // This function is no longer needed
    }

    public static function admin_menu_order(array $menu_order): array
    {
        // Insert addon menu after vehicles but before bookings
        $addon_menu = 'edit.php?post_type=vehicle_addon';
        $vehicles_menu = 'edit.php?post_type=vehicle';
        $bookings_menu = 'edit.php?post_type=vehicle_booking';

        if (in_array($vehicles_menu, $menu_order) && in_array($bookings_menu, $menu_order)) {
            $vehicles_pos = array_search($vehicles_menu, $menu_order);
            $bookings_pos = array_search($bookings_menu, $menu_order);

            if ($vehicles_pos !== false && $bookings_pos !== false && $vehicles_pos < $bookings_pos) {
                array_splice($menu_order, $bookings_pos, 0, [$addon_menu]);
            }
        }

        return $menu_order;
    }

    /**
     * Add price column to WordPress post list
     */
    public static function add_price_column(array $columns): array
    {
        $new_columns = [];
        
        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;
            
            // Add price column after title column
            if ($key === 'title') {
                $new_columns['addon_price'] = __('Price', 'mhm-rentiva');
            }
        }
        
        return $new_columns;
    }

    /**
     * Render price column
     */
    public static function render_price_column(string $column, int $post_id): void
    {
        if ($column === 'addon_price') {
            $price = get_post_meta($post_id, 'addon_price', true);
            $currency_code = self::get_default_currency();
            $currency_symbol = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol($currency_code);
            
            if ($price) {
                $formatted_price = number_format((float) $price, 2, ',', '.') . ' ' . $currency_symbol;
                echo sprintf(
                    '<span class="addon-price-display" data-addon-id="%d" data-price="%s">%s</span>',
                    $post_id,
                    esc_attr($price),
                    esc_html($formatted_price)
                );
            } else {
                echo '<span class="addon-price-display" data-addon-id="' . $post_id . '" data-price="0">0,00 ' . $currency . '</span>';
            }
        }
    }

    /**
     * Make price column sortable
     */
    public static function make_price_sortable(array $columns): array
    {
        $columns['addon_price'] = 'addon_price';
        return $columns;
    }

    /**
     * Enqueue script and style for addon page
     */
    public static function enqueue_addon_scripts(string $hook): void
    {
        global $post_type;
        
        // Only enqueue on addon list page
        if ($hook === 'edit.php' && $post_type === 'vehicle_addon') {
            wp_enqueue_style(
                'mhm-addon-list',
                MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/addon-list.css',
                [],
                MHM_RENTIVA_VERSION
            );
            
            wp_enqueue_script(
                'mhm-addon-list',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/addon-list.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            // Localize JavaScript variables
            wp_localize_script('mhm-addon-list', 'mhm_addon_list_vars', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_addon_list_nonce'),
                'no_items_selected' => __('No items selected.', 'mhm-rentiva'),
                'items_selected' => __('items selected', 'mhm-rentiva'),
                'confirm_enable' => __('Are you sure you want to enable selected additional services?', 'mhm-rentiva'),
                'confirm_disable' => __('Are you sure you want to disable selected additional services?', 'mhm-rentiva'),
                'confirm_delete' => __('Are you sure you want to delete selected additional services? This action cannot be undone.', 'mhm-rentiva'),
                'processing' => __('Processing...', 'mhm-rentiva'),
                'error_occurred' => __('An error occurred. Please try again.', 'mhm-rentiva'),
                'license_warning_title' => __('License Limit', 'mhm-rentiva'),
                'license_warning_message' => sprintf(
                    __('You can add maximum %d additional services in Lite version.', 'mhm-rentiva'),
                    self::MAX_ADDONS_LITE
                ),
                'max_addons_lite' => self::MAX_ADDONS_LITE,
                'auto_refresh' => false,
            ]);
        }
    }


    public static function get_available_addons(): array
    {
        $args = [
            'post_type' => 'vehicle_addon',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => 'addon_enabled',
                    'value' => '1',
                    'compare' => '='
                ]
            ],
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'posts_per_page' => -1
        ];

        $addons = get_posts($args);
        $result = [];

        foreach ($addons as $addon) {
            $result[] = [
                'id' => $addon->ID,
                'title' => $addon->post_title,
                'description' => $addon->post_excerpt ?: $addon->post_content,
                'price' => (float) get_post_meta($addon->ID, 'addon_price', true),
                'required' => (bool) get_post_meta($addon->ID, 'addon_required', true)
            ];
        }

        return $result;
    }

    public static function get_addon_by_id(int $addon_id): ?array
    {
        $addon = get_post($addon_id);

        if (!$addon || $addon->post_type !== 'vehicle_addon') {
            return null;
        }

        return [
            'id' => $addon->ID,
            'title' => $addon->post_title,
            'description' => $addon->post_excerpt ?: $addon->post_content,
            'price' => (float) get_post_meta($addon->ID, 'addon_price', true),
            'enabled' => (bool) get_post_meta($addon->ID, 'addon_enabled', true),
            'required' => (bool) get_post_meta($addon->ID, 'addon_required', true)
        ];
    }

    public static function process_booking_addons(array $booking_data, array $post_data): array
    {
        $selected_addons = $post_data['selected_addons'] ?? [];

        if (!is_array($selected_addons)) {
            $selected_addons = [];
        }

        // Validate selected addons
        $available_addons = self::get_available_addons();
        $available_ids = array_column($available_addons, 'id');
        $selected_addons = array_intersect($selected_addons, $available_ids);

        $booking_data['selected_addons'] = array_map('intval', $selected_addons);

        return $booking_data;
    }

    public static function calculate_addon_total(float $total, array $booking_data): float
    {
        $selected_addons = $booking_data['selected_addons'] ?? [];

        if (!empty($selected_addons) && is_array($selected_addons)) {
            foreach ($selected_addons as $addon_id) {
                $addon = self::get_addon_by_id((int) $addon_id);
                if ($addon) {
                    $total += $addon['price'];
                }
            }
        }

        return $total;
    }

    public static function save_booking_addons(int $booking_id, array $booking_data): void
    {
        $selected_addons = $booking_data['selected_addons'] ?? [];

        if (!empty($selected_addons) && is_array($selected_addons)) {
            // Save selected addons as booking meta
            update_post_meta($booking_id, 'mhm_selected_addons', $selected_addons);

            // Calculate and save addon total
            $addon_total = 0;
            $addon_details = [];

            foreach ($selected_addons as $addon_id) {
                $addon = self::get_addon_by_id((int) $addon_id);
                if ($addon) {
                    $addon_total += $addon['price'];
                    $addon_details[] = [
                        'id' => $addon['id'],
                        'title' => $addon['title'],
                        'price' => $addon['price']
                    ];
                }
            }

            update_post_meta($booking_id, 'mhm_addon_total', $addon_total);
            update_post_meta($booking_id, 'mhm_addon_details', $addon_details);
        }
    }

    public static function can_create_addon(): bool
    {
        if (Mode::isPro()) {
            return true; // Unlimited in Pro
        }

        // Count existing published addons
        $count = wp_count_posts('vehicle_addon')->publish;
        return $count < self::MAX_ADDONS_LITE;
    }

    public static function get_addon_limit_message(): string
    {
        if (Mode::isPro()) {
            return '';
        }

        $count = wp_count_posts('vehicle_addon')->publish;
        
        if ($count >= self::MAX_ADDONS_LITE) {
            return sprintf(
                __('You can add maximum %d additional services in Lite version. Upgrade to Pro version for more additional services.', 'mhm-rentiva'),
                self::MAX_ADDONS_LITE
            );
        }

        $remaining = self::MAX_ADDONS_LITE - $count;
        return sprintf(__('You can add %d more additional services in Lite version.', 'mhm-rentiva'), $remaining);
    }


    /**
     * Handle bulk actions
     */
    public static function handle_bulk_actions(): void
    {
        // Nonce check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhm_addon_list_nonce')) {
            wp_send_json_error(__('Security check failed.', 'mhm-rentiva'));
            return;
        }

        // Permission check
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission for this action.', 'mhm-rentiva'));
        }

        $action = self::sanitize_text_field_safe($_POST['bulk_action'] ?? '');
        $addon_ids = array_map('intval', $_POST['addon_ids'] ?? []);

        if (empty($addon_ids)) {
            wp_send_json_error(__('No additional services selected.', 'mhm-rentiva'));
        }

        $success_count = 0;
        $error_count = 0;

        foreach ($addon_ids as $addon_id) {
            $result = false;

            switch ($action) {
                case 'enable_addons':
                    $result = update_post_meta($addon_id, 'addon_enabled', '1');
                    break;

                case 'disable_addons':
                    $result = update_post_meta($addon_id, 'addon_enabled', '0');
                    break;

                case 'delete':
                    $result = wp_delete_post($addon_id, true);
                    break;

                default:
                    wp_send_json_error(__('Invalid action.', 'mhm-rentiva'));
            }

            if ($result) {
                $success_count++;
            } else {
                $error_count++;
            }
        }

        if ($error_count > 0) {
            /* translators: 1: Successful count, 2: Failed count */
            wp_send_json_error(sprintf(
                __('%1$d additional services processed, %2$d additional services failed.', 'mhm-rentiva'),
                $success_count,
                $error_count
            ));
        } else {
            wp_send_json_success(sprintf(
                __('%d additional services successfully processed.', 'mhm-rentiva'),
                $success_count
            ));
        }
    }


    /**
     * Get currency from settings
     */
    public static function get_default_currency(): string
    {
        // Get currency from general settings
        return \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_currency', 'USD');
    }

    /**
     * Get value from settings (from Settings class)
     */
    private static function get_setting(string $key, $default = null)
    {
        // Use Settings class
        if (class_exists('\MHMRentiva\Admin\Settings\Settings')) {
            return \MHMRentiva\Admin\Settings\Settings::get($key, $default);
        }
        
        // Fallback: direct WordPress options
        return get_option($key, $default);
    }


    /**
     * Check if confirmation is required for addons
     */
    public static function require_confirmation_for_addons(): bool
    {
        return (bool) self::get_setting('mhm_rentiva_addon_require_confirmation', false);
    }

    /**
     * Check if addon prices should be shown in calendar
     */
    public static function show_prices_in_calendar(): bool
    {
        return (bool) self::get_setting('mhm_rentiva_addon_show_prices_in_calendar', true);
    }

    /**
     * Get display order of addons
     */
    public static function get_display_order(): string
    {
        return self::get_setting('mhm_rentiva_addon_display_order', 'menu_order');
    }

    /**
     * Check if prices are tax inclusive
     */
    public static function is_tax_inclusive(): bool
    {
        return (bool) self::get_setting('mhm_rentiva_addon_tax_inclusive', true);
    }

    /**
     * Get tax rate
     */
    public static function get_tax_rate(): float
    {
        return (float) self::get_setting('mhm_rentiva_addon_tax_rate', 20.00);
    }

    /**
     * AJAX: Price update
     */
    public static function handle_update_price(): void
    {
        // Nonce check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhm_addon_list_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            return;
        }

        // Permission check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission for this action.', 'mhm-rentiva')]);
            return;
        }

        $addon_id = (int) ($_POST['addon_id'] ?? 0);
        $price = (float) ($_POST['price'] ?? 0);

        if ($addon_id <= 0) {
            wp_send_json_error(['message' => __('Invalid additional service ID.', 'mhm-rentiva')]);
            return;
        }

        if ($price < 0) {
            wp_send_json_error(['message' => __('Price cannot be negative.', 'mhm-rentiva')]);
            return;
        }

        // Check if addon exists
        $addon = get_post($addon_id);
        if (!$addon || $addon->post_type !== 'vehicle_addon') {
            wp_send_json_error(['message' => __('Additional service not found.', 'mhm-rentiva')]);
            return;
        }

        // Update price
        $result = update_post_meta($addon_id, 'addon_price', $price);

        if ($result !== false) {
            $currency_code = self::get_default_currency();
            $currency_symbol = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol($currency_code);
            wp_send_json_success([
                'message' => __('Price successfully updated.', 'mhm-rentiva'),
                'currency' => $currency_code,
                'formatted_price' => number_format($price, 2, ',', '.') . ' ' . $currency_symbol
            ]);
        } else {
            wp_send_json_error(['message' => __('Error occurred while updating price.', 'mhm-rentiva')]);
        }
    }

}
