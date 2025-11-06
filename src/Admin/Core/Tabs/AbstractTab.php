<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Tabs;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract Tab Base Class
 * 
 * WordPress Admin Tab sınıfları için merkezi base class.
 * Ortak işlevleri ve yapısal tekrarı elimine eder.
 * 
 * @abstract
 */
abstract class AbstractTab
{
    /**
     * Abstract methods - Alt sınıflarda implement edilmeli
     */
    abstract protected static function get_tab_id(): string;
    abstract protected static function get_tab_title(): string;
    abstract protected static function get_tab_description(): string;
    abstract protected static function get_tab_content(array $data = []): array;

    /**
     * Tab render et
     */
    public static function render(array $data = []): void
    {
        // Eğer veri geçilmediyse, sistem bilgilerini al
        if (empty($data)) {
            $data = static::get_system_info();
        }
        
        // Tab içeriğini al (veri ile birlikte)
        $tab_content = static::get_tab_content($data);
        
        echo '<div class="about-section">';
        
        // Tab başlığı
        if (!empty($tab_content['title'])) {
            echo '<div class="tab-header">';
            echo '<h3>' . esc_html($tab_content['title']) . '</h3>';
            if (!empty($tab_content['description'])) {
                echo '<p>' . esc_html($tab_content['description']) . '</p>';
            }
            echo '</div>';
        }

        // Tab içeriği
        if (!empty($tab_content['sections'])) {
            foreach ($tab_content['sections'] as $section) {
                static::render_section($section, $data);
            }
        }

        echo '</div>';
    }

    /**
     * Section render et
     */
    protected static function render_section(array $section, array $data = []): void
    {
        $section_type = $section['type'] ?? 'card';
        $section_title = $section['title'] ?? '';
        $section_content = $section['content'] ?? '';

        echo '<div class="tab-section ' . esc_attr($section_type) . '">';
        
        if ($section_title) {
            echo '<h4>' . esc_html($section_title) . '</h4>';
        }

        switch ($section_type) {
            case 'card':
                static::render_card_section($section, $data);
                break;
            case 'grid':
                static::render_grid_section($section, $data);
                break;
            case 'list':
                static::render_list_section($section, $data);
                break;
            case 'table':
                static::render_table_section($section, $data);
                break;
            case 'stats':
                static::render_stats_section($section, $data);
                break;
            case 'custom':
                if (isset($section['custom_render']) && is_callable($section['custom_render'])) {
                    call_user_func($section['custom_render'], $section, $data);
                }
                break;
            default:
                echo $section_content;
        }

        echo '</div>';
    }

    /**
     * Card section render
     */
    protected static function render_card_section(array $section, array $data = []): void
    {
        $cards = $section['cards'] ?? [];
        
        echo '<div class="cards-grid">';
        foreach ($cards as $card) {
            static::render_card($card, $data);
        }
        echo '</div>';
    }

    /**
     * Grid section render
     */
    protected static function render_grid_section(array $section, array $data = []): void
    {
        $items = $section['items'] ?? [];
        $columns = $section['columns'] ?? 2;
        
        echo '<div class="grid-container" style="grid-template-columns: repeat(' . esc_attr($columns) . ', 1fr);">';
        foreach ($items as $item) {
            static::render_grid_item($item, $data);
        }
        echo '</div>';
    }

    /**
     * List section render
     */
    protected static function render_list_section(array $section, array $data = []): void
    {
        $items = $section['items'] ?? [];
        $list_type = $section['list_type'] ?? 'ul';
        
        echo '<' . esc_attr($list_type) . ' class="info-list">';
        foreach ($items as $item) {
            static::render_list_item($item, $data);
        }
        echo '</' . esc_attr($list_type) . '>';
    }

    /**
     * Table section render
     */
    protected static function render_table_section(array $section, array $data = []): void
    {
        $headers = $section['headers'] ?? [];
        $rows = $section['rows'] ?? [];
        
        echo '<table class="widefat">';
        
        // Headers
        if (!empty($headers)) {
            echo '<thead><tr>';
            foreach ($headers as $header) {
                echo '<th>' . esc_html($header) . '</th>';
            }
            echo '</tr></thead>';
        }

        // Rows
        if (!empty($rows)) {
            echo '<tbody>';
            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($row as $cell) {
                    echo '<td>' . $cell . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody>';
        }
        
        echo '</table>';
    }

    /**
     * Stats section render
     */
    protected static function render_stats_section(array $section, array $data = []): void
    {
        $stats = $section['stats'] ?? [];
        
        echo '<div class="stats-grid">';
        foreach ($stats as $stat) {
            static::render_stat_item($stat, $data);
        }
        echo '</div>';
    }

    /**
     * Card render
     */
    protected static function render_card(array $card, array $data = []): void
    {
        $title = $card['title'] ?? '';
        $content = $card['content'] ?? '';
        $class = $card['class'] ?? '';
        
        echo '<div class="info-card ' . esc_attr($class) . '">';
        
        if ($title) {
            echo '<h3>' . esc_html($title) . '</h3>';
        }
        
        if (is_array($content)) {
            // Array content - render as list
            echo '<div class="info-list">';
            foreach ($content as $item) {
                static::render_info_item($item, $data);
            }
            echo '</div>';
        } else {
            echo $content;
        }
        
        echo '</div>';
    }

    /**
     * Grid item render
     */
    protected static function render_grid_item(array $item, array $data = []): void
    {
        $title = $item['title'] ?? '';
        $content = $item['content'] ?? '';
        $class = $item['class'] ?? '';
        
        echo '<div class="grid-item ' . esc_attr($class) . '">';
        
        if ($title) {
            echo '<h4>' . esc_html($title) . '</h4>';
        }
        
        echo $content;
        echo '</div>';
    }

    /**
     * List item render
     */
    protected static function render_list_item(array $item, array $data = []): void
    {
        $label = $item['label'] ?? '';
        $value = $item['value'] ?? '';
        $type = $item['type'] ?? 'text';
        
        echo '<li class="info-item">';
        
        if ($type === 'key-value') {
            echo '<span class="label">' . esc_html($label) . ':</span>';
            echo '<span class="value">' . esc_html($value) . '</span>';
        } else {
            echo esc_html($item['content'] ?? '');
        }
        
        echo '</li>';
    }

    /**
     * Stat item render
     */
    protected static function render_stat_item(array $stat, array $data = []): void
    {
        $number = $stat['number'] ?? '0';
        $label = $stat['label'] ?? '';
        $class = $stat['class'] ?? '';
        
        echo '<div class="stat-item ' . esc_attr($class) . '">';
        echo '<div class="stat-number">' . esc_html($number) . '</div>';
        echo '<div class="stat-label">' . esc_html($label) . '</div>';
        echo '</div>';
    }

    /**
     * Info item render
     */
    protected static function render_info_item(array $item, array $data = []): void
    {
        $label = $item['label'] ?? '';
        $value = $item['value'] ?? '';
        $type = $item['type'] ?? 'text';
        
        // Eğer data_key varsa, veri ile değiştir
        $data_key = $item['data_key'] ?? '';
        if ($data_key) {
            $actual_value = static::get_data_value($data, $data_key, $value);
            $suffix = $item['suffix'] ?? '';
            $value = $actual_value . $suffix;
        }
        
        echo '<div class="info-item">';
        
        switch ($type) {
            case 'key-value':
                echo '<span class="label">' . esc_html($label) . ':</span>';
                echo '<span class="value">' . esc_html($value) . '</span>';
                break;
            case 'boolean':
                echo '<span class="label">' . esc_html($label) . ':</span>';
                echo '<span class="value ' . ($value ? 'yes' : 'no') . '">';
                echo $value ? '✓' : '✗';
                echo '</span>';
                break;
            case 'link':
                echo '<span class="label">' . esc_html($label) . ':</span>';
                echo '<a href="' . esc_url($value) . '" target="_blank">' . esc_html($item['text'] ?? $value) . '</a>';
                break;
            case 'email':
                echo '<span class="label">' . esc_html($label) . ':</span>';
                echo '<a href="mailto:' . esc_attr($value) . '">' . esc_html($value) . '</a>';
                break;
            case 'phone':
                echo '<span class="label">' . esc_html($label) . ':</span>';
                echo '<a href="tel:' . esc_attr($value) . '">' . esc_html($value) . '</a>';
                break;
            default:
                echo '<span class="label">' . esc_html($label) . ':</span>';
                echo '<span class="value">' . esc_html($value) . '</span>';
        }
        
        echo '</div>';
    }

    /**
     * Helper: Button render
     */
    protected static function render_button(array $button): string
    {
        $url = $button['url'] ?? '#';
        $text = $button['text'] ?? __('Buton', 'mhm-rentiva');
        $class = $button['class'] ?? 'button button-secondary';
        $target = $button['target'] ?? '_self';
        $external = $button['external'] ?? false;
        
        $attributes = [
            'href="' . esc_url($url) . '"',
            'class="' . esc_attr($class) . '"',
            'target="' . esc_attr($target) . '"',
        ];
        
        if ($external) {
            $attributes[] = 'rel="noopener noreferrer"';
        }
        
        return '<a ' . implode(' ', $attributes) . '>' . esc_html($text) . '</a>';
    }

    /**
     * Helper: External link render
     */
    protected static function render_external_link(string $url, string $text, array $attributes = []): string
    {
        $default_attributes = [
            'href' => $url,
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
            'class' => 'button button-secondary',
        ];
        
        $attributes = array_merge($default_attributes, $attributes);
        
        $attr_string = '';
        foreach ($attributes as $key => $value) {
            $attr_string .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }
        
        return '<a' . $attr_string . '>' . esc_html($text) . '</a>';
    }

    /**
     * Helper: Notice render
     */
    protected static function render_notice(string $message, string $type = 'info'): void
    {
        echo '<div class="notice notice-' . esc_attr($type) . ' inline">';
        echo '<p>' . esc_html($message) . '</p>';
        echo '</div>';
    }

    /**
     * Helper: Badge render
     */
    protected static function render_badge(string $text, string $type = 'default'): string
    {
        return '<span class="badge badge-' . esc_attr($type) . '">' . esc_html($text) . '</span>';
    }

    /**
     * Helper: Progress bar render
     */
    protected static function render_progress_bar(int $percentage, string $label = ''): void
    {
        echo '<div class="progress-bar">';
        if ($label) {
            echo '<div class="progress-label">' . esc_html($label) . '</div>';
        }
        echo '<div class="progress-track">';
        echo '<div class="progress-fill" style="width: ' . esc_attr($percentage) . '%"></div>';
        echo '</div>';
        echo '<div class="progress-percentage">' . esc_html($percentage) . '%</div>';
        echo '</div>';
    }

    /**
     * Helper: Data getter with fallback
     */
    protected static function get_data_value(array $data, string $key, $default = '')
    {
        // Dot notation desteği (örn: 'wordpress.version')
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $value = $data;
            
            foreach ($keys as $k) {
                if (!is_array($value) || !array_key_exists($k, $value)) {
                    return $default;
                }
                $value = $value[$k];
            }
            
            return $value;
        }
        
        return $data[$key] ?? $default;
    }

    /**
     * Helper: Get system info from global
     */
    protected static function get_system_info()
    {
        return $GLOBALS['system_info'] ?? [];
    }

    /**
     * Helper: Format number
     */
    protected static function format_number($number): string
    {
        return number_format((float) $number);
    }

    /**
     * Helper: Format file size
     */
    protected static function format_file_size(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Helper: Get WordPress version
     */
    protected static function get_wp_version(): string
    {
        global $wp_version;
        return $wp_version;
    }

    /**
     * Helper: Get PHP version
     */
    protected static function get_php_version(): string
    {
        return PHP_VERSION;
    }

    /**
     * Helper: Get MySQL version
     */
    protected static function get_mysql_version(): string
    {
        global $wpdb;
        return $wpdb->db_version();
    }

    /**
     * Helper: Check if multisite
     */
    protected static function is_multisite(): bool
    {
        return is_multisite();
    }

    /**
     * Helper: Get site URL
     */
    protected static function get_site_url(): string
    {
        return get_site_url();
    }

    /**
     * Helper: Get admin URL
     */
    protected static function get_admin_url(string $path = ''): string
    {
        return admin_url($path);
    }
}
