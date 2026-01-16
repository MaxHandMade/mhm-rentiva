<?php
/*
Plugin Name: MHM Rentiva
Description: Vehicle rental management plugin with WooCommerce payment integration.
Version: 4.6.0
Author: MHM Development Team
Text Domain: mhm-rentiva
Domain Path: /languages
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit;
}

// Global sanitization overrides removed.

/**
 * Safe sanitize text field that handles null values
 */
function mhm_rentiva_sanitize_text_field_safe($value)
{
    // Use central Sanitizer if available (PSR-4 autoloader might not be ready yet in some hooks)
    if (class_exists('MHMRentiva\Admin\Core\Helpers\Sanitizer')) {
        return \MHMRentiva\Admin\Core\Helpers\Sanitizer::text_field_safe($value);
    }

    // Fallback implementation
    if ($value === null) {
        return '';
    }
    if ($value === '') {
        return '';
    }
    if (!is_string($value) && !is_numeric($value)) {
        return '';
    }
    return sanitize_text_field($value);
}

// Define Plugin Constants
define('MHM_RENTIVA_VERSION', '4.6.0');


// Recursive $_POST/$_REQUEST cleaning removed.

// ✅ Database cleaning removed - was causing infinite loop
// Null cleaning is handled in SettingsSanitizer and immediate POST cleaning above

// PHP version check
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>';
        printf(
            /* translators: %s: detected PHP version number. */
            esc_html__('MHM Rentiva plugin requires PHP 7.4 or higher. Your version: %s', 'mhm-rentiva'),
            esc_html(PHP_VERSION)
        );
        echo '</p></div>';
    });
    return;
}

// Version constant


// Plugin file constant
if (!defined('MHM_RENTIVA_PLUGIN_FILE')) {
    define('MHM_RENTIVA_PLUGIN_FILE', __FILE__);
}

// Plugin URL constant
if (!defined('MHM_RENTIVA_PLUGIN_URL')) {
    define('MHM_RENTIVA_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Plugin PATH constant
if (!defined('MHM_RENTIVA_PLUGIN_PATH')) {
    define('MHM_RENTIVA_PLUGIN_PATH', plugin_dir_path(__FILE__));
}

// Plugin directory constant
if (!defined('MHM_RENTIVA_PLUGIN_DIR')) {
    define('MHM_RENTIVA_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

// Developer mode now works only with automatic detection (for security)

// Advanced PSR-4 autoloader (MHMRentiva\* -> /src)
spl_autoload_register(function ($class) {
    if (strpos($class, 'MHMRentiva\\') !== 0) {
        return;
    }

    // Ensure AbstractShortcode is loaded first for shortcode classes
    if (
        strpos($class, 'MHMRentiva\\Admin\\Frontend\\Shortcodes\\') === 0 &&
        $class !== 'MHMRentiva\\Admin\\Frontend\\Shortcodes\\AbstractShortcode' &&
        !class_exists('MHMRentiva\\Admin\\Frontend\\Shortcodes\\AbstractShortcode')
    ) {

        $abstract_path = __DIR__ . '/src/Admin/Frontend/Shortcodes/Core/AbstractShortcode.php';
        if (file_exists($abstract_path)) {
            require_once $abstract_path;
        }
    }

    // Convert namespace to file path
    $relative = str_replace(['MHMRentiva\\', '\\'], ['', '/'], $class) . '.php';
    $path = __DIR__ . '/src/' . $relative;

    // Load file if exists
    if (file_exists($path)) {
        require_once $path;
        return;
    }

    // Log for files not following PSR-4 (only when absolutely necessary)
    // Note: Some old classes may be in different namespaces, this is normal
    // Only logged when there's a real problem (e.g., plugin doesn't work)
});

// Central bootstrap - ALL registrations are done in Plugin.php
// Priority -10: Load BEFORE AJAX requests
add_action('plugins_loaded', function () {
    // Check if already bootstrapped
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }

    if (class_exists('MHMRentiva\\Plugin')) {
        try {
            \MHMRentiva\Plugin::bootstrap();
            $bootstrapped = true;
        } catch (Exception $e) {
            add_action('admin_notices', function () use ($e) {
                echo '<div class="notice notice-error">
        <p>';
                echo esc_html__('MHM Rentiva plugin error on startup: ', 'mhm-rentiva') . esc_html($e->getMessage());
                echo '</p>
    </div>';
            });
        }
    } else {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error">
        <p>';
            echo esc_html__('MHM Rentiva plugin failed to load. Please reinstall the plugin.', 'mhm-rentiva');
            echo '</p>
    </div>';
        });
    }
}, -10); // Priority -10: Load very early (critical for AJAX)

/**
 * Single site activation operations
 */
function mhm_rentiva_single_site_activation()
{
    // Register CPT and taxonomy
    if (class_exists('MHMRentiva\\Admin\\Vehicle\\PostType\\Vehicle')) {
        \MHMRentiva\Admin\Vehicle\PostType\Vehicle::register();
    }
    if (class_exists('MHMRentiva\\Admin\\Vehicle\\Taxonomies\\VehicleCategory')) {
        \MHMRentiva\Admin\Vehicle\Taxonomies\VehicleCategory::register();
    }

    // Register Customer role
    if (class_exists('MHMRentiva\\Plugin')) {
        \MHMRentiva\Plugin::register_customer_role();
    }

    // Refresh permalinks
    flush_rewrite_rules();

    // Create rating table
    if (class_exists('MHMRentiva\Admin\Core\Utilities\DatabaseMigrator')) {
        \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::create_rating_table();
    }

    // Trigger setup wizard redirect on new installations
    update_option('mhm_rentiva_setup_redirect', '1');
}

// Activation hook - CPT and taxonomy registration + rewrite flush + Multisite support
register_activation_hook(__FILE__, function () {
    // PHP version check
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        wp_die(esc_html__('MHM Rentiva plugin requires PHP 7.4 or higher.', 'mhm-rentiva'));
    }

    // Check for WooCommerce dependency
    if (!class_exists('WooCommerce')) {
        wp_die(
            /* translators: %s: Plugin Name */
            sprintf(
                esc_html__('%s requires WooCommerce to be installed and active.', 'mhm-rentiva'),
                'MHM Rentiva'
            ),
            esc_html__('Plugin Dependency Check', 'mhm-rentiva'),
            ['back_link' => true]
        );
    }

    // Multisite check
    if (is_multisite()) {
        // Network-wide activation
        if (isset($_GET['networkwide']) && mhm_rentiva_sanitize_text_field_safe($_GET['networkwide']) === '1') {
            global $wpdb;
            $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");

            foreach ($blog_ids as $blog_id) {
                switch_to_blog($blog_id);
                mhm_rentiva_single_site_activation();
                restore_current_blog();
            }
            return;
        }
    }

    // Single site activation
    mhm_rentiva_single_site_activation();
});

// Runtime dependency check
add_action('admin_notices', function () {
    if (!class_exists('WooCommerce')) {
        echo '<div class="notice notice-error">
            <p>';
        printf(
            /* translators: %s: Plugin Name */
            esc_html__('%s requires WooCommerce to be installed and active. Please install WooCommerce to use this plugin.', 'mhm-rentiva'),
            '<strong>MHM Rentiva</strong>'
        );
        echo '</p>
        </div>';
    }
});

// When new blog is created in Multisite
add_action('wpmu_new_blog', function ($blog_id) {
    if (is_plugin_active_for_network('mhm-rentiva/mhm-rentiva.php')) {
        switch_to_blog($blog_id);
        mhm_rentiva_single_site_activation();
        restore_current_blog();
    }
}, 10, 1);

// Load ShortcodeServiceProvider (Singleton)
if (class_exists('MHMRentiva\\Admin\\Core\\ShortcodeServiceProvider')) {
    \MHMRentiva\Admin\Core\ShortcodeServiceProvider::instance();
}

// Deactivation hook - rewrite flush + license cron cleanup
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();

    // Clean license cron job
    if (class_exists('MHMRentiva\\Admin\\Licensing\\LicenseManager')) {
        \MHMRentiva\Admin\Licensing\LicenseManager::deactivatePluginHook();
    }
});
