<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Export;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Advanced Export Filtering
 */
final class ExportFilters
{
    /**
     * Get available date ranges
     */
    public static function get_date_ranges(): array
    {
        return [
            'today' => __('Today', 'mhm-rentiva'),
            'yesterday' => __('Yesterday', 'mhm-rentiva'),
            'last_7_days' => __('Last 7 days', 'mhm-rentiva'),
            'last_30_days' => __('Last 30 days', 'mhm-rentiva'),
            'this_month' => __('This month', 'mhm-rentiva'),
            'last_month' => __('Last month', 'mhm-rentiva'),
            'this_year' => __('This year', 'mhm-rentiva'),
            'last_year' => __('Last year', 'mhm-rentiva'),
            'custom' => __('Custom range', 'mhm-rentiva'),
        ];
    }

    /**
     * Get booking status options
     */
    public static function get_booking_statuses(): array
    {
        return [
            'pending' => __('Pending', 'mhm-rentiva'),
            'confirmed' => __('Confirmed', 'mhm-rentiva'),
            'completed' => __('Completed', 'mhm-rentiva'),
            'cancelled' => __('Cancelled', 'mhm-rentiva'),
            'refunded' => __('Refunded', 'mhm-rentiva'),
        ];
    }

    /**
     * Get payment status options
     */
    public static function get_payment_statuses(): array
    {
        return [
            'pending' => __('Pending', 'mhm-rentiva'),
            'paid' => __('Paid', 'mhm-rentiva'),
            'failed' => __('Failed', 'mhm-rentiva'),
            'refunded' => __('Refunded', 'mhm-rentiva'),
        ];
    }

    /**
     * Get payment gateway options
     */
    public static function get_payment_gateways(): array
    {
        return [
            'offline' => __('Offline Payment', 'mhm-rentiva'),
            'paypal' => __('PayPal', 'mhm-rentiva'),
            'paytr' => __('PayTR', 'mhm-rentiva'),
            'stripe' => __('Stripe', 'mhm-rentiva'),
        ];
    }

    /**
     * Get vehicle categories
     */
    public static function get_vehicle_categories(): array
    {
        $categories = get_terms([
            'taxonomy' => 'vehicle_category',
            'hide_empty' => false,
        ]);

        $result = [];
        if (!is_wp_error($categories)) {
            foreach ($categories as $category) {
                $result[$category->slug] = $category->name;
            }
        }

        return $result;
    }

    /**
     * Apply date filter to query args
     */
    public static function apply_date_filter(array $args, string $date_range, ?string $start_date = null, ?string $end_date = null): array
    {
        $date_query = [];

        switch ($date_range) {
            case 'today':
                $date_query = [
                    'after' => 'today',
                    'inclusive' => true,
                ];
                break;

            case 'yesterday':
                $date_query = [
                    'after' => 'yesterday',
                    'before' => 'today',
                    'inclusive' => true,
                ];
                break;

            case 'last_7_days':
                $date_query = [
                    'after' => '7 days ago',
                    'inclusive' => true,
                ];
                break;

            case 'last_30_days':
                $date_query = [
                    'after' => '30 days ago',
                    'inclusive' => true,
                ];
                break;

            case 'this_month':
                $date_query = [
                    'after' => 'first day of this month',
                    'before' => 'last day of this month',
                    'inclusive' => true,
                ];
                break;

            case 'last_month':
                $date_query = [
                    'after' => 'first day of last month',
                    'before' => 'last day of last month',
                    'inclusive' => true,
                ];
                break;

            case 'this_year':
                $date_query = [
                    'after' => 'first day of January',
                    'before' => 'last day of December',
                    'inclusive' => true,
                ];
                break;

            case 'last_year':
                $date_query = [
                    'after' => 'first day of January last year',
                    'before' => 'last day of December last year',
                    'inclusive' => true,
                ];
                break;

            case 'custom':
                if ($start_date && $end_date) {
                    $date_query = [
                        'after' => $start_date,
                        'before' => $end_date,
                        'inclusive' => true,
                    ];
                }
                break;
        }

        if (!empty($date_query)) {
            $args['date_query'] = [$date_query];
        }

        return $args;
    }

    /**
     * Apply booking status filter
     */
    public static function apply_booking_status_filter(array $args, string $status): array
    {
        if (!empty($status)) {
            $meta_query = $args['meta_query'] ?? [];
            $meta_query[] = [
                'key' => '_mhm_status',
                'value' => $status,
                'compare' => '=',
            ];
            $args['meta_query'] = $meta_query;
        }

        return $args;
    }

    /**
     * Apply payment status filter
     */
    public static function apply_payment_status_filter(array $args, string $status): array
    {
        if (!empty($status)) {
            $meta_query = $args['meta_query'] ?? [];
            $meta_query[] = [
                'key' => '_mhm_payment_status',
                'value' => $status,
                'compare' => '=',
            ];
            $args['meta_query'] = $meta_query;
        }

        return $args;
    }

    /**
     * Apply payment gateway filter
     */
    public static function apply_payment_gateway_filter(array $args, string $gateway): array
    {
        if (!empty($gateway)) {
            $meta_query = $args['meta_query'] ?? [];
            $meta_query[] = [
                'key' => '_mhm_payment_gateway',
                'value' => $gateway,
                'compare' => '=',
            ];
            $args['meta_query'] = $meta_query;
        }

        return $args;
    }

    /**
     * Apply vehicle category filter
     */
    public static function apply_vehicle_category_filter(array $args, string $category): array
    {
        if (!empty($category)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'vehicle_category',
                    'field' => 'slug',
                    'terms' => $category,
                    'operator' => 'IN',
                ],
            ];
        }

        return $args;
    }

    /**
     * Apply amount range filter
     */
    public static function apply_amount_filter(array $args, ?float $min_amount = null, ?float $max_amount = null): array
    {
        $meta_query = $args['meta_query'] ?? [];

        if ($min_amount !== null || $max_amount !== null) {
            $amount_query = [
                'key' => '_mhm_total_price',
                'type' => 'NUMERIC',
            ];

            if ($min_amount !== null && $max_amount !== null) {
                $amount_query['value'] = [$min_amount, $max_amount];
                $amount_query['compare'] = 'BETWEEN';
            } elseif ($min_amount !== null) {
                $amount_query['value'] = $min_amount;
                $amount_query['compare'] = '>=';
            } elseif ($max_amount !== null) {
                $amount_query['value'] = $max_amount;
                $amount_query['compare'] = '<=';
            }

            $meta_query[] = $amount_query;
            $args['meta_query'] = $meta_query;
        }

        return $args;
    }

    /**
     * Apply all filters to query args
     */
    public static function apply_all_filters(array $args, array $filters): array
    {
        // Date filter
        if (isset($filters['date_range'])) {
            $args = self::apply_date_filter(
                $args,
                $filters['date_range'],
                $filters['start_date'] ?? null,
                $filters['end_date'] ?? null
            );
        }

        // Booking status filter
        if (isset($filters['booking_status']) && !empty($filters['booking_status'])) {
            $args = self::apply_booking_status_filter($args, $filters['booking_status']);
        }

        // Payment status filter
        if (isset($filters['payment_status']) && !empty($filters['payment_status'])) {
            $args = self::apply_payment_status_filter($args, $filters['payment_status']);
        }

        // Payment gateway filter
        if (isset($filters['payment_gateway']) && !empty($filters['payment_gateway'])) {
            $args = self::apply_payment_gateway_filter($args, $filters['payment_gateway']);
        }

        // Vehicle category filter
        if (isset($filters['vehicle_category']) && !empty($filters['vehicle_category'])) {
            $args = self::apply_vehicle_category_filter($args, $filters['vehicle_category']);
        }

        // Amount filter
        if (isset($filters['min_amount']) || isset($filters['max_amount'])) {
            $args = self::apply_amount_filter(
                $args,
                $filters['min_amount'] ?? null,
                $filters['max_amount'] ?? null
            );
        }

        return $args;
    }

    /**
     * Render filter form
     */
    public static function render_filter_form(string $post_type): void
    {
        $date_ranges = self::get_date_ranges();
        $booking_statuses = self::get_booking_statuses();
        $payment_statuses = self::get_payment_statuses();
        $payment_gateways = self::get_payment_gateways();
        $vehicle_categories = self::get_vehicle_categories();

        echo '<div class="mhm-advanced-filters">';
        
        // Header
        echo '<div class="filters-header">';
        echo '<h2>' . esc_html__('Advanced Export Filters', 'mhm-rentiva') . '</h2>';
        echo '<p>' . esc_html__('Configure filters before exporting to get exactly the data you need.', 'mhm-rentiva') . '</p>';
        echo '</div>';
        
        echo '<form id="export-filters-form" method="post">';
        wp_nonce_field('mhm_rentiva_export_filters', 'mhm_rentiva_export_filters_nonce');

        // Bookings Export Filters Section
        if ($post_type === 'vehicle_booking') {
            echo '<div class="filter-section">';
            echo '<div class="filter-section-header">';
            echo '<h3>' . esc_html__('Bookings Export Filters', 'mhm-rentiva') . '</h3>';
            echo '<p>' . esc_html__('Filter bookings by date, status, payment method, and amount range.', 'mhm-rentiva') . '</p>';
            echo '</div>';
            
            echo '<div class="filter-grid">';
            
            // Date Range Filter
            echo '<div class="filter-group">';
            echo '<div class="filter-header">';
            echo '<label for="date_range">' . esc_html__('Date Range', 'mhm-rentiva') . '</label>';
            echo '</div>';
            echo '<div class="filter-body">';
            echo '<select name="date_range" id="date_range">';
            foreach ($date_ranges as $value => $label) {
                echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '</div>';
            echo '</div>';

            // Booking Status Filter - Dropdown
            echo '<div class="filter-group">';
            echo '<div class="filter-header">';
            echo '<label for="booking_status">' . esc_html__('Booking Status', 'mhm-rentiva') . '</label>';
            echo '</div>';
            echo '<div class="filter-body">';
            echo '<select name="booking_status" id="booking_status">';
            echo '<option value="">' . esc_html__('All Statuses', 'mhm-rentiva') . '</option>';
            foreach ($booking_statuses as $value => $label) {
                echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '</div>';
            echo '</div>';

            // Payment Status Filter - Dropdown
            echo '<div class="filter-group">';
            echo '<div class="filter-header">';
            echo '<label for="payment_status">' . esc_html__('Payment Status', 'mhm-rentiva') . '</label>';
            echo '</div>';
            echo '<div class="filter-body">';
            echo '<select name="payment_status" id="payment_status">';
            echo '<option value="">' . esc_html__('All Payment Statuses', 'mhm-rentiva') . '</option>';
            foreach ($payment_statuses as $value => $label) {
                echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '</div>';
            echo '</div>';

            // Payment Gateway Filter - Dropdown
            echo '<div class="filter-group">';
            echo '<div class="filter-header">';
            echo '<label for="payment_gateway">' . esc_html__('Payment Gateway', 'mhm-rentiva') . '</label>';
            echo '</div>';
            echo '<div class="filter-body">';
            echo '<select name="payment_gateway" id="payment_gateway">';
            echo '<option value="">' . esc_html__('All Gateways', 'mhm-rentiva') . '</option>';
            foreach ($payment_gateways as $value => $label) {
                echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '</div>';
            echo '</div>';

            // Amount Range Filter
            echo '<div class="filter-group">';
            echo '<div class="filter-header">';
            echo '<label>' . esc_html__('Amount Range', 'mhm-rentiva') . '</label>';
            echo '</div>';
            echo '<div class="filter-body">';
            echo '<div class="amount-range">';
            echo '<input type="number" name="min_amount" placeholder="' . esc_attr__('Min amount', 'mhm-rentiva') . '" step="0.01">';
            echo '<input type="number" name="max_amount" placeholder="' . esc_attr__('Max amount', 'mhm-rentiva') . '" step="0.01">';
            echo '</div>';
            echo '</div>';
            echo '</div>';

            echo '</div>'; // filter-grid
            echo '</div>'; // filter-section
        }

        // Vehicle Category Filter (for vehicles)
        if ($post_type === 'vehicle' && !empty($vehicle_categories)) {
            echo '<div class="filter-section">';
            echo '<div class="filter-section-header">';
            echo '<h3>' . esc_html__('Vehicle Filters', 'mhm-rentiva') . '</h3>';
            echo '<p>' . esc_html__('Filter vehicles by category and other criteria.', 'mhm-rentiva') . '</p>';
            echo '</div>';
            
            echo '<div class="filter-grid">';
            
            echo '<div class="filter-group">';
            echo '<div class="filter-header">';
            echo '<label for="vehicle_category">' . esc_html__('Vehicle Category', 'mhm-rentiva') . '</label>';
            echo '</div>';
            echo '<div class="filter-body">';
            echo '<select name="vehicle_category" id="vehicle_category">';
            echo '<option value="">' . esc_html__('All Categories', 'mhm-rentiva') . '</option>';
            foreach ($vehicle_categories as $value => $label) {
                echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '</div>';
            echo '</div>';
            
            echo '</div>'; // filter-grid
            echo '</div>'; // filter-section
        }

        // Custom date range (hidden by default)
        echo '<div class="filter-section custom-date-range" style="display: none;">';
        echo '<div class="filter-section-header">';
        echo '<h3>' . esc_html__('Custom Date Range', 'mhm-rentiva') . '</h3>';
        echo '<p>' . esc_html__('Select a specific date range for your export.', 'mhm-rentiva') . '</p>';
        echo '</div>';
        
        echo '<div class="filter-grid">';
        echo '<div class="filter-group">';
        echo '<label for="start_date">' . esc_html__('Start Date', 'mhm-rentiva') . '</label>';
        echo '<input type="date" name="start_date" id="start_date">';
        echo '</div>';
        echo '<div class="filter-group">';
        echo '<label for="end_date">' . esc_html__('End Date', 'mhm-rentiva') . '</label>';
        echo '<input type="date" name="end_date" id="end_date">';
        echo '</div>';
        echo '</div>'; // filter-grid
        echo '</div>'; // filter-section

        echo '<div class="filter-actions">';
        echo '<button type="button" class="button" onclick="resetFilters()">' . esc_html__('Reset Filters', 'mhm-rentiva') . '</button>';
        echo '<button type="button" class="button button-primary" onclick="applyFilters()">' . esc_html__('Apply Filters', 'mhm-rentiva') . '</button>';
        echo '</div>';

        echo '</form>';
        echo '</div>';
    }
}
