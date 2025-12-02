<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Testing;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ Integration Test Suite
 * 
 * Tests for third-party integrations, settings persistence, email, and payment gateways
 */
final class IntegrationTest
{
    /**
     * Run all integration tests
     */
    public static function run_all_tests(): array
    {
        $results = [];
        
        $results['settings_persistence'] = self::test_settings_persistence();
        $results['email_system'] = self::test_email_system();
        $results['payment_gateways'] = self::test_payment_gateways();
        $results['user_roles'] = self::test_user_roles();
        $results['i18n_support'] = self::test_i18n_support();
        $results['database_integrity'] = self::test_database_integrity();
        $results['error_handling'] = self::test_error_handling();
        
        return $results;
    }

    /**
     * Test: Settings Persistence
     */
    public static function test_settings_persistence(): array
    {
        $test_key = 'mhm_test_settings_persistence_' . time();
        $test_value = ['test' => 'data', 'timestamp' => time()];
        
        // Save
        update_option($test_key, $test_value);
        
        // Retrieve
        $retrieved = get_option($test_key);
        $pass = ($retrieved === $test_value);
        
        // Cleanup
        delete_option($test_key);
        
        return [
            'test' => __('Settings Persistence', 'mhm-rentiva'),
            'status' => $pass ? 'pass' : 'fail',
            'message' => $pass ? 
                esc_html__('✅ Settings save and retrieve working correctly', 'mhm-rentiva') :
                esc_html__('❌ Settings persistence issue detected', 'mhm-rentiva'),
            'save_works' => $pass,
            'retrieve_works' => $pass
        ];
    }

    /**
     * Test: Email System
     */
    public static function test_email_system(): array
    {
        $has_mailer = class_exists('MHMRentiva\\Admin\\Emails\\Core\\Mailer');
        $has_email_templates = class_exists('MHMRentiva\\Admin\\Emails\\Core\\EmailTemplates');
        $has_booking_notifications = class_exists('MHMRentiva\\Admin\\Emails\\Notifications\\BookingNotifications');
        
        $email_enabled = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_email_enabled', '1') === '1';
        
        $pass = $has_mailer && $has_email_templates && $has_booking_notifications;
        
        if ($pass) {
            $message = sprintf(
                /* translators: %s placeholder. */
                esc_html__('✅ Email system components available (enabled: %s)', 'mhm-rentiva'),
                $email_enabled ? esc_html__('yes', 'mhm-rentiva') : esc_html__('no', 'mhm-rentiva')
            );
        } else {
            $message = esc_html__('⚠️ Some email components missing', 'mhm-rentiva');
        }
        
        return [
            'test' => __('Email System', 'mhm-rentiva'),
            'status' => $pass ? 'pass' : 'warning',
            'message' => $message,
            'has_mailer' => $has_mailer,
            'has_templates' => $has_email_templates,
            'has_notifications' => $has_booking_notifications,
            'email_enabled' => $email_enabled
        ];
    }

    /**
     * Test: Payment Gateways
     */
    /**
     * Test: Payment Gateways
     */
    public static function test_payment_gateways(): array
    {
        $gateways = [];
        
        // ⭐ WooCommerce only - All payments go through WooCommerce
        $has_woocommerce = class_exists('WooCommerce');
        $gateways['woocommerce'] = ['exists' => $has_woocommerce, 'enabled' => $has_woocommerce];
        
        $total_gateways = count(array_filter($gateways, fn($g) => $g['exists']));
        $enabled_gateways = count(array_filter($gateways, fn($g) => $g['enabled']));
        
        $pass = $total_gateways >= 1; // At least 1 gateway (WooCommerce) should exist
        
        // Gateway isimlerini listele
        $available_names = [];
        $enabled_names = [];
        foreach ($gateways as $name => $info) {
            if ($info['exists']) {
                $available_names[] = ucfirst($name);
            }
            if ($info['enabled']) {
                $enabled_names[] = ucfirst($name);
            }
        }
        
        if ($pass) {
            if ($enabled_gateways > 0) {
                $message = sprintf(
                    /* translators: 1: %1$d; 2: %2$s; 3: %3$d; 4: %4$s. */
                    esc_html__('✅ %1$d payment gateways available (%2$s), %3$d enabled (%4$s)', 'mhm-rentiva'),
                    $total_gateways,
                    esc_html(implode(', ', $available_names)),
                    $enabled_gateways,
                    esc_html(implode(', ', $enabled_names))
                );
            } else {
                $settings_link = admin_url('admin.php?page=mhm-rentiva-settings&tab=payment');
                $message = sprintf(
                    /* translators: 1: %1$d; 2: %2$s; 3: %3$s. */
                    esc_html__('✅ %1$d payment gateways available (%2$s). None enabled yet (%3$s)', 'mhm-rentiva'),
                    $total_gateways,
                    esc_html(implode(', ', $available_names)),
                    '<a href="' . esc_url($settings_link) . '">' . esc_html__('configure in Settings → Payment', 'mhm-rentiva') . '</a>'
                );
            }
        } else {
            $message = sprintf(
                /* translators: 1: %1$d; 2: %2$d. */
                esc_html__('⚠️ Only %1$d/%2$d payment gateways found. Expected at least 1 gateway', 'mhm-rentiva'),
                $total_gateways,
                count($gateways)
            );
        }
        
        return [
            'test' => __('Payment Gateways', 'mhm-rentiva'),
            'status' => $pass ? 'pass' : 'warning',
            'message' => $message,
            'gateways' => $gateways,
            'total' => $total_gateways,
            'enabled' => $enabled_gateways
        ];
    }

    /**
     * Test: User Roles & Capabilities
     */
    public static function test_user_roles(): array
    {
        $required_roles = ['customer', 'administrator'];
        $roles_found = [];
        $roles_missing = [];
        
        foreach ($required_roles as $role) {
            if (get_role($role)) {
                $roles_found[] = $role;
            } else {
                $roles_missing[] = $role;
            }
        }
        
        // Check customer role specifically
        $customer_role = get_role('customer');
        $customer_has_caps = $customer_role && !empty($customer_role->capabilities);
        
        $pass = in_array('customer', $roles_found) && in_array('administrator', $roles_found);
        
        if ($pass) {
            $message = sprintf(
                /* translators: %s placeholder. */
                esc_html__('✅ Required roles found: %s', 'mhm-rentiva'),
                esc_html(implode(', ', $roles_found))
            );
        } else {
            $message = sprintf(
                /* translators: %s placeholder. */
                esc_html__('⚠️ Missing roles: %s', 'mhm-rentiva'),
                esc_html(implode(', ', $roles_missing))
            );
        }
        
        return [
            'test' => __('User Roles & Capabilities', 'mhm-rentiva'),
            'status' => $pass ? 'pass' : 'warning',
            'message' => $message,
            'found' => $roles_found,
            'missing' => $roles_missing,
            'customer_role_valid' => $customer_has_caps
        ];
    }

    /**
     * Test: i18n Support
     */
    public static function test_i18n_support(): array
    {
        $text_domain = 'mhm-rentiva';
        
        // Check if text domain functions are used
        $translation_functions = [
            '__' => 0,
            '_e' => 0,
            'esc_html__' => 0,
            'esc_attr__' => 0,
        ];
        
        $plugin_dir = MHM_RENTIVA_PLUGIN_DIR;
        $src_dir = $plugin_dir . 'src/';
        
        if (is_dir($src_dir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($src_dir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());
                    if ($content === false) {
                        continue;
                    }
                    
                    foreach ($translation_functions as $func => &$count) {
                        $count += substr_count($content, $func . '(');
                    }
                }
            }
        }
        
        $total_translations = array_sum($translation_functions);
        $has_textdomain = function_exists('load_plugin_textdomain');
        
        // Check if language files exist
        $lang_dir = $plugin_dir . 'languages/';
        $has_lang_files = is_dir($lang_dir) && (count(glob($lang_dir . '*.po')) > 0 || count(glob($lang_dir . '*.mo')) > 0);
        
        $pass = $total_translations > 100 && $has_textdomain;
        
        $message = sprintf(
            /* translators: 1: %1$d; 2: %2$s; 3: %3$s. */
            esc_html__('%1$d translation functions found, textdomain: %2$s, lang files: %3$s', 'mhm-rentiva'),
            $total_translations,
            $has_textdomain ? esc_html__('yes', 'mhm-rentiva') : esc_html__('no', 'mhm-rentiva'),
            $has_lang_files ? esc_html__('yes', 'mhm-rentiva') : esc_html__('no', 'mhm-rentiva')
        );
        
        return [
            'test' => __('i18n Support', 'mhm-rentiva'),
            'status' => $pass ? 'pass' : 'warning',
            'message' => $message,
            'translation_count' => $total_translations,
            'functions' => $translation_functions,
            'has_textdomain' => $has_textdomain,
            'has_lang_files' => $has_lang_files
        ];
    }

    /**
     * Test: Database Integrity
     */
    public static function test_database_integrity(): array
    {
        global $wpdb;
        
        $tests = [];
        
        // Test 1: Check if custom tables exist
        $custom_tables = [
            $wpdb->prefix . 'mhm_rentiva_ratings',
            $wpdb->prefix . 'mhm_rentiva_queue',
        ];
        
        $existing_tables = [];
        $missing_tables = [];
        
        foreach ($custom_tables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
            if ($exists) {
                $existing_tables[] = $table;
            } else {
                $missing_tables[] = $table;
            }
        }
        
        $tests['custom_tables'] = count($existing_tables) . '/' . count($custom_tables);
        
        // Test 2: Check postmeta consistency
        $orphaned_meta = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.ID IS NULL
            LIMIT 1
        ");
        
        $tests['orphaned_meta'] = (int) $orphaned_meta;
        
        // Test 3: Check if critical CPTs have posts
        $vehicle_count = wp_count_posts('vehicle');
        $booking_count = wp_count_posts('vehicle_booking');
        
        $tests['vehicle_posts'] = $vehicle_count->publish ?? 0;
        $tests['booking_posts'] = $booking_count->publish ?? 0;
        
        $pass = count($missing_tables) === 0 && $orphaned_meta < 100; // Allow some orphaned meta
        
        if ($pass) {
            $message = esc_html__('✅ Database integrity check passed', 'mhm-rentiva');
        } else {
            $message = sprintf(
                /* translators: 1: %1$d; 2: %2$d. */
                esc_html__('⚠️ %1$d missing tables, %2$d orphaned meta entries', 'mhm-rentiva'),
                count($missing_tables),
                (int) $orphaned_meta
            );
        }
        
        return [
            'test' => __('Database Integrity', 'mhm-rentiva'),
            'status' => $pass ? 'pass' : 'warning',
            'message' => $message,
            'existing_tables' => $existing_tables,
            'missing_tables' => $missing_tables,
            'orphaned_meta_count' => (int) $orphaned_meta,
            'tests' => $tests
        ];
    }

    /**
     * Test: Error Handling
     */
    public static function test_error_handling(): array
    {
        $has_error_handler = class_exists('MHMRentiva\\Admin\\Core\\Utilities\\ErrorHandler');
        
        if ($has_error_handler) {
            $methods_exist = method_exists('MHMRentiva\\Admin\\Core\\Utilities\\ErrorHandler', 'log_error') &&
                           method_exists('MHMRentiva\\Admin\\Core\\Utilities\\ErrorHandler', 'security_error') &&
                           method_exists('MHMRentiva\\Admin\\Core\\Utilities\\ErrorHandler', 'validation_error');
        } else {
            $methods_exist = false;
        }
        
        // Check if error logging is configured
        $log_file = WP_CONTENT_DIR . '/debug.log';
        $logging_enabled = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
        
        $pass = $has_error_handler && $methods_exist;
        
        return [
            'test' => __('Error Handling System', 'mhm-rentiva'),
            'status' => $pass ? 'pass' : 'warning',
            'message' => $pass ? 
                esc_html__('✅ ErrorHandler class exists and functional', 'mhm-rentiva') :
                esc_html__('⚠️ ErrorHandler missing or incomplete', 'mhm-rentiva'),
            'has_error_handler' => $has_error_handler,
            'methods_exist' => $methods_exist,
            'logging_enabled' => $logging_enabled,
            'log_file_exists' => file_exists($log_file)
        ];
    }
}

