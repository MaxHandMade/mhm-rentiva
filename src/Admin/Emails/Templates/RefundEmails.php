<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Templates;

use MHMRentiva\Admin\Emails\Core\EmailFormRenderer;
use MHMRentiva\Admin\Settings\Groups\EmailSettings;

if (!defined('ABSPATH')) {
    exit;
}

final class RefundEmails
{
    public static function register(): void
    {
        // RefundEmails class only uses render method, no register needed
    }

    public static function render(): void
    {
        echo '<h2>' . esc_html__('Refund Emails', 'mhm-rentiva') . '</h2>';
        echo '<p class="description">' . esc_html__('Manage email templates for refund operations.', 'mhm-rentiva') . '</p>';
        echo '<p class="description">' . esc_html__('Available placeholders: {booking_id}, {vehicle_title}, {amount}, {currency}, {status}, {reason}, {contact_name}, {contact_email}, {site_name}', 'mhm-rentiva') . '</p>';

        $get_val = function ($key, $default_callback, $content_check = '{') {
            $val = get_option($key, '');
            if (!is_string($val) || trim($val) === '') {
                return $default_callback();
            }
            if ($content_check !== null && strpos($val, $content_check) === false) {
                return $default_callback();
            }
            return $val;
        };

        // Customer Refund Email
        echo '<h3>' . esc_html__('Customer Refund Email', 'mhm-rentiva') . '</h3>';

        $refund_customer_enabled = get_option('mhm_rentiva_refund_customer_enabled', '1');
        $is_checked = ($refund_customer_enabled === '1' || $refund_customer_enabled === 1 || $refund_customer_enabled === true);
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Enabled', 'mhm-rentiva') . '</th>';
        echo '<td><label><input type="checkbox" name="mhm_rentiva_refund_customer_enabled" value="1" ' . ($is_checked ? 'checked="checked"' : '') . '> ' . esc_html__('Enabled', 'mhm-rentiva') . '</label></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="mhm_rentiva_refund_customer_subject">' . esc_html__('Subject', 'mhm-rentiva') . '</label></th>';
        echo '<td><input type="text" id="mhm_rentiva_refund_customer_subject" name="mhm_rentiva_refund_customer_subject" value="' . esc_attr($get_val('mhm_rentiva_refund_customer_subject', fn() => __('Refund Processed for Booking #{booking_id}', 'mhm-rentiva'), null)) . '" class="regular-text" /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="mhm_rentiva_refund_customer_body">' . esc_html__('Content (HTML)', 'mhm-rentiva') . '</label></th>';
        echo '<td><textarea id="mhm_rentiva_refund_customer_body" name="mhm_rentiva_refund_customer_body" class="large-text code" rows="10">' . esc_textarea($get_val('mhm_rentiva_refund_customer_body', [EmailSettings::class, 'get_default_refund_customer_body'], 'amount')) . '</textarea></td>';
        echo '</tr>';
        echo '</table>';

        // Admin Refund Email
        echo '<h3>' . esc_html__('Admin Refund Email', 'mhm-rentiva') . '</h3>';

        $refund_admin_enabled = get_option('mhm_rentiva_refund_admin_enabled', '1');
        $is_checked = ($refund_admin_enabled === '1' || $refund_admin_enabled === 1 || $refund_admin_enabled === true);
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Enabled', 'mhm-rentiva') . '</th>';
        echo '<td><label><input type="checkbox" name="mhm_rentiva_refund_admin_enabled" value="1" ' . ($is_checked ? 'checked="checked"' : '') . '> ' . esc_html__('Enabled', 'mhm-rentiva') . '</label></td>';
        echo '</tr>';

        $refund_admin_to = get_option('mhm_rentiva_refund_admin_to', get_option('admin_email'));
        echo '<tr>';
        echo '<th scope="row"><label for="mhm_rentiva_refund_admin_to">' . esc_html__('Admin Email', 'mhm-rentiva') . '</label></th>';
        echo '<td><input type="email" id="mhm_rentiva_refund_admin_to" name="mhm_rentiva_refund_admin_to" value="' . esc_attr((string) $refund_admin_to) . '" class="regular-text" /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="mhm_rentiva_refund_admin_subject">' . esc_html__('Subject', 'mhm-rentiva') . '</label></th>';
        echo '<td><input type="text" id="mhm_rentiva_refund_admin_subject" name="mhm_rentiva_refund_admin_subject" value="' . esc_attr($get_val('mhm_rentiva_refund_admin_subject', fn() => __('Refund Alert: Booking #{booking_id}', 'mhm-rentiva'), null)) . '" class="regular-text" /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="mhm_rentiva_refund_admin_body">' . esc_html__('Content (HTML)', 'mhm-rentiva') . '</label></th>';
        echo '<td><textarea id="mhm_rentiva_refund_admin_body" name="mhm_rentiva_refund_admin_body" class="large-text code" rows="10">' . esc_textarea($get_val('mhm_rentiva_refund_admin_body', [EmailSettings::class, 'get_default_refund_admin_body'], 'amount')) . '</textarea></td>';
        echo '</tr>';
        echo '</table>';
    }
}
