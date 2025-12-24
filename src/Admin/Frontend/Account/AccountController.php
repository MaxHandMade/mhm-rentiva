<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Account Controller
 * 
 * Customer account management using WordPress standard login system
 * Similar structure to WooCommerce My Account system
 * 
 * @since 4.0.0
 */
final class AccountController
{
    /**
     * Safe sanitize text field that handles null values
     */
    public static function sanitize_text_field_safe($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        return sanitize_text_field((string) $value);
    }

    private static ?self $instance = null;

    private function __construct()
    {
        // Singleton pattern
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function register(): void
    {
        $instance = self::instance();
        
        // Register shortcodes
        add_shortcode('rentiva_my_account', [self::class, 'render_my_account']);
        add_shortcode('rentiva_my_bookings', [self::class, 'render_my_bookings']);
        add_shortcode('rentiva_my_favorites', [self::class, 'render_my_favorites']);
        add_shortcode('rentiva_payment_history', [self::class, 'render_payment_history']);
        add_shortcode('rentiva_account_details', [self::class, 'render_account_details']);
        add_shortcode('rentiva_login_form', [self::class, 'render_login_form']);
        add_shortcode('rentiva_register_form', [self::class, 'render_register_form']);
        
        // AJAX handlers
        add_action('wp_ajax_mhm_rentiva_update_account', [self::class, 'ajax_update_account']);
        add_action('wp_ajax_mhm_rentiva_add_favorite', [self::class, 'ajax_add_favorite']);
        add_action('wp_ajax_mhm_rentiva_remove_favorite', [self::class, 'ajax_remove_favorite']);
        add_action('wp_ajax_mhm_rentiva_clear_favorites', [self::class, 'ajax_clear_favorites']);
        add_action('wp_ajax_mhm_rentiva_clear_favorites', [self::class, 'ajax_clear_favorites']);
        add_action('wp_ajax_mhm_cancel_booking', [self::class, 'ajax_cancel_booking']);
        
        // Receipt management
        add_action('wp_ajax_mhm_rentiva_upload_receipt', [self::class, 'ajax_upload_receipt']);
        add_action('wp_ajax_mhm_rentiva_remove_receipt', [self::class, 'ajax_remove_receipt']);
        
        // Assets
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
        
        // Rewrite endpoints
        add_action('init', [self::class, 'add_endpoints']);
        
        // Login redirect
        add_filter('login_redirect', [self::class, 'login_redirect'], 10, 3);
        
        // Logout redirect
        add_filter('logout_redirect', [self::class, 'logout_redirect'], 10, 3);
        
        // Registration hooks
        add_action('user_register', [self::class, 'handle_user_registration']);
        add_action('wp_ajax_nopriv_mhm_rentiva_register', [self::class, 'ajax_register_user']);
        add_action('wp_ajax_mhm_rentiva_register', [self::class, 'ajax_register_user']);
        add_action('init', [self::class, 'handle_registration_form']);

        // Receipt upload (logged-in users only)
        add_action('wp_ajax_mhm_rentiva_upload_receipt', [self::class, 'ajax_upload_receipt']);
        
        // Communication preferences handler
        add_action('init', [self::class, 'handle_communication_preferences']);
        
        // Email verification handler
        add_action('init', [self::class, 'handle_email_verification']);
    }

    /**
     * Get endpoint slug with translation and option support
     * 
     * Priority:
     * 1. Database option (custom user setting)
     * 2. Translation file (po/mo) via _x()
     * 3. Default hardcoded value
     * 
     * @param string $key Identifier key (e.g., 'bookings')
     * @param string $default Default slug in English
     * @return string Sanitized slug
     */
    public static function get_endpoint_slug(string $key, string $default): string
    {
        // 1. Check database option
        $option_key = 'mhm_rentiva_endpoint_' . $key;
        $slug = get_option($option_key);

        if (empty($slug)) {
            // 2. Use translation if no option set
            // context 'endpoint slug' helps translators know this is part of URL
            $slug = _x($default, 'endpoint slug', 'mhm-rentiva');
        }

        return sanitize_title($slug);
    }

    /**
     * Add rewrite endpoints
     */
    public static function add_endpoints(): void
    {
        // If WooCommerce is active, do not register standalone endpoints to avoid conflicts
        // WooCommerceIntegration class handles endpoints for WooCommerce My Account
        if (class_exists('WooCommerce')) {
            return;
        }

        add_rewrite_endpoint(self::get_endpoint_slug('bookings', 'rentiva-bookings'), EP_ROOT | EP_PAGES);
        add_rewrite_endpoint(self::get_endpoint_slug('favorites', 'rentiva-favorites'), EP_ROOT | EP_PAGES);
        add_rewrite_endpoint(self::get_endpoint_slug('payment_history', 'rentiva-payment-history'), EP_ROOT | EP_PAGES);
        add_rewrite_endpoint(self::get_endpoint_slug('edit_account', 'rentiva-edit-account'), EP_ROOT | EP_PAGES);
        add_rewrite_endpoint(self::get_endpoint_slug('messages', 'rentiva-messages'), EP_ROOT | EP_PAGES);
    }

    /**
     * My Account shortcode render
     */
    public static function render_my_account(array $atts = []): string
    {
        $defaults = [
            'redirect_to' => '',
            'show_register' => '1',
        ];
        
        $atts = shortcode_atts($defaults, $atts, 'rentiva_my_account');
        
        
        // Login check
        if (!is_user_logged_in()) {
            return self::render_login_form($atts);
        }
        
        // If WooCommerce exists, redirect to its My Account page
        if (class_exists('WooCommerce') && function_exists('wc_get_page_id')) {
            $account_page_id = wc_get_page_id('myaccount');
            if ($account_page_id && $account_page_id > 0 && !is_page($account_page_id)) {
                ob_start();
                ?>
                <div class="mhm-woo-redirect">
                    <p><?php _e('Redirecting to your account...', 'mhm-rentiva'); ?></p>
                    <script>
                        window.location.href = '<?php echo esc_url(get_permalink($account_page_id)); ?>';
                    </script>
                </div>
                <?php
                return ob_get_clean();
            }
        }
        
        // Load assets (in all cases)
        self::enqueue_assets();
        
        // Determine the current endpoint dynamically
        global $wp_query;
        $endpoint = '';

        $slugs = [
            'bookings'        => self::get_endpoint_slug('bookings', 'rentiva-bookings'),
            'favorites'       => self::get_endpoint_slug('favorites', 'rentiva-favorites'),
            'payment-history' => self::get_endpoint_slug('payment_history', 'rentiva-payment-history'),
            'edit-account'    => self::get_endpoint_slug('edit_account', 'rentiva-edit-account'),
            'messages'        => self::get_endpoint_slug('messages', 'rentiva-messages'),
        ];

        // Check query vars for dynamic slugs
        foreach ($slugs as $key => $slug) {
            if (isset($wp_query->query_vars[$slug])) {
                $endpoint = $key;
                break;
            }
        }

        // Fallback to query parameter (supporting both old logical names and new dynamic slugs)
        if (empty($endpoint)) {
            $req_endpoint = self::sanitize_text_field_safe($_GET['endpoint'] ?? '');
            
            // Check if request matches any dynamic slug
            $found_key = array_search($req_endpoint, $slugs);
            if ($found_key !== false) {
                $endpoint = $found_key;
            } else {
                // Check if it matches logical hardcoded keys (backward compatibility)
                $endpoint = $req_endpoint ?: 'dashboard';
            }
        }
        
        switch ($endpoint) {
            case 'bookings':
                return AccountRenderer::render_bookings($atts);
            
            case 'favorites':
                return AccountRenderer::render_favorites($atts);
            
            case 'payment-history': // Maps to 'payment-history' key in slugs array
            case 'payment_history': // Alternative logical name
                return AccountRenderer::render_payment_history($atts);
            
            case 'edit-account': // Maps to 'edit-account' key
            case 'edit_account': // Alternative
                return AccountRenderer::render_account_details($atts);
            
            case 'messages':
                return AccountRenderer::render_messages($atts);
            
            case 'booking-detail':
                $booking_id = (int) ($_GET['booking_id'] ?? 0);
                if ($booking_id > 0) {
                    return AccountRenderer::render_booking_detail($booking_id);
                }
                return '<p>' . __('Invalid booking ID.', 'mhm-rentiva') . '</p>';
            
            case 'dashboard':
            default:
                return AccountRenderer::render_dashboard($atts);
        }
    }

    /**
     * My Bookings shortcode render
     */
    public static function render_my_bookings(array $atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please login to view your bookings.', 'mhm-rentiva') . '</p>';
        }
        
        $defaults = [
            'limit' => '10',
            'status' => '', // all, confirmed, completed, cancelled
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        
        $atts = shortcode_atts($defaults, $atts, 'rentiva_my_bookings');
        
        self::enqueue_assets();
        
        return AccountRenderer::render_bookings($atts);
    }

    /**
     * My Favorites shortcode render
     */
    public static function render_my_favorites(array $atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please login to view your favorites.', 'mhm-rentiva') . '</p>';
        }
        
        $defaults = [
            'columns' => '3',
            'limit' => '12',
        ];
        
        $atts = shortcode_atts($defaults, $atts, 'rentiva_my_favorites');
        
        self::enqueue_assets();
        
        return AccountRenderer::render_favorites($atts);
    }

    /**
     * Payment History shortcode render
     */
    public static function render_payment_history(array $atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please login to view your payment history.', 'mhm-rentiva') . '</p>';
        }
        
        $defaults = [
            'limit' => '20',
        ];
        
        $atts = shortcode_atts($defaults, $atts, 'rentiva_payment_history');
        
        self::enqueue_assets();
        
        return AccountRenderer::render_payment_history($atts);
    }

    /**
     * Account Details shortcode render
     */
    public static function render_account_details(array $atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please login to edit your account.', 'mhm-rentiva') . '</p>';
        }
        
        self::enqueue_assets();
        
        return AccountRenderer::render_account_details($atts);
    }

    /**
     * Login Form shortcode render
     */
    public static function render_login_form(array $atts = []): string
    {
        if (is_user_logged_in()) {
            $account_url = self::get_account_url();
            return sprintf(
                '<p>%s <a href="%s">%s</a></p>',
                __('You are already logged in.', 'mhm-rentiva'),
                esc_url($account_url),
                __('Go to My Account', 'mhm-rentiva')
            );
        }
        
        $defaults = [
            'redirect' => '',
            'show_register_link' => '1',
        ];
        
        $atts = shortcode_atts($defaults, $atts, 'rentiva_login_form');
        
        self::enqueue_assets();
        
        return AccountRenderer::render_login_form($atts);
    }

    /**
     * Register Form shortcode render
     */
    public static function render_register_form(array $atts = []): string
    {
        if (is_user_logged_in()) {
            return '<p>' . __('You are already registered and logged in.', 'mhm-rentiva') . '</p>';
        }
        
        // Is WordPress user registration allowed?
        if (!get_option('users_can_register')) {
            return '<p>' . __('User registration is currently not allowed.', 'mhm-rentiva') . '</p>';
        }
        
        $defaults = [
            'redirect' => '',
            'show_login_link' => '1',
        ];
        
        $atts = shortcode_atts($defaults, $atts, 'rentiva_register_form');
        
        self::enqueue_assets();
        
        return AccountRenderer::render_register_form($atts);
    }

    /**
     * Load assets
     */
    public static function enqueue_assets(): void
    {
        // Check if we're on messages endpoint - dequeue customer-messages scripts
        $messages_slug = self::get_endpoint_slug('messages', 'rentiva-messages');
        $endpoint = get_query_var('endpoint') ?: self::sanitize_text_field_safe($_GET['endpoint'] ?? '');
        
        // Check both logical name and dynamic slug query var
        if ($endpoint === 'messages' || get_query_var($messages_slug) !== '') {
            // Prevent customer-messages.js from loading (we use REST API in template)
            add_action('wp_enqueue_scripts', function() {
                wp_dequeue_script('mhm-customer-messages');
                wp_dequeue_script('mhm-customer-messages-standalone');
                wp_deregister_script('mhm-customer-messages');
                wp_deregister_script('mhm-customer-messages-standalone');
            }, 999);

            // Enqueue Account Messages JS
            wp_enqueue_script(
                'mhm-rentiva-account-messages',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/account-messages.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );

            // Enqueue Dashicons for password toggles
            wp_enqueue_style('dashicons');

            // Localize Account Messages JS
            $current_user = wp_get_current_user();
            wp_localize_script('mhm-rentiva-account-messages', 'mhmRentivaMessages', [
                'restUrl' => rest_url('mhm-rentiva/v1/'),
                'restNonce' => wp_create_nonce('wp_rest'),
                'customerEmail' => $current_user->user_email,
                'customerName' => $current_user->display_name ?: $current_user->user_login,
                'i18n' => [
                    'threadIdNotFound' => __('Thread ID not found.', 'mhm-rentiva'),
                    'confirmClose' => __('Are you sure you want to close this conversation? You won\'t be able to send more messages.', 'mhm-rentiva'),
                    'closing' => __('Closing...', 'mhm-rentiva'),
                    'messageClosed' => __('Message closed successfully.', 'mhm-rentiva'),
                    'closeFailed' => __('Failed to close message.', 'mhm-rentiva'),
                    'loadingMessages' => __('Loading messages...', 'mhm-rentiva'),
                    'noMessages' => __('No messages found yet.', 'mhm-rentiva'),
                    'loadFailed' => __('Failed to load messages.', 'mhm-rentiva'),
                    'loginRequired' => __('Please login to access your messages.', 'mhm-rentiva'),
                    'permissionDenied' => __('You do not have permission to access messages.', 'mhm-rentiva'),
                    'new' => __('New', 'mhm-rentiva'),
                    'customer' => __('Customer', 'mhm-rentiva'),
                    'administrator' => __('Administrator', 'mhm-rentiva'),
                    'loadingThread' => __('Loading thread...', 'mhm-rentiva'),
                    'threadLoadFailed' => __('Failed to load thread.', 'mhm-rentiva'),
                    'noMessagesFound' => __('No messages found.', 'mhm-rentiva'),
                    'closeMessage' => __('Close Message', 'mhm-rentiva'),
                    'conversationClosed' => __('This conversation is closed.', 'mhm-rentiva'),
                    'fillRequired' => __('Please fill in all required fields.', 'mhm-rentiva'),
                    'sending' => __('Sending...', 'mhm-rentiva'),
                    'messageSent' => __('Message sent successfully.', 'mhm-rentiva'),
                    'messageSendFailed' => __('Message could not be sent.', 'mhm-rentiva'),
                    'errorOccurred' => __('An error occurred. Please try again.', 'mhm-rentiva'),
                    'enterReply' => __('Please enter your reply.', 'mhm-rentiva'),
                    'replySent' => __('Reply sent successfully.', 'mhm-rentiva'),
                    'replyFailed' => __('Failed to send reply.', 'mhm-rentiva'),
                ]
            ]);
        }
        
        // Enqueue Account Privacy JS on Dashboard
        if ($endpoint === 'dashboard' || empty($endpoint)) {
            wp_enqueue_script(
                'mhm-rentiva-account-privacy',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/account-privacy.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );

            wp_localize_script('mhm-rentiva-account-privacy', 'mhmRentivaPrivacy', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_gdpr_nonce'),
                'homeUrl' => home_url(),
                'i18n' => [
                    'confirmExport' => __('Are you sure you want to export your data? This may take a few minutes.', 'mhm-rentiva'),
                    'exporting' => __('Exporting...', 'mhm-rentiva'),
                    'exportSuccess' => __('Data exported successfully!', 'mhm-rentiva'),
                    'exportError' => __('An error occurred while exporting data', 'mhm-rentiva'),
                    'confirmWithdraw' => __('Are you sure you want to withdraw your consent? This will disable data processing for your account.', 'mhm-rentiva'),
                    'processing' => __('Processing...', 'mhm-rentiva'),
                    'withdrawSuccess' => __('Consent withdrawn successfully!', 'mhm-rentiva'),
                    'withdrawError' => __('An error occurred while withdrawing consent', 'mhm-rentiva'),
                    'confirmDeletePrompt' => __('This action cannot be undone. Type "DELETE" to confirm account deletion:', 'mhm-rentiva'),
                    'confirmDeleteFinal' => __('Are you absolutely sure? This will permanently delete your account and all data.', 'mhm-rentiva'),
                    'deleting' => __('Deleting...', 'mhm-rentiva'),
                    'deleteSuccess' => __('Account deleted successfully. You will be redirected to the homepage.', 'mhm-rentiva'),
                    'deleteError' => __('An error occurred while deleting account', 'mhm-rentiva'),
                    'error' => __('Error', 'mhm-rentiva'),
                    'unknownError' => __('Unknown error', 'mhm-rentiva'),
                ]
            ]);
        }

        // Enqueue Booking Cancellation JS on Booking Detail
        if ($endpoint === 'booking-detail') {
            wp_enqueue_script(
                'mhm-rentiva-booking-cancellation',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/booking-cancellation.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );

            wp_localize_script('mhm-rentiva-booking-cancellation', 'mhmRentivaCancellation', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_cancel_booking_nonce'),
                'i18n' => [
                    'cancelling' => __('Cancelling...', 'mhm-rentiva'),
                    'error' => __('An error occurred. Please try again.', 'mhm-rentiva'),
                ]
            ]);
        }

        // Load CSS file
        wp_enqueue_style(
            'mhm-rentiva-my-account',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/my-account.css',
            [],
            MHM_RENTIVA_VERSION
        );
        
        // Load Stats Cards CSS
        wp_enqueue_style(
            'mhm-rentiva-stats-cards',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/stats-cards.css',
            [],
            MHM_RENTIVA_VERSION
        );
        
        wp_enqueue_style(
            'mhm-rentiva-booking-detail',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/booking-detail.css',
            [],
            MHM_RENTIVA_VERSION
        );
        
        wp_enqueue_style(
            'mhm-rentiva-bookings-page',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/bookings-page.css',
            [],
            MHM_RENTIVA_VERSION
        );
        
        wp_enqueue_script(
            'mhm-rentiva-my-account',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/frontend/my-account.js',
            ['jquery'],
            MHM_RENTIVA_VERSION,
            true
        );
        
        wp_localize_script('mhm-rentiva-my-account', 'mhmRentivaAccount', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('mhm-rentiva/v1/'),
            'nonce' => wp_create_nonce('mhm_rentiva_account'),
            'uploadNonce' => wp_create_nonce('mhm_rentiva_upload_receipt'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'i18n' => [
                'loading' => __('Loading...', 'mhm-rentiva'),
                'error' => __('An error occurred.', 'mhm-rentiva'),
                'success' => __('Success!', 'mhm-rentiva'),
                'confirm' => __('Are you sure?', 'mhm-rentiva'),
                'uploading' => __('Uploading...', 'mhm-rentiva'),
                'upload_success' => __('Receipt uploaded successfully.', 'mhm-rentiva'),
                'upload_error' => __('Receipt upload failed.', 'mhm-rentiva'),
                'savedSuccessfully' => __('Account details saved successfully.', 'mhm-rentiva'),
                'removedFromFavorites' => __('Vehicle removed from favorites.', 'mhm-rentiva'),
                'addedToFavorites' => __('Vehicle added to favorites.', 'mhm-rentiva'),
                'favoritesCleared' => __('All favorites cleared.', 'mhm-rentiva'),
                'passwords_do_not_match' => __('Passwords do not match.', 'mhm-rentiva'),
                'cancel_changes_confirm' => __('Are you sure you want to cancel changes?', 'mhm-rentiva'),
                'no_favorites' => __('No favorite vehicles yet.', 'mhm-rentiva'),
                'login_required' => __('You can add vehicles to favorites using the heart icon.', 'mhm-rentiva'),
                'cancel_booking' => __('Cancel Booking', 'mhm-rentiva'),
                'save_changes' => __('Save Changes', 'mhm-rentiva'),
                'favorites_count_single' => __('vehicle in your favorites', 'mhm-rentiva'),
                'favorites_count_plural' => __('vehicles in your favorites', 'mhm-rentiva'),
            ],
        ]);
    }

    /**
     * Are we on My Account page?
     */
    private static function is_account_page(): bool
    {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Shortcode check
        $has_shortcode = has_shortcode($post->post_content, 'rentiva_my_account') ||
                        has_shortcode($post->post_content, 'rentiva_my_bookings') ||
                        has_shortcode($post->post_content, 'rentiva_my_favorites') ||
                        has_shortcode($post->post_content, 'rentiva_payment_history') ||
                        has_shortcode($post->post_content, 'rentiva_account_details') ||
                        has_shortcode($post->post_content, 'rentiva_login_form') ||
                        has_shortcode($post->post_content, 'rentiva_register_form');
        
        return $has_shortcode;
    }

    /**
     * Login redirect
     */
    public static function login_redirect(string $redirect_to, string $request, $user): string
    {
        // Is this a Rentiva customer?
        if (is_a($user, 'WP_User') && get_user_meta($user->ID, 'mhm_rentiva_customer', true)) {
            return self::get_account_url();
        }
        
        return $redirect_to;
    }

    /**
     * Logout redirect
     */
    public static function logout_redirect(string $redirect_to, string $requested_redirect_to, $user): string
    {
        // If logging out from My Account, redirect to homepage
        $account_url = self::get_account_url();
        if (strpos($requested_redirect_to, parse_url($account_url, PHP_URL_PATH)) !== false) {
            return home_url('/');
        }
        
        return $redirect_to;
    }

    /**
     * Get My Account URL
     */
    public static function get_account_url(): string
    {
        // If WooCommerce exists, use WooCommerce My Account URL
        if (class_exists('WooCommerce') && function_exists('wc_get_page_id')) {
            $account_page_id = wc_get_page_id('myaccount');
            if ($account_page_id && $account_page_id > 0) {
                return get_permalink($account_page_id);
            }
        }
        
        // Use global Shortcode URL Manager
        return ShortcodeUrlManager::get_page_url('rentiva_my_account');
    }

    /**
     * AJAX: Clear all favorites
     */
    public static function ajax_clear_favorites(): void
    {
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (empty($nonce) || !wp_verify_nonce($nonce, 'mhm_rentiva_account')) {
            wp_send_json_error(['message' => __('Security error.', 'mhm-rentiva')], 400);
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in', 'mhm-rentiva')], 403);
        }

        $user_id = get_current_user_id();
        delete_user_meta($user_id, 'mhm_rentiva_favorites');

        wp_send_json_success([
            'message' => __('All favorites cleared', 'mhm-rentiva'),
            'favorites_count' => 0,
        ]);
    }


    /**
     * AJAX: Upload payment receipt (deposit slip)
     */
    public static function ajax_upload_receipt(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in.', 'mhm-rentiva')], 403);
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce(self::sanitize_text_field_safe($_POST['nonce']), 'mhm_rentiva_upload_receipt')) {
            wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')], 400);
        }

        $user_id = get_current_user_id();
        $booking_id = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
        if ($booking_id <= 0) {
            wp_send_json_error(['message' => __('Invalid booking ID.', 'mhm-rentiva')], 400);
        }

        // Ownership check
        $booking_author = (int) get_post_field('post_author', $booking_id);
        if ($booking_author !== $user_id && !current_user_can('edit_post', $booking_id)) {
            wp_send_json_error(['message' => __('You are not allowed to upload for this booking.', 'mhm-rentiva')], 403);
        }

        if (empty($_FILES['receipt'])) {
            wp_send_json_error(['message' => __('No file uploaded.', 'mhm-rentiva')], 400);
        }

        // Allow only images/pdf
        $allowed_mimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
        ];

        $file = $_FILES['receipt'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => __('Upload error.', 'mhm-rentiva')], 400);
        }

        $filetype = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        if (empty($filetype['type']) || !isset($allowed_mimes[$filetype['type']])) {
            wp_send_json_error(['message' => __('Invalid file type. Allowed: JPG, PNG, PDF.', 'mhm-rentiva')], 400);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $overrides = [ 'test_form' => false ];
        $upload = wp_handle_upload($file, $overrides);
        if (isset($upload['error'])) {
            wp_send_json_error(['message' => $upload['error']], 400);
        }

        // Create attachment
        $attachment = [
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name($file['name']),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];
        $attach_id = wp_insert_attachment($attachment, $upload['file']);
        if (!is_wp_error($attach_id)) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
            wp_update_attachment_metadata($attach_id, $attach_data);
        }

        // Save to booking meta
        update_post_meta($booking_id, '_mhm_receipt_attachment_id', $attach_id);
        update_post_meta($booking_id, '_mhm_receipt_status', 'submitted');
        update_post_meta($booking_id, '_mhm_receipt_uploaded_by', $user_id);
        update_post_meta($booking_id, '_mhm_receipt_uploaded_at', current_time('mysql'));

        wp_send_json_success([
            'message' => __('Receipt uploaded successfully.', 'mhm-rentiva'),
            'attachment_id' => $attach_id,
            'url' => wp_get_attachment_url($attach_id),
        ]);
    }

    /**
     * AJAX: Remove payment receipt
     */
    public static function ajax_remove_receipt(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in.', 'mhm-rentiva')], 403);
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce(self::sanitize_text_field_safe($_POST['nonce']), 'mhm_rentiva_upload_receipt')) {
            wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')], 400);
        }

        $user_id = get_current_user_id();
        $booking_id = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
        
        if ($booking_id <= 0) {
            wp_send_json_error(['message' => __('Invalid booking ID.', 'mhm-rentiva')], 400);
        }

        // Ownership check
        $booking_author = (int) get_post_field('post_author', $booking_id);
        if ($booking_author !== $user_id && !current_user_can('edit_post', $booking_id)) {
            wp_send_json_error(['message' => __('You are not allowed to remove receipt for this booking.', 'mhm-rentiva')], 403);
        }

        $attachment_id = (int) get_post_meta($booking_id, '_mhm_receipt_attachment_id', true);
        if (!$attachment_id) {
            wp_send_json_error(['message' => __('No receipt found to remove.', 'mhm-rentiva')], 404);
        }

        // Remove attachment
        $deleted = wp_delete_attachment($attachment_id, true);
        
        if ($deleted) {
            // Clean up meta
            delete_post_meta($booking_id, '_mhm_receipt_attachment_id');
            delete_post_meta($booking_id, '_mhm_receipt_status');
            delete_post_meta($booking_id, '_mhm_receipt_uploaded_by');
            delete_post_meta($booking_id, '_mhm_receipt_uploaded_at');
            
            wp_send_json_success(['message' => __('Receipt removed successfully.', 'mhm-rentiva')]);
        } else {
            wp_send_json_error(['message' => __('Failed to remove receipt file.', 'mhm-rentiva')], 500);
        }
    }


    /**
     * AJAX: Update account
     */
    public static function ajax_update_account(): void
    {
        try {
            // Nonce verification
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhm_rentiva_account')) {
                wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            }

            // Login check
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => __('Please login.', 'mhm-rentiva')]);
            }

            // Profile editing check
            $profile_editable = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_profile_editable', '1');
            if ($profile_editable !== '1') {
                wp_send_json_error(['message' => __('Profile editing is currently disabled.', 'mhm-rentiva')]);
            }

            $user_id = get_current_user_id();
            
            // Get and sanitize data
            $display_name = self::sanitize_text_field_safe($_POST['display_name'] ?? '');
            $first_name = self::sanitize_text_field_safe($_POST['first_name'] ?? '');
            $last_name = self::sanitize_text_field_safe($_POST['last_name'] ?? '');
            $phone = self::sanitize_text_field_safe($_POST['phone'] ?? '');
            $address = sanitize_textarea_field((string) ($_POST['address'] ?? ''));

            // Update user information
            $user_data = [
                'ID' => $user_id,
            ];

            if (!empty($display_name)) {
                $user_data['display_name'] = $display_name;
            }
            if (!empty($first_name)) {
                $user_data['first_name'] = $first_name;
            }
            if (!empty($last_name)) {
                $user_data['last_name'] = $last_name;
            }

            $result = wp_update_user($user_data);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            // Update meta information
            if (!empty($phone)) {
                update_user_meta($user_id, 'mhm_rentiva_phone', $phone);
            }
            if (!empty($address)) {
                update_user_meta($user_id, 'mhm_rentiva_address', $address);
            }

            wp_send_json_success([
                'message' => __('Account updated successfully.', 'mhm-rentiva')
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => __('An error occurred while updating your account.', 'mhm-rentiva')]);
        }
    }

    /**
     * AJAX: Add favorite
     */
    public static function ajax_add_favorite(): void
    {
        try {
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhm_rentiva_account')) {
                wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            }

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => __('Please login.', 'mhm-rentiva')]);
            }

            $vehicle_id = (int) ($_POST['vehicle_id'] ?? 0);
            
            if (!$vehicle_id || get_post_type($vehicle_id) !== 'vehicle') {
                wp_send_json_error(['message' => __('Invalid vehicle.', 'mhm-rentiva')]);
            }

            $user_id = get_current_user_id();
            $favorites = get_user_meta($user_id, 'mhm_rentiva_favorites', true);

            if (!is_array($favorites)) {
                $favorites = [];
            }

            $favorites = array_map('intval', $favorites);

            if (!in_array($vehicle_id, $favorites, true)) {
                $favorites[] = $vehicle_id;
                $favorites = array_values(array_unique($favorites));
                update_user_meta($user_id, 'mhm_rentiva_favorites', $favorites);
            }

            wp_send_json_success([
                'message' => __('Vehicle added to favorites.', 'mhm-rentiva'),
                'vehicle_id' => $vehicle_id,
                'favorites_count' => count($favorites),
                'action' => 'added',
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => __('An error occurred.', 'mhm-rentiva')]);
        }
    }

    /**
     * AJAX: Remove favorite
     */
    public static function ajax_remove_favorite(): void
    {
        try {
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mhm_rentiva_account')) {
                wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            }

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => __('Please login.', 'mhm-rentiva')]);
            }

            $vehicle_id = (int) ($_POST['vehicle_id'] ?? 0);
            $user_id = get_current_user_id();
            
            $favorites = get_user_meta($user_id, 'mhm_rentiva_favorites', true);

            if (!is_array($favorites)) {
                $favorites = [];
            }

            $favorites = array_map('intval', $favorites);
            $favorites = array_filter($favorites, static function (int $id) use ($vehicle_id): bool {
                return $id !== $vehicle_id;
            });
            $favorites = array_values($favorites);

            update_user_meta($user_id, 'mhm_rentiva_favorites', $favorites);

            wp_send_json_success([
                'message' => __('Vehicle removed from favorites.', 'mhm-rentiva'),
                'vehicle_id' => $vehicle_id,
                'favorites_count' => count($favorites),
                'action' => 'removed',
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => __('An error occurred.', 'mhm-rentiva')]);
        }
    }

    /**
     * Handle user registration
     */
    public static function handle_user_registration($user_id): void
    {
        // Save first name and last name
        if (isset($_POST['first_name'])) {
            update_user_meta($user_id, 'first_name', self::sanitize_text_field_safe($_POST['first_name']));
        }
        
        if (isset($_POST['last_name'])) {
            update_user_meta($user_id, 'last_name', self::sanitize_text_field_safe($_POST['last_name']));
        }
        
        // Save phone number
        if (isset($_POST['phone'])) {
            update_user_meta($user_id, 'phone', self::sanitize_text_field_safe($_POST['phone']));
        }
        
        // Newsletter subscription
        if (isset($_POST['newsletter']) && $_POST['newsletter'] === '1') {
            update_user_meta($user_id, 'newsletter_subscription', '1');
        }
        
        // Terms acceptance
        if (isset($_POST['terms_accepted'])) {
            update_user_meta($user_id, 'terms_accepted', '1');
            update_user_meta($user_id, 'terms_accepted_date', current_time('mysql'));
        }
    }

    /**
     * AJAX user registration
     */
    public static function ajax_register_user(): void
    {
        // Nonce check
        if (!wp_verify_nonce($_POST['register_nonce'] ?? '', 'register_user')) {
            wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            return;
        }

        // Required fields check
        $username = sanitize_user($_POST['user_login'] ?? '');
        $email = sanitize_email((string) ($_POST['user_email'] ?? ''));
        $password = $_POST['pass1'] ?? '';
        $password_confirm = $_POST['pass2'] ?? '';
        $first_name = self::sanitize_text_field_safe($_POST['first_name'] ?? '');
        $last_name = self::sanitize_text_field_safe($_POST['last_name'] ?? '');
        $phone = self::sanitize_text_field_safe($_POST['phone'] ?? '');

        // Validation
        if (empty($username) || empty($email) || empty($password)) {
            wp_send_json_error(['message' => __('All required fields must be filled.', 'mhm-rentiva')]);
            return;
        }

        if ($password !== $password_confirm) {
            wp_send_json_error(['message' => __('Passwords do not match.', 'mhm-rentiva')]);
            return;
        }

        if (strlen($password) < 6) {
            wp_send_json_error(['message' => __('Password must be at least 6 characters.', 'mhm-rentiva')]);
            return;
        }

        // Username and email check
        if (username_exists($username)) {
            wp_send_json_error(['message' => __('Username already exists.', 'mhm-rentiva')]);
            return;
        }

        if (email_exists($email)) {
            wp_send_json_error(['message' => __('Email already exists.', 'mhm-rentiva')]);
            return;
        }

        // Create user
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
            return;
        }

        // Save additional information
        if (!empty($first_name)) {
            update_user_meta($user_id, 'first_name', $first_name);
        }
        
        if (!empty($last_name)) {
            update_user_meta($user_id, 'last_name', $last_name);
        }
        
        if (!empty($phone)) {
            update_user_meta($user_id, 'phone', $phone);
        }
        
        // Newsletter subscription
        if (isset($_POST['newsletter']) && $_POST['newsletter'] === '1') {
            update_user_meta($user_id, 'newsletter_subscription', '1');
        }
        
        // Terms acceptance
        if (isset($_POST['terms_accepted'])) {
            update_user_meta($user_id, 'terms_accepted', '1');
            update_user_meta($user_id, 'terms_accepted_date', current_time('mysql'));
        }

        // ⭐ Send welcome email (if enabled)
        $send_welcome_email = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_welcome_email', '1') === '1';
        if ($send_welcome_email) {
            /* translators: %s: site name. */
            $subject = sprintf(__('Welcome to %s!', 'mhm-rentiva'), get_bloginfo('name'));
            $message = sprintf(
                /* translators: 1: customer name; 2: site name; 3: username; 4: email; 5: site/team signature. */
                __('Hello %1$s,

Welcome to %2$s! Your account has been created successfully.

You can now log in and start booking vehicles.

Username: %3$s
Email: %4$s

Best regards,
%5$s Team', 'mhm-rentiva'),
                $first_name ?: $username,
                get_bloginfo('name'),
                $username,
                $email,
                get_bloginfo('name')
            );
            
            // Send email
            $mail_sent = wp_mail($email, $subject, $message, [
                'Content-Type: text/plain; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
            ]);
            
            // Log if email failed (for debugging)
            if (!$mail_sent) {
                error_log('MHM Rentiva: Welcome email failed to send to ' . $email);
            }
        }

        // Auto login
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        wp_send_json_success([
            'message' => __('Registration successful!', 'mhm-rentiva'),
            'redirect_url' => self::get_account_url()
        ]);
    }

    /**
     * Handle registration form submission
     */
    public static function handle_registration_form(): void
    {
        if (!isset($_POST['action']) || $_POST['action'] !== 'mhm_rentiva_register') {
            return;
        }

        // Nonce check
        if (!wp_verify_nonce($_POST['register_nonce'] ?? '', 'register_user')) {
            wp_die(__('Security check failed.', 'mhm-rentiva'));
            return;
        }

        // Required fields check
        $username = sanitize_user($_POST['user_login'] ?? '');
        $email = sanitize_email((string) ($_POST['user_email'] ?? ''));
        $password = $_POST['pass1'] ?? '';
        $password_confirm = $_POST['pass2'] ?? '';
        $first_name = self::sanitize_text_field_safe($_POST['first_name'] ?? '');
        $last_name = self::sanitize_text_field_safe($_POST['last_name'] ?? '');
        $phone = self::sanitize_text_field_safe($_POST['phone'] ?? '');

        // Validation
        if (empty($username) || empty($email) || empty($password)) {
            wp_die(__('All required fields must be filled.', 'mhm-rentiva'));
            return;
        }

        if ($password !== $password_confirm) {
            wp_die(__('Passwords do not match.', 'mhm-rentiva'));
            return;
        }

        // Password validation based on security settings
        $password_min_length = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_password_min_length', 8);
        if (strlen($password) < (int) $password_min_length) {
            /* translators: %d placeholder. */
            wp_die(sprintf(__('Password must be at least %d characters.', 'mhm-rentiva'), $password_min_length));
            return;
        }

        // Special characters validation
        $password_special = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_password_require_special', '0');
        if ($password_special === '1') {
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password)) {
                wp_die(__('Password must contain at least one lowercase letter, one uppercase letter, one number, and one special character.', 'mhm-rentiva'));
                return;
            }
        }

        // Username and email check
        if (username_exists($username)) {
            wp_die(__('Username already exists.', 'mhm-rentiva'));
            return;
        }

        if (email_exists($email)) {
            wp_die(__('Email already exists.', 'mhm-rentiva'));
            return;
        }

        // Check if email verification is required
        $email_verification = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_email_verification', '0');
        
        if ($email_verification === '1') {
            // Create user with pending verification status
            $user_id = wp_create_user($username, $password, $email);

            if (is_wp_error($user_id)) {
                wp_die($user_id->get_error_message());
                return;
            }

            // Set user as pending verification
            update_user_meta($user_id, 'mhm_email_verified', '0');
            update_user_meta($user_id, 'mhm_verification_key', wp_generate_password(32, false));
            
            // ⭐ Send welcome email BEFORE verification email (if enabled)
            $send_welcome_email = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_welcome_email', '1') === '1';
            if ($send_welcome_email) {
                $subject = sprintf(__('Welcome to %s!', 'mhm-rentiva'), get_bloginfo('name'));
                $message = sprintf(
                    /* translators: 1: customer name; 2: site name; 3: username; 4: email; 5: site/team signature. */
                    __('Hello %1$s,

Welcome to %2$s! Your account has been created successfully.

Please verify your email address to activate your account. We have sent you a verification link.

Username: %3$s
Email: %4$s

Best regards,
%5$s Team', 'mhm-rentiva'),
                    $first_name ?: $username,
                    get_bloginfo('name'),
                    $username,
                    $email,
                    get_bloginfo('name')
                );
                
                wp_mail($email, $subject, $message, [
                    'Content-Type: text/plain; charset=UTF-8',
                    'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
                ]);
            }
            
            // Send verification email
            self::send_verification_email($user_id, $email);
            
            // Show verification message instead of auto-login
            wp_redirect(add_query_arg('verification_sent', '1', wp_login_url()));
            exit;
        } else {
            // Normal user creation (no verification required)
            $user_id = wp_create_user($username, $password, $email);

            if (is_wp_error($user_id)) {
                wp_die($user_id->get_error_message());
                return;
            }

            // Mark email as verified (no verification required)
            update_user_meta($user_id, 'mhm_email_verified', '1');
        }

        // Assign default customer role
        $default_role = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_default_role', 'customer');
        $user = new \WP_User($user_id);
        $user->set_role($default_role);

        // Save additional information
        if (!empty($first_name)) {
            update_user_meta($user_id, 'first_name', $first_name);
        }
        
        if (!empty($last_name)) {
            update_user_meta($user_id, 'last_name', $last_name);
        }
        
        if (!empty($phone)) {
            update_user_meta($user_id, 'phone', $phone);
        }
        
        // Newsletter subscription
        if (isset($_POST['newsletter']) && $_POST['newsletter'] === '1') {
            update_user_meta($user_id, 'newsletter_subscription', '1');
        }
        
        // Terms acceptance
        if (isset($_POST['terms_accepted'])) {
            update_user_meta($user_id, 'terms_accepted', '1');
            update_user_meta($user_id, 'terms_accepted_date', current_time('mysql'));
        }

        // ⭐ Send welcome email (if enabled) - Send even if email verification is required
        $send_welcome_email = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_welcome_email', '1') === '1';
        if ($send_welcome_email) {
            $subject = sprintf(__('Welcome to %s!', 'mhm-rentiva'), get_bloginfo('name'));
            
            // Different message if email verification is required
            if ($email_verification === '1') {
                $message = sprintf(
                    /* translators: 1: customer name; 2: site name; 3: username; 4: email; 5: site/team signature. */
                    __('Hello %1$s,

Welcome to %2$s! Your account has been created successfully.

Please verify your email address to activate your account. We have sent you a verification link.

Username: %3$s
Email: %4$s

Best regards,
%5$s Team', 'mhm-rentiva'),
                    $first_name ?: $username,
                    get_bloginfo('name'),
                    $username,
                    $email,
                    get_bloginfo('name')
                );
            } else {
                $message = sprintf(
                    /* translators: 1: customer name; 2: site name; 3: username; 4: email; 5: site/team signature. */
                    __('Hello %1$s,

Welcome to %2$s! Your account has been created successfully.

You can now log in and start booking vehicles.

Username: %3$s
Email: %4$s

Best regards,
%5$s Team', 'mhm-rentiva'),
                    $first_name ?: $username,
                    get_bloginfo('name'),
                    $username,
                    $email,
                    get_bloginfo('name')
                );
            }
            
            wp_mail($email, $subject, $message, [
                'Content-Type: text/plain; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
            ]);
        }

        // Auto login after registration (if enabled and email verification is not required)
        $auto_login = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_auto_login', '1');
        if ($auto_login === '1' && $email_verification !== '1') {
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
        }

        // Redirect to account page (or login page if verification required)
        if ($email_verification === '1') {
            wp_redirect(add_query_arg('verification_sent', '1', wp_login_url()));
        } else {
            wp_redirect(self::get_account_url());
        }
        exit;
    }

    /**
     * Send verification email to user
     */
    public static function send_verification_email(int $user_id, string $email): void
    {
        $verification_key = get_user_meta($user_id, 'mhm_verification_key', true);
        $verification_url = add_query_arg([
            'action' => 'verify_email',
            'user_id' => $user_id,
            'key' => $verification_key
        ], home_url());

        $subject = __('Verify Your Email Address', 'mhm-rentiva');
        $message = sprintf(
            /* translators: %s placeholder. */
            __('Please click the following link to verify your email address: %s', 'mhm-rentiva'),
            $verification_url
        );

        wp_mail($email, $subject, $message);
    }

    /**
     * Handle email verification
     */
    public static function handle_email_verification(): void
    {
        if (!isset($_GET['action']) || $_GET['action'] !== 'verify_email') {
            return;
        }

        $user_id = (int) ($_GET['user_id'] ?? 0);
        $key = self::sanitize_text_field_safe($_GET['key'] ?? '');

        if (!$user_id || !$key) {
            wp_die(__('Invalid verification link.', 'mhm-rentiva'));
            return;
        }

        $stored_key = get_user_meta($user_id, 'mhm_verification_key', true);
        
        if ($key !== $stored_key) {
            wp_die(__('Invalid verification key.', 'mhm-rentiva'));
            return;
        }

        // Mark email as verified
        update_user_meta($user_id, 'mhm_email_verified', '1');
        delete_user_meta($user_id, 'mhm_verification_key');

        // Auto login if enabled
        $auto_login = \MHMRentiva\Admin\Settings\Core\SettingsCore::get('mhm_rentiva_customer_auto_login', '1');
        if ($auto_login === '1') {
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
        }

        wp_redirect(add_query_arg('email_verified', '1', self::get_account_url()));
        exit;
    }

    /**
     * Handle communication preferences update
     */
    public static function handle_communication_preferences(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        if (!isset($_POST['update_comm_preferences'])) {
            return;
        }

        // Nonce check
        if (!wp_verify_nonce($_POST['comm_prefs_nonce'] ?? '', 'update_comm_preferences')) {
            wp_die(__('Security check failed.', 'mhm-rentiva'));
            return;
        }

        $user_id = get_current_user_id();
        
        // Update communication preferences
        $welcome_email = isset($_POST['welcome_email']) ? '1' : '0';
        $booking_notifications = isset($_POST['booking_notifications']) ? '1' : '0';
        $marketing_emails = isset($_POST['marketing_emails']) ? '1' : '0';
        
        update_user_meta($user_id, 'mhm_welcome_email', $welcome_email);
        update_user_meta($user_id, 'mhm_booking_notifications', $booking_notifications);
        update_user_meta($user_id, 'mhm_marketing_emails', $marketing_emails);
        
        // Redirect back to dashboard with success message
        wp_redirect(add_query_arg('comm_prefs_updated', '1', self::get_account_url()));
        exit;
    }

    /**
     * AJAX: Cancel Booking
     * Handles booking cancellation requests from frontend
     */
    public static function ajax_cancel_booking(): void
    {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => __('You must be logged in to cancel a booking.', 'mhm-rentiva')
            ], 403);
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(self::sanitize_text_field_safe($_POST['nonce']), 'mhm_cancel_booking_nonce')) {
            wp_send_json_error([
                'message' => __('Security verification failed. Please refresh and try again.', 'mhm-rentiva')
            ], 400);
        }

        // Get booking ID
        $booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        if ($booking_id === 0) {
            wp_send_json_error([
                'message' => __('Invalid booking ID.', 'mhm-rentiva')
            ], 400);
        }

        // Get cancellation reason (optional)
        $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';

        // Get current user ID
        $user_id = get_current_user_id();

        // Use CancellationHandler to process cancellation
        $result = CancellationHandler::cancel_booking($booking_id, $user_id, $reason, false);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ], 400);
        }

        // Success
        wp_send_json_success([
            'message' => __('Booking cancelled successfully. A confirmation email has been sent.', 'mhm-rentiva'),
            'booking_id' => $booking_id
        ]);
    }
    /**
     * Get booking view URL
     * Handles both standalone and WooCommerce environments
     * 
     * @param int $booking_id Booking ID
     * @return string URL
     */
    public static function get_booking_view_url(int $booking_id): string
    {
        // WooCommerce Integration
        if (class_exists('WooCommerce') && function_exists('wc_get_endpoint_url')) {
            // Get base bookings URL
            $bookings_slug = self::get_endpoint_slug('bookings', 'rentiva-bookings');
            $url = wc_get_endpoint_url($bookings_slug, '', wc_get_page_permalink('myaccount'));
            
            // Add params for detail view
            return add_query_arg([
                'endpoint' => 'booking-detail', 
                'booking_id' => $booking_id
            ], $url);
        }
        
        // Standalone
        return add_query_arg([
            'endpoint' => 'booking-detail', 
            'booking_id' => $booking_id
        ], self::get_account_url());
    }
}

