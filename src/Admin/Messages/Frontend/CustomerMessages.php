<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\Frontend;

use MHMRentiva\Admin\PostTypes\Message\Message;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Messages\Settings\MessagesSettings;

if (!defined('ABSPATH')) {
    exit;
}

final class CustomerMessages
{
    /**
     * @deprecated 4.0.0 Token-based portal hooks kaldırıldı, My Account sistemi kullanılıyor
     */
    public static function register(): void
    {
        // ⭐ v4.0.0: Portal hooks completely removed
        // Messaging is now integrated into My Account system
        // Deprecated methods removed, hook registrations also removed
    }

    public static function add_query_vars(array $vars): array
    {
        $vars[] = 'mhm_messages';
        return $vars;
    }

    // ☠️ DEAD CODE REMOVED: maybe_render_messages() - deprecated in v4.0.0, empty function

    /**
     * @deprecated 4.0.0 Token verification kaldırıldı - WordPress Login kullanılıyor
     */
    private static function verify_customer_token(string $token): ?array
    {
        // This simple implementation, real project should use secure token validation
        // For now, session or temporary token system can be used
        
        // Temporary solution: decode token and get customer information
        $decoded = base64_decode($token);
        if (!$decoded) {
            return null;
        }

        $data = json_decode($decoded, true);
        if (!$data || !isset($data['email'])) {
            return null;
        }

        // Customer check (has booking?)
        global $wpdb;
        $customer_check = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = 'vehicle_booking'
             AND p.post_status = 'publish'
             AND pm.meta_key = '_mhm_contact_email'
             AND pm.meta_value = %s",
            $data['email']
        ));

        if (!$customer_check) {
            return null;
        }

        return [
            'email' => $data['email'],
            'name' => $data['name'] ?? '',
            'token' => $token,
        ];
    }

    /**
     * @deprecated 4.0.0 Portal tabs kaldırıldı - My Account sistemi kullanılıyor
     */
    public static function add_portal_tab(): void
    {
        // ⭐ v4.0.0: Portal tabs removed
        return;
    }

    /**
     * @deprecated 4.0.0 Portal content hooks removed - My Account system is used
     */
    public static function render_portal_messages(): void
    {
        if (!Mode::featureEnabled(Mode::FEATURE_MESSAGES)) {
            echo '<div class="portal-section">';
            echo '<p>' . esc_html__('Messaging feature is available in Pro version.', 'mhm-rentiva') . '</p>';
            echo '</div>';
            return;
        }

        // Get customer information (from portal context)
        $customer_email = ''; // Should be retrieved from portal context
        $customer_name = '';  // Should be retrieved from portal context

        ?>
        <div class="portal-section messages-section">
            <div class="messages-header">
                <h3><?php _e('Support Messages', 'mhm-rentiva'); ?></h3>
                <button class="button button-primary" id="new-message-btn">
                    <?php _e('Yeni Mesaj', 'mhm-rentiva'); ?>
                </button>
            </div>

            <div class="messages-list" id="messages-list">
                <div class="loading"><?php _e('Loading messages...', 'mhm-rentiva'); ?></div>
            </div>

            <div class="message-thread" id="message-thread" class="hidden">
                <div class="thread-header">
                    <button class="back-to-list">&larr; <?php _e('Back to Messages', 'mhm-rentiva'); ?></button>
                    <h4 id="thread-subject"></h4>
                </div>
                <div class="thread-messages" id="thread-messages"></div>
                <div class="thread-reply" id="thread-reply" class="hidden">
                    <form id="reply-form">
                        <div class="form-group">
                            <label for="reply-message"><?php _e('Your Reply:', 'mhm-rentiva'); ?></label>
                            <textarea id="reply-message" name="message" rows="4" required></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="button button-primary">
                                <?php _e('Send', 'mhm-rentiva'); ?>
                            </button>
                            <button type="button" class="button cancel-reply">
                                <?php _e('Cancel', 'mhm-rentiva'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="new-message-form" id="new-message-form" class="hidden">
                <div class="form-header">
                    <h4><?php _e('Send New Message', 'mhm-rentiva'); ?></h4>
                    <button class="close-form">&times;</button>
                </div>
                <form id="send-message-form">
                    <div class="form-group">
                        <label for="message-category"><?php _e('Kategori:', 'mhm-rentiva'); ?></label>
                        <select id="message-category" name="category" required>
                            <?php foreach (MessagesSettings::get_categories() as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="message-subject"><?php _e('Konu:', 'mhm-rentiva'); ?></label>
                        <input type="text" id="message-subject" name="subject" required>
                    </div>

                    <div class="form-group">
                        <label for="message-content"><?php _e('Your Message:', 'mhm-rentiva'); ?></label>
                        <textarea id="message-content" name="message" rows="6" required></textarea>
                    </div>

                    <div class="form-group">
                        <label><?php _e('Booking Association (Optional):', 'mhm-rentiva'); ?></label>
                        <select id="message-booking" name="booking_id">
                            <option value=""><?php _e('Select booking', 'mhm-rentiva'); ?></option>
                            <!-- AJAX ile doldurulacak -->
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="button button-primary">
                            <?php _e('Send Message', 'mhm-rentiva'); ?>
                        </button>
                        <button type="button" class="button close-form">
                            <?php _e('Cancel', 'mhm-rentiva'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        // Global variables will be defined in separate JS file
        window.mhmCustomerMessages = {
            customerEmail: '<?php echo esc_js($customer_email); ?>',
            customerName: '<?php echo esc_js($customer_name); ?>',
            ajaxUrl: '<?php echo esc_url(\MHMRentiva\Admin\Messages\Core\MessageUrlHelper::get_ajax_url()); ?>',
            nonce: '<?php echo wp_create_nonce('mhm_customer_messages'); ?>'
        };
        </script>
        <?php
    }

    private static function render_customer_messages_page(array $customer_data): void
    {
        ?>
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php _e('My Messages', 'mhm-rentiva'); ?> - <?php echo esc_html(get_bloginfo('name')); ?></title>
            <!-- CSS ayrı dosyaya taşınacak -->
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php _e('My Messages', 'mhm-rentiva'); ?></h1>
                    <p>
                        <?php
                        /* translators: %s: customer name. */
                        printf(__('Hello %s, you can manage your support messages here.', 'mhm-rentiva'), esc_html($customer_data['name']));
                        ?>
                    </p>
                </div>
                <div class="content">
                    <div id="messages-container">
                        <!-- AJAX ile doldurulacak -->
                    </div>
                    <button class="button" id="new-message-btn"><?php _e('Yeni Mesaj', 'mhm-rentiva'); ?></button>
                </div>
            </div>

            <!-- JavaScript ayrı dosyaya taşınacak -->
        </body>
        </html>
        <?php
    }
}
