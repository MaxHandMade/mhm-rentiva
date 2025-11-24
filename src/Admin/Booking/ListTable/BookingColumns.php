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
            if ($val === 'offline') {
                // Special case for offline: include legacy with receipt id
                $meta[] = [
                    'relation' => 'OR',
                    ['key' => '_booking_payment_gateway', 'value' => 'offline', 'compare' => '='],
                    ['key' => '_mhm_payment_gateway', 'value' => 'offline', 'compare' => '='],
                    ['key' => '_booking_offline_receipt_id', 'compare' => 'EXISTS'],
                    ['key' => '_mhm_offline_receipt_id', 'compare' => 'EXISTS']
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
        
        // Month names - Manual for global compatibility
        $month_names = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
        
        $current_month_name = $month_names[$current_month];
        $days_in_month = date('t', mktime(0, 0, 0, $current_month, 1, $current_year));
        $today = date('j');
        
        // Fetch booking entries for calendar
        $booking_days = self::get_booking_calendar_days($current_month, $current_year);
        
        
        ?>
        <div class="calendar-container booking-page">
            <div class="calendar-header">
                <button id="prevMonth">&lt;</button>
                <h2 id="monthYear"><?php echo esc_html($current_month_name . ' ' . $current_year); ?></h2>
                <button id="nextMonth">&gt;</button>
            </div>
            <div class="calendar-days">
                <div class="day-name"><?php _e('Mon', 'mhm-rentiva'); ?></div>
                <div class="day-name"><?php _e('Tue', 'mhm-rentiva'); ?></div>
                <div class="day-name"><?php _e('Wed', 'mhm-rentiva'); ?></div>
                <div class="day-name"><?php _e('Thu', 'mhm-rentiva'); ?></div>
                <div class="day-name"><?php _e('Fri', 'mhm-rentiva'); ?></div>
                <div class="day-name"><?php _e('Sat', 'mhm-rentiva'); ?></div>
                <div class="day-name"><?php _e('Sun', 'mhm-rentiva'); ?></div>
            </div>
            <div id="calendarDays" class="calendar-grid">
                <?php
                // Determine day-of-week for the first day of the month
                $first_day_of_month = date('w', mktime(0, 0, 0, $current_month, 1, $current_year));
                // Normalize so Monday = 1, Sunday = 0
                $first_day_of_month = ($first_day_of_month == 0) ? 6 : $first_day_of_month - 1;
                
                // Previous month's trailing days
                $prev_month = $current_month == 1 ? 12 : $current_month - 1;
                $prev_year = $current_month == 1 ? $current_year - 1 : $current_year;
                $prev_days_in_month = cal_days_in_month(CAL_GREGORIAN, $prev_month, $prev_year);
                
                for ($i = $first_day_of_month; $i > 0; $i--) {
                    $day = $prev_days_in_month - $i + 1;
                    echo '<div class="prev-date">' . $day . '</div>';
                }
                
                // Current month's days
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $is_today = ($day == $today && $current_month == date('n') && $current_year == date('Y'));
                    $booking_data = $booking_days[$day] ?? null;
                    
                    $classes = [];
                    if ($is_today) $classes[] = 'today';
                    
                    // Support split-day display
                    if ($booking_data) {
                        $classes[] = 'has-booking';
                        
                        if ($booking_data['type'] === 'single') {
                            $classes[] = 'booking-' . $booking_data['status'];
                            $data_attrs = 'data-status="' . esc_attr($booking_data['status']) . '" data-count="' . $booking_data['count'] . '"';
                        } else {
                            $classes[] = 'split-day';
                            $classes[] = 'booking-' . $booking_data['left_status'] . '-' . $booking_data['right_status'];
                            $data_attrs = 'data-left-status="' . esc_attr($booking_data['left_status']) . '" data-right-status="' . esc_attr($booking_data['right_status']) . '" data-left-count="' . $booking_data['left_count'] . '" data-right-count="' . $booking_data['right_count'] . '"';
                        }
                    } else {
                        $data_attrs = 'data-status=""';
                    }
                    
                    echo '<div class="' . implode(' ', $classes) . '" data-day="' . $day . '" ' . $data_attrs . '>';
                    
                    if ($booking_data && $booking_data['type'] === 'split') {
                        // Split day HTML
                        echo '<div class="day-number">' . $day . '</div>';
                        echo '<div class="split-container">';
                        echo '<div class="split-left booking-' . $booking_data['left_status'] . '" title="' . esc_attr(self::get_status_label($booking_data['left_status'])) . ' (' . $booking_data['left_count'] . ')">';
                        echo '<span class="split-icon">' . self::get_status_icon($booking_data['left_status']) . '</span>';
                        echo '</div>';
                        echo '<div class="split-right booking-' . $booking_data['right_status'] . '" title="' . esc_attr(self::get_status_label($booking_data['right_status'])) . ' (' . $booking_data['right_count'] . ')">';
                        echo '<span class="split-icon">' . self::get_status_icon($booking_data['right_status']) . '</span>';
                        echo '</div>';
                        echo '</div>';
                    } else {
                        // Normal day HTML
                        echo $day;
                        if ($booking_data && $booking_data['type'] === 'single') {
                            $icon = self::get_status_icon($booking_data['status']);
                            echo '<span class="booking-icon" title="' . esc_attr(self::get_status_label($booking_data['status'])) . ' (' . $booking_data['count'] . ')">' . $icon . '</span>';
                        }
                    }
                    
                    echo '</div>';
                }
                
                // Next month's leading days
                $last_day_of_month = date('w', mktime(0, 0, 0, $current_month, (int)$days_in_month, $current_year));
                $last_day_of_month = ($last_day_of_month == 0) ? 6 : $last_day_of_month - 1;
                $next_days = 6 - $last_day_of_month;
                
                for ($j = 1; $j <= $next_days; $j++) {
                    echo '<div class="next-date">' . $j . '</div>';
                }
                ?>
            </div>
            
            <!-- Status Color Information -->
            <div class="calendar-legend">
                <h4><?php esc_html_e('Status Legend', 'mhm-rentiva'); ?></h4>
                <div class="legend-items">
                    <div class="legend-item">
                        <span class="legend-color booking-pending"></span>
                        <span class="legend-label"><?php esc_html_e('Pending', 'mhm-rentiva'); ?></span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color booking-confirmed"></span>
                        <span class="legend-label"><?php esc_html_e('Confirmed', 'mhm-rentiva'); ?></span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color booking-in_progress"></span>
                        <span class="legend-label"><?php esc_html_e('In Progress', 'mhm-rentiva'); ?></span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color booking-completed"></span>
                        <span class="legend-label"><?php esc_html_e('Completed', 'mhm-rentiva'); ?></span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color booking-cancelled"></span>
                        <span class="legend-label"><?php esc_html_e('Cancelled', 'mhm-rentiva'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        /* ✅ Legend Stilleri */
        .calendar-legend {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .calendar-legend h4 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 14px;
        }
        
        .legend-items {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 3px;
            display: inline-block;
        }
        
        .legend-color.booking-pending {
            background-color: #ffc107;
        }
        
        .legend-color.booking-confirmed {
            background-color: #28a745;
        }
        
        .legend-color.booking-in_progress {
            background-color: #fd7e14;
        }
        
        .legend-color.booking-completed {
            background-color: #0073E6;
        }
        
        .legend-color.booking-cancelled {
            background-color: #dc3545;
        }
        
        .legend-label {
            font-size: 12px;
            color: #555;
        }
        </style>
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
        // Retrieve relevant bookings
        $bookings = get_posts([
            'post_type' => 'vehicle_booking',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_mhm_pickup_date',
                    'compare' => 'EXISTS'
                ],
                [
                    'key' => '_booking_pickup_date',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);
        
        $day_statuses = [];
        
        foreach ($bookings as $booking) {
            // Pickup date
            $pickup_date = get_post_meta($booking->ID, '_mhm_pickup_date', true) ?: get_post_meta($booking->ID, '_booking_pickup_date', true);
            
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
            $status = get_post_meta($booking->ID, '_mhm_status', true) ?: 
                     get_post_meta($booking->ID, '_booking_status', true) ?: 
                     'pending';
            
            // Split-day handling when multiple statuses exist
            if (!isset($day_statuses[$pickup_day])) {
                $day_statuses[$pickup_day] = [
                    'type' => 'single',
                    'status' => $status,
                    'count' => 1
                ];
            } else {
                $current = $day_statuses[$pickup_day];
                
                // Same status: increase count
                if ($current['type'] === 'single' && $current['status'] === $status) {
                    $day_statuses[$pickup_day]['count']++;
                } 
                // Different status: convert to split-day
                else if ($current['type'] === 'single' && $current['status'] !== $status) {
                    $day_statuses[$pickup_day] = [
                        'type' => 'split',
                        'left_status' => $current['status'],
                        'right_status' => $status,
                        'left_count' => $current['count'],
                        'right_count' => 1
                    ];
                }
                // Already split: bump appropriate side
                else if ($current['type'] === 'split') {
                    if ($current['right_status'] === $status) {
                        $day_statuses[$pickup_day]['right_count']++;
                    } else {
                        // Third status fallback: increment left side
                        $day_statuses[$pickup_day]['left_count']++;
                    }
                }
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
     * Replace booking title with customer details.
     */
    public static function modify_booking_title(string $title, int $post_id = null): string
    {
        // Apply only within admin booking list context
        if (!is_admin() || !$post_id) {
            return $title;
        }

        global $post_type;
        if ($post_type !== 'vehicle_booking') {
            return $title;
        }

        // Gather customer info (first/last name fields preferred)
        $customer_first_name = get_post_meta($post_id, '_mhm_customer_first_name', true);
        $customer_last_name = get_post_meta($post_id, '_mhm_customer_last_name', true);
        
        if ($customer_first_name && $customer_last_name) {
            $customer_name = trim($customer_first_name . ' ' . $customer_last_name);
        } else {
            // Fallback to legacy meta fields
            $customer_name = get_post_meta($post_id, '_booking_customer_name', true) ?: 
                            get_post_meta($post_id, '_mhm_customer_name', true);
        }
        
        $customer_email = get_post_meta($post_id, '_booking_customer_email', true) ?: 
                         get_post_meta($post_id, '_mhm_customer_email', true);
        
        $customer_phone = get_post_meta($post_id, '_booking_customer_phone', true) ?: 
                         get_post_meta($post_id, '_mhm_customer_phone', true);

        // If still empty, resolve via related WP user
        if (!$customer_name) {
            $user_id = get_post_meta($post_id, '_mhm_customer_user_id', true);
            if ($user_id) {
                $user = get_userdata($user_id);
                if ($user) {
                    $customer_name = $user->display_name ?: $user->first_name . ' ' . $user->last_name;
                    $customer_email = $user->user_email;
                    $customer_phone = get_user_meta($user_id, 'phone', true);
                }
            }
        }

        // Without a customer name, keep original title
        if (!$customer_name) {
            return $title;
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
}
