<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\ContactMessages;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Content check for a contact-form attachment (spec §4).
 *
 * The plugin's own extension map, never the site's upload_mimes: a site that
 * allows SVG or HTML uploads for its editors must not widen what an anonymous
 * visitor may leave in the private folder. The extension names the type; the
 * bytes must prove it. Core's wp_check_filetype_and_ext() runs on top, with
 * this same map, as a second opinion (fileinfo where present). Used for new
 * uploads and by the 4.4.1 migration alike.
 */
final class ContactAttachmentValidator {
	/** Extension => the only MIME type a file carrying it may have. */
	public const TYPES = array(
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'gif'  => 'image/gif',
		'pdf'  => 'application/pdf',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	);

	/** @return array{mime:string,ext:string}|\WP_Error */
	public static function validate(string $path, string $name)
	{
		$ext  = strtolower( (string) pathinfo($name, PATHINFO_EXTENSION));
		$mime = self::TYPES[ $ext ] ?? '';
		if ('' === $mime || ! is_file($path) || ! is_readable($path)) {
			return self::invalid();
		}
		if (! self::bytes_match($path, $ext, $mime)) {
			return self::invalid();
		}

		// A renamed image comes back with proper_filename set: core would
		// "fix" the extension, this plugin refuses instead (spec §4). Core
		// also applies the site's allowed-MIME list here: a site may narrow
		// the seven types (a PDF-less site refuses PDFs), never widen them (R-17).
		$core = wp_check_filetype_and_ext($path, $name, self::TYPES);
		if ( ( $core['type'] ?? false ) !== $mime || ! empty($core['proper_filename'])) {
			return self::invalid();
		}

		return array(
			'mime' => $mime,
			'ext'  => $ext,
		);
	}

	private static function bytes_match(string $path, string $ext, string $mime): bool
	{
		if (str_starts_with($mime, 'image/')) {
			$info = wp_getimagesize($path);
			return is_array($info) && ( $info['mime'] ?? '' ) === $mime;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file's first eight bytes; wp_remote_get() is for URLs.
		$head = (string) file_get_contents($path, false, null, 0, 8);
		if ('pdf' === $ext) {
			return str_starts_with($head, '%PDF-');
		}
		if ('doc' === $ext) {
			return str_starts_with($head, "\xD0\xCF\x11\xE0");
		}
		if (! str_starts_with($head, "PK\x03\x04")) {
			return false;
		}
		if (! class_exists('ZipArchive')) {
			// Signature only. The file is still served as an attachment with
			// nosniff, never rendered, so the remaining risk is low (spec §4).
			return true;
		}
		$zip = new \ZipArchive();
		if (true !== $zip->open($path, \ZipArchive::RDONLY)) {
			return false;
		}
		$ok = false !== $zip->locateName('[Content_Types].xml') && false !== $zip->locateName('word/document.xml');
		$zip->close();

		return $ok;
	}

	private static function invalid(): \WP_Error
	{
		return new \WP_Error('contact_attachment_type', __('Invalid file type.', 'mhm-rentiva'));
	}
}
