<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\View;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper class for Settings View operations
 */
final class SettingsViewHelper
{
    /**
     * Safely remove nested form elements from HTML content
     * 
     * @param string $content HTML content that may contain nested forms
     * @return string Cleaned HTML content without nested forms
     */
    public static function remove_nested_forms(string $content): string
    {
        if (empty($content)) {
            return '';
        }

        // Just strip the <form> and </form> tags themselves, preserving inner content
        // This is safer than DOM manipulation which might remove children.
        $content = preg_replace('/<form[^>]*>/i', '<!-- nested form stripped -->', $content) ?? '';
        $content = preg_replace('/<\/form>/i', '<!-- nested form end stripped -->', $content) ?? '';

        // Also remove any form attribute on other elements (standard WP cleanup)
        $content = preg_replace('/\s+form\s*=\s*["\'][^"\']*["\']/i', '', $content) ?? '';

        return $content;
    }
    /**
     * Render a SPECIFIC settings section cleanly (removes nested forms)
     * 
     * @param string $section_id The ID of the section to render
     * @param string $page The page ID it belongs to (defaults to main settings)
     */
    public static function render_section_cleanly(string $section_id, string $page = \MHMRentiva\Admin\Settings\Core\SettingsCore::PAGE): void
    {
        global $wp_settings_sections, $wp_settings_fields;

        if (!isset($wp_settings_sections[$page]) || !isset($wp_settings_sections[$page][$section_id])) {
            return;
        }

        $section = $wp_settings_sections[$page][$section_id];

        ob_start();

        if ($section['title']) {
            echo "<h2>{$section['title']}</h2>\n";
        }

        if ($section['callback']) {
            call_user_func($section['callback'], $section);
        }

        if (isset($wp_settings_fields) && isset($wp_settings_fields[$page]) && isset($wp_settings_fields[$page][$section_id])) {
            echo '<table class="form-table" role="presentation">';
            do_settings_fields($page, $section_id);
            echo '</table>';
        }

        $content = ob_get_clean();

        echo self::remove_nested_forms((string) $content);
    }
}
