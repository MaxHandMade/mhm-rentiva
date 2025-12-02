<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\ListTable;

use MHMRentiva\Admin\Settings\Settings;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Booking\Core\Status;

if (!defined('ABSPATH')) {
    exit;
}

final class BookingColumns
{
    /**
     * Flag to prevent infinite loop in title filter
     */
    private static $in_title_filter = false;

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
        add_filter('manage_vehicle_booking_posts_columns', [self::class, 'columns']);
        add_action('manage_vehicle_booking_posts_custom_column', [self::class, 'render'], 10, 2);
        add_filter('manage_edit-vehicle_booking_sortable_columns', [self::class, 'sortable']);
        add_action('pre_get_posts', [self::class, 'apply_sorting']);
        add_action('restrict_manage_posts', [self::class, 'status_filter']);
        add_action('pre_get_posts', [self::class, 'apply_status_filter']);
        add_action('restrict_manage_posts', [self::class, 'booking_id_filter']);
        add_action('restrict_manage_posts', [self::class, 'license_plate_filter']);
        add_action('pre_get_posts', [self::class, 'apply_custom_filters']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts']);
        add_filter('the_title', [self::class, 'modify_booking_title'], 10, 2);
        // Optional UI extras; now enabled by default (safe after export form fix)
        if (apply_filters('mhm_rentiva_enable_booking_admin_extras', true)) {
            add_action('admin_notices', [self::class, 'add_booking_stats_cards']);
            add_action('admin_notices', [self::class, 'add_booking_calendar']);
        }
    }

    public static function columns(array $cols): array
    {
        // Keep title; move date column to the end
        $date = $cols['date'] ?? null;
        unset($cols['date']);

        $cols['mhm_booking_id'] = __('Booking ID', 'mhm-rentiva');
        $cols['mhm_booking_vehicle'] = __('Vehicle', 'mhm-rentiva');
        $cols['mhm_booking_license_plate'] = __('License Plate', 'mhm-rentiva');
        $cols['mhm_booking_dates'] = __('Dates', 'mhm-rentiva');
        $cols['mhm_booking_days'] = __('Days', 'mhm-rentiva');
        $cols['mhm_booking_total'] = __('Total', 'mhm-rentiva');
        $cols['mhm_booking_deposit'] = __('Deposit Amount', 'mhm-rentiva');
        $cols['mhm_booking_remaining'] = __('Remaining Amount', 'mhm-rentiva');
        $cols['mhm_booking_status'] = __('Status', 'mhm-rentiva');
        $cols['mhm_booking_payment'] = __('Payment', 'mhm-rentiva');
        $cols['mhm_booking_type'] = __('Booking Type', 'mhm-rentiva');

        if ($date !== null) {
            $cols['date'] = $date;
        }
        return $cols;
    }

    public static function enqueue_scripts(string $hook): void
    {
        global $post_type;
        
        // Load only on booking list page
        if ($hook === 'edit.php' && $post_type === 'vehicle_booking') {
            wp_enqueue_style(
                'mhm-booking-list',
                MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/booking-list.css',
                [],
                MHM_RENTIVA_VERSION
            );
            
            wp_enqueue_style(
                'mhm-booking-calendar',
                MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/booking-calendar.css',
                [],
                MHM_RENTIVA_VERSION
            );
            
            // Load statistics cards CSS
            wp_enqueue_style(
                'mhm-stats-cards',
                MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/stats-cards.css',
                [],
                MHM_RENTIVA_VERSION
            );
            
            
            // Load simple calendar CSS
            wp_enqueue_style(
                'mhm-simple-calendars',
                MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/simple-calendars.css',
                [],
                MHM_RENTIVA_VERSION
            );
            
            // Calendar JavaScript file
            wp_enqueue_script(
                'mhm-booking-calendar',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/booking-calendar.js',
                [],
                MHM_RENTIVA_VERSION,
                true
            );
            
            // Localization
            wp_localize_script('mhm-booking-calendar', 'mhmBookingCalendar', [
                'strings' => [
                    'selectedDate' => __('Selected date', 'mhm-rentiva')
                ]
            ]);

            // Note: rely on WordPress core bulk-action behavior to avoid interference.
            
            // Add body class
            add_filter('admin_body_class', [self::class, 'add_body_class']);

            // Filters UX: auto-submit on change
            wp_enqueue_script(
                'mhm-booking-list-filters',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/booking-list-filters.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
        }
    }

    public static function add_body_class(string $classes): string
    {
        return $classes . ' mhm-booking-list';
    }

    public static function render(string $column, int $post_id): void
    {
        switch ($column) {
            case 'mhm_booking_id':
                echo '<span class="booking-id">#' . esc_html($post_id) . '</span>';
                break;

            case 'mhm_booking_vehicle':
                // Check both old and new meta keys
                $vehicle_id = (int) (get_post_meta($post_id, '_booking_vehicle_id', true) ?: get_post_meta($post_id, '_mhm_vehicle_id', true));
                if ($vehicle_id) {
                    $vehicle_title = get_the_title($vehicle_id);
                    $vehicle_link = get_edit_post_link($vehicle_id);
                    echo '<div class="vehicle-info">';
                    if ($vehicle_link) {
                        echo '<span class="vehicle-name"><a href="' . esc_url($vehicle_link) . '">' . esc_html($vehicle_title) . '</a></span>';
                    } else {
                        echo '<span class="vehicle-name">' . esc_html($vehicle_title) . '</span>';
                    }
                    // Show vehicle plate if available
                    $vehicle_plate = get_post_meta($vehicle_id, '_mhm_vehicle_plate', true);
                    if ($vehicle_plate) {
                        echo '<span class="vehicle-plate">' . esc_html($vehicle_plate) . '</span>';
                    }
                    echo '</div>';
                } else {
                    echo '—';
                }
                break;

            case 'mhm_booking_license_plate':
                // Check both old and new meta keys
                $vehicle_id = (int) (get_post_meta($post_id, '_booking_vehicle_id', true) ?: get_post_meta($post_id, '_mhm_vehicle_id', true));
                if ($vehicle_id) {
                    $license_plate = get_post_meta($vehicle_id, '_mhm_rentiva_license_plate', true);
                    if ($license_plate) {
                        echo '<span class="license-plate">' . esc_html($license_plate) . '</span>';
                    } else {
                        echo '—';
                    }
                } else {
                    echo '—';
                }
                break;

            case 'mhm_booking_dates':
                // Check both old and new meta keys
                $pickup_date = get_post_meta($post_id, '_booking_pickup_date', true) ?: get_post_meta($post_id, '_mhm_pickup_date', true);
                $pickup_time = get_post_meta($post_id, '_booking_pickup_time', true) ?: get_post_meta($post_id, '_mhm_pickup_time', true) ?: get_post_meta($post_id, '_mhm_start_time', true);
                $dropoff_date = get_post_meta($post_id, '_booking_dropoff_date', true) ?: get_post_meta($post_id, '_mhm_dropoff_date', true);
                $dropoff_time = get_post_meta($post_id, '_booking_dropoff_time', true) ?: get_post_meta($post_id, '_mhm_dropoff_time', true) ?: get_post_meta($post_id, '_mhm_end_time', true);
                
                if ($pickup_date && $dropoff_date) {
                    // Normalize date format (convert to DD.MM.YYYY format)
                    $formatted_pickup = self::format_date_for_display($pickup_date);
                    $formatted_dropoff = self::format_date_for_display($dropoff_date);
                    
                    echo '<div class="date-info">';
                    // Show date and time information together
                    $pickup_datetime = $formatted_pickup;
                    if ($pickup_time) {
                        $pickup_datetime .= ', ' . esc_html($pickup_time);
                    }
                    
                    $dropoff_datetime = $formatted_dropoff;
                    if ($dropoff_time) {
                        $dropoff_datetime .= ', ' . esc_html($dropoff_time);
                    }
                    
                    echo '<div class="date-range">' . esc_html($pickup_datetime . ' - ' . $dropoff_datetime) . '</div>';
                    echo '</div>';
                } else {
                    echo '—';
                }
                break;

            case 'mhm_booking_days':
                // Check both old and new meta keys
                $days = (int) (get_post_meta($post_id, '_booking_rental_days', true) ?: get_post_meta($post_id, '_mhm_rental_days', true));
                echo $days > 0 ? esc_html((string) $days) : '—';
                break;

            case 'mhm_booking_total':
                // Check both old and new meta keys
                $total = (float) (get_post_meta($post_id, '_booking_total_price', true) ?: get_post_meta($post_id, '_mhm_total_price', true));
                if ($total > 0) {
                    echo '<span class="total-amount">' . esc_html(self::format_price($total)) . '</span>';
                } else {
                    echo '—';
                }
                break;

            case 'mhm_booking_deposit':
                // Check payment type
                $payment_type = get_post_meta($post_id, '_mhm_payment_type', true);
                
                if ($payment_type === 'deposit') {
                    // Get deposit amount (already calculated)
                    $deposit_amount = get_post_meta($post_id, '_mhm_deposit_amount', true);
                    
                    if ($deposit_amount && $deposit_amount > 0) {
                        echo '<span class="deposit-amount">' . esc_html(self::format_price(floatval($deposit_amount))) . '</span>';
                    } else {
                        echo '—';
                    }
                } else {
                    // Full payment made
                    echo '—';
                }
                break;

            case 'mhm_booking_remaining':
                // Check payment type and amounts
                $payment_type = get_post_meta($post_id, '_mhm_payment_type', true);
                
                if ($payment_type === 'deposit') {
                    // Get remaining amount (already calculated)
                    $remaining_amount = get_post_meta($post_id, '_mhm_remaining_amount', true);
                    
                    if ($remaining_amount && $remaining_amount > 0) {
                        echo '<span class="remaining-amount">' . esc_html(self::format_price(floatval($remaining_amount))) . '</span>';
                    } else {
                        echo '<span class="remaining-amount paid">' . __('Paid', 'mhm-rentiva') . '</span>';
                    }
                } else {
                    // Full payment made or payment type unclear
                    echo '<span class="remaining-amount paid">' . __('Paid', 'mhm-rentiva') . '</span>';
                }
                break;

            case 'mhm_booking_status':
                $status = Status::get($post_id);
                $label = Status::get_label($status);
                echo '<span class="badge status-' . esc_attr($status) . '">' . esc_html($label) . '</span>';
                break;

            case 'mhm_booking_payment':
                // Check both old and new meta keys
                $status    = (string) (get_post_meta($post_id, '_booking_payment_status', true) ?: get_post_meta($post_id, '_mhm_payment_status', true));
                $amount    = (int) (get_post_meta($post_id, '_booking_payment_amount', true) ?: get_post_meta($post_id, '_mhm_payment_amount', true));
                $currency  = (string) (get_post_meta($post_id, '_booking_payment_currency', true) ?: get_post_meta($post_id, '_mhm_payment_currency', true));
                $gateway   = (string) (get_post_meta($post_id, '_booking_payment_gateway', true) ?: get_post_meta($post_id, '_mhm_payment_gateway', true));
                $receiptId = (int) (get_post_meta($post_id, '_booking_offline_receipt_id', true) ?: get_post_meta($post_id, '_mhm_offline_receipt_id', true));

                if ($currency === '') {
                    $currency = is_callable([Settings::class, 'get']) ? (string) Settings::get('currency', 'USD') : 'USD';
                }
                
                echo '<div class="payment-info">';
                $label = $status ? self::get_payment_status_label($status) : __('Unpaid', 'mhm-rentiva');
                echo '<div class="payment-status">' . esc_html($label) . '</div>';
                
                if ($amount > 0) {
                    $val = number_format_i18n($amount / 100, 2);
                    echo '<div class="amount">' . esc_html($val . ' ' . strtoupper($currency)) . '</div>';
                }
                
                $gw = $gateway !== '' ? $gateway : ($receiptId ? 'offline' : '');
                if ($gw !== '') {
                    $gateway_label = self::get_payment_gateway_label($gw);
                    echo '<div class="gateway">[' . esc_html($gateway_label) . ']</div>';
                }
                echo '</div>';
                break;

            case 'mhm_booking_type':
                // Manuel rezervasyon mu kontrol et
                $is_manual = get_post_meta($post_id, '_mhm_booking_type', true);
                
                if ($is_manual === 'manual') {
                    echo '<span class="booking-type manual">' . __('Manual', 'mhm-rentiva') . '</span>';
                } else {
                    // Default to online
                    echo '<span class="booking-type online">' . __('Online', 'mhm-rentiva') . '</span>';
                }
                break;
        }
    }

    public static function sortable(array $cols): array
    {
        $cols['mhm_booking_total'] = 'mhm_booking_total';
        $cols['mhm_booking_dates'] = 'mhm_booking_dates';
        $cols['mhm_booking_payment'] = 'mhm_booking_payment';
        return $cols;
    }

    public static function apply_sorting(\WP_Query $q): void
    {
        if (!is_admin() || !$q->is_main_query()) {
            return;
        }
        if (($q->get('post_type') ?? '') !== 'vehicle_booking') {
            return;
        }
        
        $orderby = $q->get('orderby');
        if ($orderby === 'mhm_booking_total') {
            // Check both old and new meta keys
            $q->set('meta_query', [
                'relation' => 'OR',
                ['key' => '_booking_total_price', 'compare' => 'EXISTS'],
                ['key' => '_mhm_total_price', 'compare' => 'EXISTS']
            ]);
            $q->set('orderby', 'meta_value_num');
        } elseif ($orderby === 'mhm_booking_dates') {
            // Check both old and new meta keys
            $q->set('meta_query', [
                'relation' => 'OR',
                ['key' => '_booking_start_ts', 'compare' => 'EXISTS'],
                ['key' => '_mhm_start_ts', 'compare' => 'EXISTS']
            ]);
            $q->set('orderby', 'meta_value_num');
        } elseif ($orderby === 'mhm_booking_payment') {
            // Check both old and new meta keys
            $q->set('meta_query', [
                'relation' => 'OR',
                ['key' => '_booking_payment_amount', 'compare' => 'EXISTS'],
                ['key' => '_mhm_payment_amount', 'compare' => 'EXISTS']
            ]);
            $q->set('orderby', 'meta_value_num');
        }
    }

    public static function status_filter(string $post_type): void
    {
        if ($post_type !== 'vehicle_booking') {
            return;
        }
        
        $current = isset($_GET['mhm_booking_status']) ? self::sanitize_text_field_safe((string) $_GET['mhm_booking_status']) : '';
        
        echo '<select name="mhm_booking_status" class="postform">';
        echo '  <option value="">' . esc_html__('All statuses', 'mhm-rentiva') . '</option>';
        
        foreach (Status::allowed() as $status) {
            $label = Status::get_label($status);
            $selected = selected($current, $status, false);
            echo '  <option value="' . esc_attr($status) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        
        echo '</select>';

        // Payment status filter
        $pcur = isset($_GET['mhm_payment_status']) ? self::sanitize_text_field_safe((string) $_GET['mhm_payment_status']) : '';
        echo '<select name="mhm_payment_status" class="postform">';
        echo '  <option value="">' . esc_html__('All payments', 'mhm-rentiva') . '</option>';
        foreach (['unpaid','paid','refunded','failed'] as $s) {
            $label = self::get_payment_status_label($s);
            echo '  <option value="' . esc_attr($s) . '"' . selected($pcur, $s, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';

        // Payment gateway filter
        $gcur = isset($_GET['mhm_payment_gateway']) ? self::sanitize_text_field_safe((string) $_GET['mhm_payment_gateway']) : '';
        echo '<select name="mhm_payment_gateway" class="postform">';
        echo '  <option value="">' . esc_html__('All payment methods', 'mhm-rentiva') . '</option>';
        $allowedGateways = class_exists(Mode::class) ? Mode::allowedGateways() : ['offline'];
        foreach ($allowedGateways as $gw) {
            $label = self::get_payment_gateway_label($gw);
            echo '  <option value="' . esc_attr($gw) . '"' . selected($gcur, $gw, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public static function apply_status_filter(\WP_Query $q): void
    {
        if (!is_admin() || !$q->is_main_query()) {
            return;
        }
        if (($q->get('post_type') ?? '') !== 'vehicle_booking') {
            return;
        }

        $meta = [];

        if (isset($_GET['mhm_booking_status']) && $_GET['mhm_booking_status'] !== '') {
            $val = self::sanitize_text_field_safe((string) $_GET['mhm_booking_status']);
            if (in_array($val, Status::allowed(), true)) {
                // Check both old and new meta keys
                $meta[] = [
                    'relation' => 'OR',
                    ['key' => '_booking_status', 'value' => $val, 'compare' => '='],
                    ['key' => '_mhm_status', 'value' => $val, 'compare' => '=']
                ];
            }
        }
        if (isset($_GET['mhm_payment_status']) && $_GET['mhm_payment_status'] !== '') {
            $val = self::sanitize_text_field_safe((string) $_GET['mhm_payment_status']);
            if (in_array($val, ['unpaid','paid','refunded','failed'], true)) {
                // Hem eski hem yeni meta key'leri kontrol et
                $meta[] = [
                    'relation' => 'OR',
                    ['key' => '_booking_payment_status', 'value' => $val, 'compare' => '='],
                    ['key' => '_mhm_payment_status', 'value' => $val, 'compare' => '=']
                ];
            }
        }
        if (isset($_GET['mhm_payment_gateway']) && $_GET['mhm_payment_gateway'] !== '') {
            $val = self::sanitize_text_field_safe((string) $_GET['mhm_payment_gateway']);
            if ($val === 'woocommerce') {
                // ⭐ WooCommerce only - All payments go through WooCommerce
                $meta[] = [
                    ['key' => '_mhm_payment_gateway', 'value' => 'woocommerce', 'compare' => '=']
                ];
            }
        }
        if (!empty($meta)) {
            $meta['relation'] = 'AND';
            $q->set('meta_query', $meta);
        }
    }

    /**
     * Return localized label for payment status.
     */
    private static function get_payment_status_label(string $status): string
    {
        $labels = [
            'unpaid' => __('Unpaid', 'mhm-rentiva'),
            'paid' => __('Paid', 'mhm-rentiva'),
            'refunded' => __('Refunded', 'mhm-rentiva'),
            'failed' => __('Failed', 'mhm-rentiva'),
            'pending_verification' => __('Pending Verification', 'mhm-rentiva'),
        ];

        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * Return localized label for payment gateway.
     */
    private static function get_payment_gateway_label(string $gateway): string
    {
        $labels = [
            'offline' => __('Offline', 'mhm-rentiva'),
        ];

        return $labels[$gateway] ?? strtoupper($gateway);
    }

    private static function format_price(float $price): string
    {
        // ✅ Same format as Dashboard/Vehicle
        $amount = number_format($price, 2, '.', ',');
        return $amount . ' ' . self::get_currency_symbol();
    }
    
    /**
     * Retrieve currency symbol (shared with Dashboard).
     */
    /**
     * Get currency symbol
     * 
     * @deprecated Use CurrencyHelper::get_currency_symbol() instead
     */
    private static function get_currency_symbol(): string
    {
        return \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();
    }

    /**
     * Format date for display according to settings.
     */
    private static function format_date_for_display(string $date): string
    {
        if (empty($date)) {
            return '';
        }

        // Get date format from settings
        // ✅ Use SettingsCore::get() instead of removed BookingSettings method
        $date_format = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_date_format', 'Y-m-d');
        
        // If already in desired format, return as is
        if ($date_format === 'DD.MM.YYYY' && preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $date)) {
            return $date;
        }
        
        if ($date_format === 'YYYY-MM-DD' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        // If in YYYY-MM-DD format, convert to desired format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date_obj = \DateTime::createFromFormat('Y-m-d', $date);
            if ($date_obj !== false) {
                switch ($date_format) {
                    case 'DD.MM.YYYY':
                        return $date_obj->format('d.m.Y');
                    case 'YYYY-MM-DD':
                        return $date_obj->format('Y-m-d');
                    case 'MM/DD/YYYY':
                        return $date_obj->format('m/d/Y');
                    case 'DD-MM-YYYY':
                        return $date_obj->format('d-m-Y');
                    default:
                        return $date_obj->format('d.m.Y');
                }
            }
        }

        // Try other formats as well
        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return date('d.m.Y', $timestamp);
        }

        // If none work, return original value
        return $date;
    }

    /**
     * Output booking statistics cards.
     */
    public static function add_booking_stats_cards(): void
    {
        global $pagenow, $post_type;
        
        // Show only on booking list page
        if ($pagenow !== 'edit.php' || $post_type !== 'vehicle_booking') {
            return;
        }

        // Add page title
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Bookings', 'mhm-rentiva'); ?></h1>
            <hr class="wp-header-end">
        </div>
        
        <?php
        // Display Developer Mode banner and limit notices
        \MHMRentiva\Admin\Core\ProFeatureNotice::displayDeveloperModeAndLimits('bookings', [
            __('Unlimited Bookings', 'mhm-rentiva'),
            __('Advanced Booking Management', 'mhm-rentiva'),
        ]);
        
        // Get statistics data
        $stats = self::get_booking_stats();
        
        ?>
        <div class="mhm-stats-cards">
            <div class="stats-grid">
                <!-- Pending bookings -->
                <div class="stat-card stat-card-pending">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($stats['pending']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Pending', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text"><?php echo esc_html($stats['pending_this_week']); ?> <?php esc_html_e('This week', 'mhm-rentiva'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Confirmed bookings -->
                <div class="stat-card stat-card-confirmed">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($stats['confirmed']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Confirmed', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text"><?php echo esc_html($stats['confirmed_this_month']); ?> <?php esc_html_e('This month', 'mhm-rentiva'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Completed bookings -->
                <div class="stat-card stat-card-completed">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-yes"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html($stats['completed']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Completed', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text"><?php echo esc_html($stats['completed_this_month']); ?> <?php esc_html_e('This month', 'mhm-rentiva'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Monthly Revenue -->
                <div class="stat-card stat-card-revenue">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo esc_html(self::format_price($stats['monthly_revenue'])); ?></div>
                        <div class="stat-label"><?php esc_html_e('Monthly Revenue', 'mhm-rentiva'); ?></div>
                        <div class="stat-trend">
                            <span class="trend-text <?php echo $stats['revenue_trend'] >= 0 ? 'trend-up' : 'trend-down'; ?>">
                                <?php echo $stats['revenue_trend'] >= 0 ? '+' : ''; ?><?php echo esc_html($stats['revenue_trend']); ?>% <?php esc_html_e('vs last month', 'mhm-rentiva'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Collect booking statistics data.
     */
    private static function get_booking_stats(): array
    {
        global $wpdb;
        
        // Pending bookings (check both old and new meta keys)
        $pending = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE p.post_type = %s AND p.post_status = %s 
             AND ((pm.meta_key = %s AND pm.meta_value = %s) OR (pm.meta_key = %s AND pm.meta_value = %s))",
            'vehicle_booking', 'publish', '_booking_status', 'pending', '_mhm_status', 'pending'
        ));

        // Confirmed bookings (check both old and new meta keys)
        $confirmed = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE p.post_type = %s AND p.post_status = %s 
             AND ((pm.meta_key = %s AND pm.meta_value = %s) OR (pm.meta_key = %s AND pm.meta_value = %s))",
            'vehicle_booking', 'publish', '_booking_status', 'confirmed', '_mhm_status', 'confirmed'
        ));

        // Completed bookings (check both old and new meta keys)
        $completed = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE p.post_type = %s AND p.post_status = %s 
             AND ((pm.meta_key = %s AND pm.meta_value = %s) OR (pm.meta_key = %s AND pm.meta_value = %s))",
            'vehicle_booking', 'publish', '_booking_status', 'completed', '_mhm_status', 'completed'
        ));

        // Pending bookings this week (check both old and new meta keys)
        $pending_this_week = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE p.post_type = %s AND p.post_status = %s 
             AND ((pm.meta_key = %s AND pm.meta_value = %s) OR (pm.meta_key = %s AND pm.meta_value = %s))
             AND p.post_date >= %s",
            'vehicle_booking', 'publish', '_booking_status', 'pending', '_mhm_status', 'pending',
            date('Y-m-d', strtotime('-7 days'))
        ));

        // Confirmed bookings this month (check both old and new meta keys)
        $confirmed_this_month = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE p.post_type = %s AND p.post_status = %s 
             AND ((pm.meta_key = %s AND pm.meta_value = %s) OR (pm.meta_key = %s AND pm.meta_value = %s))
             AND p.post_date >= %s",
            'vehicle_booking', 'publish', '_booking_status', 'confirmed', '_mhm_status', 'confirmed',
            date('Y-m-01')
        ));

        // Completed bookings this month (check both old and new meta keys)
        $completed_this_month = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE p.post_type = %s AND p.post_status = %s 
             AND ((pm.meta_key = %s AND pm.meta_value = %s) OR (pm.meta_key = %s AND pm.meta_value = %s))
             AND p.post_date >= %s",
            'vehicle_booking', 'publish', '_booking_status', 'completed', '_mhm_status', 'completed',
            date('Y-m-01')
        ));

        // This month revenue - ONLY COMPLETED AND CONFIRMED BOOKINGS
        $monthly_revenue = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
             WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND pm.meta_key = %s
             AND pm_status.meta_key = '_mhm_status'
             AND pm_status.meta_value IN ('completed', 'confirmed')
             AND p.post_date >= %s",
            'vehicle_booking', '_mhm_total_price', date('Y-m-01')
        ));

        // Trend calculation - with real data
        // ✅ Use SettingsCore::get() instead of removed BookingSettings method
        $trend_range = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_booking_stats_trend_range', 30);
        $revenue_trend = self::calculate_revenue_trend($trend_range);

        return [
            'pending' => $pending,
            'confirmed' => $confirmed,
            'completed' => $completed,
            'pending_this_week' => $pending_this_week,
            'confirmed_this_month' => $confirmed_this_month,
            'completed_this_month' => $completed_this_month,
            'monthly_revenue' => $monthly_revenue,
            'revenue_trend' => $revenue_trend
        ];
    }

    /**
     * Calculate revenue trend.
     */
    private static function calculate_revenue_trend(int $trend_range_days): float
    {
        global $wpdb;
        
        // Current period start
        $current_period_start = date('Y-m-01');
        
        // Previous period start
        $previous_period_start = date('Y-m-01', strtotime("-{$trend_range_days} days"));
        $previous_period_end = date('Y-m-d', strtotime('-1 day'));
        
        // Current period revenue - ONLY COMPLETED AND CONFIRMED BOOKINGS
        $current_revenue = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
             WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND pm.meta_key = %s
             AND pm_status.meta_key = '_mhm_status'
             AND pm_status.meta_value IN ('completed', 'confirmed')
             AND p.post_date >= %s",
            'vehicle_booking', '_mhm_total_price', $current_period_start
        ));
        
        // Previous period revenue - ONLY COMPLETED AND CONFIRMED BOOKINGS
        $previous_revenue = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
             WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND pm.meta_key = %s
             AND pm_status.meta_key = '_mhm_status'
             AND pm_status.meta_value IN ('completed', 'confirmed')
             AND p.post_date >= %s AND p.post_date <= %s",
            'vehicle_booking', '_mhm_total_price', $previous_period_start, $previous_period_end
        ));
        
        // Calculate revenue trend.
        if ($previous_revenue > 0) {
            $trend = (($current_revenue - $previous_revenue) / $previous_revenue) * 100;
            return round($trend, 1);
        } elseif ($current_revenue > 0) {
            return 100.0; // New revenue exists, none in previous period
        } else {
            return 0.0; // No revenue in both periods
        }
    }

    /**
     * Render monthly booking calendar.
     */
    public static function add_booking_calendar(): void
    {
        global $pagenow, $post_type;
        
        // Show only on booking list page
        if ($pagenow !== 'edit.php' || $post_type !== 'vehicle_booking') {
            return;
        }

        // Get month and year from URL parameters, otherwise use current month/year
        $current_month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
        $current_year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
        
        // Check for invalid values
        if ($current_month < 1 || $current_month > 12) {
            $current_month = (int) date('n');
        }
        if ($current_year < 2020 || $current_year > 2030) {
            $current_year = (int) date('Y');
        }
        
        // Dynamic month names (i18n supported)
        $month_names = [
            1 => __('January', 'mhm-rentiva'),
            2 => __('February', 'mhm-rentiva'),
            3 => __('March', 'mhm-rentiva'),
            4 => __('April', 'mhm-rentiva'),
            5 => __('May', 'mhm-rentiva'),
            6 => __('June', 'mhm-rentiva'),
            7 => __('July', 'mhm-rentiva'),
            8 => __('August', 'mhm-rentiva'),
            9 => __('September', 'mhm-rentiva'),
            10 => __('October', 'mhm-rentiva'),
            11 => __('November', 'mhm-rentiva'),
            12 => __('December', 'mhm-rentiva')
        ];
        
        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
        $today = (int) date('j');
        $current_month_num = (int) date('n');
        $current_year_num = (int) date('Y');
        
        // Fetch booking entries for calendar
        $booking_days = self::get_booking_calendar_days($current_month, $current_year);
        
        ?>
        <div class="mhm-calendars booking-calendar-page">
            <!-- Calendar Header -->
            <div class="calendar-header">
                <h2><?php esc_html_e('Monthly Reservation Calendar', 'mhm-rentiva'); ?></h2>
                
                <!-- Month Navigation -->
                <div class="calendar-navigation">
                    <?php
                    $prev_month = $current_month == 1 ? 12 : $current_month - 1;
                    $prev_year = $current_month == 1 ? $current_year - 1 : $current_year;
                    $next_month = $current_month == 12 ? 1 : $current_month + 1;
                    $next_year = $current_month == 12 ? $current_year + 1 : $current_year;
                    ?>
                    
                    <a href="<?php echo esc_url(add_query_arg(['month' => $prev_month, 'year' => $prev_year])); ?>" 
                       class="calendar-nav-btn prev-btn" data-action="prev">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                        <?php echo esc_html($month_names[$prev_month]); ?>
                    </a>
                    
                    <div class="calendar-current">
                        <strong><?php echo esc_html($month_names[$current_month] . ' ' . $current_year); ?></strong>
            </div>
                    
                    <a href="<?php echo esc_url(add_query_arg(['month' => $next_month, 'year' => $next_year])); ?>" 
                       class="calendar-nav-btn next-btn" data-action="next">
                        <?php echo esc_html($month_names[$next_month]); ?>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </a>
            </div>
            </div>

            <!-- Calendar Grid -->
            <div class="calendar-container">
                <div class="calendar-grid-wrapper">
                <?php
                    // Get WordPress week start setting (0 = Sunday, 1 = Monday, etc.)
                    $week_start = (int) get_option('start_of_week', 1);
                    
                    // Day names - Reorder based on WordPress setting
                    $all_day_names = [
                        __('Sun', 'mhm-rentiva'),
                        __('Mon', 'mhm-rentiva'), 
                        __('Tue', 'mhm-rentiva'), 
                        __('Wed', 'mhm-rentiva'), 
                        __('Thu', 'mhm-rentiva'), 
                        __('Fri', 'mhm-rentiva'), 
                        __('Sat', 'mhm-rentiva')
                    ];
                    
                    // Reorder days based on week start
                    $day_names = array_merge(
                        array_slice($all_day_names, $week_start),
                        array_slice($all_day_names, 0, $week_start)
                    );
                    
                    // Current month's days only - positioned in 7-column grid
                for ($day = 1; $day <= $days_in_month; $day++) {
                        $is_today = ($day == $today && $current_month == $current_month_num && $current_year == $current_year_num);
                    $booking_data = $booking_days[$day] ?? null;
                    
                        // Get day name for this date and calculate grid column
                        $day_of_week = date('w', mktime(0, 0, 0, $current_month, $day, $current_year));
                        $day_name_index = ($day_of_week - $week_start + 7) % 7;
                        $day_name = $day_names[$day_name_index];
                        
                        // Calculate grid column (1-7) based on day of week
                        $grid_column = $day_name_index + 1;
                        
                        $classes = ['day-cell'];
                        if ($is_today) {
                            $classes[] = 'today';
                        }
                        
                        // Booking status classes
                    if ($booking_data) {
                            $classes[] = 'booked';
                        
                        if ($booking_data['type'] === 'single') {
                                $status = $booking_data['status'] ?? 'pending';
                                $status_class = [
                                    'pending' => 'status-pending',
                                    'confirmed' => 'status-confirmed',
                                    'in_progress' => 'status-in-progress',
                                    'completed' => 'status-completed',
                                    'cancelled' => 'status-cancelled'
                                ][$status] ?? 'status-pending';
                                $classes[] = $status_class;
                                
                                $status_label = \MHMRentiva\Admin\Booking\Core\Status::get_label($status);
                                $title = sprintf(__('Reservations: %s (%d)', 'mhm-rentiva'), $status_label, $booking_data['count']);
                                
                                // Get all bookings for popup data
                                $all_bookings = $booking_data['bookings'] ?? [];
                                
                                // Data attributes for popup - include all bookings as JSON
                                $data_attrs = '';
                                if (!empty($all_bookings)) {
                                    // Add first booking for backward compatibility
                                    $first_booking = $all_bookings[0];
                                    $data_attrs = sprintf(
                                        'data-booking-id="%s" data-customer-name="%s" data-customer-email="%s" data-customer-phone="%s" data-vehicle-title="%s" data-vehicle-plate="%s" data-total-price="%s" data-status="%s" data-status-label="%s" data-start-date="%s" data-end-date="%s" data-created-date="%s" data-bookings="%s"',
                                        esc_attr($first_booking['booking_id'] ?? ''),
                                        esc_attr($first_booking['customer_name'] ?? ''),
                                        esc_attr($first_booking['customer_email'] ?? ''),
                                        esc_attr($first_booking['customer_phone'] ?? ''),
                                        esc_attr($first_booking['vehicle_title'] ?? ''),
                                        esc_attr($first_booking['vehicle_plate'] ?? ''),
                                        esc_attr($first_booking['total_price'] ?? ''),
                                        esc_attr($first_booking['status'] ?? ''),
                                        esc_attr(\MHMRentiva\Admin\Booking\Core\Status::get_label($first_booking['status'] ?? 'pending')),
                                        esc_attr($first_booking['start_date'] ?? ''),
                                        esc_attr($first_booking['end_date'] ?? ''),
                                        esc_attr($first_booking['created_date'] ?? ''),
                                        esc_attr(wp_json_encode($all_bookings))
                                    );
                                }
                                
                                echo '<div class="' . esc_attr(implode(' ', $classes)) . '" style="grid-column: ' . esc_attr($grid_column) . ';" title="' . esc_attr($title) . '" ' . $data_attrs . ' data-booking-popup>';
                                echo '<span class="day-name">' . esc_html($day_name) . '</span>';
                                echo '<span class="day-number">' . esc_html($day) . '</span>';
                                echo '<span class="dashicons dashicons-calendar-alt booking-icon"></span>';
                                echo '</div>';
                    } else {
                                // Multi-status day - show all statuses as equal segments
                                $classes[] = 'multi-status-day';
                                $statuses = $booking_data['statuses'] ?? [];
                                $status_count = count($statuses);
                                
                                // Build title with all statuses
                                $title_parts = [];
                                foreach ($statuses as $status => $count) {
                                    $status_label = \MHMRentiva\Admin\Booking\Core\Status::get_label($status);
                                    $title_parts[] = sprintf('%s (%d)', $status_label, $count);
                                }
                                $title = __('Reservations: ', 'mhm-rentiva') . implode(', ', $title_parts);
                                
                                // Get all bookings for popup data
                                $all_bookings = $booking_data['bookings'] ?? [];
                                
                                // Data attributes for popup - include all bookings as JSON
                                $data_attrs = '';
                                if (!empty($all_bookings)) {
                                    // Add first booking for backward compatibility
                                    $first_booking = $all_bookings[0];
                                    $data_attrs = sprintf(
                                        'data-booking-id="%s" data-customer-name="%s" data-customer-email="%s" data-customer-phone="%s" data-vehicle-title="%s" data-vehicle-plate="%s" data-total-price="%s" data-status="%s" data-status-label="%s" data-start-date="%s" data-end-date="%s" data-created-date="%s" data-bookings="%s"',
                                        esc_attr($first_booking['booking_id'] ?? ''),
                                        esc_attr($first_booking['customer_name'] ?? ''),
                                        esc_attr($first_booking['customer_email'] ?? ''),
                                        esc_attr($first_booking['customer_phone'] ?? ''),
                                        esc_attr($first_booking['vehicle_title'] ?? ''),
                                        esc_attr($first_booking['vehicle_plate'] ?? ''),
                                        esc_attr($first_booking['total_price'] ?? ''),
                                        esc_attr($first_booking['status'] ?? ''),
                                        esc_attr(\MHMRentiva\Admin\Booking\Core\Status::get_label($first_booking['status'] ?? 'pending')),
                                        esc_attr($first_booking['start_date'] ?? ''),
                                        esc_attr($first_booking['end_date'] ?? ''),
                                        esc_attr($first_booking['created_date'] ?? ''),
                                        esc_attr(wp_json_encode($all_bookings))
                                    );
                                }
                                
                                echo '<div class="' . esc_attr(implode(' ', $classes)) . '" style="grid-column: ' . esc_attr($grid_column) . ';" title="' . esc_attr($title) . '" ' . $data_attrs . ' data-booking-popup>';
                                echo '<span class="day-name">' . esc_html($day_name) . '</span>';
                                echo '<span class="day-number">' . esc_html($day) . '</span>';
                                echo '<span class="dashicons dashicons-calendar-alt booking-icon"></span>';
                                echo '<div class="status-segments" data-segments="' . esc_attr($status_count) . '">';
                                
                                foreach ($statuses as $status => $count) {
                                    $status_class = [
                                        'pending' => 'status-pending',
                                        'confirmed' => 'status-confirmed',
                                        'in_progress' => 'status-in-progress',
                                        'completed' => 'status-completed',
                                        'cancelled' => 'status-cancelled'
                                    ][$status] ?? 'status-pending';
                                    echo '<div class="status-segment ' . esc_attr($status_class) . '"></div>';
                    }
                    
                    echo '</div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="' . esc_attr(implode(' ', $classes)) . '" style="grid-column: ' . esc_attr($grid_column) . ';" title="' . esc_attr__('Available', 'mhm-rentiva') . '">';
                            echo '<span class="day-name">' . esc_html($day_name) . '</span>';
                            echo '<span class="day-number">' . esc_html($day) . '</span>';
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
            </div>
            
            <!-- Status Color Information -->
            <div class="calendar-legend">
                <h4><?php esc_html_e('Status Legend', 'mhm-rentiva'); ?></h4>
                <div class="legend-items">
                    <div class="legend-item">
                        <span class="legend-color status-pending"></span>
                        <span class="legend-label"><?php esc_html_e('Pending', 'mhm-rentiva'); ?></span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color status-confirmed"></span>
                        <span class="legend-label"><?php esc_html_e('Confirmed', 'mhm-rentiva'); ?></span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color status-in-progress"></span>
                        <span class="legend-label"><?php esc_html_e('In Progress', 'mhm-rentiva'); ?></span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color status-completed"></span>
                        <span class="legend-label"><?php esc_html_e('Completed', 'mhm-rentiva'); ?></span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color status-cancelled"></span>
                        <span class="legend-label"><?php esc_html_e('Cancelled', 'mhm-rentiva'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Booking Popup Modal -->
        <div id="mhm-booking-popup" class="mhm-popup-modal" style="display: none;">
            <div class="mhm-popup-overlay"></div>
            <div class="mhm-popup-content">
                <div class="mhm-popup-header">
                    <h3><?php esc_html_e('Booking Details', 'mhm-rentiva'); ?></h3>
                    <button class="mhm-popup-close" type="button">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="mhm-popup-body">
                    <div id="popup-bookings-list">
                        <!-- Bookings will be rendered here by JavaScript -->
                    </div>
                </div>
                <div class="mhm-popup-footer">
                    <button class="button button-secondary mhm-popup-close">
                        <?php esc_html_e('Close', 'mhm-rentiva'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Open popup
            $('[data-booking-popup]').on('click', function(e) {
                e.preventDefault();
                
                var $this = $(this);
                var bookingsJson = $this.data('bookings');
                var bookings = [];
                
                // Parse bookings from JSON
                if (bookingsJson) {
                    try {
                        bookings = typeof bookingsJson === 'string' ? JSON.parse(bookingsJson) : bookingsJson;
                    } catch (e) {
                        console.error('Error parsing bookings JSON:', e);
                        // Fallback to single booking data
                        bookings = [{
                            booking_id: $this.data('booking-id'),
                            customer_name: $this.data('customer-name'),
                            customer_email: $this.data('customer-email'),
                            customer_phone: $this.data('customer-phone'),
                            vehicle_title: $this.data('vehicle-title'),
                            vehicle_plate: $this.data('vehicle-plate'),
                            total_price: $this.data('total-price'),
                            status: $this.data('status'),
                            status_label: $this.data('status-label') || $this.data('status'),
                            start_date: $this.data('start-date'),
                            end_date: $this.data('end-date'),
                            created_date: $this.data('created-date')
                        }];
                    }
                } else {
                    // Fallback to single booking data
                    bookings = [{
                        booking_id: $this.data('booking-id'),
                        customer_name: $this.data('customer-name'),
                        customer_email: $this.data('customer-email'),
                        customer_phone: $this.data('customer-phone'),
                        vehicle_title: $this.data('vehicle-title'),
                        vehicle_plate: $this.data('vehicle-plate'),
                        total_price: $this.data('total-price'),
                        status: $this.data('status'),
                        status_label: $this.data('status-label') || $this.data('status'),
                        start_date: $this.data('start-date'),
                        end_date: $this.data('end-date'),
                        created_date: $this.data('created-date')
                    }];
                }
                
                // Render bookings list
                var html = '';
                if (bookings.length === 0) {
                    html = '<p>' + '<?php echo esc_js(__('No bookings found.', 'mhm-rentiva')); ?>' + '</p>';
                } else if (bookings.length === 1) {
                    // Single booking - show in grid format
                    var booking = bookings[0];
                    html = '<div class="booking-info-grid">';
                    html += '<div class="info-item"><label><?php echo esc_js(__('Customer Name:', 'mhm-rentiva')); ?></label><span>' + (booking.customer_name || '—') + '</span></div>';
                    html += '<div class="info-item"><label><?php echo esc_js(__('Email:', 'mhm-rentiva')); ?></label><span>' + (booking.customer_email || '—') + '</span></div>';
                    html += '<div class="info-item"><label><?php echo esc_js(__('Phone:', 'mhm-rentiva')); ?></label><span>' + (booking.customer_phone || '—') + '</span></div>';
                    html += '<div class="info-item"><label><?php echo esc_js(__('Vehicle:', 'mhm-rentiva')); ?></label><span>' + (booking.vehicle_title || '—') + '</span></div>';
                    html += '<div class="info-item"><label><?php echo esc_js(__('Plate:', 'mhm-rentiva')); ?></label><span>' + (booking.vehicle_plate || '—') + '</span></div>';
                    html += '<div class="info-item"><label><?php echo esc_js(__('Start Date:', 'mhm-rentiva')); ?></label><span>' + (booking.start_date || '—') + '</span></div>';
                    html += '<div class="info-item"><label><?php echo esc_js(__('End Date:', 'mhm-rentiva')); ?></label><span>' + (booking.end_date || '—') + '</span></div>';
                    html += '<div class="info-item"><label><?php echo esc_js(__('Total Price:', 'mhm-rentiva')); ?></label><span>' + (booking.total_price || '—') + '</span></div>';
                    html += '<div class="info-item"><label><?php echo esc_js(__('Status:', 'mhm-rentiva')); ?></label><span>' + (booking.status_label || booking.status || '—') + '</span></div>';
                    html += '<div class="info-item"><label><?php echo esc_js(__('Created:', 'mhm-rentiva')); ?></label><span>' + (booking.created_date || '—') + '</span></div>';
                    html += '</div>';
                    html += '<div class="mhm-popup-footer mhm-popup-footer-inline">';
                    html += '<button class="button button-primary popup-edit-booking-btn" data-booking-id="' + (booking.booking_id || '') + '" type="button"><?php echo esc_js(__('Edit Booking', 'mhm-rentiva')); ?></button>';
                    html += '</div>';
                } else {
                    // Multiple bookings - show as list
                    html = '<div class="bookings-list">';
                    bookings.forEach(function(booking, index) {
                        html += '<div class="booking-item' + (index > 0 ? ' booking-item-separator' : '') + '">';
                        html += '<div class="booking-info-grid">';
                        html += '<div class="info-item"><label><?php echo esc_js(__('Customer Name:', 'mhm-rentiva')); ?></label><span>' + (booking.customer_name || '—') + '</span></div>';
                        html += '<div class="info-item"><label><?php echo esc_js(__('Email:', 'mhm-rentiva')); ?></label><span>' + (booking.customer_email || '—') + '</span></div>';
                        html += '<div class="info-item"><label><?php echo esc_js(__('Phone:', 'mhm-rentiva')); ?></label><span>' + (booking.customer_phone || '—') + '</span></div>';
                        html += '<div class="info-item"><label><?php echo esc_js(__('Vehicle:', 'mhm-rentiva')); ?></label><span>' + (booking.vehicle_title || '—') + '</span></div>';
                        html += '<div class="info-item"><label><?php echo esc_js(__('Plate:', 'mhm-rentiva')); ?></label><span>' + (booking.vehicle_plate || '—') + '</span></div>';
                        html += '<div class="info-item"><label><?php echo esc_js(__('Start Date:', 'mhm-rentiva')); ?></label><span>' + (booking.start_date || '—') + '</span></div>';
                        html += '<div class="info-item"><label><?php echo esc_js(__('End Date:', 'mhm-rentiva')); ?></label><span>' + (booking.end_date || '—') + '</span></div>';
                        html += '<div class="info-item"><label><?php echo esc_js(__('Total Price:', 'mhm-rentiva')); ?></label><span>' + (booking.total_price || '—') + '</span></div>';
                        html += '<div class="info-item"><label><?php echo esc_js(__('Status:', 'mhm-rentiva')); ?></label><span>' + (booking.status_label || booking.status || '—') + '</span></div>';
                        html += '<div class="info-item"><label><?php echo esc_js(__('Created:', 'mhm-rentiva')); ?></label><span>' + (booking.created_date || '—') + '</span></div>';
                        html += '</div>';
                        html += '<div class="booking-item-footer">';
                        html += '<button class="button button-primary popup-edit-booking-btn" data-booking-id="' + (booking.booking_id || '') + '" type="button"><?php echo esc_js(__('Edit Booking', 'mhm-rentiva')); ?></button>';
                        html += '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                }
                
                $('#popup-bookings-list').html(html);
                
                // Add Edit booking link as click event
                $('.popup-edit-booking-btn').off('click').on('click', function(e) {
                    e.preventDefault();
                    var bookingId = $(this).data('booking-id');
                    if (bookingId) {
                        window.location.href = 'post.php?post=' + bookingId + '&action=edit';
                    }
                });
                
                // Show popup
                $('#mhm-booking-popup').fadeIn(300);
            });
            
            // Close popup
            $('.mhm-popup-close, .mhm-popup-overlay').on('click', function() {
                $('#mhm-booking-popup').fadeOut(300);
            });
            
            // Close with ESC key
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27) {
                    $('#mhm-booking-popup').fadeOut(300);
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Fetch booking data for calendar view.
     */
    private static function get_calendar_bookings(int $month, int $year): array
    {
        global $wpdb;
        
        // Rezervasyon verilerini al
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = sprintf('%04d-%02d-%02d', $year, $month, date('t', mktime(0, 0, 0, $month, 1, $year)));
        
        $query = $wpdb->prepare("
            SELECT p.ID, p.post_title, pm_customer.meta_value as customer_name
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_customer_name'
            WHERE p.post_type = 'vehicle_booking'
                AND p.post_status = 'publish'
                AND p.post_date >= %s
                AND p.post_date <= %s
            ORDER BY p.post_date DESC
            LIMIT 20
        ", $start_date, $end_date . ' 23:59:59');
        
        $results = $wpdb->get_results($query);
        
        $bookings = [];
        foreach ($results as $result) {
            $bookings[] = [
                'id' => $result->ID,
                'title' => $result->post_title ?: __('Booking #', 'mhm-rentiva') . $result->ID,
                'customer' => $result->customer_name ?: __('Unknown Customer', 'mhm-rentiva')
            ];
        }
        
        // Provide sample entries if no bookings exist
        if (empty($bookings)) {
            $bookings = [
                [
                    'id' => 1,
                    'title' => __('Sample Booking 1', 'mhm-rentiva'),
                    'customer' => __('Sample Customer 1', 'mhm-rentiva')
                ],
                [
                    'id' => 2,
                    'title' => __('Sample Booking 2', 'mhm-rentiva'),
                    'customer' => __('Sample Customer 2', 'mhm-rentiva')
                ]
            ];
        }
        
        return $bookings;
    }

    /**
     * Build calendar day status map.
     */
    private static function get_booking_calendar_days(int $month, int $year): array
    {
        global $wpdb;
        
        // Retrieve relevant bookings with all details for popup
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = sprintf('%04d-%02d-%02d', $year, $month, date('t', mktime(0, 0, 0, $month, 1, $year)));
        
        $bookings = $wpdb->get_results($wpdb->prepare("
            SELECT 
                p.ID as booking_id,
                pm_vehicle.meta_value as vehicle_id,
                pm_start.meta_value as pickup_date,
                pm_end.meta_value as dropoff_date,
                pm_customer.meta_value as customer_name,
                pm_customer_email.meta_value as customer_email,
                pm_customer_phone.meta_value as customer_phone,
                pm_total_price.meta_value as total_price,
                pm_status.meta_value as status,
                p.post_date as created_date
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_vehicle ON p.ID = pm_vehicle.post_id 
                AND (pm_vehicle.meta_key = '_mhm_vehicle_id' OR pm_vehicle.meta_key = '_booking_vehicle_id')
            LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id 
                AND (pm_start.meta_key = '_mhm_pickup_date' OR pm_start.meta_key = '_booking_pickup_date')
            LEFT JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id 
                AND (pm_end.meta_key = '_mhm_dropoff_date' OR pm_end.meta_key = '_booking_dropoff_date')
            LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id 
                AND (pm_customer.meta_key = '_mhm_customer_name' OR pm_customer.meta_key = '_customer_name')
            LEFT JOIN {$wpdb->postmeta} pm_customer_email ON p.ID = pm_customer_email.post_id 
                AND (pm_customer_email.meta_key = '_mhm_customer_email' OR pm_customer_email.meta_key = '_customer_email')
            LEFT JOIN {$wpdb->postmeta} pm_customer_phone ON p.ID = pm_customer_phone.post_id 
                AND (pm_customer_phone.meta_key = '_mhm_customer_phone' OR pm_customer_phone.meta_key = '_customer_phone')
            LEFT JOIN {$wpdb->postmeta} pm_total_price ON p.ID = pm_total_price.post_id 
                AND (pm_total_price.meta_key = '_mhm_total_price' OR pm_total_price.meta_key = '_total_price')
            LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id 
                AND (pm_status.meta_key = '_mhm_status' OR pm_status.meta_key = '_booking_status')
            WHERE p.post_type = 'vehicle_booking'
                AND p.post_status = 'publish'
                AND pm_start.meta_value >= %s
                AND pm_start.meta_value <= %s
        ", $start_date, $end_date . ' 23:59:59'));
        
        $day_statuses = [];
        
        foreach ($bookings as $booking) {
            // Pickup date
            $pickup_date = $booking->pickup_date;
            
            if (!$pickup_date) {
                continue;
            }
            
            // Normalize date format
            $pickup_timestamp = strtotime($pickup_date);
            if (!$pickup_timestamp) {
                continue;
            }
            
            $pickup_month = (int) date('n', $pickup_timestamp);
            $pickup_year = (int) date('Y', $pickup_timestamp);
            $pickup_day = (int) date('j', $pickup_timestamp);
            
            // Only consider bookings within requested month/year
            if ($pickup_month !== $month || $pickup_year !== $year) {
                continue;
            }
            
            // Retrieve status information
            $status = $booking->status ?: 'pending';
            
            // Get vehicle info
            $vehicle_id = (int) $booking->vehicle_id;
            $vehicle_title = $vehicle_id ? get_the_title($vehicle_id) : '';
            // Check both plate meta keys
            $vehicle_plate = '';
            if ($vehicle_id) {
                $vehicle_plate = get_post_meta($vehicle_id, '_mhm_vehicle_plate', true) ?: 
                                get_post_meta($vehicle_id, '_mhm_rentiva_license_plate', true) ?: '';
            }
            
            // Format dates
            $start_date_formatted = $booking->pickup_date ? date_i18n(get_option('date_format'), strtotime($booking->pickup_date)) : '';
            $end_date_formatted = $booking->dropoff_date ? date_i18n(get_option('date_format'), strtotime($booking->dropoff_date)) : '';
            $created_date_formatted = $booking->created_date ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->created_date)) : '';
            
            // Format price
            $currency_symbol = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();
            $total_price_formatted = $booking->total_price ? number_format_i18n((float) $booking->total_price, 2) . ' ' . $currency_symbol : '';
            
            // Get translated status label
            $status_label = \MHMRentiva\Admin\Booking\Core\Status::get_label($status);
            
            // ⭐ Get customer info using BookingQueryHelper (handles WooCommerce & WordPress integration)
            $customer_info = [];
            if (class_exists('\\MHMRentiva\\Admin\\Core\\Utilities\\BookingQueryHelper')) {
                $customer_info = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingCustomerInfo($booking->booking_id);
            }
            
            // Build customer name from first_name and last_name
            $customer_name = '';
            if (!empty($customer_info['first_name']) && !empty($customer_info['last_name'])) {
                $customer_name = trim($customer_info['first_name'] . ' ' . $customer_info['last_name']);
            } elseif (!empty($customer_info['first_name'])) {
                $customer_name = $customer_info['first_name'];
            } elseif (!empty($customer_info['last_name'])) {
                $customer_name = $customer_info['last_name'];
            }
            
            // Fallback to SQL result if BookingQueryHelper didn't find anything
            if (empty($customer_name)) {
                $customer_name = $booking->customer_name ?: '';
            }
            
            // Use customer info from BookingQueryHelper (prioritizes WooCommerce/WordPress data)
            $customer_email = !empty($customer_info['email']) ? $customer_info['email'] : ($booking->customer_email ?: '');
            $customer_phone = !empty($customer_info['phone']) ? $customer_info['phone'] : ($booking->customer_phone ?: '');
            
            // Booking data for popup
            $booking_data = [
                'booking_id' => $booking->booking_id,
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'customer_phone' => $customer_phone,
                'vehicle_id' => $vehicle_id,
                'vehicle_title' => $vehicle_title,
                'vehicle_plate' => $vehicle_plate ?: '',
                'start_date' => $start_date_formatted,
                'end_date' => $end_date_formatted,
                'total_price' => $total_price_formatted,
                'status' => $status,
                'status_label' => $status_label,
                'created_date' => $created_date_formatted
            ];
            
            // Multi-status handling - collect all unique statuses for the day
            if (!isset($day_statuses[$pickup_day])) {
                $day_statuses[$pickup_day] = [
                    'type' => 'multi',
                    'statuses' => [$status => 1],
                    'bookings' => [$booking_data]
                ];
            } else {
                $current = $day_statuses[$pickup_day];
                
                if ($current['type'] === 'multi') {
                    // Increment count for existing status or add new status
                    if (isset($current['statuses'][$status])) {
                        $current['statuses'][$status]++;
                    } else {
                        $current['statuses'][$status] = 1;
                    }
                    // Add booking data
                    if (!isset($current['bookings'])) {
                        $current['bookings'] = [];
                    }
                    $current['bookings'][] = $booking_data;
                    $day_statuses[$pickup_day] = $current;
                } else {
                    // Legacy single status - convert to multi
                    $old_status = $current['status'] ?? 'pending';
                    $old_count = $current['count'] ?? 1;
                    $old_bookings = $current['bookings'] ?? [];
                    $day_statuses[$pickup_day] = [
                        'type' => 'multi',
                        'statuses' => [
                            $old_status => $old_count,
                            $status => 1
                        ],
                        'bookings' => array_merge($old_bookings, [$booking_data])
                    ];
                }
            }
        }
        
        // Normalize: convert single-status multi to 'single' type for backward compatibility
        foreach ($day_statuses as $day => $data) {
            if ($data['type'] === 'multi' && count($data['statuses']) === 1) {
                $status = array_key_first($data['statuses']);
                $count = $data['statuses'][$status];
                $bookings = $data['bookings'] ?? [];
                $day_statuses[$day] = [
                    'type' => 'single',
                    'status' => $status,
                    'count' => $count,
                    'bookings' => $bookings
                ];
            }
        }
        
        return $day_statuses;
    }

    /**
     * Priority mapping for statuses.
     */
    private static function get_status_priority(string $status): int
    {
        switch ($status) {
            case 'confirmed':
                return 3;
            case 'pending':
                return 2;
            case 'cancelled':
                return 1;
            default:
                return 0;
        }
    }

    /**
     * Return display icon for a status.
     */
    private static function get_status_icon(string $status): string
    {
        switch ($status) {
            case 'confirmed':
                return '✅';
            case 'pending':
                return '⏳';
            case 'cancelled':
                return '❌';
            default:
                return '📅';
        }
    }

    /**
     * Return descriptive label for status icon.
     */
    private static function get_status_label(string $status): string
    {
        switch ($status) {
            case 'confirmed':
                return __('Confirmed Booking', 'mhm-rentiva');
            case 'pending':
                return __('Pending Booking', 'mhm-rentiva');
            case 'cancelled':
                return __('Cancelled Booking', 'mhm-rentiva');
            default:
                return __('Booking', 'mhm-rentiva');
        }
    }

    /**
     * Render booking ID filter input.
     */
    public static function booking_id_filter(string $post_type): void
    {
        if ($post_type !== 'vehicle_booking') {
            return;
        }
        
        $current = isset($_GET['mhm_booking_id']) ? self::sanitize_text_field_safe((string) $_GET['mhm_booking_id']) : '';
        
        echo '<input type="text" name="mhm_booking_id" value="' . esc_attr($current) . '" placeholder="' . esc_attr__('Booking ID', 'mhm-rentiva') . '" class="postform" style="width: 120px;" />';
    }

    /**
     * Render vehicle license plate filter.
     */
    public static function license_plate_filter(string $post_type): void
    {
        if ($post_type !== 'vehicle_booking') {
            return;
        }
        
        $current = isset($_GET['mhm_license_plate']) ? self::sanitize_text_field_safe((string) $_GET['mhm_license_plate']) : '';
        
        echo '<input type="text" name="mhm_license_plate" value="' . esc_attr($current) . '" placeholder="' . esc_attr__('License Plate', 'mhm-rentiva') . '" class="postform" style="width: 120px;" />';
    }

    /**
     * Apply custom filters to query.
     */
    public static function apply_custom_filters(\WP_Query $q): void
    {
        if (!is_admin() || !$q->is_main_query()) {
            return;
        }
        if (($q->get('post_type') ?? '') !== 'vehicle_booking') {
            return;
        }

        $meta_query = $q->get('meta_query') ?: [];

        // Booking ID filter
        if (isset($_GET['mhm_booking_id']) && $_GET['mhm_booking_id'] !== '') {
            $booking_id = intval($_GET['mhm_booking_id']);
            if ($booking_id > 0) {
                $q->set('p', $booking_id);
            }
        }

        // License plate filter
        if (isset($_GET['mhm_license_plate']) && $_GET['mhm_license_plate'] !== '') {
            $license_plate = self::sanitize_text_field_safe($_GET['mhm_license_plate']);
            
            // Lookup vehicle IDs by license plate fragment
            global $wpdb;
            $vehicle_ids = $wpdb->get_col($wpdb->prepare("
                SELECT DISTINCT p.ID 
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'vehicle'
                    AND p.post_status = 'publish'
                    AND pm.meta_key = '_mhm_rentiva_license_plate'
                    AND pm.meta_value LIKE %s
            ", '%' . $wpdb->esc_like($license_plate) . '%'));
            
            if (!empty($vehicle_ids)) {
                // Collect bookings for those vehicles
                $vehicle_ids_placeholder = implode(',', array_fill(0, count($vehicle_ids), '%d'));
                $booking_ids = $wpdb->get_col($wpdb->prepare("
                    SELECT DISTINCT p.ID 
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    WHERE p.post_type = 'vehicle_booking'
                        AND p.post_status = 'publish'
                        AND pm.meta_key IN ('_booking_vehicle_id', '_mhm_vehicle_id')
                        AND pm.meta_value IN ($vehicle_ids_placeholder)
                ", array_map('intval', $vehicle_ids)));
                
                if (!empty($booking_ids)) {
                    $q->set('post__in', $booking_ids);
                } else {
                    // No bookings found
                    $q->set('post__in', [0]);
                }
            } else {
                // No vehicles found
                $q->set('post__in', [0]);
            }
        }

        if (!empty($meta_query)) {
            $q->set('meta_query', $meta_query);
        }
    }

    /**
     * AJAX: Retrieve customer information payload.
     */
    public static function ajax_get_booking_customer_info(): void
    {
        // Nonce validation
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhm_booking_list_nonce')) {
            wp_send_json_error(['message' => __('Security error', 'mhm-rentiva')]);
        }

        $booking_id = intval($_POST['booking_id'] ?? 0);
        
        if (!$booking_id) {
            wp_send_json_error(['message' => __('Invalid booking ID', 'mhm-rentiva')]);
        }

        // Pull customer meta fields
        $customer_name = get_post_meta($booking_id, '_booking_customer_name', true) ?: 
                        get_post_meta($booking_id, '_mhm_customer_name', true);
        
        $customer_email = get_post_meta($booking_id, '_booking_customer_email', true) ?: 
                         get_post_meta($booking_id, '_mhm_customer_email', true);
        
        $customer_phone = get_post_meta($booking_id, '_booking_customer_phone', true) ?: 
                         get_post_meta($booking_id, '_mhm_customer_phone', true);

        // If meta empty, try resolving via user account
        if (!$customer_name) {
            $user_id = get_post_meta($booking_id, '_mhm_customer_user_id', true);
            if ($user_id) {
                $user = get_userdata($user_id);
                if ($user) {
                    $customer_name = $user->display_name ?: $user->first_name . ' ' . $user->last_name;
                    $customer_email = $user->user_email;
                    $customer_phone = get_user_meta($user_id, 'phone', true);
                }
            }
        }

        wp_send_json_success([
            'customer_name' => $customer_name ?: __('Unknown Customer', 'mhm-rentiva'),
            'customer_email' => $customer_email ?: '',
            'customer_phone' => $customer_phone ?: ''
        ]);
    }

    /**
     * Get booking title display text for list table
     */
    public static function get_booking_title_display(int $post_id): string
    {
        // Use BookingQueryHelper to get customer info (handles multiple meta keys)
        $customer_info = [];
        if (class_exists('\\MHMRentiva\\Admin\\Core\\Utilities\\BookingQueryHelper')) {
            $customer_info = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingCustomerInfo($post_id);
        }
        
        $customer_first_name = $customer_info['first_name'] ?? '';
        $customer_last_name = $customer_info['last_name'] ?? '';
        $customer_email = $customer_info['email'] ?? '';
        $customer_phone = $customer_info['phone'] ?? '';
        
        // Build customer name
        if ($customer_first_name && $customer_last_name) {
            $customer_name = trim($customer_first_name . ' ' . $customer_last_name);
        } elseif ($customer_first_name) {
            $customer_name = $customer_first_name;
        } elseif ($customer_last_name) {
            $customer_name = $customer_last_name;
        } else {
            // Fallback to legacy meta fields
            $customer_name = get_post_meta($post_id, '_booking_customer_name', true) ?: 
                            get_post_meta($post_id, '_mhm_customer_name', true) ?:
                            get_post_meta($post_id, '_mhm_contact_name', true);
        }

        // If still empty, resolve via related WP user
        if (!$customer_name) {
            $user_id = get_post_meta($post_id, '_mhm_customer_user_id', true);
            if ($user_id) {
                $user = get_userdata($user_id);
                if ($user) {
                    $customer_name = $user->display_name ?: trim($user->first_name . ' ' . $user->last_name);
                    if (empty($customer_email)) {
                    $customer_email = $user->user_email;
                    }
                    if (empty($customer_phone)) {
                    $customer_phone = get_user_meta($user_id, 'phone', true);
                    }
                }
            }
        }

        // If still no customer name, try WooCommerce order
        if (!$customer_name && function_exists('wc_get_order')) {
            // Try multiple order ID meta keys
            $order_id = get_post_meta($post_id, '_mhm_order_id', true) ?: 
                       get_post_meta($post_id, '_mhm_wc_order_id', true) ?: 
                       get_post_meta($post_id, '_booking_order_id', true);
            
            if ($order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                    if (empty($customer_email)) {
                        $customer_email = $order->get_billing_email();
                    }
                    if (empty($customer_phone)) {
                        $customer_phone = $order->get_billing_phone();
                    }
                }
            }
        }

        // If still no customer name, try to extract from email
        if (!$customer_name && $customer_email) {
            // Extract name from email (part before @)
            $email_parts = explode('@', $customer_email);
            if (!empty($email_parts[0])) {
                $customer_name = $email_parts[0];
                // Replace dots and underscores with spaces, capitalize first letter
                $customer_name = str_replace(['.', '_', '-'], ' ', $customer_name);
                $customer_name = ucwords(strtolower($customer_name));
            }
        }

        // Without a customer name, use default title
        if (!$customer_name) {
            // Get post title directly from database to avoid infinite loop with the_title filter
            $post = get_post($post_id);
            $default_title = $post ? $post->post_title : '';
            if (empty($default_title) || $default_title === __('Auto Draft', 'mhm-rentiva')) {
                return sprintf(__('Booking #%d', 'mhm-rentiva'), $post_id);
            }
            return $default_title;
        }

        // Return plain text summary prioritizing phone over email
        $new_title = $customer_name;
        
        if ($customer_phone) {
            $new_title .= ' - ' . $customer_phone;
        } elseif ($customer_email) {
            $new_title .= ' - ' . $customer_email;
        }

        return $new_title;
    }

    /**
     * Replace booking title with customer details.
     */
    public static function modify_booking_title(string $title, int $post_id = null): string
    {
        // Prevent infinite loop
        if (self::$in_title_filter) {
            return $title;
        }

        // Apply only within admin booking list context
        if (!is_admin() || !$post_id) {
            return $title;
        }

        // Check if we're on the booking list page
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'vehicle_booking' || $screen->base !== 'edit') {
            return $title;
        }

        // Set flag to prevent recursion
        self::$in_title_filter = true;

        // Use the shared function to get booking title display
        $new_title = self::get_booking_title_display($post_id);
        
        // Reset flag
        self::$in_title_filter = false;
        
        // If we got a valid title, return it; otherwise keep original
        return !empty($new_title) ? $new_title : $title;
    }
}
