<?php

/**
 * WordPress function stubs for standalone testing.
 *
 * @package MHMRentiva\Tests
 */

// WordPress constants
if (!defined('ABSPATH')) {
    define('ABSPATH', '/var/www/html/');
}

// WordPress functions
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str)
    {
        if (is_object($str) || is_array($str)) {
            return '';
        }
        $str = (string) $str;
        $str = trim(strip_tags($str));
        return $str;
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key)
    {
        $key = strtolower($key);
        $key = preg_replace('/[^a-z0-9_\-]/', '', $key);
        return $key;
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email)
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '';
        }
        return $email;
    }
}

if (!function_exists('absint')) {
    function absint($maybeint)
    {
        return abs((int) $maybeint);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default')
    {
        return $text;
    }
}

if (!function_exists('add_action')) {
    function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {}
}

if (!function_exists('register_setting')) {
    function register_setting($option_group, $option_name, $args = []) {}
}

if (!function_exists('add_settings_section')) {
    function add_settings_section($id, $title, $callback, $page, $args = []) {}
}

if (!function_exists('add_settings_field')) {
    function add_settings_field($id, $title, $callback, $page, $section = 'default', $args = []) {}
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false)
    {
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null)
    {
        return true;
    }
}
