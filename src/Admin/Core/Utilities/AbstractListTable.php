<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

// WP_List_Table sınıfını yükle (admin panelinde)
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Abstract ListTable Base Class
 * 
 * WordPress ListTable sınıfları için merkezi base class.
 * Ortak işlevleri ve yapısal tekrarı elimine eder.
 * 
 * @abstract
 */
abstract class AbstractListTable extends \WP_List_Table
{
    protected int $default_per_page = 20;
    protected string $nonce_action = 'mhm_listtable_bulk_action';
    protected string $nonce_name = 'mhm_listtable_nonce';

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

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct([
            'singular' => $this->get_singular_name(),
            'plural' => $this->get_plural_name(),
            'ajax' => false,
        ]);
    }

    /**
     * Abstract methods - Alt sınıflarda implement edilmeli
     */
    abstract protected function get_singular_name(): string;
    abstract protected function get_plural_name(): string;
    abstract protected function get_data_query_args(): array;
    abstract protected function get_data_from_results($results): array;
    abstract protected function get_total_count(): int;

    /**
     * Column headers'ı hazırla
     */
    public function prepare_items(): void
    {
        // Bulk actions'ı handle et (data'yı yüklemeden önce)
        // NOT: Redirect yapılırsa, bu fonksiyon yarıda kesilir ve sayfa yenilenir
        // Bu yüzden redirect'i render_page() başında yapmak daha güvenli
        if (isset($_POST['action']) || isset($_POST['action2'])) {
            $this->handle_bulk_actions();
        }
        
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        // Pagination
        $per_page = $this->default_per_page;
        $current_page = $this->get_pagenum();
        $total_items = $this->get_total_count();

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
        ]);

        // Data'yı getir
        $this->items = $this->get_paginated_data($per_page, $current_page);
    }

    /**
     * Paginated data'yı getir
     */
    protected function get_paginated_data(int $per_page, int $current_page): array
    {
        $offset = ($current_page - 1) * $per_page;
        $args = $this->get_data_query_args();
        
        // Pagination ekle
        $args['posts_per_page'] = $per_page;
        $args['offset'] = $offset;
        
        // Sorting ekle
        $orderby = sanitize_key($_GET['orderby'] ?? 'date');
        $order = sanitize_key($_GET['order'] ?? 'desc');
        $args = $this->apply_sorting($args, $orderby, $order);
        
        // Search ekle
        if (!empty($_GET['s'])) {
            $args['s'] = self::sanitize_text_field_safe($_GET['s']);
        }
        
        // Custom filters ekle
        $args = $this->apply_custom_filters($args);
        
        $query = new \WP_Query($args);
        return $this->get_data_from_results($query->posts);
    }

    /**
     * Sorting uygula
     */
    protected function apply_sorting(array $args, string $orderby, string $order): array
    {
        $sortable_columns = $this->get_sortable_columns();
        
        if (isset($sortable_columns[$orderby])) {
            $args['orderby'] = $orderby;
            $args['order'] = strtoupper($order);
        } else {
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
        }
        
        return $args;
    }

    /**
     * Custom filters uygula (override edilebilir)
     */
    protected function apply_custom_filters(array $args): array
    {
        return $args;
    }

    /**
     * Bulk actions'ı handle et (public yapıldı - dışarıdan çağrılabilir)
     */
    public function handle_bulk_actions(): void
    {
        if (!isset($_POST[$this->get_bulk_action_name()]) || !is_array($_POST[$this->get_bulk_action_name()])) {
            return;
        }

        if (!isset($_POST[$this->nonce_name]) || !wp_verify_nonce($_POST[$this->nonce_name], $this->nonce_action)) {
            $this->show_error(__('Security check failed.', 'mhm-rentiva'));
            return;
        }

        $action = sanitize_key($_POST['action'] ?? $_POST['action2'] ?? '');
        $item_ids = array_map('intval', $_POST[$this->get_bulk_action_name()]);

        if (empty($item_ids)) {
            return;
        }

        $success_count = $this->process_bulk_action($action, $item_ids);

        if ($success_count > 0) {
            // Cache'i temizle (mesajlar için)
            if (method_exists($this, 'clear_cache_after_bulk_action')) {
                $this->clear_cache_after_bulk_action($action, $item_ids);
            }
            
            // Redirect with success message (to avoid resubmission)
            // Get base admin URL - use $_SERVER['REQUEST_URI'] to get current page
            $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
            
            if (empty($current_page)) {
                // Fallback: try to get from REQUEST_URI
                $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
                if (preg_match('/[?&]page=([^&]+)/', $request_uri, $matches)) {
                    $current_page = $matches[1];
                }
            }
            
            // Build redirect URL - use admin_url with proper page parameter
            if (empty($current_page)) {
                // If we can't determine the page, redirect to admin
                $redirect_url = admin_url('admin.php');
            } else {
                $redirect_url = admin_url('admin.php?page=' . urlencode($current_page));
            }
            
            // Remove POST parameters and add success parameters
            $redirect_url = remove_query_arg(['bulk_action', 'bulk_count', 'deleted', 'action', 'action2', $this->get_bulk_action_name(), 'paged'], $redirect_url);
            $redirect_url = add_query_arg([
                'bulk_action' => $action,
                'bulk_count' => $success_count,
            ], $redirect_url);
            
            // Preserve other GET parameters (filters, search, etc.)
            foreach (['s', 'status_filter', 'category_filter', 'thread_id', 'unread_only', 'orderby', 'order'] as $param) {
                if (isset($_GET[$param]) && !empty($_GET[$param])) {
                    $redirect_url = add_query_arg($param, sanitize_text_field($_GET[$param]), $redirect_url);
                }
            }
            
            // Redirect (avoid redirect loop by checking if we're already on the target page)
            wp_safe_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Bulk action'ı işle (override edilebilir)
     */
    protected function process_bulk_action(string $action, array $item_ids): int
    {
        return 0;
    }

    /**
     * Bulk action success message'ı al (override edilebilir)
     */
    protected function get_bulk_success_message(string $action, int $count): string
    {
        return sprintf(__('%d items processed.', 'mhm-rentiva'), $count);
    }

    /**
     * Bulk action name'i al (override edilebilir)
     */
    protected function get_bulk_action_name(): string
    {
        return 'item';
    }

    /**
     * Error message göster
     */
    protected function show_error(string $message): void
    {
        add_action('admin_notices', function() use ($message) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>' . esc_html($message) . '</p>';
            echo '</div>';
        });
    }

    /**
     * Success message göster
     */
    protected function show_success(string $message): void
    {
        add_action('admin_notices', function() use ($message) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html($message) . '</p>';
            echo '</div>';
        });
    }

    /**
     * Extra table navigation (override edilebilir)
     */
    public function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }
        
        $this->render_custom_filters();
    }

    /**
     * Custom filters render et (override edilebilir)
     */
    protected function render_custom_filters(): void
    {
        // Alt sınıflarda implement edilebilir
    }

    /**
     * No items message (override edilebilir)
     */
    public function no_items(): void
    {
        printf(
            __('No %s created yet.', 'mhm-rentiva'),
            $this->get_plural_name()
        );
    }

    /**
     * Checkbox column (ortak)
     */
    protected function column_cb($item): string
    {
        $id = $this->get_item_id($item);
        return sprintf(
            '<input type="checkbox" name="%s[]" value="%s" />',
            esc_attr($this->get_bulk_action_name()),
            esc_attr($id)
        );
    }

    /**
     * Item ID'sini al (override edilebilir)
     */
    protected function get_item_id($item): string
    {
        if (is_object($item)) {
            return (string) $item->ID;
        }
        return (string) ($item['id'] ?? '');
    }

    /**
     * Date column formatı (ortak)
     */
    protected function format_date(string $date, string $format = 'd.m.Y'): string
    {
        if (empty($date)) {
            return '-';
        }
        
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }
        
        return date($format, $timestamp);
    }

    /**
     * Price formatı (ortak)
     */
    protected function format_price(float $price, string $currency = 'TRY'): string
    {
        return number_format($price, 2, ',', '.') . ' ' . $currency;
    }

    /**
     * Status badge render et (ortak)
     */
    protected function render_status_badge(string $status, array $status_labels = []): string
    {
        $label = $status_labels[$status] ?? ucfirst($status);
        $class = 'status-' . sanitize_html_class($status);
        
        return sprintf(
            '<span class="status-badge %s">%s</span>',
            esc_attr($class),
            esc_html($label)
        );
    }

    /**
     * Row actions render et (ortak)
     */
    protected function render_row_actions(array $actions): string
    {
        return $this->row_actions($actions);
    }

    /**
     * View link oluştur (ortak)
     */
    protected function create_view_link(string $page, string $item_id, string $text = ''): string
    {
        if (empty($text)) {
            $text = __('View', 'mhm-rentiva');
        }
        
        return sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url($page . '&id=' . $item_id)),
            esc_html($text)
        );
    }

    /**
     * Edit link oluştur (ortak)
     */
    protected function create_edit_link(string $page, string $item_id, string $text = ''): string
    {
        if (empty($text)) {
            $text = __('Edit', 'mhm-rentiva');
        }
        
        return sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url($page . '&id=' . $item_id)),
            esc_html($text)
        );
    }

    /**
     * Delete link oluştur (ortak)
     */
    protected function create_delete_link(string $item_id, string $text = '', string $confirm_message = ''): string
    {
        if (empty($text)) {
            $text = __('Delete', 'mhm-rentiva');
        }
        
        if (empty($confirm_message)) {
            $confirm_message = __('Are you sure you want to delete this item?', 'mhm-rentiva');
        }
        
        return sprintf(
            '<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
            esc_url(get_delete_post_link($item_id)),
            esc_js($confirm_message),
            esc_html($text)
        );
    }

    /**
     * Nonce field render et (ortak)
     */
    protected function render_nonce_field(): void
    {
        wp_nonce_field($this->nonce_action, $this->nonce_name);
    }

    /**
     * Search box render et (ortak)
     */
    protected function render_search_box(): void
    {
        $search_term = self::sanitize_text_field_safe($_GET['s'] ?? '');
        
        echo '<p class="search-box">';
        echo '<label class="screen-reader-text" for="' . esc_attr($this->get_search_input_id()) . '">' . __('Ara:', 'mhm-rentiva') . '</label>';
        echo '<input type="search" id="' . esc_attr($this->get_search_input_id()) . '" name="s" value="' . esc_attr($search_term) . '" />';
        submit_button(__('Ara', 'mhm-rentiva'), '', '', false, ['id' => 'search-submit']);
        echo '</p>';
    }

    /**
     * Search input ID'sini al (override edilebilir)
     */
    protected function get_search_input_id(): string
    {
        return $this->get_plural_name() . '-search-input';
    }
}
