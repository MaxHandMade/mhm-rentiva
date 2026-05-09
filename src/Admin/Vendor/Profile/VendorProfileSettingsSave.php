<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Profile;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Vendor\Profile\VendorSlugManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles saving vendor profile self-edit form (photo, slug, bio, phone, city).
 *
 * @since 4.39.0
 */
final class VendorProfileSettingsSave
{
	private const ALLOWED_MIME_TYPES = array( 'image/jpeg', 'image/png' );
	private const MAX_BYTES          = 2 * 1024 * 1024; // 2 MB

	/**
	 * Validate a $_FILES entry before upload.
	 *
	 * @param array{type: string, size: int} $file File array with 'type' and 'size' keys.
	 * @return string|null Error message, or null on success.
	 */
	public static function validate_upload( array $file ): ?string {
		if ( ! in_array( $file['type'], self::ALLOWED_MIME_TYPES, true ) ) {
			return __( 'Only JPG and PNG files are allowed.', 'mhm-rentiva' );
		}
		if ( (int) $file['size'] > self::MAX_BYTES ) {
			return __( 'File size must not exceed 2 MB.', 'mhm-rentiva' );
		}
		return null;
	}

	/**
	 * Process the profile form POST.
	 *
	 * validate_upload is a client-side pre-check; wp_handle_upload enforces server-side extension validation.
	 *
	 * @param int                                                     $user_id
	 * @param array{phone: string, city: string, bio: string, slug: string} $post_data Sanitized POST values.
	 * @param array<string, mixed>|null                               $file      $_FILES['vendor_avatar'] entry, or null if none.
	 * @return array{success: bool, error: string}
	 */
	public static function handle( int $user_id, array $post_data, ?array $file ): array {
		// Photo upload (only when a file was actually selected)
		if ( $file !== null && isset( $file['size'] ) && (int) $file['size'] > 0 ) {
			$error = self::validate_upload( $file );
			if ( $error !== null ) {
				return [ 'success' => false, 'error' => $error ];
			}
			$uploaded = wp_handle_upload( $file, [ 'test_form' => false ] );
			if ( isset( $uploaded['error'] ) ) {
				return [ 'success' => false, 'error' => (string) $uploaded['error'] ];
			}
			$attachment_id = self::create_attachment( $uploaded, $user_id );
			if ( $attachment_id > 0 ) {
				update_user_meta( $user_id, MetaKeys::VENDOR_AVATAR_ID, $attachment_id );
			}
		}

		// Slug (empty = keep existing)
		$slug_raw = (string) ( $post_data['slug'] ?? '' );
		if ( $slug_raw !== '' ) {
			VendorSlugManager::change_slug( $user_id, $slug_raw );
		}

		// Profile fields
		update_user_meta( $user_id, '_rentiva_vendor_phone', (string) ( $post_data['phone'] ?? '' ) );
		update_user_meta( $user_id, '_rentiva_vendor_city',  (string) ( $post_data['city']  ?? '' ) );
		update_user_meta( $user_id, '_rentiva_vendor_bio',   (string) ( $post_data['bio']   ?? '' ) );

		return [ 'success' => true, 'error' => '' ];
	}

	/**
	 * Insert a wp_handle_upload result into the media library.
	 *
	 * @param array{file: string, url: string, type: string} $uploaded
	 * @return int Attachment ID, or 0 on failure.
	 */
	private static function create_attachment( array $uploaded, int $user_id ): int {
		$attachment = [
			'post_mime_type' => $uploaded['type'],
			'post_title'     => sanitize_file_name( basename( $uploaded['file'] ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_author'    => $user_id,
		];
		$id = wp_insert_attachment( $attachment, $uploaded['file'] );
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$meta = wp_generate_attachment_metadata( $id, $uploaded['file'] );
		wp_update_attachment_metadata( $id, $meta );
		return $id;
	}
}
