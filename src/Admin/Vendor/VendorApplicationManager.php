<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor;

use MHMRentiva\Admin\Vendor\PostType\VendorApplication;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * CRUD operations and state transitions for vendor applications.
 */
final class VendorApplicationManager
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'publish';
    public const STATUS_REJECTED = 'trash';

    /**
     * Determine if a user is eligible to submit a vendor application.
     */
    public static function can_apply(int $user_id): bool
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        if (in_array('rentiva_vendor', (array) $user->roles, true)) {
            return false;
        }

        $existing = get_posts(array(
            'post_type'      => VendorApplication::POST_TYPE,
            'author'         => $user_id,
            'post_status'    => self::STATUS_PENDING,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ));

        return empty($existing);
    }

    /**
     * Create a new vendor application.
     *
     * @param  int   $user_id WordPress user ID.
     * @param  array $data    Application field data.
     * @return int|\WP_Error Post ID on success, WP_Error on failure.
     */
    public static function create_application(int $user_id, array $data)
    {
        if (!self::can_apply($user_id)) {
            return new \WP_Error(
                'cannot_apply',
                __('You are not eligible to submit a vendor application.', 'mhm-rentiva')
            );
        }

        $user  = get_userdata($user_id);
        $title = sprintf(
            /* translators: %s: user display name */
            __('Vendor Application — %s', 'mhm-rentiva'),
            $user ? $user->display_name : (string) $user_id
        );

        $post_id = wp_insert_post(array(
            'post_type'   => VendorApplication::POST_TYPE,
            'post_author' => $user_id,
            'post_status' => self::STATUS_PENDING,
            'post_title'  => sanitize_text_field($title),
        ), true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        update_post_meta($post_id, '_vendor_phone',         sanitize_text_field($data['phone'] ?? ''));
        update_post_meta($post_id, '_vendor_city',          sanitize_text_field($data['city'] ?? ''));

        $encrypted_iban = self::encrypt_iban($data['iban'] ?? '');
        if (($data['iban'] ?? '') !== '' && $encrypted_iban === '') {
            return new \WP_Error(
                'iban_encryption_failed',
                __('IBAN could not be encrypted. Please contact support.', 'mhm-rentiva')
            );
        }
        update_post_meta($post_id, '_vendor_iban', $encrypted_iban);
        update_post_meta($post_id, '_vendor_service_areas', array_map('sanitize_text_field', (array) ($data['service_areas'] ?? array())));
        update_post_meta($post_id, '_vendor_profile_bio',   sanitize_textarea_field($data['bio'] ?? ''));
        update_post_meta($post_id, '_vendor_tax_number',    sanitize_text_field($data['tax_number'] ?? ''));
        update_post_meta($post_id, '_vendor_doc_id',        (int) ($data['doc_id'] ?? 0));
        update_post_meta($post_id, '_vendor_doc_license',   (int) ($data['doc_license'] ?? 0));
        update_post_meta($post_id, '_vendor_doc_address',   (int) ($data['doc_address'] ?? 0));
        update_post_meta($post_id, '_vendor_doc_insurance', (int) ($data['doc_insurance'] ?? 0));
        update_post_meta($post_id, '_vendor_status',        self::STATUS_PENDING);

        return $post_id;
    }

    /**
     * Get an application post with validation.
     *
     * @return \WP_Post|\WP_Error
     */
    public static function get_application(int $application_id)
    {
        $post = get_post($application_id);
        if (!$post instanceof \WP_Post || $post->post_type !== VendorApplication::POST_TYPE) {
            return new \WP_Error('invalid_application', __('Invalid application ID.', 'mhm-rentiva'));
        }
        return $post;
    }

    /**
     * Encrypt IBAN for storage.
     * Returns empty string if OpenSSL is unavailable or encryption fails — never stores plain text.
     */
    public static function encrypt_iban(string $iban): string
    {
        if ($iban === '') {
            return '';
        }

        if (!extension_loaded('openssl')) {
            \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::warning(
                'OpenSSL not loaded — IBAN cannot be encrypted. Install the OpenSSL PHP extension.'
            );
            return ''; // Do NOT store plain text
        }

        $key    = substr(hash('sha256', AUTH_KEY . SECURE_AUTH_SALT), 0, 32);
        $iv     = openssl_random_pseudo_bytes(16);
        $cipher = openssl_encrypt($iban, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($cipher === false) {
            \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::warning(
                'IBAN encryption failed — openssl_encrypt returned false.'
            );
            return '';
        }

        return base64_encode($iv . $cipher); // Both $iv and $cipher are raw bytes — safe concatenation
    }

    /**
     * Decrypt a stored IBAN value.
     */
    public static function decrypt_iban(string $encrypted): string
    {
        if ($encrypted === '') {
            return '';
        }

        if (!extension_loaded('openssl')) {
            return '';
        }

        $key  = substr(hash('sha256', AUTH_KEY . SECURE_AUTH_SALT), 0, 32);
        $raw  = base64_decode($encrypted, true);

        if ($raw === false || strlen($raw) <= 16) {
            return '';
        }

        $iv   = substr($raw, 0, 16);
        $data = substr($raw, 16);

        $plain = openssl_decrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $plain !== false ? $plain : '';
    }
}
