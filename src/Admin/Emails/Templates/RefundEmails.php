<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Templates;

use MHMRentiva\Admin\Emails\Core\EmailFormRenderer;

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

        // Customer Refund Email
        echo '<h3>' . esc_html__('Customer Refund Email', 'mhm-rentiva') . '</h3>';
        
        $refund_customer_enabled = get_option('mhm_rentiva_refund_customer_enabled', '1');
        $is_checked = ($refund_customer_enabled === '1' || $refund_customer_enabled === 1 || $refund_customer_enabled === true);
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Enabled', 'mhm-rentiva') . '</th>';
        echo '<td><label><input type="checkbox" name="mhm_rentiva_refund_customer_enabled" value="1" ' . ($is_checked ? 'checked="checked"' : '') . '> ' . esc_html__('Enabled', 'mhm-rentiva') . '</label></td>';
        echo '</tr>';
        
        $refund_customer_subject = get_option('mhm_rentiva_refund_customer_subject', __('Your refund for booking #{booking_id}', 'mhm-rentiva'));
        echo '<tr>';
        echo '<th scope="row"><label for="mhm_rentiva_refund_customer_subject">' . esc_html__('Subject', 'mhm-rentiva') . '</label></th>';
        echo '<td><input type="text" id="mhm_rentiva_refund_customer_subject" name="mhm_rentiva_refund_customer_subject" value="' . esc_attr($refund_customer_subject) . '" class="regular-text" /></td>';
        echo '</tr>';
        
        $refund_customer_body = get_option('mhm_rentiva_refund_customer_body', __('<p>Dear {contact_name},</p><p>Your refund request for booking #{booking_id} has been processed.</p><p><strong>Refund Details:</strong><br>Refund Amount: {amount} {currency}<br>Status: {status}<br>Reason: {reason}<br>Vehicle: {vehicle_title}</p><p>The refund will be reflected in your account within 3-5 business days.</p><p>{site_name}</p>', 'mhm-rentiva'));
        echo '<tr>';
        echo '<th scope="row"><label for="mhm_rentiva_refund_customer_body">' . esc_html__('Content (HTML)', 'mhm-rentiva') . '</label></th>';
        echo '<td><textarea id="mhm_rentiva_refund_customer_body" name="mhm_rentiva_refund_customer_body" class="large-text code" rows="8">' . esc_textarea($refund_customer_body) . '</textarea></td>';
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
        echo '<td><input type="email" id="mhm_rentiva_refund_admin_to" name="mhm_rentiva_refund_admin_to" value="' . esc_attr($refund_admin_to) . '" class="regular-text" /></td>';
        echo '</tr>';
        
        $refund_admin_subject = get_option('mhm_rentiva_refund_admin_subject', __('Refund processed for booking #{booking_id}', 'mhm-rentiva'));
        echo '<tr>';
        echo '<th scope="row"><label for="mhm_rentiva_refund_admin_subject">' . esc_html__('Subject', 'mhm-rentiva') . '</label></th>';
        echo '<td><input type="text" id="mhm_rentiva_refund_admin_subject" name="mhm_rentiva_refund_admin_subject" value="' . esc_attr($refund_admin_subject) . '" class="regular-text" /></td>';
        echo '</tr>';
        
        $refund_admin_body = get_option('mhm_rentiva_refund_admin_body', __('<p>Refund Notification</p><p>Refund processed for booking #{booking_id}.</p><p><strong>Refund Details:</strong><br>Booking: {vehicle_title}<br>Refund Amount: {amount} {currency}<br>Status: {status}<br>Reason: {reason}<br>Customer: {contact_name}<br>Email: {contact_email}</p><p>Refund completed.</p>', 'mhm-rentiva'));
        echo '<tr>';
        echo '<th scope="row"><label for="mhm_rentiva_refund_admin_body">' . esc_html__('Content (HTML)', 'mhm-rentiva') . '</label></th>';
        echo '<td><textarea id="mhm_rentiva_refund_admin_body" name="mhm_rentiva_refund_admin_body" class="large-text code" rows="8">' . esc_textarea($refund_admin_body) . '</textarea></td>';
        echo '</tr>';
        echo '</table>';
    }
}
