<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Meta;

use MHMRentiva\Admin\Core\MetaBoxes\AbstractMetaBox;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vehicle Gallery Meta Box
 * 
 * Manages vehicle images with WordPress Media Library integration.
 */
final class VehicleGallery extends AbstractMetaBox
{
    /**
     * Safe sanitize text field that handles null values
     */
    public static function sanitize_text_field_safe($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        return sanitize_text_field(wp_unslash((string) $value));
    }

    protected static function get_post_type(): string
    {
        return 'vehicle';
    }

    protected static function get_meta_box_id(): string
    {
        return 'mhm_rentiva_vehicle_gallery';
    }

    protected static function get_title(): string
    {
        return __('Vehicle Gallery', 'mhm-rentiva');
    }

    protected static function get_fields(): array
    {
        return [
            'mhm_rentiva_vehicle_gallery' => [
                'title' => __('Vehicle Gallery', 'mhm-rentiva'),
                'context' => 'side',
                'priority' => 'high',
                'template' => 'render_gallery_meta_box',
            ],
        ];
    }

    public static function register(): void
    {
        parent::register();

        add_action('init', [self::class, 'register_meta_fields']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts']);
        add_action('save_post_vehicle', [self::class, 'save_gallery_images']);

        add_action('wp_ajax_mhm_add_gallery_image', [self::class, 'ajax_add_gallery_image']);
        add_action('wp_ajax_mhm_remove_gallery_image', [self::class, 'ajax_remove_gallery_image']);
        add_action('wp_ajax_mhm_reorder_gallery_images', [self::class, 'ajax_reorder_gallery_images']);
    }

    /**
     * Register meta fields
     */
    public static function register_meta_fields(): void
    {
        register_post_meta('vehicle', '_mhm_rentiva_gallery_images', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => [self::class, 'sanitize_gallery_images'],
        ]);
    }

    /**
     * Enqueue scripts and styles
     */
    public static function enqueue_scripts(): void
    {
        global $post_type;

        if ($post_type !== 'vehicle') {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_script(
            'mhm-vehicle-gallery',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/vehicle-gallery.js',
            ['jquery', 'media-upload', 'media-views'],
            MHM_RENTIVA_VERSION,
            true
        );

        wp_enqueue_style(
            'mhm-vehicle-gallery',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/vehicle-gallery.css',
            [],
            MHM_RENTIVA_VERSION
        );

        // ⭐ Get max gallery images from settings (default: 50)
        $max_gallery_images = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get(
            'mhm_rentiva_vehicle_max_gallery_images',
            50 // Default: 50 images
        );

        wp_localize_script('mhm-vehicle-gallery', 'mhmVehicleGallery', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mhm_vehicle_gallery_nonce'),
            'maxImages' => $max_gallery_images,
            'strings' => [
                'selectImages' => __('Select Images', 'mhm-rentiva'),
                'addImages' => __('Add Image', 'mhm-rentiva'),
                'removeImage' => __('Remove Image', 'mhm-rentiva'),
                'setAsFeatured' => __('Set as Featured Image', 'mhm-rentiva'),
                'noImages' => __('No images added yet', 'mhm-rentiva'),
                /* translators: %d placeholder. */
                'maxImages' => sprintf(__('You can add maximum %d images', 'mhm-rentiva'), $max_gallery_images),
                'confirmRemove' => __('Are you sure you want to remove this image?', 'mhm-rentiva'),
                'uploading' => __('Uploading...', 'mhm-rentiva'),
                'uploadError' => __('Error occurred while uploading image', 'mhm-rentiva'),
            ]
        ]);
    }

    /**
     * Render gallery meta box
     */
    public static function render_gallery_meta_box(\WP_Post $post): void
    {
        $gallery_images = get_post_meta($post->ID, '_mhm_rentiva_gallery_images', true);
        $gallery_images = $gallery_images ? json_decode($gallery_images, true) : [];

        include MHM_RENTIVA_PLUGIN_PATH . 'src/Admin/Vehicle/Templates/vehicle-gallery.php';
    }

    /**
     * Save gallery images
     */
    public static function save_gallery_images(int $post_id): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (
            !isset($_POST['mhm_rentiva_gallery_images_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mhm_rentiva_gallery_images_nonce'])), 'mhm_rentiva_gallery_images')
        ) {
            return;
        }

        if (isset($_POST['mhm_rentiva_gallery_images'])) {
            $gallery_images = self::sanitize_text_field_safe($_POST['mhm_rentiva_gallery_images']);
            update_post_meta($post_id, '_mhm_rentiva_gallery_images', $gallery_images);
        }
    }

    /**
     * Sanitize gallery images
     */
    public static function sanitize_gallery_images($value): string
    {
        if (empty($value)) {
            return '';
        }

        $images = json_decode($value, true);
        if (!is_array($images)) {
            return '';
        }

        $sanitized_images = [];
        foreach ($images as $image) {
            if (isset($image['id']) && is_numeric($image['id'])) {
                $sanitized_images[] = [
                    'id' => intval($image['id']),
                    'url' => esc_url_raw($image['url'] ?? ''),
                    'alt' => self::sanitize_text_field_safe($image['alt'] ?? ''),
                    'title' => self::sanitize_text_field_safe($image['title'] ?? ''),
                ];
            }
        }

        return wp_json_encode($sanitized_images);
    }

    /**
     * AJAX: Add gallery image
     */
    public static function ajax_add_gallery_image(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mhm_vehicle_gallery_nonce')) {
            wp_send_json_error(__('Security error', 'mhm-rentiva'));
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission error', 'mhm-rentiva'));
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $image_ids = array_map('intval', $_POST['image_ids'] ?? []);

        if (!$post_id || empty($image_ids)) {
            wp_send_json_error(__('Invalid data', 'mhm-rentiva'));
        }

        $gallery_images = get_post_meta($post_id, '_mhm_rentiva_gallery_images', true);
        $gallery_images = $gallery_images ? json_decode($gallery_images, true) : [];

        $existing_ids = array_column($gallery_images, 'id');

        foreach ($image_ids as $image_id) {
            if (!in_array($image_id, $existing_ids) && count($gallery_images) < 10) {
                $image_url = wp_get_attachment_image_url($image_id, 'medium');
                $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
                $image_title = get_the_title($image_id);

                $gallery_images[] = [
                    'id' => $image_id,
                    'url' => $image_url,
                    'alt' => $image_alt,
                    'title' => $image_title,
                ];
            }
        }

        update_post_meta($post_id, '_mhm_rentiva_gallery_images', wp_json_encode($gallery_images));

        wp_send_json_success([
            'message' => __('Images successfully added', 'mhm-rentiva'),
            'gallery_images' => $gallery_images
        ]);
    }

    /**
     * AJAX: Remove gallery image
     */
    public static function ajax_remove_gallery_image(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mhm_vehicle_gallery_nonce')) {
            wp_send_json_error(__('Security error', 'mhm-rentiva'));
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission error', 'mhm-rentiva'));
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $image_id = intval($_POST['image_id'] ?? 0);

        if (!$post_id || !$image_id) {
            wp_send_json_error(__('Invalid data', 'mhm-rentiva'));
        }

        $gallery_images = get_post_meta($post_id, '_mhm_rentiva_gallery_images', true);
        $gallery_images = $gallery_images ? json_decode($gallery_images, true) : [];

        $gallery_images = array_filter($gallery_images, function ($image) use ($image_id) {
            return $image['id'] !== $image_id;
        });

        update_post_meta($post_id, '_mhm_rentiva_gallery_images', wp_json_encode(array_values($gallery_images)));

        wp_send_json_success([
            'message' => __('Image successfully removed', 'mhm-rentiva'),
            'gallery_images' => array_values($gallery_images)
        ]);
    }

    /**
     * AJAX: Reorder gallery images
     */
    public static function ajax_reorder_gallery_images(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mhm_vehicle_gallery_nonce')) {
            wp_send_json_error(__('Security error', 'mhm-rentiva'));
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission error', 'mhm-rentiva'));
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $image_order = array_map('intval', $_POST['image_order'] ?? []);

        if (!$post_id || empty($image_order)) {
            wp_send_json_error(__('Invalid data', 'mhm-rentiva'));
        }

        $gallery_images = get_post_meta($post_id, '_mhm_rentiva_gallery_images', true);
        $gallery_images = $gallery_images ? json_decode($gallery_images, true) : [];

        $reordered_images = [];
        foreach ($image_order as $image_id) {
            foreach ($gallery_images as $image) {
                if ($image['id'] === $image_id) {
                    $reordered_images[] = $image;
                    break;
                }
            }
        }

        update_post_meta($post_id, '_mhm_rentiva_gallery_images', wp_json_encode($reordered_images));

        wp_send_json_success([
            'message' => __('Images successfully reordered', 'mhm-rentiva'),
            'gallery_images' => $reordered_images
        ]);
    }

    /**
     * Get gallery images
     */
    public static function get_gallery_images(int $post_id): array
    {
        $gallery_images = get_post_meta($post_id, '_mhm_rentiva_gallery_images', true);
        return $gallery_images ? json_decode($gallery_images, true) : [];
    }

    /**
     * Get gallery images for frontend use
     */
    public static function get_gallery_for_frontend(int $post_id, string $size = 'medium'): array
    {
        $gallery_images = self::get_gallery_images($post_id);
        $frontend_images = [];

        foreach ($gallery_images as $image) {
            $image_url = wp_get_attachment_image_url($image['id'], $size);
            if ($image_url) {
                $frontend_images[] = [
                    'id' => $image['id'],
                    'url' => $image_url,
                    'alt' => $image['alt'],
                    'title' => $image['title'],
                    'full_url' => wp_get_attachment_image_url($image['id'], 'full'),
                    'thumbnail_url' => wp_get_attachment_image_url($image['id'], 'thumbnail'),
                ];
            }
        }

        return $frontend_images;
    }
}
