<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Traits;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ ADMIN HELPER TRAIT - Code Duplication Eliminasyonu
 * 
 * Admin sayfalarında tekrarlanan kodları merkezi hale getirir
 */
trait AdminHelperTrait
{
    /**
     * Admin yetki kontrolü
     * 
     * @param string $capability Gerekli yetki
     * @return bool Yetki durumu
     */
    protected function check_admin_capability(string $capability = 'manage_options'): bool
    {
        return current_user_can($capability);
    }

    /**
     * Admin yetki kontrolü ve erişim engelleme
     * 
     * @param string $capability Gerekli yetki
     * @throws \Exception Yetki yoksa exception fırlatır
     */
    protected function require_admin_capability(string $capability = 'manage_options'): void
    {
        if (!current_user_can($capability)) {
            throw new \Exception(__('You do not have permission to access this page.', 'mhm-rentiva'));
        }
    }

    /**
     * Admin sayfa wrapper başlat
     * 
     * @param string $title Sayfa başlığı
     * @param string $class CSS class
     */
    protected function start_admin_wrapper(string $title, string $class = 'mhm-rentiva-wrap'): void
    {
        echo '<div class="wrap ' . esc_attr($class) . '">';
        echo '<h1>' . esc_html($title) . '</h1>';
    }

    /**
     * Admin sayfa wrapper bitir
     */
    protected function end_admin_wrapper(): void
    {
        echo '</div>';
    }

    /**
     * Admin notice göster
     * 
     * @param string $message Mesaj
     * @param string $type Notice tipi (success, error, warning, info)
     * @param bool $dismissible Kapatılabilir mi
     */
    protected function show_admin_notice(string $message, string $type = 'info', bool $dismissible = true): void
    {
        $dismissible_class = $dismissible ? 'is-dismissible' : '';
        echo '<div class="notice notice-' . esc_attr($type) . ' ' . esc_attr($dismissible_class) . '">';
        echo '<p>' . wp_kses_post($message) . '</p>';
        echo '</div>';
    }

    /**
     * Admin tab navigation oluştur
     * 
     * @param array $tabs Tab'lar ['key' => 'label']
     * @param string $current_active Aktif tab
     * @param string $base_url Base URL
     */
    protected function render_admin_tabs(array $tabs, string $current_active, string $base_url): void
    {
        echo '<nav class="nav-tab-wrapper">';
        foreach ($tabs as $key => $label) {
            $active_class = ($key === $current_active) ? 'nav-tab-active' : '';
            $url = add_query_arg('tab', $key, $base_url);
            echo '<a href="' . esc_url($url) . '" class="nav-tab ' . esc_attr($active_class) . '">';
            echo esc_html($label);
            echo '</a>';
        }
        echo '</nav>';
    }

    /**
     * Form nonce field ekle
     * 
     * @param string $action Action name
     * @param string $name Field name
     */
    protected function add_nonce_field(string $action, string $name = '_wpnonce'): void
    {
        wp_nonce_field($action, $name);
    }

    /**
     * Nonce doğrula
     * 
     * @param string $action Action name
     * @param string $name Field name
     * @return bool Doğrulama durumu
     */
    protected function verify_nonce(string $action, string $name = '_wpnonce'): bool
    {
        return wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$name] ?? '')), $action) !== false;
    }

    /**
     * Admin form submit kontrolü
     * 
     * @param string $action Action name
     * @param string $nonce_name Nonce field name
     * @return bool Submit durumu
     */
    protected function is_form_submitted(string $action, string $nonce_name = '_wpnonce'): bool
    {
        return isset($_POST[$nonce_name]) && $this->verify_nonce($action, $nonce_name);
    }

    /**
     * Sanitize form data
     * 
     * @param array $data Form verisi
     * @param array $fields İzin verilen alanlar
     * @return array Sanitize edilmiş veri
     */
    protected function sanitize_form_data(array $data, array $fields = []): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (!empty($fields) && !in_array($key, $fields, true)) {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_form_data($value, $fields);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    /**
     * Admin redirect
     * 
     * @param string $url Redirect URL
     * @param array $query_params Query parametreleri
     */
    protected function admin_redirect(string $url, array $query_params = []): void
    {
        if (!empty($query_params)) {
            $url = add_query_arg($query_params, $url);
        }

        wp_redirect($url);
        exit;
    }

    /**
     * Admin ajax response gönder
     * 
     * @param bool $success Başarı durumu
     * @param mixed $data Response data
     * @param string $message Mesaj
     * @param int $status_code HTTP status code
     */
    protected function send_ajax_response(bool $success, $data = null, string $message = '', int $status_code = 200): void
    {
        $response = [
            'success' => $success,
            'data' => $data,
            'message' => $message
        ];

        wp_send_json($response, $status_code);
    }

    /**
     * Admin table pagination oluştur
     * 
     * @param int $total_items Toplam item sayısı
     * @param int $per_page Sayfa başına item
     * @param int $current_page Mevcut sayfa
     * @param string $base_url Base URL
     * @param string $page_param Page parametresi
     */
    protected function render_pagination(int $total_items, int $per_page, int $current_page, string $base_url, string $page_param = 'paged'): void
    {
        $total_pages = ceil($total_items / $per_page);

        if ($total_pages <= 1) {
            return;
        }

        echo '<div class="tablenav-pages">';
        echo '<span class="displaying-num">' . sprintf(
            /* translators: %s placeholder. */
            _n('%s item', '%s items', $total_items, 'mhm-rentiva'),
            number_format_i18n($total_items)
        ) . '</span>';

        echo '<span class="pagination-links">';

        // Previous page
        if ($current_page > 1) {
            $prev_url = add_query_arg($page_param, $current_page - 1, $base_url);
            echo '<a class="first-page" href="' . esc_url($prev_url) . '">‹‹</a>';
            echo '<a class="prev-page" href="' . esc_url($prev_url) . '">‹</a>';
        }

        // Page numbers
        $start = max(1, $current_page - 2);
        $end = min($total_pages, $current_page + 2);

        for ($i = $start; $i <= $end; $i++) {
            if ($i === $current_page) {
                echo '<span class="current">' . $i . '</span>';
            } else {
                $page_url = add_query_arg($page_param, $i, $base_url);
                echo '<a href="' . esc_url($page_url) . '">' . $i . '</a>';
            }
        }

        // Next page
        if ($current_page < $total_pages) {
            $next_url = add_query_arg($page_param, $current_page + 1, $base_url);
            echo '<a class="next-page" href="' . esc_url($next_url) . '">›</a>';
            echo '<a class="last-page" href="' . esc_url($next_url) . '">››</a>';
        }

        echo '</span>';
        echo '</div>';
    }

    /**
     * Admin table bulk actions oluştur
     * 
     * @param array $actions Bulk actions
     * @param string $name Field name
     */
    protected function render_bulk_actions(array $actions, string $name = 'bulk_action'): void
    {
        echo '<select name="' . esc_attr($name) . '">';
        echo '<option value="">' . __('Bulk Actions', 'mhm-rentiva') . '</option>';

        foreach ($actions as $value => $label) {
            echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
        }

        echo '</select>';
        echo '<input type="submit" class="button" value="' . esc_attr__('Apply', 'mhm-rentiva') . '">';
    }

    /**
     * Admin loading spinner göster
     * 
     * @param string $message Loading mesajı
     */
    protected function show_loading_spinner(string $message = ''): void
    {
        echo '<div class="mhm-loading-spinner">';
        echo '<div class="spinner is-active"></div>';
        if ($message) {
            echo '<span class="loading-message">' . esc_html($message) . '</span>';
        }
        echo '</div>';
    }

    /**
     * Admin success message göster
     * 
     * @param string $message Başarı mesajı
     */
    protected function show_success_message(string $message): void
    {
        $this->show_admin_notice($message, 'success');
    }

    /**
     * Admin error message göster
     * 
     * @param string $message Hata mesajı
     */
    protected function show_error_message(string $message): void
    {
        $this->show_admin_notice($message, 'error');
    }

    /**
     * Admin warning message göster
     * 
     * @param string $message Uyarı mesajı
     */
    protected function show_warning_message(string $message): void
    {
        $this->show_admin_notice($message, 'warning');
    }

    /**
     * Admin info message göster
     * 
     * @param string $message Bilgi mesajı
     */
    protected function show_info_message(string $message): void
    {
        $this->show_admin_notice($message, 'info');
    }
}
