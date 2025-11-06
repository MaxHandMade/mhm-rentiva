<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Templates;

use MHMRentiva\Admin\Emails\Core\EmailFormRenderer;

if (!defined('ABSPATH')) {
    exit;
}

final class OfflinePayment
{
    public static function register(): void
    {
        // OfflinePayment class only uses render method, no register needed
    }

    public static function render(): void
    {
        echo '<h2>' . esc_html__('Offline Payment Emails', 'mhm-rentiva') . '</h2>';
        echo '<p class="description">' . esc_html__('Configure email notifications for bank transfer (Wire/EFT) process.', 'mhm-rentiva') . '</p>';
        echo '<p class="description">' . esc_html__('Available placeholders: {booking_id}, {vehicle_title}, {amount}, {currency}, {status}, {site_name}, {receipt_url}, {customer_name}', 'mhm-rentiva') . '</p>';

        // Notify Admin When Receipt Uploaded
        echo '<h3>' . esc_html__('Notify Admin When Receipt Uploaded', 'mhm-rentiva') . '</h3>';
        
        $admin_enabled = get_option('mhm_rentiva_offline_email_admin_enabled', '1');
        $is_checked = ($admin_enabled === '1' || $admin_enabled === 1 || $admin_enabled === true);
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Enabled', 'mhm-rentiva') . '</th>';
        echo '<td><label><input type="checkbox" name="mhm_rentiva_offline_email_admin_enabled" value="1" ' . ($is_checked ? 'checked="checked"' : '') . '> ' . esc_html__('Enabled', 'mhm-rentiva') . '</label></td>';
        echo '</tr>';
        
        $admin_to = get_option('mhm_rentiva_offline_email_admin_to', get_option('admin_email'));
        echo '<tr>';
        echo '<th scope="row"><label for="mhm_rentiva_offline_email_admin_to">' . esc_html__('Admin Email', 'mhm-rentiva') . '</label></th>';
        echo '<td><input type="email" id="mhm_rentiva_offline_email_admin_to" name="mhm_rentiva_offline_email_admin_to" value="' . esc_attr($admin_to) . '" class="regular-text" /></td>';
        echo '</tr>';
        
        $admin_subject = get_option('mhm_rentiva_offline_email_admin_subject', __('New receipt uploaded for booking #{booking_id}', 'mhm-rentiva'));
        echo '<tr>';
        echo '<th scope="row"><label for="mhm_rentiva_offline_email_admin_subject">' . esc_html__('Admin Subject (Receipt Uploaded)', 'mhm-rentiva') . '</label></th>';
        echo '<td><input type="text" id="mhm_rentiva_offline_email_admin_subject" name="mhm_rentiva_offline_email_admin_subject" value="' . esc_attr($admin_subject) . '" class="regular-text" /></td>';
        echo '</tr>';
        
        $admin_body = get_option('mhm_rentiva_offline_email_admin_body', __('<p>New receipt uploaded for booking #{booking_id}.</p><p>Amount: {amount} {currency}<br>Status: {status}<br>Vehicle: {vehicle_title}</p><p>Receipt: <a href="{receipt_url}" target="_blank">Open</a></p>', 'mhm-rentiva'));
        echo '<tr>';
        echo '<th scope="row"><label for="mhm_rentiva_offline_email_admin_body">' . esc_html__('Admin Content (HTML)', 'mhm-rentiva') . '</label></th>';
        echo '<td><textarea id="mhm_rentiva_offline_email_admin_body" name="mhm_rentiva_offline_email_admin_body" class="large-text code" rows="6">' . esc_textarea($admin_body) . '</textarea></td>';
        echo '</tr>';
        echo '</table>';

        // Notify Customer on Approval/Rejection
        echo '<h3>' . esc_html__('Notify Customer on Approval/Rejection', 'mhm-rentiva') . '</h3>';
        
        $customer_enabled = get_option('mhm_rentiva_offline_email_customer_enabled', '1');
        $is_checked = ($customer_enabled === '1' || $customer_enabled === 1 || $customer_enabled === true);
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Enabled', 'mhm-rentiva') . '</th>';
        echo '<td><label><input type="checkbox" name="mhm_rentiva_offline_email_customer_enabled" value="1" ' . ($is_checked ? 'checked="checked"' : '') . '> ' . esc_html__('Enabled', 'mhm-rentiva') . '</label></td>';
        echo '</tr>';
        
        $customer_subject_approved = get_option('mhm_rentiva_offline_email_customer_subject_approved', __('Your payment for booking #{booking_id} has been approved', 'mhm-rentiva'));
        echo '<tr>';
        echo '<th scope="row"><label for="mhm_rentiva_offline_email_customer_subject_approved">' . esc_html__('Customer Subject (Approved)', 'mhm-rentiva') . '</label></th>';
        echo '<td><input type="text" id="mhm_rentiva_offline_email_customer_subject_approved" name="mhm_rentiva_offline_email_customer_subject_approved" value="' . esc_attr($customer_subject_approved) . '" class="regular-text" /></td>';
        echo '</tr>';
        
        $customer_body_approved = get_option('mhm_rentiva_offline_email_customer_body_approved', __('<p>Dear {customer_name},</p><p>Your bank transfer for booking #{booking_id} has been approved.</p><p>We look forward to seeing you.</p><p>{site_name}</p>', 'mhm-rentiva'));
        echo '<tr>';
        echo '<th scope="row"><label for="mhm_rentiva_offline_email_customer_body_approved">' . esc_html__('Customer Content (Approved, HTML)', 'mhm-rentiva') . '</label></th>';
        echo '<td><textarea id="mhm_rentiva_offline_email_customer_body_approved" name="mhm_rentiva_offline_email_customer_body_approved" class="large-text code" rows="6">' . esc_textarea($customer_body_approved) . '</textarea></td>';
        echo '</tr>';
        
        $customer_subject_rejected = get_option('mhm_rentiva_offline_email_customer_subject_rejected', __('Your payment for booking #{booking_id} could not be verified', 'mhm-rentiva'));
        echo '<tr>';
        echo '<th scope="row"><label for="mhm_rentiva_offline_email_customer_subject_rejected">' . esc_html__('Customer Subject (Rejected)', 'mhm-rentiva') . '</label></th>';
        echo '<td><input type="text" id="mhm_rentiva_offline_email_customer_subject_rejected" name="mhm_rentiva_offline_email_customer_subject_rejected" value="' . esc_attr($customer_subject_rejected) . '" class="regular-text" /></td>';
        echo '</tr>';
        
        $customer_body_rejected = get_option('mhm_rentiva_offline_email_customer_body_rejected', __('<p>Dear {customer_name},</p><p>We could not verify your bank transfer for booking #{booking_id}. Please reply to this email with your receipt or contact us.</p><p>{site_name}</p>', 'mhm-rentiva'));
        echo '<tr>';
        echo '<th scope="row"><label for="mhm_rentiva_offline_email_customer_body_rejected">' . esc_html__('Customer Content (Rejected, HTML)', 'mhm-rentiva') . '</label></th>';
        echo '<td><textarea id="mhm_rentiva_offline_email_customer_body_rejected" name="mhm_rentiva_offline_email_customer_body_rejected" class="large-text code" rows="6">' . esc_textarea($customer_body_rejected) . '</textarea></td>';
        echo '</tr>';
        echo '</table>';
    }
}
