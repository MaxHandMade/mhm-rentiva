<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Profile;

use MHMRentiva\Admin\Core\MetaKeys;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles saving vendor profile self-edit form (photo, slug, bio, phone, city).
 *
 * @since 4.39.0
 */
final class VendorProfileSettingsSave {
	private const ALLOWED_MIME_TYPES = array( 'image/jpeg', 'image/png' );
	private const MAX_BYTES          = 2 * 1024 * 1024; // 2 MB

	/**
	 * Validate a $_FILES entry before upload.
	 *
	 * @param array<string, mixed> $file File array with 'type' and 'size' keys.
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
}
