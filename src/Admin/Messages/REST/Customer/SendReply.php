<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\REST\Customer;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\PostTypes\Message\Message;
use MHMRentiva\Admin\Messages\REST\Helpers\Auth;
use MHMRentiva\Admin\Messages\REST\Helpers\MessageQuery;
use MHMRentiva\Admin\Messages\Core\MessageCache;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

final class SendReply
{
    /**
     * Customer reply sending (WordPress User Auth)
     */
    public static function handle(WP_REST_Request $request): WP_REST_Response
    {
        if (!Mode::featureEnabled(Mode::FEATURE_MESSAGES)) {
            return new WP_REST_Response(['error' => __('Messaging feature is not active', 'mhm-rentiva')], 403);
        }

        // WordPress user authentication check
        if (!is_user_logged_in()) {
            return new WP_REST_Response([
                'error' => __('Please login to send reply.', 'mhm-rentiva')
            ], 401);
        }

        $user = wp_get_current_user();
        $customer_email = $user->user_email;
        $customer_name = $user->display_name ?: $user->user_login;

        $thread_id = $request->get_param('thread_id');
        $message_content = $request->get_param('message');

        // Check if thread belongs to this customer
        if (!MessageQuery::verifyCustomerThread($thread_id, $customer_email)) {
            return new WP_REST_Response(['error' => __('Thread not found', 'mhm-rentiva')], 404);
        }

        // Check if thread is open
        $thread_messages = Message::get_thread_messages($thread_id);
        
        if (empty($thread_messages)) {
            return new WP_REST_Response(['error' => __('Thread not found or empty', 'mhm-rentiva')], 404);
        }
        
        $last_message = end($thread_messages);
        
        if (!$last_message || !isset($last_message->ID)) {
            return new WP_REST_Response(['error' => __('Invalid thread data', 'mhm-rentiva')], 400);
        }
        
        $last_meta = Message::get_message_meta($last_message->ID);
        
        if (!isset($last_meta['status'])) {
            $last_meta['status'] = 'pending';
        }

        if ($last_meta['status'] === 'closed') {
            return new WP_REST_Response(['error' => __('This conversation is closed', 'mhm-rentiva')], 400);
        }

        $subject = !empty($last_message->post_title) ? $last_message->post_title : __('Message', 'mhm-rentiva');
        
        $reply_data = [
            'subject' => 'Re: ' . $subject,
            'message' => $message_content,
            'message_type' => 'customer_to_admin',
            'customer_email' => $customer_email,
            'customer_name' => $customer_name,
            'thread_id' => $thread_id, // Can be UUID string or integer
            'parent_message_id' => $last_message->ID,
        ];

        $reply_id = Message::create_message($reply_data);

        if (!$reply_id) {
            return new WP_REST_Response(['error' => __('Reply could not be sent', 'mhm-rentiva')], 400);
        }

        // Ana mesajın status'unu "pending" olarak güncelle (müşteri yeni cevap gönderdi)
        // Ana mesajı bul: thread_id'deki parent_message_id = 0 olan mesaj
        global $wpdb;
        
        // Thread ID can be integer or UUID string
        $main_message_id = $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_thread ON p.ID = pm_thread.post_id AND pm_thread.meta_key = '_mhm_thread_id' AND pm_thread.meta_value = %s
             LEFT JOIN {$wpdb->postmeta} pm_parent ON p.ID = pm_parent.post_id AND pm_parent.meta_key = '_mhm_parent_message_id'
             WHERE p.post_type = 'mhm_message'
             AND p.post_status = 'publish'
             AND (pm_parent.meta_value IS NULL OR pm_parent.meta_value = '' OR pm_parent.meta_value = '0')
             ORDER BY p.post_date ASC
             LIMIT 1",
            $thread_id
        ));
        
        // Ana mesajı bulamazsak, thread_id'yi direkt kullan (eğer thread_id numeric ise)
        if (!$main_message_id && is_numeric($thread_id)) {
            $main_message_id = (int) $thread_id;
        }
        
        // Ana mesajın status'unu güncelle
        if ($main_message_id && is_numeric($main_message_id) && get_post((int) $main_message_id)) {
            $main_message_id_int = (int) $main_message_id;
            
            // Direkt meta güncelle (Message::update_message_status cache sorunları olabilir)
            $updated = update_post_meta($main_message_id_int, '_mhm_message_status', 'pending');
            
            // Doğrulama: status'un gerçekten güncellendiğini kontrol et
            $current_status = get_post_meta($main_message_id_int, '_mhm_message_status', true);
            
            // Eğer hala güncellenmediyse, tekrar dene
            if ($current_status !== 'pending') {
                delete_post_meta($main_message_id_int, '_mhm_message_status');
                add_post_meta($main_message_id_int, '_mhm_message_status', 'pending', true);
            }
            
            // Cache temizle
            MessageCache::clear_message_cache($main_message_id_int);
            MessageCache::flush(); // Tüm cache'i temizle
            clean_post_cache($main_message_id_int);
            
            // WordPress object cache'i de temizle
            wp_cache_delete($main_message_id_int, 'posts');
            wp_cache_delete($main_message_id_int, 'post_meta');
            
            // Transient cache'i de temizle
            delete_transient('mhm_message_' . $main_message_id_int);
        }
        
        // Reply için de cache temizle (reply_id is always integer)
        if ($reply_id && is_numeric($reply_id)) {
            MessageCache::clear_message_cache((int) $reply_id);
            clean_post_cache((int) $reply_id);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Your reply has been sent successfully', 'mhm-rentiva'),
            'reply_id' => $reply_id,
        ], 200);
    }
}
