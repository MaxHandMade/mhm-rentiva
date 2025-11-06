<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class LicenseManager
{
    public const OPTION    = 'mhm_rentiva_license';
    public const CRON_HOOK = 'mhm_rentiva_license_daily';

    private static ?self $instance = null;

    /**
     * Get singleton instance
     * 
     * @return self
     */
    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Register license manager hooks
     */
    public function register(): void
    {
        add_action('admin_init', [$this, 'maybeHandleActions']);
        add_action(self::CRON_HOOK, [$this, 'cronValidate']);

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 3600, 'daily', self::CRON_HOOK);
        }

        add_action('admin_notices', [$this, 'adminNotices']);
    }

    /**
     * Deactivate plugin hook - cleanup scheduled events
     */
    public static function deactivatePluginHook(): void
    {
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if ($ts) {
            wp_unschedule_event($ts, self::CRON_HOOK);
        }
    }

    /**
     * Get license data
     * 
     * @return array License data
     */
    public function get(): array
    {
        $opt = get_option(self::OPTION, []);
        return is_array($opt) ? $opt : [];
    }

    /**
     * Save license data
     * 
     * @param array $data License data to save
     */
    public function save(array $data): void
    {
        update_option(self::OPTION, $data, false);
    }

    /**
     * Set license data
     * 
     * @param array $data License data
     */
    public function setLicenseData(array $data): void
    {
        $this->save($data);
    }

    /**
     * Clear license data
     */
    public function clearLicense(): void
    {
        $this->save([]);
    }

    /**
     * Get license key
     * 
     * @return string License key
     */
    public function getKey(): string
    {
        $o = $this->get();
        return (string) ($o['key'] ?? '');
    }

    /**
     * Check if license is active
     * 
     * @return bool True if active
     */
    public function isActive(): bool
    {
        // Only automatic developer mode (secure)
        if ($this->isDevelopmentEnvironment()) {
            return true;
        }

        $o = $this->get();
        if (($o['status'] ?? '') !== 'active') {
            return false;
        }
        
        $exp = $o['expires_at'] ?? null;
        if ($exp && is_numeric($exp)) {
            return (int) $exp > time();
        }
        
        return true;
    }

    /**
     * Handle license actions
     */
    public function maybeHandleActions(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (!isset($_POST['mhm_license_action'])) {
            return;
        }
        check_admin_referer('mhm_rentiva_license_action', 'mhm_license_nonce');

        $action = sanitize_text_field((string) ($_POST['mhm_license_action'] ?? ''));
        if ($action === 'activate') {
            $key = sanitize_text_field((string) ($_POST['mhm_license_key'] ?? ''));
            $res = $this->activate($key);
            $this->flash($res instanceof WP_Error ? $res->get_error_message() : __('License activated.', 'mhm-rentiva'), !($res instanceof WP_Error));
        } elseif ($action === 'deactivate') {
            $res = $this->deactivate();
            $this->flash($res instanceof WP_Error ? $res->get_error_message() : __('License deactivated.', 'mhm-rentiva'), !($res instanceof WP_Error));
        } elseif ($action === 'validate') {
            $res = $this->validate();
            $this->flash($res instanceof WP_Error ? $res->get_error_message() : __('License validated.', 'mhm-rentiva'), !($res instanceof WP_Error));
        }

        wp_safe_redirect(remove_query_arg(['mhm_notice']));
        exit;
    }

    /**
     * Activate license
     * 
     * @param string $key License key
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function activate(string $key)
    {
        if ($key === '') {
            return new WP_Error('empty_key', __('Please enter a license key.', 'mhm-rentiva'));
        }


        $resp = $this->request('/licenses/activate', [
            'license_key' => $key,
            'site_hash'   => $this->siteHash(),
            'site_url'    => home_url(),
            'is_staging'  => $this->isStaging(),
        ]);
        if (is_wp_error($resp)) {
            return $resp;
        }

        $data = [
            'key'           => $key,
            'status'        => $resp['status']        ?? 'active',
            'plan'          => $resp['plan']          ?? 'pro',
            'expires_at'    => isset($resp['expires_at']) ? (int) $resp['expires_at'] : null,
            'activation_id' => $resp['activation_id'] ?? '',
            'token'         => $resp['token']         ?? '',
            'last_check_at' => time(),
        ];
        $this->save($data);
        return true;
    }

    /**
     * Deactivate license
     * 
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function deactivate()
    {
        $o = $this->get();
        $key = $o['key'] ?? '';
        if ($key !== '' && empty($o['activation_id']) === false) {
            $this->request('/licenses/deactivate', [
                'license_key'   => $key,
                'activation_id' => $o['activation_id'],
            ]);
        }
        $this->save([]);
        return true;
    }

    /**
     * Validate license
     * 
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function validate()
    {
        $o = $this->get();
        $key = $o['key'] ?? '';
        if ($key === '') {
            return new WP_Error('no_key', __('No saved license key found.', 'mhm-rentiva'));
        }


        $resp = $this->request('/licenses/validate', [
            'license_key' => $key,
            'site_hash'   => $this->siteHash(),
        ]);
        if (is_wp_error($resp)) {
            return $resp;
        }

        $o['status']        = $resp['status']     ?? ($o['status'] ?? 'inactive');
        $o['plan']          = $resp['plan']       ?? ($o['plan'] ?? null);
        $o['expires_at']    = isset($resp['expires_at']) ? (int) $resp['expires_at'] : ($o['expires_at'] ?? null);
        $o['last_check_at'] = time();
        $this->save($o);
        return true;
    }

    /**
     * Cron job to validate license
     */
    public function cronValidate(): void
    {
        $this->validate();
    }

    /**
     * Make API request to license server
     * 
     * @param string $path API path
     * @param array $body Request body
     * @return array|WP_Error Response data or error
     */
    private function request(string $path, array $body)
    {
        $base = defined('MHM_RENTIVA_LICENSE_API_BASE') ? MHM_RENTIVA_LICENSE_API_BASE : 'https://your-domain.tld/wp-json/mhm-license/v1';
        $url  = rtrim($base, '/') . $path;
        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
                'User-Agent'   => 'MHM-Rentiva/' . (defined('MHM_RENTIVA_VERSION') ? MHM_RENTIVA_VERSION : 'dev'),
            ],
            'timeout' => 15,
            'body'    => wp_json_encode($body),
            'method'  => 'POST',
        ];
        $r = wp_remote_request($url, $args);
        if (is_wp_error($r)) {
            return $r;
        }
        $code = wp_remote_retrieve_response_code($r);
        $json = json_decode(wp_remote_retrieve_body($r), true);
        if ($code >= 200 && $code < 300 && is_array($json)) {
            return $json;
        }
        return new WP_Error('license_http', __('License server error.', 'mhm-rentiva'));
    }

    /**
     * Generate site hash for license validation
     * 
     * @return string Site hash
     */
    private function siteHash(): string
    {
        $payload = [
            'home' => home_url(),
            'site' => site_url(),
            'wp'   => get_bloginfo('version'),
            'php'  => PHP_VERSION,
        ];
        return hash('sha256', wp_json_encode($payload));
    }

    /**
     * Check if site is staging environment
     * 
     * @return bool True if staging
     */
    private function isStaging(): bool
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST) ?: '';
        foreach (['.local', '.test', '.dev', '.staging', 'localhost'] as $p) {
            if ($host === $p || str_ends_with($host, $p)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Development environment check (secure automatic detection)
     * 
     * @return bool True if development environment
     */
    public function isDevelopmentEnvironment(): bool
    {
        // 1. Host check (localhost, .local, .dev, .test, .staging)
        $host = wp_parse_url(home_url(), PHP_URL_HOST) ?: '';
        $dev_domains = ['.local', '.test', '.dev', '.staging'];
        
        // localhost check
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }
        
        // Local domain check
        foreach ($dev_domains as $domain) {
            if (str_ends_with($host, $domain)) {
                return true;
            }
        }

        // 2. Local servers like XAMPP, WAMP, MAMP (only with localhost)
        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            $server_software = $_SERVER['SERVER_SOFTWARE'] ?? '';
            if (stripos($server_software, 'xampp') !== false ||
                stripos($server_software, 'wamp') !== false ||
                stripos($server_software, 'mamp') !== false ||
                stripos($server_software, 'lamp') !== false) {
                return true;
            }
        }

        // 3. Port check (only with localhost)
        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            $port = wp_parse_url(home_url(), PHP_URL_PORT);
            if (in_array($port, ['8080', '8081', '3000', '3001', '8000', '8001'], true)) {
                return true;
            }
        }

        // 4. WordPress debug mode (only with localhost)
        if (in_array($host, ['localhost', '127.0.0.1'], true) && defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }

        // 5. WordPress development environment (only with localhost)
        if (in_array($host, ['localhost', '127.0.0.1'], true) && 
            defined('WP_ENV') && in_array(WP_ENV, ['development', 'dev', 'local'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Flash message for admin notices
     * 
     * @param string $msg Message
     * @param bool $ok Success status
     */
    private function flash(string $msg, bool $ok): void
    {
        $key = $ok ? 'success' : 'error';
        set_transient('mhm_license_notice', [$key, $msg], 60);
    }

    /**
     * Display admin notices
     */
    public function adminNotices(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if ($n = get_transient('mhm_license_notice')) {
            delete_transient('mhm_license_notice');
            $class = $n[0] === 'success' ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($n[1]) . '</p></div>';
        }

        $o = $this->get();
        if (($o['status'] ?? '') === 'active' && !empty($o['expires_at']) && ((int) $o['expires_at'] - time()) < 14 * DAY_IN_SECONDS) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Your Rentiva license will expire soon. Please renew for Pro features and updates.', 'mhm-rentiva') . '</p></div>';
        }
    }
}
