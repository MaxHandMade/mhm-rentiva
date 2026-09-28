<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\ContactMessages;

use MHMRentiva\Admin\Frontend\Shortcodes\ContactMessagePostType;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Streams a contact attachment to an administrator (spec §4).
 *
 * Uses admin-post.php, not REST: a REST response JSON-encodes the body. The
 * decision (resolve) and the output (serve) are separate from handle(),
 * which ends in exit and so cannot be reached by PHPUnit.
 */
final class ContactAttachmentDownload {
	public const ACTION = 'mhmrentiva_contact_attachment';

	/** The nonce names the record: a link for one message opens no other. */
	public static function nonce_action(int $id): string
	{
		return self::ACTION . '_' . $id;
	}

	public static function handle(): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The ID names the nonce action; check_admin_referer() on the next line verifies it.
		$id = isset($_GET['id']) ? absint(wp_unslash($_GET['id'])) : 0;
		check_admin_referer(self::nonce_action($id));

		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('Unauthorized.', 'mhm-rentiva'), '', array( 'response' => 403 ));
		}

		$file = self::resolve($id);
		if (null === $file) {
			wp_die(esc_html__('File not available', 'mhm-rentiva'), '', array( 'response' => 404 ));
		}

		// Drop every buffer PHP lets us drop; a non-removable one stops the loop.
		while (ob_get_level() > 0) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A non-removable buffer returns false with a notice; stopping there is the intent.
			if (! @ob_end_clean()) {
				break;
			}
		}

		self::serve($file, 'header', ! (bool) ini_get('zlib.output_compression'));
		exit;
	}

	/** @return array{path:string,name:string,mime:string,size:int}|null */
	public static function resolve(int $id): ?array
	{
		if ($id <= 0 || ContactMessagePostType::TYPE !== get_post_type($id)) {
			return null;
		}
		$record = ContactAttachmentStore::record($id);
		$path   = null !== $record ? ContactAttachmentStore::path($record) : null;
		if (null === $record || null === $path) {
			return null;
		}

		return array(
			'path' => $path,
			'name' => $record['name'],
			'mime' => $record['mime'], // record() admits only ContactAttachmentValidator::TYPES values.
			'size' => (int) filesize($path),
		);
	}

	/**
	 * @param array{path:string,name:string,mime:string,size:int} $file
	 * @param callable(string):void $header
	 */
	public static function serve(array $file, callable $header, bool $send_length): void
	{
		$header('Content-Type: ' . $file['mime']);
		$header('Content-Disposition: ' . self::disposition($file['name']));
		$header('X-Content-Type-Options: nosniff');
		$header('Cache-Control: no-store, private');
		if ($send_length) {
			$header('Content-Length: ' . $file['size']);
		}

		// Streams without holding the file in memory. WPCS warns on readfile();
		// WP_Filesystem has no streaming read (R-11).
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- See the note above.
		readfile($file['path']);
	}

	/** RFC 6266: an ASCII fallback plus the real name percent-encoded. */
	public static function disposition(string $name): string
	{
		$ascii = trim( (string) preg_replace('/[^A-Za-z0-9._-]+/', '_', remove_accents($name)), '_');
		if ('' === $ascii || '.' === $ascii[0]) {
			$ascii = 'attachment' . $ascii;
		}

		return sprintf("attachment; filename=\"%s\"; filename*=UTF-8''%s", $ascii, rawurlencode($name));
	}
}
