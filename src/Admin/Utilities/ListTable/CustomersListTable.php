<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\ListTable;

use MHMRentiva\Admin\Core\Utilities\AbstractListTable;
use MHMRentiva\Admin\Core\Utilities\MetaQueryHelper;
use MHMRentiva\Admin\Core\Utilities\ErrorHandler;
use MHMRentiva\Admin\Core\Utilities\TypeValidator;
use MHMRentiva\Admin\Core\Utilities\I18nHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress List Table class for customers
 */
final class CustomersListTable extends AbstractListTable
{
    public function __construct()
    {
        parent::__construct();
        $this->nonce_action = 'mhm_rentiva_customers_bulk_action';
        $this->nonce_name = 'mhm_rentiva_customers_nonce';
    }

    protected function get_singular_name(): string
    {
        return I18nHelper::__('customer');
    }

    protected function get_plural_name(): string
    {
        return I18nHelper::__('customers');
    }

    protected function get_bulk_action_name(): string
    {
        return 'customer';
    }

    protected function get_data_query_args(): array
    {
        return [
            'post_type' => 'vehicle_booking',
            'post_status' => 'publish',
            'posts_per_page' => $this->default_per_page,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
    }

    protected function get_data_from_results($results): array
    {
        // This method will be overridden as we use custom SQL query
        return [];
    }

    protected function get_total_count(): int
    {
        global $wpdb;
        
        $total_query = "
            SELECT COUNT(DISTINCT u.ID) as total
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->postmeta} email_meta ON u.user_email = email_meta.meta_value
                AND email_meta.meta_key = '_mhm_customer_email'
            INNER JOIN {$wpdb->posts} p ON p.ID = email_meta.post_id
                AND p.post_type = 'vehicle_booking'
                AND p.post_status = 'publish'
            WHERE u.ID > 1
        ";
        
        return (int) $wpdb->get_var($total_query);
    }

    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'name' => __('Customer', 'mhm-rentiva'),
            'email' => __('Email', 'mhm-rentiva'),
            'phone' => __('Phone', 'mhm-rentiva'),
            'bookings' => __('Bookings', 'mhm-rentiva'),
            'total_spent' => __('Total Spent', 'mhm-rentiva'),
            'last_booking' => __('Last Booking', 'mhm-rentiva'),
            'date' => __('Date', 'mhm-rentiva'),
        ];
    }

    public function get_sortable_columns(): array
    {
        return [
            'name' => ['name', false],
            'email' => ['email', false],
            'bookings' => ['bookings', false],
            'total_spent' => ['total_spent', false],
            'last_booking' => ['last_booking', false],
            'date' => ['date', true],
        ];
    }

    protected function get_bulk_actions(): array
    {
        return [
            'export' => __('Export', 'mhm-rentiva'),
            'delete' => __('Delete', 'mhm-rentiva'),
        ];
    }

    protected function column_cb($item): string
    {
        $id = TypeValidator::validateString($item['id'] ?? '');
        return sprintf('<input type="checkbox" name="customer[]" value="%s" />', esc_attr($id));
    }

    protected function column_name(array $item): string
    {
        $actions = [
            'view' => sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('admin.php?page=mhm-rentiva-customers&action=view&customer_id=' . TypeValidator::validateString($item['id'] ?? ''))),
                __('View', 'mhm-rentiva')
            ),
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('admin.php?page=mhm-rentiva-customers&action=edit&customer_id=' . TypeValidator::validateString($item['id'] ?? ''))),
                __('Edit', 'mhm-rentiva')
            ),
            'bookings' => sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('edit.php?post_type=vehicle_booking&customer_id=' . TypeValidator::validateString($item['id'] ?? ''))),
                esc_html__('Bookings', 'mhm-rentiva')
            ),
        ];

        $id = TypeValidator::validateString($item['id'] ?? '');
        $name = TypeValidator::validateString($item['name'] ?? '');
        
        return sprintf(
            '<strong><a href="%s">%s</a></strong>%s',
            esc_url(admin_url('admin.php?page=mhm-rentiva-customers&action=view&customer_id=' . $id)),
            esc_html($name),
            $this->row_actions($actions)
        );
    }

    protected function column_email(array $item): string
    {
        $email = TypeValidator::validateString($item['email'] ?? '');
        return sprintf('<a href="mailto:%s">%s</a>', esc_attr($email), esc_html($email));
    }

    protected function column_phone(array $item): string
    {
        $phone = TypeValidator::validateString($item['phone'] ?? '');
        return esc_html($phone);
    }

    protected function column_bookings(array $item): string
    {
        $booking_count = TypeValidator::validateInt($item['booking_count'] ?? 0);
        $id = TypeValidator::validateString($item['id'] ?? '');
        
        if ($booking_count > 0) {
            return sprintf(
                '<a href="%s">%d</a>',
                esc_url(admin_url('edit.php?post_type=vehicle_booking&customer_id=' . $id)),
                $booking_count
            );
        }
        return '0';
    }

    protected function column_total_spent(array $item): string
    {
        $total_spent = TypeValidator::validateString($item['total_spent'] ?? '0');
        $currency = TypeValidator::validateString($item['currency'] ?? 'USD');
        return esc_html($total_spent) . ' ' . esc_html($currency);
    }

    protected function column_last_booking(array $item): string
    {
        $last_booking = TypeValidator::validateString($item['last_booking'] ?? '');
        return esc_html($last_booking);
    }

    protected function column_date(array $item): string
    {
        $created_date = TypeValidator::validateString($item['created_date'] ?? '');
        return esc_html($created_date);
    }

    public function prepare_items(): void
    {
        parent::prepare_items();
        
        // Override as we use custom SQL query
        $per_page = $this->default_per_page;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        $this->items = $this->get_customers_data($per_page, $offset);
    }

    private function get_customers_data(int $per_page = 20, int $offset = 0): array
    {
        // ✅ MEMORY ABUSE FIXED - Database level pagination
        global $wpdb;
        
        // ✅ CODE QUALITY IMPROVEMENT - MetaQueryHelper usage
        $meta_joins = MetaQueryHelper::get_booking_meta_joins();
        
        $query = "
            SELECT 
                u.ID as user_id,
                u.display_name as customer_name,
                u.user_email as customer_email,
                MIN(p.post_date) as first_booking_date,
                COUNT(p.ID) as booking_count,
                SUM(CAST(COALESCE(price_meta.meta_value, '0') AS DECIMAL(10,2))) as total_spent,
                MAX(p.post_date) as last_booking
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->postmeta} email_meta ON u.user_email = email_meta.meta_value
                AND email_meta.meta_key = '_mhm_customer_email'
            INNER JOIN {$wpdb->posts} p ON p.ID = email_meta.post_id
                AND p.post_type = 'vehicle_booking'
                AND p.post_status = 'publish'
            LEFT JOIN {$wpdb->postmeta} price_meta ON p.ID = price_meta.post_id
                AND price_meta.meta_key = '_mhm_total_price'
            WHERE u.ID > 1
            GROUP BY u.ID, u.display_name, u.user_email
            ORDER BY last_booking DESC
            LIMIT %d OFFSET %d
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($query, $per_page, $offset));
        
        $customer_data = [];
        $currency = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_currency', 'USD');

        foreach ($results as $result) {
            // Get phone number from user meta
            $customer_phone = get_user_meta($result->user_id, 'mhm_rentiva_phone', true);
            
            $customer_data[] = [
                'id' => $result->user_id,
                'name' => $result->customer_name ?: $result->customer_email,
                'email' => $result->customer_email,
                'phone' => $customer_phone ?: '-',
                'booking_count' => (int) $result->booking_count,
                'total_spent' => number_format((float) $result->total_spent, 2, ',', '.'),
                'last_booking' => current_time('d.m.Y', strtotime($result->last_booking)),
                'created_date' => $result->first_booking_date ? current_time('d.m.Y', strtotime($result->first_booking_date)) : '-',
                'currency' => $currency
            ];
        }

        return $customer_data;
    }

    protected function process_bulk_action(string $action, array $item_ids): int
    {
        switch ($action) {
            case 'export':
                $this->export_customers($item_ids);
                return count($item_ids);
            case 'delete':
                return $this->delete_customers($item_ids);
            default:
                return 0;
        }
    }

    protected function get_bulk_success_message(string $action, int $count): string
    {
        switch ($action) {
            case 'export':
                /* translators: %d placeholder. */
                return sprintf(__('%d customers exported.', 'mhm-rentiva'), $count);
            case 'delete':
                /* translators: %d placeholder. */
                return sprintf(__('%d customers deleted.', 'mhm-rentiva'), $count);
            default:
                return parent::get_bulk_success_message($action, $count);
        }
    }

    private function export_customers(array $customer_ids): void
    {
        // CSV export process
        $filename = 'customers_' . current_time('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header row
        fputcsv($output, [
            __('Customer', 'mhm-rentiva'),
            __('Email', 'mhm-rentiva'),
            __('Phone', 'mhm-rentiva'),
            __('Bookings', 'mhm-rentiva'),
            __('Total Spent', 'mhm-rentiva'),
            __('Last Booking', 'mhm-rentiva'),
        ]);
        
        // Data rows
        $customers = $this->get_customers_data();
        foreach ($customers as $customer) {
            if (in_array($customer['id'], $customer_ids)) {
                fputcsv($output, [
                    $customer['name'],
                    $customer['email'],
                    $customer['phone'],
                    $customer['booking_count'],
                    $customer['total_spent'],
                    $customer['last_booking'],
                ]);
            }
        }
        
        fclose($output);
        exit;
    }

    private function delete_customers(array $customer_ids): int
    {
        // Customer deletion process (also delete bookings)
        $deleted_count = 0;
        
        foreach ($customer_ids as $customer_id) {
            // Find and delete bookings for this customer
            $bookings = get_posts([
                'post_type' => 'vehicle_booking',
                'post_status' => 'publish',
                'numberposts' => -1,
                'meta_query' => [
                    [
                        'key' => 'mhm_rentiva_customer_email',
                        'value' => $customer_id, // Here we use email hash
                        'compare' => 'LIKE'
                    ]
                ]
            ]);
            
            foreach ($bookings as $booking) {
                wp_delete_post($booking->ID, true);
                $deleted_count++;
            }
        }
        
        return $deleted_count;
    }
}
