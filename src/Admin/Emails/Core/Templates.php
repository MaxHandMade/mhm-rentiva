<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class Templates
{
    private static array $registry = [
        // Refund notifications
        'refund_customer' => [
            'subject' => 'Refund for Booking #{{booking.id}}',
            'file'    => 'refund-customer',
        ],
        'refund_admin' => [
            'subject' => 'Refund Processed for Booking #{{booking.id}}',
            'file'    => 'refund-admin',
        ],
        
        // Booking notifications
        'booking_created_customer' => [
            'subject' => 'Booking #{{booking.id}} Confirmed - {{site.name}}',
            'file'    => 'booking-created-customer',
        ],
        'booking_created_admin' => [
            'subject' => 'New Booking Request #{{booking.id}} - {{site.name}}',
            'file'    => 'booking-created-admin',
        ],
        'booking_status_changed_customer' => [
            'subject' => 'Booking #{{booking.id}} Status Updated - {{site.name}}',
            'file'    => 'booking-status-changed-customer',
        ],
        'booking_status_changed_admin' => [
            'subject' => 'Booking #{{booking.id}} Status Updated - {{site.name}}',
            'file'    => 'booking-status-changed-admin',
        ],
        'booking_reminder_customer' => [
            'subject' => 'Reminder: Your Booking #{{booking.id}} Starts Soon - {{site.name}}',
            'file'    => 'booking-reminder-customer',
        ],
        'welcome_customer' => [
            'subject' => 'Welcome to {{site.name}}',
            'file'    => 'welcome-customer',
        ],
        
        // Offline payment notifications
        'offline_receipt_uploaded_admin' => [
            'subject' => 'New Receipt Uploaded for Booking #{{booking.id}} - {{site.name}}',
            'file'    => 'offline-receipt-uploaded-admin',
        ],
        'offline_verified_approved_customer' => [
            'subject' => 'Payment Confirmed for Booking #{{booking.id}} - {{site.name}}',
            'file'    => 'offline-verified-approved-customer',
        ],
        'offline_verified_rejected_customer' => [
            'subject' => 'Payment Could Not Be Verified for Booking #{{booking.id}} - {{site.name}}',
            'file'    => 'offline-verified-rejected-customer',
        ],
        
        // Message notifications
        'message_received_admin' => [
            'subject' => 'New Message - {{message.subject}} - {{site.name}}',
            'file'    => 'message-received-admin',
        ],
        'message_replied_customer' => [
            'subject' => 'Reply to Your Message - {{message.subject}} - {{site.name}}',
            'file'    => 'message-replied-customer',
        ],
    ];

    /**
     * Map template keys to options base for Subject/Body overrides
     */
    private static array $overrideMap = [
        // Booking
        'booking_created_customer' => 'booking_created',
        'booking_created_admin' => 'booking_admin',
        'booking_status_changed_customer' => 'booking_status',
        'booking_status_changed_admin' => 'booking_status',
        'booking_reminder_customer' => 'booking_reminder',
        'welcome_customer' => 'welcome_email',
        // Offline payment
        'offline_receipt_uploaded_admin' => 'offline_email_admin',
        'offline_verified_approved_customer' => 'offline_email_customer_subject_approved',
        'offline_verified_rejected_customer' => 'offline_email_customer_subject_rejected',
        // Refunds
        'refund_customer' => 'refund_customer',
        'refund_admin' => 'refund_admin',
        // Messages (no overrides yet)
    ];

    public static function register(): void
    {
        // No hooks yet; exists for consistency and future extensions
    }

    public static function registry(): array
    {
        return apply_filters('mhm_rentiva_email_registry', self::$registry);
    }

    public static function locate_template(string $slug): ?string
    {
        // Get template path from settings
        $template_path = \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_template_path();
        $rel = $template_path . $slug . '.html.php';
        
        $themePath = trailingslashit(get_stylesheet_directory()) . $rel;
        if (file_exists($themePath)) {
            return $themePath;
        }
        $parentPath = trailingslashit(get_template_directory()) . $rel;
        if (file_exists($parentPath)) {
            return $parentPath;
        }
        $plugin = MHM_RENTIVA_PLUGIN_PATH . 'templates/emails/' . $slug . '.html.php';
        if (file_exists($plugin)) {
            return $plugin;
        }
        return null;
    }

    public static function compile_subject(string $key, array $context): string
    {
        // Subject override from settings (if defined and non-empty)
        $subject = self::getSubjectOverride($key, $context);
        if ($subject !== null) {
            return $subject;
        }

        $reg = self::registry();
        $tpl = $reg[$key]['subject'] ?? ('Notification: ' . $key);
        // Apply i18n
        $tpl = __($tpl, 'mhm-rentiva');
        $sub = self::replace_placeholders($tpl, $context);
        $sub = apply_filters('mhm_rentiva_email_subject', $sub, $key, $context);
        $sub = apply_filters('mhm_rentiva_email_subject_' . $key, $sub, $context);
        return $sub;
    }

    public static function render_body(string $key, array $context): string
    {
        // Body override from settings if available (HTML)
        $override = self::getBodyOverride($key, $context);
        if ($override !== null && $override !== '') {
            $html = (string) $override;
            // If override is only a fragment (no full HTML), wrap with standard layout
            if (stripos($html, '<html') === false && stripos($html, '<body') === false) {
                $subject = self::compile_subject($key, $context);
                $html = self::wrapWithLayout($context, $subject, $html);
            }
            $html = apply_filters('mhm_rentiva_email_body', $html, $key, $context);
            $html = apply_filters('mhm_rentiva_email_body_' . $key, $html, $context);
            return $html;
        }

        $reg = self::registry();
        $slug = $reg[$key]['file'] ?? $key;
        $path = self::locate_template($slug);
        $ctx  = apply_filters('mhm_rentiva_email_context', $context, $key);
        $ctx  = apply_filters('mhm_rentiva_email_context_' . $key, $ctx);
        if ($path) {
            ob_start();
            $data = $ctx;
            include $path;
            $html = (string) ob_get_clean();
        } else {
            $html = '<html><body><pre>' . esc_html(wp_json_encode($ctx, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre></body></html>';
        }
        $html = apply_filters('mhm_rentiva_email_body', $html, $key, $ctx);
        $html = apply_filters('mhm_rentiva_email_body_' . $key, $html, $ctx);
        return $html;
    }

    /**
     * Wrap inner HTML with the standard modern email layout
     */
    private static function wrapWithLayout(array $context, string $subject, string $innerHtml): string
    {
        $siteName = (string) ($context['site']['name'] ?? get_bloginfo('name'));
        $title = esc_html($subject);
        $brand = esc_html($siteName);
        // Basic sanitized inner HTML (allow common tags)
        $allowed = [
            'a' => ['href' => [], 'title' => [], 'target' => [], 'rel' => []],
            'b' => [], 'strong' => [], 'em' => [], 'i' => [], 'u' => [],
            'p' => ['style' => []], 'br' => [], 'span' => ['style' => []],
            'ul' => [], 'ol' => [], 'li' => [], 'h1' => [], 'h2' => [], 'h3' => [],
            'table' => ['border' => [], 'cellpadding' => [], 'cellspacing' => [], 'width' => [], 'style' => []],
            'tr' => [], 'td' => ['style' => []], 'th' => ['style' => []],
            'img' => ['src' => [], 'alt' => [], 'width' => [], 'height' => [], 'style' => []],
        ];
        $content = wp_kses($innerHtml, $allowed);

        ob_start();
        ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; }
        .content { padding: 30px; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
    </style>
    </head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo $brand; ?></h1>
        </div>
        <div class="content">
            <?php echo $content; // already kses-filtered ?>
        </div>
        <div class="footer">
            <p><strong><?php echo $brand; ?></strong></p>
        </div>
    </div>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Try to pull a subject override from settings for the given key
     */
    private static function getSubjectOverride(string $key, array $context): ?string
    {
        $base = self::$overrideMap[$key] ?? '';
        if ($base === '') {
            return null;
        }
        // Special cases where option keys differ
        switch ($key) {
            case 'offline_verified_approved_customer':
                $opt = 'mhm_rentiva_offline_email_customer_subject_approved';
                break;
            case 'offline_verified_rejected_customer':
                $opt = 'mhm_rentiva_offline_email_customer_subject_rejected';
                break;
            case 'offline_receipt_uploaded_admin':
                $opt = 'mhm_rentiva_offline_email_admin_subject';
                break;
            case 'refund_customer':
                $opt = 'mhm_rentiva_refund_customer_subject';
                break;
            case 'refund_admin':
                $opt = 'mhm_rentiva_refund_admin_subject';
                break;
            case 'booking_created_admin':
                $opt = 'mhm_rentiva_booking_admin_subject';
                break;
            case 'booking_created_customer':
                $opt = 'mhm_rentiva_booking_created_subject';
                break;
            case 'booking_status_changed_customer':
            case 'booking_status_changed_admin':
                $opt = 'mhm_rentiva_booking_status_subject';
                break;
            case 'booking_reminder_customer':
                $opt = 'mhm_rentiva_booking_reminder_subject';
                break;
            case 'welcome_customer':
                $opt = 'mhm_rentiva_welcome_email_subject';
                break;
            default:
                $opt = '';
        }
        if ($opt === '') return null;
        $raw = get_option($opt, '');
        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw === '') return null;
        // Replace placeholders using same engine
        $tpl = __($raw, 'mhm-rentiva');
        return self::replace_placeholders($tpl, $context);
    }

    /**
     * Try to pull a body override (HTML) from settings for the given key
     */
    private static function getBodyOverride(string $key, array $context): ?string
    {
        switch ($key) {
            case 'offline_verified_approved_customer':
                $opt = 'mhm_rentiva_offline_email_customer_body_approved';
                break;
            case 'offline_verified_rejected_customer':
                $opt = 'mhm_rentiva_offline_email_customer_body_rejected';
                break;
            case 'offline_receipt_uploaded_admin':
                $opt = 'mhm_rentiva_offline_email_admin_body';
                break;
            case 'refund_customer':
                $opt = 'mhm_rentiva_refund_customer_body';
                break;
            case 'refund_admin':
                $opt = 'mhm_rentiva_refund_admin_body';
                break;
            case 'booking_created_admin':
                $opt = 'mhm_rentiva_booking_admin_body';
                break;
            case 'booking_created_customer':
                $opt = 'mhm_rentiva_booking_created_body';
                break;
            case 'booking_status_changed_customer':
            case 'booking_status_changed_admin':
                $opt = 'mhm_rentiva_booking_status_body';
                break;
            case 'booking_reminder_customer':
                $opt = 'mhm_rentiva_booking_reminder_body';
                break;
            case 'welcome_customer':
                $opt = 'mhm_rentiva_welcome_email_body';
                break;
            default:
                $opt = '';
        }
        if ($opt === '') return null;
        $raw = get_option($opt, '');
        if (!is_string($raw)) return null;
        $raw = trim($raw);
        if ($raw === '') return null;
        // Perform simple placeholder replacement for common tokens
        $html = self::replace_placeholders($raw, $context);
        return $html;
    }

    public static function replace_placeholders(string $tpl, array $context): string
    {
        // Pass 1: {{dot.path}} format
        $out = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\.\-]+)\s*\}\}/', function ($m) use ($context) {
            $path = (string) $m[1];
            $val  = self::get_context_value($context, $path);
            if (is_scalar($val)) return (string) $val;
            if (is_object($val) && method_exists($val, '__toString')) return (string) $val;
            return '';
        }, $tpl);

        // Pass 2: {snake_case_or_dot} format (admin UI uses single braces)
        $map = [
            'site_name' => 'site.name',
            'site_url' => 'site.url',
            'contact_name' => 'customer.name',
            'contact_email' => 'customer.email',
            'booking_id' => 'booking.id',
            'vehicle_title' => 'vehicle.title',
            'pickup_date' => 'booking.pickup_date',
            'dropoff_date' => 'booking.return_date',
            'return_date' => 'booking.return_date',
            'total_price' => 'booking.total_price',
            'status' => 'booking.status',
        ];

        $out = preg_replace_callback('/\{\s*([a-zA-Z0-9_\.\-]+)\s*\}/', function ($m) use ($context, $map) {
            $token = (string) $m[1];
            $path = $map[$token] ?? str_replace('_', '.', $token);
            $val  = self::get_context_value($context, $path);
            if (is_scalar($val)) return (string) $val;
            if (is_object($val) && method_exists($val, '__toString')) return (string) $val;
            return '';
        }, $out);

        return $out;
    }

    private static function get_context_value(array $ctx, string $path)
    {
        $parts = explode('.', $path);
        $cur = $ctx;
        foreach ($parts as $p) {
            if (is_array($cur) && array_key_exists($p, $cur)) {
                $cur = $cur[$p];
            } else {
                return null;
            }
        }
        return $cur;
    }
}
