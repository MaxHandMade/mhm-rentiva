<?php
/**
 * My Account - Navigation Template
 * 
 * @var array $navigation
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load plugin textdomain
if (!function_exists('mhm_rentiva_load_textdomain')) {
    function mhm_rentiva_load_textdomain() {
        load_plugin_textdomain('mhm-rentiva', false, dirname(plugin_basename(__FILE__)) . '/../languages/');
    }
    mhm_rentiva_load_textdomain();
}

// Active page detection - check endpoint parameter
$current_endpoint = 'dashboard'; // default

// 1. Get endpoint from URL
if (isset($_GET['endpoint']) && !empty($_GET['endpoint'])) {
    $current_endpoint = mhm_rentiva_sanitize_text_field_safe(wp_unslash($_GET['endpoint']));
}

// 2. Get endpoint from query var
if (get_query_var('endpoint')) {
    $current_endpoint = get_query_var('endpoint');
}

// 3. URL comparison (fallback)
$current_url = home_url($_SERVER['REQUEST_URI']);
foreach ($navigation as $endpoint => $nav_item) {
    if (strpos($current_url, $nav_item['url']) !== false) {
        $current_endpoint = $endpoint;
        break;
    }
}
?>

<nav class="mhm-account-navigation">
    <ul class="account-menu">
        <?php foreach ($navigation as $endpoint => $nav_item): ?>
            <li class="menu-item <?php echo $current_endpoint === $endpoint ? 'active' : ''; ?>">
                <a href="<?php echo esc_url($nav_item['url']); ?>">
                    <span class="menu-icon"><?php echo $nav_item['icon']; ?></span>
                    <span class="menu-title"><?php echo esc_html($nav_item['title']); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

