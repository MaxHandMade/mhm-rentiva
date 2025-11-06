<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

final class Templates
{
    // Template bul ve include et. $return=true ise çıktı tamponlanır ve string döner.
    public static function render(string $relative, array $vars = [], bool $return = false)
    {
        $file = self::locate($relative);
        if (!$file || !is_file($file)) {
            
            // Not found: boş string veya uyarı
            if ($return) return '';
            return;
        }


        if (!empty($vars)) {
            extract($vars, EXTR_SKIP);
        }

        if ($return) {
            ob_start();
            include $file;
            $output = ob_get_clean();
            // Remove all whitespace characters including newlines
            $output = preg_replace('/\s+/', ' ', $output);
            $output = trim($output);
            return (string) $output;
        }

        include $file;
    }

    // Tema > ebeveyn tema > eklenti sırası ile dosyayı bulur
    public static function locate(string $relative): ?string
    {
        $relative = ltrim($relative, '/\\');
        $candidates = [];

        // 1) Child theme
        $child_theme = trailingslashit(get_stylesheet_directory()) . 'mhm-rentiva/' . $relative;
        if (!str_ends_with($child_theme, '.php')) {
            $child_theme .= '.php';
        }
        $candidates[] = $child_theme;
        
        // 2) Parent theme
        if (get_stylesheet_directory() !== get_template_directory()) {
            $parent_theme = trailingslashit(get_template_directory()) . 'mhm-rentiva/' . $relative;
            if (!str_ends_with($parent_theme, '.php')) {
                $parent_theme .= '.php';
            }
            $candidates[] = $parent_theme;
        }
        // 3) Plugin templates - doğru path kullan
        $plugin_templates = MHM_RENTIVA_PLUGIN_PATH . 'templates/' . $relative;
        
        
        // Debug log'ları kapatıldı (performans için)
        // .php uzantısı yoksa ekle
        if (!str_ends_with($plugin_templates, '.php')) {
            $plugin_templates .= '.php';
        }
        $candidates[] = $plugin_templates;
        
        // Debug log'ları kaldırıldı

        // Filtre ile alternatif yollar eklenebilir
        $candidates = apply_filters('mhm_rentiva/template_candidates', $candidates, $relative);

        foreach ($candidates as $path) {
            if (is_file($path)) {
                $located = (string) $path;
                return apply_filters('mhm_rentiva/locate_template', $located, $relative);
            }
        }
        
        
        return null;
    }

    // Fiyat HTML yardımcı metodu (şablonlardan kullanılabilir)
    public static function price_html(int $post_id): string
    {
        $meta_key = apply_filters('mhm_rentiva/vehicle/price_meta_key', '_mhm_rentiva_price_per_day');
        $raw = get_post_meta($post_id, $meta_key, true);
        if ($raw === '' || !is_numeric($raw)) return '';
        $price = (float)$raw;
        $currency = apply_filters('mhm_rentiva/currency_code', 'TRY');
        $formatted = apply_filters('mhm_rentiva/format_price', number_format_i18n($price, 0) . ' ' . $currency, $price, $currency, $post_id);
        return sprintf(
            '<span class="amount">%s</span> <span class="unit">%s</span>',
            esc_html((string)$formatted),
            esc_html__('/day', 'mhm-rentiva')
        );
    }

    private static function plugin_file(): string
    {
        // MHM_RENTIVA_PLUGIN_FILE sabitini kullan (daha güvenilir)
        if (defined('MHM_RENTIVA_PLUGIN_FILE')) {
            return MHM_RENTIVA_PLUGIN_FILE;
        }
        
        // Fallback: Bu sınıfın bulunduğu dizinden eklenti köküne ulaş
        // .../src/Admin/Core/Utilities/Templates.php -> eklenti kökü: ../../../../
        $plugin_file = dirname(__DIR__, 4) . '/mhm-rentiva.php';
        
        // Debug log'ları kapatıldı (performans için)
        
        return $plugin_file;
    }

    // Backward compatibility için eski metodları koru
    public static function load(string $template_name, array $args = [], bool $echo = true): ?string
    {
        return self::render($template_name . '.php', $args, !$echo);
    }

    public static function template_exists(string $template_name): bool
    {
        return self::locate($template_name . '.php') !== null;
    }

    public static function get_template_path(string $template_name): ?string
    {
        return self::locate($template_name . '.php');
    }

    public static function get_available_templates(): array
    {
        $templates = [];
        $plugin_templates_dir = trailingslashit(plugin_dir_path(self::plugin_file())) . 'templates/';
        
        if (is_dir($plugin_templates_dir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($plugin_templates_dir)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relative_path = str_replace($plugin_templates_dir, '', $file->getPathname());
                    $template_name = str_replace('.php', '', $relative_path);
                    $templates[] = $template_name;
                }
            }
        }
        
        return $templates;
    }

    public static function get_override_paths(): array
    {
        return [
            'child_theme' => trailingslashit(get_stylesheet_directory()) . 'mhm-rentiva/',
            'parent_theme' => trailingslashit(get_template_directory()) . 'mhm-rentiva/',
            'plugin_default' => trailingslashit(plugin_dir_path(self::plugin_file())) . 'templates/',
        ];
    }
}
