<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsHelper
{
    /**
     * ✅ BaseSettingsGroup functions integrated
     */
    /**
     * Text field helper for settings
     */
    public static function text_field(string $group, string $name, string $label, string $section = ''): void
    {
        add_settings_field($name, $label, function () use ($name) {
            // ✅ Use central SettingsCore::get
            $val = esc_attr(SettingsCore::get($name, ''));
            echo '<input type="text" name="mhm_rentiva_settings[' . esc_attr($name) . ']" class="regular-text" value="' . $val . '"/>';
        }, $group, $section);
    }

    /**
     * Checkbox field helper for settings
     */
    public static function checkbox_field(string $group, string $name, string $label, string $description = '', string $section = ''): void
    {
        add_settings_field($name, $label, function () use ($name, $description) {
            // ✅ Use central SettingsCore::get
            $val = (string) SettingsCore::get($name, '0');
            
            // ✅ Always use settings array format (no more standalone options)
            // This ensures consistency across all settings
            echo '<label><input type="checkbox" name="mhm_rentiva_settings[' . esc_attr($name) . ']" value="1" ' . checked($val, '1', false) . '> ' . esc_html($description) . '</label>';
        }, $group, $section);
    }

    /**
     * Select field helper for settings
     */
    public static function select_field(string $group, string $name, string $label, array $options, string $description = '', string $section = ''): void
    {
        add_settings_field($name, $label, function () use ($name, $options, $description) {
            // ✅ Use central SettingsCore::get
            $val = (string) SettingsCore::get($name, '');
            echo '<select name="mhm_rentiva_settings[' . esc_attr($name) . ']">';
            foreach ($options as $value => $text) {
                echo '<option value="' . esc_attr($value) . '"' . selected($val, $value, false) . '>' . esc_html($text) . '</option>';
            }
            echo '</select>';
            if ($description) {
                echo '<p class="description">' . esc_html($description) . '</p>';
            }
        }, $group, $section);
    }

    /**
     * Number field helper for settings
     */
    public static function number_field(string $group, string $name, string $label, int $min = 0, int $max = 999, string $description = '', string $section = ''): void
    {
        add_settings_field($name, $label, function () use ($name, $min, $max, $description) {
            // ✅ Use central SettingsCore::get
            $val = (int) SettingsCore::get($name, $min);
            echo '<input type="number" class="small-text" min="' . esc_attr($min) . '" max="' . esc_attr($max) . '" name="mhm_rentiva_settings[' . esc_attr($name) . ']" value="' . (int) $val . '"/>';
            if ($description) {
                echo '<p class="description">' . esc_html($description) . '</p>';
            }
        }, $group, $section);
    }

    /**
     * Textarea field helper for settings
     */
    public static function textarea_field(string $group, string $name, string $label, int $rows = 5, string $description = '', string $section = ''): void
    {
        add_settings_field($name, $label, function () use ($name, $rows, $description) {
            // ✅ Use central SettingsCore::get
            $val = esc_textarea((string) SettingsCore::get($name, ''));
            echo '<textarea name="mhm_rentiva_settings[' . esc_attr($name) . ']" class="large-text code" rows="' . esc_attr($rows) . '">' . $val . '</textarea>';
            if ($description) {
                echo '<p class="description">' . esc_html($description) . '</p>';
            }
        }, $group, $section);
    }

    /**
     * Email field helper for settings
     */
    public static function email_field(string $group, string $name, string $label, string $description = '', string $section = ''): void
    {
        add_settings_field($name, $label, function () use ($name, $description) {
            // ✅ Use central SettingsCore::get
            $val = esc_attr((string) SettingsCore::get($name, ''));
            echo '<input type="email" class="regular-text" name="mhm_rentiva_settings[' . esc_attr($name) . ']" value="' . $val . '"/>';
            if ($description) {
                echo '<p class="description">' . esc_html($description) . '</p>';
            }
        }, $group, $section);
    }

    /**
     * URL field helper for settings
     */
    public static function url_field(string $group, string $name, string $label, string $description = '', string $section = ''): void
    {
        add_settings_field($name, $label, function () use ($name, $description) {
            $settings = get_option('mhm_rentiva_settings', []);
            $val = esc_attr((string) ($settings[$name] ?? ''));
            echo '<input type="url" class="regular-text" name="mhm_rentiva_settings[' . esc_attr($name) . ']" value="' . $val . '"/>';
            if ($description) {
                echo '<p class="description">' . esc_html($description) . '</p>';
            }
        }, $group, $section);
    }

    /**
     * Password field helper for settings
     */
    public static function password_field(string $group, string $name, string $label, string $description = '', string $section = ''): void
    {
        add_settings_field($name, $label, function () use ($name, $description) {
            $settings = get_option('mhm_rentiva_settings', []);
            $val = esc_attr((string) ($settings[$name] ?? ''));
            echo '<input type="password" class="regular-text" name="mhm_rentiva_settings[' . esc_attr($name) . ']" value="' . $val . '"/>';
            if ($description) {
                echo '<p class="description">' . esc_html($description) . '</p>';
            }
        }, $group, $section);
    }

    /**
     * Readonly field helper for settings
     */
    public static function readonly_field(string $group, string $name, string $label, string $value, string $description = '', string $section = ''): void
    {
        add_settings_field($name, $label, function () use ($name, $value, $description) {
            echo '<input type="text" class="regular-text" readonly value="' . esc_attr($value) . '" onclick="this.select();" />';
            if ($description) {
                echo '<p class="description">' . esc_html($description) . '</p>';
            }
        }, $group, $section);
    }

    /**
     * Button field helper for settings
     */
    public static function button_field(string $group, string $name, string $label, string $button_text, string $url, string $description = '', string $section = ''): void
    {
        add_settings_field($name, $label, function () use ($name, $button_text, $url, $description) {
            echo '<a href="' . esc_url($url) . '" class="button button-secondary">' . esc_html($button_text) . '</a>';
            if ($description) {
                echo '<p class="description">' . esc_html($description) . '</p>';
            }
        }, $group, $section);
    }
    
    /**
     * Safe sanitize text field that handles null values
     */
    public static function sanitize_text_field_safe($value)
    {
        if ($value === null) {
            return '';
        }
        if ($value === '') {
            return '';
        }
        // Convert to string if not already
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }
        return sanitize_text_field((string) $value);
    }

    /**
     * Safe sanitize email that handles null values
     */
    public static function sanitize_email_safe($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }
        
        
        return sanitize_email((string) $value);
    }

    /**
     * Safe sanitize textarea field that handles null values
     */
    public static function sanitize_textarea_field_safe($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }
        return sanitize_textarea_field((string) $value);
    }

    /**
     * ✅ Functions integrated from BaseSettingsGroup
     */
    
    /**
     * Register a setting with common options
     */
    public static function register_setting(string $group, string $name, string $sanitize_callback = 'sanitize_text_field'): void
    {
        // Use safe sanitize functions by default
        if ($sanitize_callback === 'sanitize_text_field') {
            $sanitize_callback = [self::class, 'sanitize_text_field_safe'];
        } elseif ($sanitize_callback === 'sanitize_email') {
            $sanitize_callback = [self::class, 'sanitize_email_safe'];
        } elseif ($sanitize_callback === 'sanitize_textarea_field') {
            $sanitize_callback = [self::class, 'sanitize_textarea_field_safe'];
        }
        
        register_setting($group, $name, [
            'type' => 'string',
            'sanitize_callback' => $sanitize_callback
        ]);
    }

    /**
     * Register a setting with custom options
     */
    public static function register_setting_custom(string $group, string $name, array $options = []): void
    {
        $default_options = [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitize_text_field_safe']
        ];
        
        // Convert string sanitize callbacks to safe versions
        if (isset($options['sanitize_callback'])) {
            if ($options['sanitize_callback'] === 'sanitize_text_field') {
                $options['sanitize_callback'] = [self::class, 'sanitize_text_field_safe'];
            } elseif ($options['sanitize_callback'] === 'sanitize_email') {
                $options['sanitize_callback'] = [self::class, 'sanitize_email_safe'];
            } elseif ($options['sanitize_callback'] === 'sanitize_textarea_field') {
                $options['sanitize_callback'] = [self::class, 'sanitize_textarea_field_safe'];
            }
        }
        
        register_setting($group, $name, array_merge($default_options, $options));
    }

    /**
     * Add a settings field with common pattern
     */
    public static function add_field(string $name, string $label, callable $callback, string $group, string $section): void
    {
        add_settings_field($name, $label, $callback, $group, $section);
    }

    /**
     * Add a settings section with common pattern
     */
    public static function add_section(string $id, string $title, callable $callback, string $group): void
    {
        add_settings_section($id, $title, $callback, $group);
    }

    /**
     * Render radio buttons for enabled/disabled options
     */
    public static function render_radio_enabled(string $name, string $current_value, string $description = ''): void
    {
        echo '<label><input type="radio" name="' . esc_attr($name) . '" value="1" ' . checked($current_value, '1', false) . '> ' . esc_html__('Enabled', 'mhm-rentiva') . '</label><br>';
        echo '<label><input type="radio" name="' . esc_attr($name) . '" value="0" ' . checked($current_value, '0', false) . '> ' . esc_html__('Disabled', 'mhm-rentiva') . '</label>';
        
        if ($description) {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
    }

    /**
     * Get option value with fallback - Use SettingsCore::get
     */
    public static function get_option(string $name, $default = ''): string
    {
        return (string) SettingsCore::get($name, $default);
    }

    /**
     * Sanitize callback for enabled/disabled fields
     */
    public static function sanitize_enabled($value): string
    {
        return $value === '1' ? '1' : '0';
    }

    /**
     * Sanitize callback for checkbox fields.
     *
     * Returns '1' if the value is '1', otherwise '0'.
     * This prevents errors when a checkbox is unchecked and its value is null.
     *
     * @param mixed $value The input value.
     * @return string '1' or '0'.
     */
    public static function sanitize_checkbox($value): string
    {
        return ($value === '1' || $value === 1) ? '1' : '0';
    }

    /**
     * Sanitize callback for integer fields with min/max
     */
    public static function sanitize_integer($value, int $min = 0, int $max = 999): int
    {
        $int_value = (int) $value;
        if ($int_value < $min) $int_value = $min;
        if ($int_value > $max) $int_value = $max;
        return $int_value;
    }

    /**
     * Sanitize callback for select fields with allowed values
     */
    public static function sanitize_select($value, array $allowed_values, $default = ''): string
    {
        return in_array($value, $allowed_values, true) ? $value : $default;
    }
}
