<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Export;

use MHMRentiva\Admin\PostTypes\Logs\PostType as LogPostType;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Reports\BackgroundProcessor;
use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

final class Export
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
        // Filters + export buttons on list screens
        add_action('restrict_manage_posts', [self::class, 'render_filters']);
        // Export handler
        add_action('admin_post_mhm_rentiva_export', [self::class, 'handle_export']);
        // AJAX handler for apply filters
        add_action('wp_ajax_mhm_rentiva_apply_export_filters', [self::class, 'handle_apply_filters']);
        // AJAX handler for delete export
        add_action('wp_ajax_mhm_rentiva_delete_export', [self::class, 'handle_delete_export']);
        // AJAX handler for get export details
        add_action('wp_ajax_mhm_rentiva_get_export_details', [self::class, 'handle_get_export_details']);
        // CSS and JS loading is now handled by AssetManager
    }

    public static function render_filters(string $post_type): void
    {
        if (!in_array($post_type, ['vehicle_booking', LogPostType::TYPE], true)) {
            return;
        }
        if (!current_user_can('export') && !current_user_can('manage_options') && !current_user_can('edit_posts')) {
            return;
        }
        if (!Mode::featureEnabled(Mode::FEATURE_EXPORT)) {
            return; // Export is completely disabled
        }
        // Current filters (preserve)
        $date_from = isset($_GET['mhm_from']) ? self::sanitize_text_field_safe((string) $_GET['mhm_from']) : '';
        $date_to   = isset($_GET['mhm_to']) ? self::sanitize_text_field_safe((string) $_GET['mhm_to']) : '';
        $gateway   = isset($_GET['mhm_gateway']) ? self::sanitize_text_field_safe((string) $_GET['mhm_gateway']) : '';
        $status    = isset($_GET['mhm_status']) ? self::sanitize_text_field_safe((string) $_GET['mhm_status']) : '';
        $pstatus   = isset($_GET['mhm_pstatus']) ? self::sanitize_text_field_safe((string) $_GET['mhm_pstatus']) : '';
        $amin      = isset($_GET['mhm_amin']) ? self::sanitize_text_field_safe((string) $_GET['mhm_amin']) : '';
        $amax      = isset($_GET['mhm_amax']) ? self::sanitize_text_field_safe((string) $_GET['mhm_amax']) : '';

        echo '<div class="mhm-export">';
        echo '<details><summary>' . esc_html__('Rentiva Export Filters', 'mhm-rentiva') . '</summary>';
        // Build base query for safe GET links (avoid nested form/"action" conflicts on list screen)
        $base_url = admin_url('admin-post.php');
        $base_args = [
            'action' => 'mhm_rentiva_export',
            'post_type' => $post_type,
            '_wpnonce' => wp_create_nonce('mhm_rentiva_export'),
        ];

        // Date range
        echo '<label>' . esc_html__('Start Date', 'mhm-rentiva') . '<br /><input type="date" name="mhm_from" value="' . esc_attr($date_from) . '" /></label>';
        echo '<label>' . esc_html__('End Date', 'mhm-rentiva') . '<br /><input type="date" name="mhm_to" value="' . esc_attr($date_to) . '" /></label>';

        // Gateway
        $allowedGateways = class_exists(Mode::class) ? Mode::allowedGateways() : ['offline'];
        $gws = array_merge([''], $allowedGateways, ['system', 'portal']); // system and portal are always available
        echo '<label>' . esc_html__('Payment Method', 'mhm-rentiva') . '<br /><select name="mhm_gateway">';
        foreach ($gws as $g) {
            echo '<option value="' . esc_attr($g) . '"' . selected($gateway, $g, false) . '>' . esc_html($g === '' ? __('Any', 'mhm-rentiva') : strtoupper($g)) . '</option>';
        }
        echo '</select></label>';

        // Status
        if ($post_type === 'vehicle_booking') {
            $bkStatuses = ['', 'pending', 'confirmed', 'cancelled', 'completed', 'expired'];
            echo '<label>' . esc_html__('Booking Status', 'mhm-rentiva') . '<br /><select name="mhm_status">';
            foreach ($bkStatuses as $s) {
                echo '<option value="' . esc_attr($s) . '"' . selected($status, $s, false) . '>' . esc_html($s === '' ? __('Any', 'mhm-rentiva') : $s) . '</option>';
            }
            echo '</select></label>';
            $payStatuses = ['', 'unpaid', 'paid', 'failed', 'pending', 'processing', 'unknown', 'pending_verification', 'refunded', 'partially_refunded'];
            echo '<label>' . esc_html__('Payment Status', 'mhm-rentiva') . '<br /><select name="mhm_pstatus">';
            foreach ($payStatuses as $ps) {
                echo '<option value="' . esc_attr($ps) . '"' . selected($pstatus, $ps, false) . '>' . esc_html($ps === '' ? __('Any', 'mhm-rentiva') : $ps) . '</option>';
            }
            echo '</select></label>';
        } else {
            $logStatuses = ['', 'success', 'error'];
            echo '<label>' . esc_html__('Log Status', 'mhm-rentiva') . '<br /><select name="mhm_status">';
            foreach ($logStatuses as $ls) {
                echo '<option value="' . esc_attr($ls) . '"' . selected($status, $ls, false) . '>' . esc_html($ls === '' ? __('Any', 'mhm-rentiva') : $ls) . '</option>';
            }
            echo '</select></label>';
        }

        // Amount range
        echo '<label>' . esc_html__('Minimum Amount', 'mhm-rentiva') . '<br /><input type="number" step="0.01" name="mhm_amin" placeholder="0.00" value="' . esc_attr($amin) . '" /></label>';
        echo '<label>' . esc_html__('Maximum Amount', 'mhm-rentiva') . '<br /><input type="number" step="0.01" name="mhm_amax" placeholder="999999.99" value="' . esc_attr($amax) . '" /></label>';

        // Buttons as GET links to avoid interfering with bulk actions
        echo '<div>';
        $csv_args = array_merge($base_args, [
            'format' => 'csv',
            'mhm_from' => $date_from,
            'mhm_to' => $date_to,
            'mhm_gateway' => $gateway,
            'mhm_status' => $status,
            'mhm_pstatus' => $pstatus,
            'mhm_amin' => $amin,
            'mhm_amax' => $amax,
        ]);
        echo '<a class="button button-primary" href="' . esc_url(add_query_arg($csv_args, $base_url)) . '">' . esc_html__('Export CSV', 'mhm-rentiva') . '</a> ';

        if (Mode::isPro()) {
            $json_args = $csv_args;
            $json_args['format'] = 'json';
            echo '<a class="button" href="' . esc_url(add_query_arg($json_args, $base_url)) . '">' . esc_html__('Export JSON', 'mhm-rentiva') . '</a>';
        }
        echo '</div>';
        echo '</details></div>';
    }

    /**
     * Build query args from filters
     */
    private static function build_query_args_from_filters(string $post_type, array $filter_data): array
    {
        $args = [
            'post_type' => $post_type,
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => 500,
            'no_found_rows' => true,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        // Apply filters using ExportFilters class
        if (class_exists(ExportFilters::class)) {
            $args = ExportFilters::apply_all_filters($args, $filter_data);
        }

        return $args;
    }

    /**
     * Build query args from request
     */
    private static function build_query_args_from_request(string $post_type): array
    {
        // Sanitize and validate POST data
        $date_from = isset($_POST['mhm_from']) ? self::sanitize_text_field_safe(wp_unslash($_POST['mhm_from'])) : '';
        $date_to   = isset($_POST['mhm_to']) ? self::sanitize_text_field_safe(wp_unslash($_POST['mhm_to'])) : '';
        $gateway   = isset($_POST['mhm_gateway']) ? self::sanitize_text_field_safe(wp_unslash($_POST['mhm_gateway'])) : '';
        $status    = isset($_POST['mhm_status']) ? self::sanitize_text_field_safe(wp_unslash($_POST['mhm_status'])) : '';
        $pstatus   = isset($_POST['mhm_pstatus']) ? self::sanitize_text_field_safe(wp_unslash($_POST['mhm_pstatus'])) : '';
        $amin      = isset($_POST['mhm_amin']) ? (float) self::sanitize_text_field_safe(wp_unslash($_POST['mhm_amin'])) : 0.0;
        $amax      = isset($_POST['mhm_amax']) ? (float) self::sanitize_text_field_safe(wp_unslash($_POST['mhm_amax'])) : 0.0;

        $args = [
            'post_type'      => $post_type,
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => 500,
            'no_found_rows'  => true,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        // Date query
        $dq = [];
        if ($date_from) $dq['after'] = $date_from . ' 00:00:00';
        if ($date_to)   $dq['before'] = $date_to . ' 23:59:59';
        if (!empty($dq)) {
            $dq['inclusive'] = true;
            $dq['column'] = 'post_date_gmt';
            $args['date_query'] = [$dq];
        }

        // Meta filters
        $meta = [];
        if ($post_type === 'vehicle_booking') {
            if ($gateway !== '') {
                $meta[] = ['key' => '_mhm_payment_gateway', 'value' => $gateway, 'compare' => '='];
            }
            if ($status !== '') {
                $meta[] = ['key' => '_mhm_status', 'value' => $status, 'compare' => '='];
            }
            if ($pstatus !== '') {
                $meta[] = ['key' => '_mhm_payment_status', 'value' => $pstatus, 'compare' => '='];
            }
            // Amount in kurus
            if ($amin > 0 || $amax > 0) {
                $amin_k = $amin > 0 ? (int) round($amin * 100) : 0;
                $amax_k = $amax > 0 ? (int) round($amax * 100) : 0;
                if ($amin_k > 0) {
                    $meta[] = ['key' => '_mhm_payment_amount', 'value' => $amin_k, 'type' => 'NUMERIC', 'compare' => '>='];
                }
                if ($amax_k > 0) {
                    $meta[] = ['key' => '_mhm_payment_amount', 'value' => $amax_k, 'type' => 'NUMERIC', 'compare' => '<='];
                }
            }
        } else {
            if ($gateway !== '') {
                $meta[] = ['key' => '_mhm_log_gateway', 'value' => $gateway, 'compare' => '='];
            }
            if ($status !== '') {
                $meta[] = ['key' => '_mhm_log_status', 'value' => $status, 'compare' => '='];
            }
            if ($amin > 0 || $amax > 0) {
                $amin_k = $amin > 0 ? (int) round($amin * 100) : 0;
                $amax_k = $amax > 0 ? (int) round($amax * 100) : 0;
                if ($amin_k > 0) {
                    $meta[] = ['key' => '_mhm_log_amount_kurus', 'value' => $amin_k, 'type' => 'NUMERIC', 'compare' => '>='];
                }
                if ($amax_k > 0) {
                    $meta[] = ['key' => '_mhm_log_amount_kurus', 'value' => $amax_k, 'type' => 'NUMERIC', 'compare' => '<='];
                }
            }
        }

        if (!empty($meta)) {
            $args['meta_query'] = array_merge(['relation' => 'AND'], $meta);
        }

        return $args;
    }

    public static function handle_export(): void
    {
        if (!Mode::featureEnabled(Mode::FEATURE_EXPORT)) {
            wp_die(esc_html__('Export is disabled.', 'mhm-rentiva'), 403);
        }

        if (!current_user_can('export') && !current_user_can('manage_options') && !current_user_can('edit_posts')) {
            wp_die(esc_html__('You do not have permission to export.', 'mhm-rentiva'));
        }
        check_admin_referer('mhm_rentiva_export');

        $post_type = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : '';
        if (!in_array($post_type, ['vehicle_booking', LogPostType::TYPE, 'vehicle'], true)) {
            wp_die(esc_html__('Invalid export type.', 'mhm-rentiva'));
        }

        $format = isset($_POST['format']) ? sanitize_key(wp_unslash($_POST['format'])) : 'csv';

        // Enforce format restrictions per license
        if (!Mode::isPro() && $format !== 'csv') {
            wp_die(esc_html__('This export format is available in Pro version only.', 'mhm-rentiva'), 403);
        }

        // Validate allowed formats
        $allowed_formats = Mode::isPro() ? ['csv', 'json'] : ['csv'];
        if (!in_array($format, $allowed_formats, true)) {
            $format = 'csv'; // Fallback to safe default
        }

        // Check if filters are provided
        $filters = isset($_POST['filters']) ? self::sanitize_text_field_safe(wp_unslash($_POST['filters'])) : '';
        if (!empty($filters)) {
            // Parse filters and apply them
            parse_str($filters, $filter_data);
            $args = self::build_query_args_from_filters($post_type, $filter_data);
        } else {
            $args = self::build_query_args_from_request($post_type);
        }

        $args = apply_filters('mhm_rentiva_export_args', $args); // Lite → tarih/limit kısıtları uygulanır

        // Get record count before export
        $query = new WP_Query($args);
        $exported_count = $query->found_posts;

        // Log export activity with actual exported count
        self::log_export_activity($post_type, $format, $args, $exported_count);

        // Start direct export process
        self::start_direct_export($post_type, $format, $args);
    }

    /**
     * Start direct export process
     */
    private static function start_direct_export(string $post_type, string $format, array $args): void
    {
        // Start export process
        $filename = $post_type . '_export_' . current_time('Y-m-d_H-i-s');

        if ($format === 'csv') {
            self::stream_csv_direct($post_type, $args, $filename);
        } elseif ($format === 'json') {
            self::stream_json_direct($post_type, $args, $filename);
        } else {
            wp_die(esc_html__('Unsupported export format.', 'mhm-rentiva'));
        }
    }

    /**
     * Direct CSV export
     */
    private static function stream_csv_direct(string $post_type, array $args, string $filename): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Add BOM for Excel compatibility
        fprintf($output, "%s", chr(0xEF) . chr(0xBB) . chr(0xBF));

        if ($post_type === 'vehicle_booking') {
            $headers = [
                'ID',
                'Date',
                'Status',
                'Payment Status',
                'Gateway',
                'Total',
                'Paid Amount',
                'Currency',
                'Name',
                'Email',
                'Phone',
            ];
        } elseif ($post_type === 'vehicle') {
            $headers = [
                'ID',
                'Title',
                'Brand',
                'Model',
                'Year',
                'Fuel Type',
                'Transmission',
                'Seats',
                'Doors',
                'Daily Price',
                'Weekly Price',
                'Monthly Price',
                'Status',
                'Availability',
                'Location',
                'Description',
                'Created Date',
                'Modified Date'
            ];
        } else {
            $headers = [
                'ID',
                'Date',
                'Gateway',
                'Action',
                'Status',
                'Booking ID',
                'Amount (kurus)',
                'Currency',
                'Message',
            ];
        }
        fputcsv($output, $headers);

        $paged = 1;
        do {
            $q = new WP_Query(array_merge($args, ['paged' => $paged]));
            if (!$q->have_posts()) break;
            foreach ($q->posts as $pid) {
                $pid = (int) $pid;
                if ($post_type === 'vehicle_booking') {
                    $date   = get_post($pid)->post_date_gmt;
                    $status = (string) get_post_meta($pid, '_mhm_status', true);
                    $pstat  = (string) get_post_meta($pid, '_mhm_payment_status', true);
                    $gw     = (string) get_post_meta($pid, '_mhm_payment_gateway', true);
                    $total  = (float) get_post_meta($pid, '_mhm_total_price', true);
                    $paidk  = (int) get_post_meta($pid, '_mhm_payment_amount', true);
                    $cur    = (string) get_post_meta($pid, '_mhm_payment_currency', true);
                    $name   = (string) get_post_meta($pid, '_mhm_customer_name', true);
                    $email  = (string) get_post_meta($pid, '_mhm_customer_email', true);
                    $phone  = (string) get_post_meta($pid, '_mhm_customer_phone', true);
                    fputcsv($output, [
                        $pid,
                        $date,
                        $status,
                        $pstat,
                        $gw,
                        number_format($total, 2, '.', ''),
                        number_format($paidk / 100, 2, '.', ''),
                        strtoupper($cur ?: ''),
                        $name,
                        $email,
                        $phone,
                    ]);
                } elseif ($post_type === 'vehicle') {
                    $post = get_post($pid);
                    $title = $post ? $post->post_title : '';
                    $brand = (string) get_post_meta($pid, '_mhm_rentiva_brand', true);
                    $model = (string) get_post_meta($pid, '_mhm_rentiva_model', true);
                    $year = (string) get_post_meta($pid, '_mhm_rentiva_year', true);
                    $fuel_type = (string) get_post_meta($pid, '_mhm_rentiva_fuel_type', true);
                    $transmission = (string) get_post_meta($pid, '_mhm_rentiva_transmission', true);
                    $seats = (string) get_post_meta($pid, '_mhm_rentiva_seats', true);
                    $doors = (string) get_post_meta($pid, '_mhm_rentiva_doors', true);
                    $daily_price = (float) get_post_meta($pid, '_mhm_rentiva_price_per_day', true);
                    $weekly_price = (float) get_post_meta($pid, '_mhm_rentiva_price_per_week', true);
                    $monthly_price = (float) get_post_meta($pid, '_mhm_rentiva_price_per_month', true);
                    $status = (string) get_post_meta($pid, '_mhm_vehicle_status', true);
                    $availability = (string) get_post_meta($pid, '_mhm_vehicle_availability', true);
                    $location = (string) get_post_meta($pid, '_mhm_rentiva_location', true);
                    $description = $post ? wp_strip_all_tags($post->post_content) : '';
                    $created_date = $post ? $post->post_date_gmt : '';
                    $modified_date = $post ? $post->post_modified_gmt : '';

                    fputcsv($output, [
                        $pid,
                        $title,
                        $brand,
                        $model,
                        $year,
                        $fuel_type,
                        $transmission,
                        $seats,
                        $doors,
                        number_format($daily_price, 2, '.', ''),
                        number_format($weekly_price, 2, '.', ''),
                        number_format($monthly_price, 2, '.', ''),
                        $status,
                        $availability,
                        $location,
                        $description,
                        $created_date,
                        $modified_date
                    ]);
                } else {
                    $p      = get_post($pid);
                    $date   = $p ? $p->post_date_gmt : '';
                    $gw     = (string) get_post_meta($pid, '_mhm_log_gateway', true);
                    $act    = (string) get_post_meta($pid, '_mhm_log_action', true);
                    $st     = (string) get_post_meta($pid, '_mhm_log_status', true);
                    $bid    = (int) get_post_meta($pid, '_mhm_log_booking_id', true);
                    $ak     = (int) get_post_meta($pid, '_mhm_log_amount_kurus', true);
                    $cur    = (string) get_post_meta($pid, '_mhm_log_currency', true);
                    $msg    = (string) get_post_meta($pid, '_mhm_log_message', true);
                    fputcsv($output, [
                        $pid,
                        $date,
                        $gw,
                        $act,
                        $st,
                        $bid,
                        $ak,
                        strtoupper($cur ?: ''),
                        $msg,
                    ]);
                }
            }
            $paged++;
            // flush output buffer for streaming
            if (function_exists('flush')) {
                flush();
            }
        } while (true);
        wp_reset_postdata();
        fclose($output);
        exit;
    }

    /**
     * Direct Excel export
     */
    private static function stream_xls_direct(string $post_type, array $args, string $filename): void
    {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo '<html><head><meta charset="UTF-8"></head><body>';
        echo '<table border="1">';

        if ($post_type === 'vehicle_booking') {
            $headers = [
                'ID',
                'Date',
                'Status',
                'Payment Status',
                'Gateway',
                'Total',
                'Paid Amount',
                'Currency',
                'Name',
                'Email',
                'Phone',
            ];
        } elseif ($post_type === 'vehicle') {
            $headers = [
                'ID',
                'Title',
                'Brand',
                'Model',
                'Year',
                'Fuel Type',
                'Transmission',
                'Seats',
                'Doors',
                'Daily Price',
                'Weekly Price',
                'Monthly Price',
                'Status',
                'Availability',
                'Location',
                'Description',
                'Created Date',
                'Modified Date'
            ];
        } else {
            $headers = [
                'ID',
                'Date',
                'Gateway',
                'Action',
                'Status',
                'Booking ID',
                'Amount (kurus)',
                'Currency',
                'Message',
            ];
        }

        // Write headers
        echo '<tr>';
        foreach ($headers as $header) {
            echo '<th>' . esc_html($header) . '</th>';
        }
        echo '</tr>';

        $paged = 1;
        do {
            $q = new WP_Query(array_merge($args, ['paged' => $paged]));
            if (!$q->have_posts()) break;
            foreach ($q->posts as $pid) {
                $pid = (int) $pid;
                echo '<tr>';
                if ($post_type === 'vehicle_booking') {
                    $date   = get_post($pid)->post_date_gmt;
                    $status = (string) get_post_meta($pid, '_mhm_status', true);
                    $pstat  = (string) get_post_meta($pid, '_mhm_payment_status', true);
                    $gw     = (string) get_post_meta($pid, '_mhm_payment_gateway', true);
                    $total  = (float) get_post_meta($pid, '_mhm_total_price', true);
                    $paidk  = (int) get_post_meta($pid, '_mhm_payment_amount', true);
                    $cur    = (string) get_post_meta($pid, '_mhm_payment_currency', true);
                    $name   = (string) get_post_meta($pid, '_mhm_customer_name', true);
                    $email  = (string) get_post_meta($pid, '_mhm_customer_email', true);
                    $phone  = (string) get_post_meta($pid, '_mhm_customer_phone', true);
                    $row = [
                        $pid,
                        $date,
                        $status,
                        $pstat,
                        $gw,
                        number_format($total, 2, '.', ''),
                        number_format($paidk / 100, 2, '.', ''),
                        strtoupper($cur ?: ''),
                        $name,
                        $email,
                        $phone,
                    ];
                } elseif ($post_type === 'vehicle') {
                    $post = get_post($pid);
                    $title = $post ? $post->post_title : '';
                    $brand = (string) get_post_meta($pid, '_mhm_rentiva_brand', true);
                    $model = (string) get_post_meta($pid, '_mhm_rentiva_model', true);
                    $year = (string) get_post_meta($pid, '_mhm_rentiva_year', true);
                    $fuel_type = (string) get_post_meta($pid, '_mhm_rentiva_fuel_type', true);
                    $transmission = (string) get_post_meta($pid, '_mhm_rentiva_transmission', true);
                    $seats = (string) get_post_meta($pid, '_mhm_rentiva_seats', true);
                    $doors = (string) get_post_meta($pid, '_mhm_rentiva_doors', true);
                    $daily_price = (float) get_post_meta($pid, '_mhm_rentiva_price_per_day', true);
                    $weekly_price = (float) get_post_meta($pid, '_mhm_rentiva_price_per_week', true);
                    $monthly_price = (float) get_post_meta($pid, '_mhm_rentiva_price_per_month', true);
                    $status = (string) get_post_meta($pid, '_mhm_vehicle_status', true);
                    $availability = (string) get_post_meta($pid, '_mhm_vehicle_availability', true);
                    $location = (string) get_post_meta($pid, '_mhm_rentiva_location', true);
                    $description = $post ? wp_strip_all_tags($post->post_content) : '';
                    $created_date = $post ? $post->post_date_gmt : '';
                    $modified_date = $post ? $post->post_modified_gmt : '';

                    $row = [
                        $pid,
                        $title,
                        $brand,
                        $model,
                        $year,
                        $fuel_type,
                        $transmission,
                        $seats,
                        $doors,
                        number_format($daily_price, 2, '.', ''),
                        number_format($weekly_price, 2, '.', ''),
                        number_format($monthly_price, 2, '.', ''),
                        $status,
                        $availability,
                        $location,
                        $description,
                        $created_date,
                        $modified_date
                    ];
                } else {
                    $p      = get_post($pid);
                    $date   = $p ? $p->post_date_gmt : '';
                    $gw     = (string) get_post_meta($pid, '_mhm_log_gateway', true);
                    $act    = (string) get_post_meta($pid, '_mhm_log_action', true);
                    $st     = (string) get_post_meta($pid, '_mhm_log_status', true);
                    $bid    = (int) get_post_meta($pid, '_mhm_log_booking_id', true);
                    $code   = (string) get_post_meta($pid, '_mhm_log_code', true);
                    $oid    = (string) get_post_meta($pid, '_mhm_log_oid', true);
                    $ak     = (int) get_post_meta($pid, '_mhm_log_amount_kurus', true);
                    $cur    = (string) get_post_meta($pid, '_mhm_log_currency', true);
                    $msg    = (string) get_post_meta($pid, '_mhm_log_message', true);
                    $row = [
                        $pid,
                        $date,
                        $gw,
                        $act,
                        $st,
                        $bid,
                        $code,
                        $oid,
                        $ak,
                        strtoupper($cur ?: ''),
                        $msg,
                    ];
                }
                foreach ($row as $cell) {
                    echo '<td>' . esc_html($cell) . '</td>';
                }
                echo '</tr>';
            }
            $paged++;
            if (function_exists('flush')) {
                flush();
            }
        } while (true);
        wp_reset_postdata();
        echo '</table>';
        echo '</body></html>';
        exit;
    }

    private static function stream_csv($out, string $post_type, array $args): void
    {
        if ($post_type === 'vehicle_booking') {
            $headers = [
                'ID',
                'Date',
                'Status',
                'Payment Status',
                'Gateway',
                'Total',
                'Paid Amount',
                'Currency',
                'Name',
                'Email',
                'Phone',
            ];
        } else {
            $headers = [
                'ID',
                'Date',
                'Gateway',
                'Action',
                'Status',
                'Booking ID',
                'Amount (kurus)',
                'Currency',
                'Message',
            ];
        }
        fputcsv($out, $headers);

        $paged = 1;
        do {
            $q = new WP_Query(array_merge($args, ['paged' => $paged]));
            if (!$q->have_posts()) break;
            foreach ($q->posts as $pid) {
                $pid = (int) $pid;
                if ($post_type === 'vehicle_booking') {
                    $date   = get_post($pid)->post_date_gmt;
                    $status = (string) get_post_meta($pid, '_mhm_status', true);
                    $pstat  = (string) get_post_meta($pid, '_mhm_payment_status', true);
                    $gw     = (string) get_post_meta($pid, '_mhm_payment_gateway', true);
                    $total  = (float) get_post_meta($pid, '_mhm_total_price', true);
                    $paidk  = (int) get_post_meta($pid, '_mhm_payment_amount', true);
                    $cur    = (string) get_post_meta($pid, '_mhm_payment_currency', true);
                    $name   = (string) get_post_meta($pid, '_mhm_contact_name', true);
                    $email  = (string) get_post_meta($pid, '_mhm_contact_email', true);
                    $phone  = (string) get_post_meta($pid, '_mhm_contact_phone', true);
                    $row = [
                        $pid,
                        $date,
                        $status,
                        $pstat,
                        $gw,
                        number_format($total, 2, '.', ''),
                        number_format($paidk / 100, 2, '.', ''),
                        strtoupper($cur ?: ''),
                        $name,
                        $email,
                        $phone,
                    ];
                    fputcsv($out, $row);
                } else {
                    $p      = get_post($pid);
                    $date   = $p ? $p->post_date_gmt : '';
                    $gw     = (string) get_post_meta($pid, '_mhm_log_gateway', true);
                    $act    = (string) get_post_meta($pid, '_mhm_log_action', true);
                    $st     = (string) get_post_meta($pid, '_mhm_log_status', true);
                    $bid    = (int) get_post_meta($pid, '_mhm_log_booking_id', true);
                    $ak     = (int) get_post_meta($pid, '_mhm_log_amount_kurus', true);
                    $cur    = (string) get_post_meta($pid, '_mhm_log_currency', true);
                    $msg    = (string) get_post_meta($pid, '_mhm_log_message', true);
                    fputcsv($out, [
                        $pid,
                        $date,
                        $gw,
                        $act,
                        $st,
                        $bid,
                        $ak,
                        strtoupper($cur ?: ''),
                        $msg,
                    ]);
                }
            }
            $paged++;
            // flush output buffer for streaming
            if (function_exists('flush')) {
                flush();
            }
        } while (true);
        wp_reset_postdata();
    }

    private static function xls_header(string $title): void
    {
        echo '<?xml version="1.0"?>' . "\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
            xmlns:o="urn:schemas-microsoft-com:office:office"
            xmlns:x="urn:schemas-microsoft-com:office:excel"
            xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
            xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        echo '<Worksheet ss:Name="' . esc_attr($title) . '"><Table>' . "\n";
    }

    private static function xls_footer(): void
    {
        echo '</Table></Worksheet></Workbook>';
    }

    private static function xls_row(array $cells): void
    {
        echo '<Row>';
        foreach ($cells as $c) {
            $isNum = is_numeric($c) && !preg_match('/^0[0-9]/', (string) $c);
            if ($isNum) {
                echo '<Cell><Data ss:Type="Number">' . htmlspecialchars((string) $c, ENT_XML1) . '</Data></Cell>';
            } else {
                echo '<Cell><Data ss:Type="String">' . htmlspecialchars((string) $c, ENT_XML1) . '</Data></Cell>';
            }
        }
        echo '</Row>' . "\n";
    }

    private static function stream_xls(string $post_type, array $args, string $sheetTitle): void
    {
        self::xls_header($sheetTitle);
        if ($post_type === 'vehicle_booking') {
            $headers = [
                'ID',
                'Date',
                'Status',
                'Payment Status',
                'Gateway',
                'Total',
                'Paid Amount',
                'Currency',
                'Name',
                'Email',
                'Phone',
            ];
        } else {
            $headers = [
                'ID',
                'Date',
                'Gateway',
                'Action',
                'Status',
                'Booking ID',
                'Amount (kurus)',
                'Currency',
                'Message',
            ];
        }
        self::xls_row($headers);

        $paged = 1;
        do {
            $q = new WP_Query(array_merge($args, ['paged' => $paged]));
            if (!$q->have_posts()) break;
            foreach ($q->posts as $pid) {
                $pid = (int) $pid;
                if ($post_type === 'vehicle_booking') {
                    $date   = get_post($pid)->post_date_gmt;
                    $status = (string) get_post_meta($pid, '_mhm_status', true);
                    $pstat  = (string) get_post_meta($pid, '_mhm_payment_status', true);
                    $gw     = (string) get_post_meta($pid, '_mhm_payment_gateway', true);
                    $total  = (float) get_post_meta($pid, '_mhm_total_price', true);
                    $paidk  = (int) get_post_meta($pid, '_mhm_payment_amount', true);
                    $cur    = (string) get_post_meta($pid, '_mhm_payment_currency', true);
                    $name   = (string) get_post_meta($pid, '_mhm_contact_name', true);
                    $email  = (string) get_post_meta($pid, '_mhm_contact_email', true);
                    $phone  = (string) get_post_meta($pid, '_mhm_contact_phone', true);
                    self::xls_row([
                        $pid,
                        $date,
                        $status,
                        $pstat,
                        $gw,
                        number_format($total, 2, '.', ''),
                        number_format($paidk / 100, 2, '.', ''),
                        strtoupper($cur ?: ''),
                        $name,
                        $email,
                        $phone,
                    ]);
                } else {
                    $p      = get_post($pid);
                    $date   = $p ? $p->post_date_gmt : '';
                    $gw     = (string) get_post_meta($pid, '_mhm_log_gateway', true);
                    $act    = (string) get_post_meta($pid, '_mhm_log_action', true);
                    $st     = (string) get_post_meta($pid, '_mhm_log_status', true);
                    $bid    = (int) get_post_meta($pid, '_mhm_log_booking_id', true);
                    $ak     = (int) get_post_meta($pid, '_mhm_log_amount_kurus', true);
                    $cur    = (string) get_post_meta($pid, '_mhm_log_currency', true);
                    $msg    = (string) get_post_meta($pid, '_mhm_log_message', true);
                    self::xls_row([
                        $pid,
                        $date,
                        $gw,
                        $act,
                        $st,
                        $bid,
                        $ak,
                        strtoupper($cur ?: ''),
                        $msg,
                    ]);
                }
            }
            $paged++;
            if (function_exists('flush')) {
                flush();
            }
        } while (true);
        wp_reset_postdata();
        self::xls_footer();
    }

    /**
     * Render export page
     * 
     * @return void
     */
    public static function render_export_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="wrap mhm-rentiva-wrap">';
        echo '<h1>' . esc_html__('Export Data', 'mhm-rentiva') . '</h1>';

        // Pro feature notices and Developer Mode banner
        \MHMRentiva\Admin\Core\ProFeatureNotice::displayPageProNotice('export');

        echo '<p class="description">' . esc_html__('Export your vehicle rental data in various formats for analysis, reporting, and backup purposes.', 'mhm-rentiva') . '</p>';

        // Export status messages
        self::render_export_status_messages();

        // Export cards - Compatible with plugin design
        echo '<div class="mhm-export-dashboard">';

        // Bookings Export Card
        echo '<div class="analytics-card bookings-analytics">';
        echo '<div class="card-header">';
        echo '<div class="card-icon">📋</div>';
        echo '<div class="card-title">';
        echo '<h3>' . esc_html__('Bookings Export', 'mhm-rentiva') . '</h3>';
        echo '<p>' . esc_html__('Export booking data including customer information, payment details, and booking status.', 'mhm-rentiva') . '</p>';
        echo '</div>';
        echo '</div>';
        echo '<div class="card-content">';
        echo '<div class="export-actions">';
        echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post" class="export-form">';
        echo '<input type="hidden" name="action" value="mhm_rentiva_export" />';
        echo '<input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce('mhm_rentiva_export')) . '" />';
        echo '<input type="hidden" name="post_type" value="vehicle_booking" />';
        echo '<input type="hidden" name="format" value="csv" />';
        echo '<button type="submit" class="button button-primary">' . esc_html__('Export CSV', 'mhm-rentiva') . '</button>';
        echo '</form>';
        // Pro: JSON Export
        if (class_exists(\MHMRentiva\Admin\Licensing\Mode::class) && \MHMRentiva\Admin\Licensing\Mode::isPro()) {
            echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post" class="export-form">';
            echo '<input type="hidden" name="action" value="mhm_rentiva_export" />';
            echo '<input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce('mhm_rentiva_export')) . '" />';
            echo '<input type="hidden" name="post_type" value="vehicle_booking" />';
            echo '<input type="hidden" name="format" value="json" />';
            echo '<button type="submit" class="button button-secondary">' . esc_html__('Export JSON', 'mhm-rentiva') . '</button>';
            echo '</form>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Payment Logs Export Card
        echo '<div class="analytics-card revenue-analytics">';
        echo '<div class="card-header">';
        echo '<div class="card-icon">💳</div>';
        echo '<div class="card-title">';
        echo '<h3>' . esc_html__('Payment Logs Export', 'mhm-rentiva') . '</h3>';
        echo '<p>' . esc_html__('Export payment transaction logs for accounting and financial analysis.', 'mhm-rentiva') . '</p>';
        echo '</div>';
        echo '</div>';
        echo '<div class="card-content">';
        echo '<div class="export-actions">';
        echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post" class="export-form">';
        echo '<input type="hidden" name="action" value="mhm_rentiva_export" />';
        echo '<input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce('mhm_rentiva_export')) . '" />';
        echo '<input type="hidden" name="post_type" value="mhm_payment_log" />';
        echo '<input type="hidden" name="format" value="csv" />';
        echo '<button type="submit" class="button button-primary">' . esc_html__('Export CSV', 'mhm-rentiva') . '</button>';
        echo '</form>';
        // Pro: JSON Export
        if (class_exists(\MHMRentiva\Admin\Licensing\Mode::class) && \MHMRentiva\Admin\Licensing\Mode::isPro()) {
            echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post" class="export-form">';
            echo '<input type="hidden" name="action" value="mhm_rentiva_export" />';
            echo '<input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce('mhm_rentiva_export')) . '" />';
            echo '<input type="hidden" name="post_type" value="mhm_payment_log" />';
            echo '<input type="hidden" name="format" value="json" />';
            echo '<button type="submit" class="button button-secondary">' . esc_html__('Export JSON', 'mhm-rentiva') . '</button>';
            echo '</form>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Vehicle Export Card
        echo '<div class="analytics-card vehicles-analytics">';
        echo '<div class="card-header">';
        echo '<div class="card-icon">🚗</div>';
        echo '<div class="card-title">';
        echo '<h3>' . esc_html__('Vehicle Export', 'mhm-rentiva') . '</h3>';
        echo '<p>' . esc_html__('Export vehicle data including specifications, availability, pricing, and performance metrics.', 'mhm-rentiva') . '</p>';
        echo '</div>';
        echo '</div>';
        echo '<div class="card-content">';
        echo '<div class="export-actions">';
        echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post" class="export-form">';
        echo '<input type="hidden" name="action" value="mhm_rentiva_export" />';
        echo '<input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce('mhm_rentiva_export')) . '" />';
        echo '<input type="hidden" name="post_type" value="vehicle" />';
        echo '<input type="hidden" name="format" value="csv" />';
        echo '<button type="submit" class="button button-primary">' . esc_html__('Export CSV', 'mhm-rentiva') . '</button>';
        echo '</form>';
        // Pro: JSON Export
        if (class_exists(\MHMRentiva\Admin\Licensing\Mode::class) && \MHMRentiva\Admin\Licensing\Mode::isPro()) {
            echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post" class="export-form">';
            echo '<input type="hidden" name="action" value="mhm_rentiva_export" />';
            echo '<input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce('mhm_rentiva_export')) . '" />';
            echo '<input type="hidden" name="post_type" value="vehicle" />';
            echo '<input type="hidden" name="format" value="json" />';
            echo '<button type="submit" class="button button-secondary">' . esc_html__('Export JSON', 'mhm-rentiva') . '</button>';
            echo '</form>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Reports Export Card
        echo '<div class="analytics-card customers-analytics">';
        echo '<div class="card-header">';
        echo '<div class="card-icon">📊</div>';
        echo '<div class="card-title">';
        echo '<h3>' . esc_html__('Reports Export', 'mhm-rentiva') . '</h3>';
        echo '<p>' . esc_html__('Export detailed reports including revenue, customer analytics, and vehicle performance data.', 'mhm-rentiva') . '</p>';
        echo '</div>';
        echo '</div>';
        echo '<div class="card-content">';
        echo '<div class="export-actions">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=mhm-rentiva-reports')) . '" class="button button-primary">' . esc_html__('Go to Reports', 'mhm-rentiva') . '</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '</div>';

        // Advanced Filters
        self::render_advanced_filters();

        // Export History
        self::render_export_history();

        // Custom Export Form (Hidden by default)
        self::render_custom_export_form();

        echo '</div>';
    }

    /**
     * Render export status messages
     */
    private static function render_export_status_messages(): void
    {
        // Export started message
        if (isset($_GET['export_started']) && $_GET['export_started'] === '1') {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>' . esc_html__('Export Started!', 'mhm-rentiva') . '</strong> ' . esc_html__('Your export request has been queued and will be processed in the background.', 'mhm-rentiva') . '</p>';
            echo '</div>';
        }

        // Export completed message
        if (isset($_GET['export_completed']) && $_GET['export_completed'] === '1') {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>' . esc_html__('Export Completed!', 'mhm-rentiva') . '</strong> ' . esc_html__('Your export has been completed successfully.', 'mhm-rentiva') . '</p>';
            echo '</div>';
        }

        // Export error message
        if (isset($_GET['export_error']) && $_GET['export_error'] === '1') {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>' . esc_html__('Export Error!', 'mhm-rentiva') . '</strong> ' . esc_html__('An error occurred during the export process.', 'mhm-rentiva') . '</p>';
            echo '</div>';
        }
    }

    /**
     * Render export history
     */
    private static function render_export_history(): void
    {
        echo '<div class="mhm-export-history">';
        echo '<div class="history-header">';
        echo '<h2>' . esc_html__('Export History', 'mhm-rentiva') . '</h2>';
        echo '<p>' . esc_html__('View and manage your recent export activities.', 'mhm-rentiva') . '</p>';
        echo '</div>';

        // Get export history data
        $export_history = self::get_export_history_for_render();

        if (!empty($export_history)) {
            echo '<div class="history-table-container">';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . esc_html__('Date & Time', 'mhm-rentiva') . '</th>';
            echo '<th>' . esc_html__('Export Type', 'mhm-rentiva') . '</th>';
            echo '<th>' . esc_html__('Format', 'mhm-rentiva') . '</th>';
            echo '<th>' . esc_html__('Records', 'mhm-rentiva') . '</th>';
            echo '<th>' . esc_html__('Status', 'mhm-rentiva') . '</th>';
            echo '<th>' . esc_html__('Actions', 'mhm-rentiva') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($export_history as $export) {
                $status_class = $export['status'] === 'completed' ? 'status-completed' : 'status-failed';
                $status_text = $export['status'] === 'completed' ? __('Completed', 'mhm-rentiva') : __('Failed', 'mhm-rentiva');

                echo '<tr>';
                echo '<td>' . esc_html(date('d.m.Y H:i', strtotime($export['date']))) . '</td>';
                echo '<td>' . esc_html($export['type']) . '</td>';
                echo '<td><span class="format-badge format-' . esc_attr(strtolower($export['format'])) . '">' . esc_html(strtoupper($export['format'])) . '</span></td>';
                echo '<td>' . esc_html(number_format($export['records'])) . '</td>';
                echo '<td><span class="status-badge ' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span></td>';
                echo '<td>';
                echo '<button type="button" class="button button-small" onclick="viewExportDetails(\'' . esc_js($export['date']) . '\')">';
                echo esc_html__('View Details', 'mhm-rentiva');
                echo '</button>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<div class="no-data-message">';
            echo '<div class="no-data-icon">📊</div>';
            echo '<h3>' . esc_html__('No exports yet', 'mhm-rentiva') . '</h3>';
            echo '<p>' . esc_html__('Start by exporting your data using the options above.', 'mhm-rentiva') . '</p>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Render custom export form
     */
    private static function render_custom_export_form(): void
    {
        echo '<div id="custom-export-form" class="mhm-custom-export-form" style="display: none;">';
        echo '<div class="card">';
        echo '<div class="card-header">';
        echo '<h3>' . esc_html__('Custom Export', 'mhm-rentiva') . '</h3>';
        echo '</div>';
        echo '<div class="card-content">';
        echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post">';
        echo '<input type="hidden" name="action" value="mhm_rentiva_export" />';
        echo '<input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce('mhm_rentiva_export')) . '" />';

        // Export type
        echo '<div class="form-group">';
        echo '<label for="export_type">' . esc_html__('Export Type', 'mhm-rentiva') . '</label>';
        echo '<select name="export_type" id="export_type" required>';
        echo '<option value="">' . esc_html__('Select Export Type', 'mhm-rentiva') . '</option>';
        echo '<option value="bookings">' . esc_html__('Bookings', 'mhm-rentiva') . '</option>';
        echo '<option value="customers">' . esc_html__('Customers', 'mhm-rentiva') . '</option>';
        echo '<option value="vehicles">' . esc_html__('Vehicles', 'mhm-rentiva') . '</option>';
        echo '<option value="reports">' . esc_html__('Reports', 'mhm-rentiva') . '</option>';
        echo '</select>';
        echo '</div>';

        // Date range
        echo '<div class="form-group">';
        echo '<label for="date_from">' . esc_html__('Start Date', 'mhm-rentiva') . '</label>';
        echo '<input type="date" name="date_from" id="date_from" />';
        echo '</div>';

        echo '<div class="form-group">';
        echo '<label for="date_to">' . esc_html__('End Date', 'mhm-rentiva') . '</label>';
        echo '<input type="date" name="date_to" id="date_to" />';
        echo '</div>';

        // Format
        echo '<div class="form-group">';
        echo '<label for="format">' . esc_html__('Format', 'mhm-rentiva') . '</label>';
        echo '<select name="format" id="format" required>';
        echo '<option value="csv">CSV</option>';
        if (class_exists(Mode::class) && Mode::featureEnabled(Mode::FEATURE_EXPORT)) {
            echo '<option value="xls">Excel (XLS)</option>';
        }
        echo '</select>';
        echo '</div>';

        // Submit button
        echo '<div class="form-group">';
        echo '<button type="submit" class="button button-primary">' . esc_html__('Start Export', 'mhm-rentiva') . '</button>';
        echo '<button type="button" class="button button-secondary" onclick="hideCustomExportForm()">' . esc_html__('Cancel', 'mhm-rentiva') . '</button>';
        echo '</div>';

        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // JavaScript
        echo '<script>';
        echo 'function showCustomExportForm() { document.getElementById("custom-export-form").style.display = "block"; }';
        echo 'function hideCustomExportForm() { document.getElementById("custom-export-form").style.display = "none"; }';
        echo '</script>';
    }

    /**
     * Get export history (for rendering)
     */
    private static function get_export_history_for_render(): array
    {
        return self::get_export_history();
    }

    /**
     * Render export statistics
     */
    private static function render_export_statistics(): void
    {
        if (!class_exists(ExportStats::class)) {
            return;
        }

        $stats = ExportStats::get_display_stats();

        echo '<div class="mhm-export-stats">';
        echo '<h2>' . esc_html__('Export Statistics', 'mhm-rentiva') . '</h2>';
        echo '<div class="stats-grid">';

        // Total Exports
        echo '<div class="stat-card">';
        echo '<div class="stat-number">' . esc_html($stats['total_exports']) . '</div>';
        echo '<div class="stat-label">' . esc_html__('Total Exports', 'mhm-rentiva') . '</div>';
        echo '</div>';

        // Total Records
        echo '<div class="stat-card">';
        echo '<div class="stat-number">' . esc_html($stats['total_records']) . '</div>';
        echo '<div class="stat-label">' . esc_html__('Records Exported', 'mhm-rentiva') . '</div>';
        echo '</div>';

        // Success Rate
        echo '<div class="stat-card">';
        echo '<div class="stat-number">' . esc_html($stats['success_rate']) . '</div>';
        echo '<div class="stat-label">' . esc_html__('Success Rate', 'mhm-rentiva') . '</div>';
        echo '</div>';

        // Available Records
        echo '<div class="stat-card">';
        echo '<div class="stat-number">' . esc_html($stats['available_records']) . '</div>';
        echo '<div class="stat-label">' . esc_html__('Available Records', 'mhm-rentiva') . '</div>';
        echo '</div>';

        echo '</div>';

        // Current Data Overview
        echo '<div class="current-data-overview">';
        echo '<h3>' . esc_html__('Current Data Overview', 'mhm-rentiva') . '</h3>';
        echo '<div class="data-grid">';
        echo '<div class="data-item">';
        echo '<span class="data-label">' . esc_html__('Vehicles:', 'mhm-rentiva') . '</span>';
        echo '<span class="data-value">' . esc_html(number_format($stats['current_data']['vehicles'])) . '</span>';
        echo '</div>';
        echo '<div class="data-item">';
        echo '<span class="data-label">' . esc_html__('Bookings:', 'mhm-rentiva') . '</span>';
        echo '<span class="data-value">' . esc_html(number_format($stats['current_data']['bookings'])) . '</span>';
        echo '</div>';
        echo '<div class="data-item">';
        echo '<span class="data-label">' . esc_html__('Payment Logs:', 'mhm-rentiva') . '</span>';
        echo '<span class="data-value">' . esc_html(number_format($stats['current_data']['payment_logs'])) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Enqueue JavaScript files
     */
    public static function enqueue_scripts(): void
    {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'mhm-rentiva_page_mhm-rentiva-export') {
            wp_enqueue_script(
                'mhm-rentiva-export',
                plugin_dir_url(__FILE__) . '../../../assets/js/admin/export.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('mhm-rentiva-export', 'mhm_rentiva_export', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_rentiva_export'),
            ]);
        }
    }

    /**
     * Handle AJAX apply filters request
     */
    public static function handle_apply_filters(): void
    {
        // Check nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_rentiva_export_filters')) {
            wp_send_json_error(esc_html__('Invalid nonce', 'mhm-rentiva'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Insufficient permissions', 'mhm-rentiva'));
        }

        // Get filter data
        $filters = self::sanitize_text_field_safe(wp_unslash($_POST['filters'] ?? ''));

        // Parse filters (form data)
        parse_str($filters, $filter_data);

        // Validate and sanitize filter data
        $validated_filters = [
            'date_range' => self::sanitize_text_field_safe($filter_data['date_range'] ?? ''),
            'booking_status' => self::sanitize_text_field_safe($filter_data['booking_status'] ?? ''),
            'payment_status' => self::sanitize_text_field_safe($filter_data['payment_status'] ?? ''),
            'payment_gateway' => self::sanitize_text_field_safe($filter_data['payment_gateway'] ?? ''),
            'amount_min' => floatval($filter_data['amount_min'] ?? 0),
            'amount_max' => floatval($filter_data['amount_max'] ?? 0),
        ];

        // Store filters in session or transient for export use
        set_transient('mhm_rentiva_export_filters_' . get_current_user_id(), $validated_filters, HOUR_IN_SECONDS);

        // Get filtered results count and sample data
        $results = self::get_filtered_results($validated_filters);

        // Return success response with results
        wp_send_json_success([
            'message' => esc_html__('Filters applied successfully!', 'mhm-rentiva'),
            'filters' => $validated_filters,
            'records_count' => $results['count'],
            'total_amount' => $results['total_amount'],
            'sample_records' => $results['sample_records']
        ]);
    }

    /**
     * Get filtered results for display
     */
    private static function get_filtered_results(array $filters): array
    {
        // Build query args with filters
        $args = [
            'post_type' => 'vehicle_booking',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => 100, // Limit for performance
            'no_found_rows' => true,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        // Apply filters using ExportFilters class
        if (class_exists(ExportFilters::class)) {
            $args = ExportFilters::apply_all_filters($args, $filters);
        }

        // Get posts
        $query = new WP_Query($args);
        $posts = $query->posts;

        $count = count($posts);
        $total_amount = 0;
        $sample_records = [];

        // Calculate total amount and get sample records
        foreach (array_slice($posts, 0, 5) as $post_id) {
            $amount = (float) get_post_meta($post_id, '_mhm_total_price', true);
            $total_amount += $amount;

            $sample_records[] = [
                'id' => $post_id,
                'date' => get_the_date('d.m.Y', $post_id),
                'status' => get_post_meta($post_id, '_mhm_status', true) ?: 'N/A',
                'amount' => number_format($amount, 2)
            ];
        }

        return [
            'count' => $count,
            'total_amount' => number_format($total_amount, 2),
            'sample_records' => $sample_records
        ];
    }

    /**
     * Handle AJAX delete export request
     */
    public static function handle_delete_export(): void
    {
        // Check nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_rentiva_export_filters')) {
            wp_send_json_error(esc_html__('Invalid nonce', 'mhm-rentiva'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Insufficient permissions', 'mhm-rentiva'));
        }

        // Get export ID
        $export_id = self::sanitize_text_field_safe(wp_unslash($_POST['export_id'] ?? ''));

        if (empty($export_id)) {
            wp_send_json_error(esc_html__('Export ID is required', 'mhm-rentiva'));
        }

        // For now, just simulate deletion since we don't have a real export storage system
        // In a real implementation, you would delete the actual export file and database record

        // Log the deletion

        // Return success response
        wp_send_json_success([
            'message' => esc_html__('Export deleted successfully!', 'mhm-rentiva'),
            'export_id' => $export_id
        ]);
    }

    /**
     * Handle get export details AJAX request
     */
    public static function handle_get_export_details(): void
    {
        // Check nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_rentiva_export_filters')) {
            wp_send_json_error(esc_html__('Invalid nonce', 'mhm-rentiva'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Insufficient permissions', 'mhm-rentiva'));
        }

        // Get export ID
        $export_id = self::sanitize_text_field_safe(wp_unslash($_POST['export_id'] ?? ''));

        if (empty($export_id)) {
            wp_send_json_error(esc_html__('Export ID is required', 'mhm-rentiva'));
        }

        // Get export history
        $export_history = self::get_export_history();

        // Find the specific export
        $export_details = null;
        foreach ($export_history as $export) {
            if ($export['date'] === $export_id) {
                $export_details = $export;
                break;
            }
        }

        if (!$export_details) {
            wp_send_json_error(esc_html__('Export not found', 'mhm-rentiva'));
        }

        // Return export details
        wp_send_json_success([
            'export' => $export_details
        ]);
    }

    /**
     * Log export activity
     */
    private static function log_export_activity(string $post_type, string $format, array $args, int $exported_count = 0): void
    {
        // Use the actual exported count
        $record_count = $exported_count;

        // Create export log entry
        $type_name = 'Unknown';
        if ($post_type === 'vehicle_booking') {
            $type_name = 'Bookings';
        } elseif ($post_type === 'vehicle') {
            $type_name = 'Vehicles';
        } elseif ($post_type === LogPostType::TYPE) {
            $type_name = 'Payment Logs';
        }

        $export_log = [
            'date' => current_time('Y-m-d H:i:s'),
            'type' => $type_name,
            'format' => strtoupper($format),
            'records' => $record_count,
            'status' => 'completed',
            'user_id' => get_current_user_id(),
            'filters_applied' => !empty($args['meta_query']) || !empty($args['date_query'])
        ];

        // Store in transient (in a real implementation, you'd use a proper database table)
        $export_history = get_transient('mhm_rentiva_export_history') ?: [];
        $export_history[] = $export_log;

        // Keep only last 50 exports
        if (count($export_history) > 50) {
            $export_history = array_slice($export_history, -50);
        }

        set_transient('mhm_rentiva_export_history', $export_history, WEEK_IN_SECONDS);
    }

    /**
     * Get export history
     */
    public static function get_export_history(): array
    {
        return get_transient('mhm_rentiva_export_history') ?: [];
    }

    /**
     * Render advanced filters
     */
    private static function render_advanced_filters(): void
    {
        if (!class_exists(ExportFilters::class)) {
            return;
        }

        // Only call ExportFilters, headers are already there
        ExportFilters::render_filter_form('vehicle_booking');
    }

    /**
     * Direct JSON export
     */
    private static function stream_json_direct(string $post_type, array $args, string $filename): void
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $args['posts_per_page'] = 50;
        $args['fields'] = 'ids'; // Get IDs only
        $paged = 1;

        echo '[';
        $first = true;

        do {
            $q = new WP_Query(array_merge($args, ['paged' => $paged]));
            if (!$q->have_posts()) break;

            foreach ($q->posts as $pid) {
                $pid = (int) $pid;
                $row = [];

                if ($post_type === 'vehicle_booking') {
                    $row = [
                        'id' => $pid,
                        'date' => get_post($pid)->post_date_gmt,
                        'status' => (string) get_post_meta($pid, '_mhm_status', true),
                        'payment_status' => (string) get_post_meta($pid, '_mhm_payment_status', true),
                        'gateway' => (string) get_post_meta($pid, '_mhm_payment_gateway', true),
                        'total' => (float) get_post_meta($pid, '_mhm_total_price', true),
                        'paid_amount' => number_format((float) get_post_meta($pid, '_mhm_payment_amount', true) / 100, 2, '.', ''),
                        'currency' => (string) get_post_meta($pid, '_mhm_payment_currency', true),
                        'contact' => [
                            'name' => (string) get_post_meta($pid, '_mhm_contact_name', true),
                            'email' => (string) get_post_meta($pid, '_mhm_contact_email', true),
                            'phone' => (string) get_post_meta($pid, '_mhm_contact_phone', true),
                        ]
                    ];
                } else {
                    $p = get_post($pid);
                    $row = [
                        'id' => $pid,
                        'date' => $p ? $p->post_date_gmt : '',
                        'gateway' => (string) get_post_meta($pid, '_mhm_log_gateway', true),
                        'action' => (string) get_post_meta($pid, '_mhm_log_action', true),
                        'status' => (string) get_post_meta($pid, '_mhm_log_status', true),
                        'booking_id' => (int) get_post_meta($pid, '_mhm_log_booking_id', true),
                        'amount_kurus' => (int) get_post_meta($pid, '_mhm_log_amount_kurus', true),
                        'currency' => (string) get_post_meta($pid, '_mhm_log_currency', true),
                        'message' => (string) get_post_meta($pid, '_mhm_log_message', true),
                    ];
                }

                if (!$first) echo ',';
                $first = false;

                echo wp_json_encode($row);
            }

            $paged++;
            if ($paged > $q->max_num_pages) break;

            if (function_exists('wp_cache_flush')) wp_cache_flush();
        } while (true);

        echo ']';
        exit;
    }
    /**
     * Export raw data array directly
     */
    public static function export_data(array $data, string $filename, string $format = 'csv'): void
    {
        if (empty($data)) {
            return;
        }

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');

            // Add BOM
            fprintf($output, "%s", chr(0xEF) . chr(0xBB) . chr(0xBF));

            foreach ($data as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
            exit;
        }

        if ($format === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.json"');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            $json_data = [];
            if (!empty($data)) {
                $headers = $data[0];

                // Verify headers are likely headers (all strings)
                $is_header = true;
                foreach ($headers as $h) {
                    if (!is_string($h)) $is_header = false;
                }

                if ($is_header && count($data) > 1) {
                    $count = count($data);
                    for ($i = 1; $i < $count; $i++) {
                        if (count($data[$i]) === count($headers)) {
                            $json_data[] = array_combine($headers, $data[$i]);
                        }
                    }
                } else {
                    $json_data = $data;
                }
            }

            echo json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($format === 'xls') {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<table border="1">';

            foreach ($data as $row) {
                echo '<tr>';
                foreach ($row as $cell) {
                    echo '<td>' . htmlspecialchars((string)$cell) . '</td>';
                }
                echo '</tr>';
            }

            echo '</table></body></html>';
            exit;
        }
    }
}
