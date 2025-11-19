<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

use MHMRentiva\Admin\Core\Utilities\AbstractListTable;
use MHMRentiva\Admin\Addons\AddonManager;

if (!defined('ABSPATH')) {
    exit;
}

final class AddonListTable extends AbstractListTable
{
    public function __construct()
    {
        parent::__construct();
        $this->nonce_action = 'mhm_addon_bulk_action';
        $this->nonce_name = 'mhm_addon_nonce';
        
        // Add statistics cards and styles
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts']);
        add_action('admin_notices', [self::class, 'add_addon_stats_cards']);
    }

    protected function get_singular_name(): string
    {
        return 'addon';
    }

    protected function get_plural_name(): string
    {
        return 'addons';
    }

    protected function get_bulk_action_name(): string
    {
        return 'addon';
    }

    protected function get_data_query_args(): array
    {
        return [
            'post_type' => 'vehicle_addon',
            'post_status' => 'any',
            'posts_per_page' => $this->default_per_page,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
    }

    protected function get_data_from_results($results): array
    {
        return $results; // We use WP_Post objects directly
    }

    protected function get_total_count(): int
    {
        $counts = wp_count_posts('vehicle_addon');
        return ($counts->publish ?? 0) + ($counts->draft ?? 0);
    }

    public function get_columns(): array
    {
        return [
            'cb'             => '<input type="checkbox" />',
            'title'          => __('Title', 'mhm-rentiva'),
            'addon_price'    => __('Price', 'mhm-rentiva'),
            'addon_enabled'  => __('Status', 'mhm-rentiva'),
            'addon_required' => __('Required', 'mhm-rentiva'),
            'date'           => __('Date', 'mhm-rentiva'),
        ];
    }

    public function get_sortable_columns(): array
    {
        return [
            'title'          => ['title', false],
            'addon_price'    => ['addon_price', false],
            'addon_enabled'  => ['addon_enabled', false],
            'date'           => ['date', false],
        ];
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'addon_price':
                $price = get_post_meta($item->ID, 'addon_price', true);
                $currency_code = AddonManager::get_default_currency();
                $currency_symbol = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol($currency_code);
                $formatted_price = number_format((float) $price, 2, ',', '.') . ' ' . $currency_symbol;
                
                // Inline edit için
                return sprintf(
                    '<span class="addon-price-display" data-addon-id="%d" data-price="%s">%s</span>',
                    $item->ID,
                    esc_attr($price),
                    esc_html($formatted_price)
                );

            case 'addon_enabled':
                $enabled = get_post_meta($item->ID, 'addon_enabled', true);
                $status = $enabled ? __('Active', 'mhm-rentiva') : __('Inactive', 'mhm-rentiva');
                $class = $enabled ? 'status-active' : 'status-inactive';
                return '<span class="' . esc_attr($class) . '">' . esc_html($status) . '</span>';

            case 'addon_required':
                $required = get_post_meta($item->ID, 'addon_required', true);
                return $required ? '<span class="dashicons dashicons-yes" style="color: #46b450;"></span>' : '<span class="dashicons dashicons-no" style="color: #dc3232;"></span>';

            default:
                return esc_html($item->$column_name);
        }
    }

    public function column_title($item): string
    {
        $actions = [
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                get_edit_post_link($item->ID),
                __('Edit', 'mhm-rentiva')
            ),
            'duplicate' => sprintf(
                '<a href="%s">%s</a>',
                wp_nonce_url(
                    admin_url('admin-post.php?action=mhm_duplicate_addon&post_id=' . $item->ID),
                    'mhm_duplicate_addon_' . $item->ID
                ),
                __('Duplicate', 'mhm-rentiva')
            ),
            'delete' => sprintf(
                '<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
                get_delete_post_link($item->ID),
                esc_js(__('Are you sure you want to delete this additional service?', 'mhm-rentiva')),
                __('Delete', 'mhm-rentiva')
            ),
        ];

        return sprintf(
            '<strong><a class="row-title" href="%s">%s</a></strong>%s',
            get_edit_post_link($item->ID),
            esc_html($item->post_title),
            $this->row_actions($actions)
        );
    }

    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="addon[]" value="%s" />',
            $item->ID
        );
    }

    public function get_bulk_actions(): array
    {
        return [
            'enable_addons'  => __('Enable', 'mhm-rentiva'),
            'disable_addons' => __('Disable', 'mhm-rentiva'),
            'delete'         => __('Delete', 'mhm-rentiva'),
        ];
    }

    protected function apply_custom_filters(array $args): array
    {
        // Addon status filter
        if (!empty($_REQUEST['addon_status'])) {
            $status = sanitize_text_field((string) ($_REQUEST['addon_status'] ?? ''));
            $args['meta_query'] = $args['meta_query'] ?? [];
            
            if ($status === 'enabled') {
                $args['meta_query'][] = [
                    'key' => 'addon_enabled',
                    'value' => '1',
                    'compare' => '='
                ];
            } elseif ($status === 'disabled') {
                $args['meta_query'][] = [
                    'key' => 'addon_enabled',
                    'value' => '0',
                    'compare' => '='
                ];
            }
        }

        // Category filter
        if (!empty($_REQUEST['addon_category'])) {
            $category = sanitize_text_field((string) ($_REQUEST['addon_category'] ?? ''));
            $args['tax_query'] = [
                [
                    'taxonomy' => 'addon_category',
                    'field'    => 'slug',
                    'terms'    => $category,
                ],
            ];
        }

        // Price range filter
        if (!empty($_REQUEST['price_min']) || !empty($_REQUEST['price_max'])) {
            $args['meta_query'] = $args['meta_query'] ?? [];
            
            $price_query = [
                'key' => 'addon_price',
                'type' => 'NUMERIC',
            ];
            
            if (!empty($_REQUEST['price_min'])) {
                $price_query['value'] = (float) $_REQUEST['price_min'];
                $price_query['compare'] = '>=';
            }
            
            if (!empty($_REQUEST['price_max'])) {
                if (!empty($_REQUEST['price_min'])) {
                    $price_query['value'] = [
                        (float) $_REQUEST['price_min'],
                        (float) $_REQUEST['price_max']
                    ];
                    $price_query['compare'] = 'BETWEEN';
                } else {
                    $price_query['value'] = (float) $_REQUEST['price_max'];
                    $price_query['compare'] = '<=';
                }
            }
            
            $args['meta_query'][] = $price_query;
        }

        return $args;
    }

    public function no_items(): void
    {
        _e('No additional services created yet.', 'mhm-rentiva');
        echo '<br><br>';
        printf(
            '<a href="%s" class="button button-primary">%s</a>',
            admin_url('post-new.php?post_type=vehicle_addon'),
            __('Create First Additional Service', 'mhm-rentiva')
        );
    }

    public function get_views(): array
    {
        $views = [];
        $current = (!empty($_REQUEST['addon_status'])) ? $_REQUEST['addon_status'] : 'all';

        // All
        $class = ($current === 'all') ? ' class="current"' : '';
        $all_url = remove_query_arg('addon_status');
        $all_count = wp_count_posts('vehicle_addon')->publish + wp_count_posts('vehicle_addon')->draft;
        $views['all'] = sprintf(
            '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
            $all_url,
            $class,
            __('All', 'mhm-rentiva'),
            $all_count
        );

        // Published (Enabled)
        $class = ($current === 'enabled') ? ' class="current"' : '';
        $enabled_url = add_query_arg('addon_status', 'enabled');
        $enabled_count = $this->get_addon_count_by_status('1');
        $views['enabled'] = sprintf(
            '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
            $enabled_url,
            $class,
            __('Active', 'mhm-rentiva'),
            $enabled_count
        );

        // Draft (Disabled)
        $class = ($current === 'disabled') ? ' class="current"' : '';
        $disabled_url = add_query_arg('addon_status', 'disabled');
        $disabled_count = $this->get_addon_count_by_status('0');
        $views['disabled'] = sprintf(
            '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
            $disabled_url,
            $class,
            __('Inactive', 'mhm-rentiva'),
            $disabled_count
        );

        return $views;
    }

    private function get_addon_count_by_status(string $status): int
    {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'vehicle_addon'
            AND pm.meta_key = 'addon_enabled'
            AND pm.meta_value = %s
        ", $status));

        return (int) $count;
    }

    protected function render_custom_filters(): void
    {
        echo '<div class="alignleft actions">';

        // License limit warning
        if (!AddonManager::can_create_addon()) {
            echo '<div class="notice notice-warning inline" style="margin: 0 10px 0 0; padding: 5px 10px;">';
            echo '<p style="margin: 0;">' . esc_html(AddonManager::get_addon_limit_message()) . '</p>';
            echo '</div>';
        } else {
            // Add new button
            echo '<a href="' . esc_url(admin_url('post-new.php?post_type=vehicle_addon')) . '" class="button button-primary">';
            echo esc_html__('Add New Additional Service', 'mhm-rentiva');
            echo '</a>';
        }

        echo '</div>';

        // Filtering controls
        echo '<div class="alignright actions">';
        echo '<form method="get" class="filter-form">';
        echo '<input type="hidden" name="post_type" value="vehicle_addon" />';
        
        // Status filter
        $current_status = isset($_GET['addon_status']) ? sanitize_text_field((string) ($_GET['addon_status'] ?? '')) : '';
        echo '<select name="addon_status" class="postform">';
        echo '<option value="">' . esc_html__('All statuses', 'mhm-rentiva') . '</option>';
        echo '<option value="enabled"' . selected($current_status, 'enabled', false) . '>' . esc_html__('Active', 'mhm-rentiva') . '</option>';
        echo '<option value="disabled"' . selected($current_status, 'disabled', false) . '>' . esc_html__('Inactive', 'mhm-rentiva') . '</option>';
        echo '</select>';

        // Category filter
        $current_category = isset($_GET['addon_category']) ? sanitize_text_field((string) ($_GET['addon_category'] ?? '')) : '';
        echo '<select name="addon_category" class="postform">';
        echo '<option value="">' . esc_html__('All categories', 'mhm-rentiva') . '</option>';
        
        $categories = get_terms([
            'taxonomy' => 'addon_category',
            'hide_empty' => false,
        ]);
        
        foreach ($categories as $category) {
            $selected = selected($current_category, $category->slug, false);
            echo '<option value="' . esc_attr($category->slug) . '" ' . $selected . '>' . esc_html($category->name) . '</option>';
        }
        echo '</select>';

        // Price range filter
        $current_price_min = isset($_GET['price_min']) ? sanitize_text_field((string) ($_GET['price_min'] ?? '')) : '';
        $current_price_max = isset($_GET['price_max']) ? sanitize_text_field((string) ($_GET['price_max'] ?? '')) : '';
        echo '<input type="number" name="price_min" placeholder="' . esc_attr__('Min price', 'mhm-rentiva') . '" value="' . esc_attr($current_price_min) . '" class="postform" style="width: 100px;" />';
        echo '<span style="margin: 0 5px;">-</span>';
        echo '<input type="number" name="price_max" placeholder="' . esc_attr__('Max price', 'mhm-rentiva') . '" value="' . esc_attr($current_price_max) . '" class="postform" style="width: 100px;" />';

        echo '<input type="submit" class="button" value="' . esc_attr__('Filter', 'mhm-rentiva') . '" />';
        echo '<a href="' . esc_url(admin_url('edit.php?post_type=vehicle_addon')) . '" class="button">' . esc_html__('Clear', 'mhm-rentiva') . '</a>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_scripts(string $hook): void
    {
        global $post_type;
        
        // Only enqueue on addon list page
        if ($hook === 'edit.php' && $post_type === 'vehicle_addon') {
            wp_enqueue_style(
                'mhm-stats-cards',
                MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/stats-cards.css',
                [],
                MHM_RENTIVA_VERSION
            );
            
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
                    /* translators: %d placeholder. */
                    __('You can add maximum %d additional services in Lite version.', 'mhm-rentiva'),
                    AddonManager::MAX_ADDONS_LITE
                ),
                'max_addons_lite' => AddonManager::MAX_ADDONS_LITE,
                'auto_refresh' => false,
            ]);
        }
    }

    /**
     * Add addon statistics cards
     */
    public static function add_addon_stats_cards(): void
    {
        global $post_type, $pagenow;
        
        // Only show on addon list page
        if ($pagenow !== 'edit.php' || $post_type !== 'vehicle_addon') {
            return;
        }
        
        $stats = self::get_addon_stats();
        
        ?>
        <div class="mhm-stats-cards">
            <div class="stats-grid">
                <!-- Total Additional Services -->
                <div class="stat-card stat-card-total-addons">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-plus-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($stats['total_addons']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Total Additional Services', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text"><?php esc_html_e('All services', 'mhm-rentiva'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Active Additional Services -->
                <div class="stat-card stat-card-active-addons">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($stats['active_addons']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Active Services', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text trend-up"><?php echo esc_html($stats['active_percentage']); ?>% <?php esc_html_e('active', 'mhm-rentiva'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Average Price -->
                <div class="stat-card stat-card-avg-price">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($stats['avg_price']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Average Price', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text"><?php esc_html_e('All services', 'mhm-rentiva'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Total Value -->
                <div class="stat-card stat-card-total-value">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($stats['total_value']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Total Value', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text"><?php esc_html_e('All prices', 'mhm-rentiva'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get addon statistics
     */
    private static function get_addon_stats(): array
    {
        global $wpdb;
        
        // Total number of addons
        $total_addons = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
            'vehicle_addon', 'publish'
        ));
        
        // Number of active addons
        $active_addons = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s AND p.post_status = %s
             AND pm.meta_key = 'addon_enabled' AND pm.meta_value = '1'",
            'vehicle_addon', 'publish'
        ));
        
        // Average price
        $avg_price = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(CAST(pm.meta_value AS DECIMAL(10,2)))
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s AND p.post_status = %s
             AND pm.meta_key = 'addon_price'",
            'vehicle_addon', 'publish'
        ));
        
        // Total value
        $total_value = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2)))
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s AND p.post_status = %s
             AND pm.meta_key = 'addon_price'",
            'vehicle_addon', 'publish'
        ));
        
        // Currency
        $currency_code = AddonManager::get_default_currency();
        $currency_symbol = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol($currency_code);
        
        // Active percentage
        $active_percentage = $total_addons > 0 ? round(($active_addons / $total_addons) * 100) : 0;
        
        return [
            'total_addons' => $total_addons,
            'active_addons' => $active_addons,
            'active_percentage' => $active_percentage,
            'avg_price' => number_format($avg_price, 2, ',', '.') . ' ' . $currency_symbol,
            'total_value' => number_format($total_value, 2, ',', '.') . ' ' . $currency_symbol,
        ];
    }
}
