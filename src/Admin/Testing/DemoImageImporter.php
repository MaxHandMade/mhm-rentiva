<?php
declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

namespace MHMRentiva\Admin\Testing;

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Demo image cleanup intentionally queries by meta to perform surgical removal.

/**
 * DemoImageImporter
 *
 * Imports demo vehicle WebP images from the plugin's assets/demo/images/ directory
 * into the WordPress Media Library and cleans them up on removal.
 *
 * All imported attachments are tagged with `_mhm_is_demo = '1'` post meta so
 * they can be identified and batch-deleted without touching real uploads.
 *
 * @package MHMRentiva\Admin\Testing
 * @since   4.25.1
 */
final class DemoImageImporter
{
    /**
     * Returns the absolute path to the assets/demo/images/ directory.
     *
     * @since 4.25.1
     * @return string Trailing-slash included directory path.
     */
    public static function get_images_dir(): string
    {
        return MHM_RENTIVA_PLUGIN_PATH . 'assets/demo/images/';
    }

    /**
     * Returns an array of filename => absolute_path for every .webp file found
     * in the images directory.
     *
     * @since  4.25.1
     * @return array<string, string> Keyed by basename, value is full path.
     */
    public static function get_available_images(): array
    {
        $dir = self::get_images_dir();

        if (! is_dir($dir)) {
            return array();
        }

        $files  = glob($dir . '*.webp');
        $result = array();

        if (! is_array($files)) {
            return array();
        }

        foreach ($files as $file) {
            $result[ basename($file) ] = $file;
        }

        return $result;
    }

    /**
     * Imports a single WebP file into the WordPress Media Library.
     *
     * Copies the source file to wp_upload_dir(), creates the attachment post,
     * generates and saves metadata, then tags the attachment with _mhm_is_demo.
     *
     * @since  4.25.1
     * @param  string $filename Basename of the file (e.g. 'economy-01.webp').
     * @return int Attachment ID on success, 0 on failure.
     */
    public static function import(string $filename): int
    {
        $images     = self::get_available_images();
        $source     = $images[ $filename ] ?? '';

        if ('' === $source || ! file_exists($source)) {
            return 0;
        }

        // Load required WP helpers.
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $upload_dir = wp_upload_dir();

        if (! empty($upload_dir['error'])) {
            return 0;
        }

        $target_path = $upload_dir['path'] . DIRECTORY_SEPARATOR . $filename;

        if (! copy($source, $target_path)) {
            return 0;
        }

        $target_url  = $upload_dir['url'] . '/' . $filename;
        $file_type   = wp_check_filetype($filename, null);

        $attachment = array(
            'guid'           => $target_url,
            'post_mime_type' => $file_type['type'] ?: 'image/webp',
            'post_title'     => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        $attach_id = wp_insert_attachment($attachment, $target_path);

        if (is_wp_error($attach_id) || $attach_id <= 0) {
            @unlink( $target_path ); // Remove orphaned physical file
            return 0;
        }

        $metadata = wp_generate_attachment_metadata($attach_id, $target_path);
        wp_update_attachment_metadata($attach_id, $metadata);

        update_post_meta($attach_id, '_mhm_is_demo', '1');

        return $attach_id;
    }

    /**
     * Imports all available demo images.
     *
     * @since  4.25.1
     * @return array<string, int> Map of filename => attachment_id (0 on failure).
     */
    public static function import_all(): array
    {
        $images = self::get_available_images();
        $result = array();

        foreach ($images as $filename => $path) {
            $result[ $filename ] = self::import($filename);
        }

        return $result;
    }

    /**
     * Deletes all attachments tagged with _mhm_is_demo = '1'.
     *
     * Uses wp_delete_attachment( $id, true ) to permanently remove both the
     * file on disk and the database record.
     *
     * @since  4.25.1
     * @return array{count: int} Number of attachments deleted.
     */
    public static function cleanup(): array
    {
        $posts = get_posts(
            array(
                'post_type'      => 'attachment',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_key'       => '_mhm_is_demo',
                'meta_value'     => '1',
                'no_found_rows'  => true,
            )
        );

        $count = 0;

        foreach ($posts as $id) {
            $deleted = wp_delete_attachment((int) $id, true);

            if (false !== $deleted && null !== $deleted) {
                ++$count;
            }
        }

        return array( 'count' => $count );
    }
}
