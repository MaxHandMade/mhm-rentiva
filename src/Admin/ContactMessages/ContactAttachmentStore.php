<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\ContactMessages;

use MHMRentiva\Admin\Frontend\Shortcodes\ContactMessagePostType;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Contact-form attachments, outside the media library (spec §4, R4).
 *
 * NOT encrypted. The claim is "random name, no extension, direct access denied
 * on Apache"; on nginx only the unguessable name and index.php stand between a
 * guesser and the file (readme says so). The parent folder mhm-rentiva-private/
 * belongs to the add-on as well: this class writes its guard files only when
 * missing and never removes them. A file name read back from meta is trusted
 * only if it has the exact shape this class writes.
 */
final class ContactAttachmentStore {
	public const META_KEY     = '_mhmrentiva_contact_attachment';
	public const FILE_META    = '_mhmrentiva_contact_attachment_file';
	public const ROOT_FILTER  = 'mhmrentiva_contact_attachment_root';
	public const NAME_PATTERN = '/^[a-f0-9]{32}$/';

	private const PARENT = 'mhm-rentiva-private';
	private const SUBDIR = 'contact';
	private const INDEX  = "<?php\n// Silence is golden.\n";

	/** Byte-identical to the add-on's own parent guard, so whichever plugin writes first, the file is the same. */
	private const PARENT_HTACCESS = "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";

	/**
	 * Wrapped on purpose: an unwrapped "Deny from all" is a 500 on Apache 2.4
	 * without mod_access_compat (spec §4).
	 */
	private const HTACCESS = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\nForceType application/octet-stream\n<IfModule mod_headers.c>\nHeader set X-Content-Type-Options nosniff\n</IfModule>\n";

	public static function default_root(): string
	{
		return trailingslashit( (string) wp_get_upload_dir()['basedir']) . self::PARENT . '/' . self::SUBDIR;
	}

	/** @return string|\WP_Error The existing, guarded, writable root (normalized, no trailing slash). */
	public static function root()
	{
		$uploads = wp_get_upload_dir();
		if (! empty($uploads['error']) || empty($uploads['basedir'])) {
			return self::unavailable();
		}

		$default = wp_normalize_path(self::default_root());
		/**
		 * Filters the contact attachment folder before it is created.
		 *
		 * @param string $root Absolute path; default <uploads>/mhm-rentiva-private/contact.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- self::ROOT_FILTER is 'mhmrentiva_contact_attachment_root', which carries the plugin prefix.
		$root = untrailingslashit(wp_normalize_path( (string) apply_filters(self::ROOT_FILTER, $default)));
		if ('' === $root || ! wp_mkdir_p($root) || ! wp_is_writable($root)) {
			return self::unavailable();
		}

		if ($root === $default) {
			self::write_if_missing(dirname($root) . '/index.php', self::INDEX);
			self::write_if_missing(dirname($root) . '/.htaccess', self::PARENT_HTACCESS);
		}
		self::write_if_missing($root . '/index.php', self::INDEX);
		self::write_if_missing($root . '/.htaccess', self::HTACCESS);

		return $root;
	}

	/**
	 * Store an HTTP upload. The caller has already checked UPLOAD_ERR_OK and size.
	 *
	 * @param callable(string,string):bool|null $mover Test seam; null means
	 *        move_uploaded_file(), which itself proves the source is an HTTP upload.
	 * @return array{file:string,name:string,mime:string,size:int}|\WP_Error
	 */
	public static function store_upload(string $tmp, string $original_name, ?callable $mover = null)
	{
		$name  = self::clean_name($original_name);
		$valid = ContactAttachmentValidator::validate($tmp, $name);
		if (is_wp_error($valid)) {
			return $valid;
		}
		$root = self::root();
		if (is_wp_error($root)) {
			return $root;
		}

		$file  = bin2hex(random_bytes(16));
		$dest  = $root . '/' . $file;
		$size  = (int) filesize($tmp);
		$moved = null !== $mover ? (bool) $mover($tmp, $dest) : move_uploaded_file($tmp, $dest);

		return self::finish($moved, $dest, $file, $name, $valid['mime'], $size);
	}

	/**
	 * Copy a file already on disk (4.4.1 migration). The caller has validated it.
	 *
	 * @return array{file:string,name:string,mime:string,size:int}|\WP_Error
	 */
	public static function store_copy(string $src, string $original_name, string $mime)
	{
		$root = self::root();
		if (is_wp_error($root)) {
			return $root;
		}

		$file = bin2hex(random_bytes(16));
		$dest = $root . '/' . $file;
		// Plain copy(), not WP_Filesystem: that may need credentials on a
		// non-direct host, and this must run wherever wp_handle_upload() did (R-11).
		$ok = copy($src, $dest) && hash_file('sha256', $src) === hash_file('sha256', $dest);

		return self::finish($ok, $dest, $file, self::clean_name($original_name), $mime, (int) filesize($src));
	}

	/** @param array{file:string,name:string,mime:string,size:int} $record */
	public static function attach(int $post_id, array $record): void
	{
		update_post_meta($post_id, self::META_KEY, $record);
		update_post_meta($post_id, self::FILE_META, $record['file']);
	}

	/** @return array{file:string,name:string,mime:string,size:int}|null */
	public static function record(int $post_id): ?array
	{
		$r = get_post_meta($post_id, self::META_KEY, true);
		if (! is_array($r) || ! is_string($r['file'] ?? null) || 1 !== preg_match(self::NAME_PATTERN, $r['file'])) {
			return null;
		}
		if (! in_array($r['mime'] ?? '', ContactAttachmentValidator::TYPES, true)) {
			return null;
		}

		return array(
			'file' => $r['file'],
			'name' => self::clean_name( (string) ( $r['name'] ?? '' )),
			'mime' => (string) $r['mime'],
			'size' => (int) ( $r['size'] ?? 0 ),
		);
	}

	/** A pre-4.4.1 URL still in meta ('' when the meta is a record or empty). */
	public static function legacy_url(int $post_id): string
	{
		$v = get_post_meta($post_id, self::META_KEY, true);
		return is_string($v) ? $v : '';
	}

	/** Absolute path of an existing stored file, or null. Never follows a name outside the root. */
	public static function path(array $record): ?string
	{
		$file = (string) ( $record['file'] ?? '' );
		if (1 !== preg_match(self::NAME_PATTERN, $file)) {
			return null;
		}
		$root = self::existing_root();
		if (null === $root) {
			return null;
		}
		$real = realpath($root . '/' . $file);
		if (false === $real || ! is_file($real)) {
			return null;
		}
		$real = wp_normalize_path($real);

		return str_starts_with($real, $root . '/') ? $real : null;
	}

	/** Remove a stored file no message points at yet (a submission whose save failed). */
	public static function discard(array $record): void
	{
		$path = self::path($record);
		if (null !== $path) {
			wp_delete_file($path);
		}
	}

	public static function on_before_delete_post(int $post_id): void
	{
		if (ContactMessagePostType::TYPE === get_post_type($post_id)) {
			self::delete_for_post($post_id);
		}
	}

	/** Delete this message's file unless another contact message still references it. */
	public static function delete_for_post(int $post_id): void
	{
		$record = self::record($post_id);
		if (null === $record || self::other_references($record['file'], $post_id) > 0) {
			return;
		}
		self::discard($record);
	}

	/**
	 * Uninstall (R-2): only files this class names, then the guards and the
	 * folder if nothing else is left. Never recursive, whatever the filter says.
	 */
	public static function purge(): void
	{
		$root = self::existing_root();
		if (null === $root) {
			return;
		}
		foreach ( (array) scandir($root) as $entry) {
			if (1 === preg_match(self::NAME_PATTERN, (string) $entry)) {
				wp_delete_file($root . '/' . $entry);
			}
		}
		$left = array_diff( (array) scandir($root), array( '.', '..', 'index.php', '.htaccess' ));
		if (array() !== $left) {
			return;
		}
		wp_delete_file($root . '/index.php');
		wp_delete_file($root . '/.htaccess');
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- R-11; the folder is empty at this point (checked above).
		rmdir($root);
	}

	private static function other_references(string $file, int $exclude): int
	{
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- A security-relevant existence COUNT joined on post type; a cached or hydrated WP_Query result could miss a reference and delete a file still in use.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_type = %s AND p.ID <> %d",
				self::FILE_META,
				$file,
				ContactMessagePostType::TYPE,
				$exclude
			)
		);
	}

	/** Realpath() of the (filtered) root without creating it. */
	private static function existing_root(): ?string
	{
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- self::ROOT_FILTER is 'mhmrentiva_contact_attachment_root', which carries the plugin prefix.
		$real = realpath( (string) apply_filters(self::ROOT_FILTER, self::default_root()));
		return false === $real ? null : untrailingslashit(wp_normalize_path($real));
	}

	/** @return array{file:string,name:string,mime:string,size:int}|\WP_Error */
	private static function finish(bool $ok, string $dest, string $file, string $name, string $mime, int $size)
	{
		if (! $ok || ! is_file($dest)) {
			if (file_exists($dest)) {
				wp_delete_file($dest);
			}
			return self::unavailable();
		}
		// A failed chmod is not an upload failure (spec §4): the host decides.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod,WordPress.PHP.NoSilencedErrors.Discouraged -- R-11.
		@chmod($dest, 0600);
		clearstatcache(true, $dest);
		if ( (int) filesize($dest) !== $size) {
			wp_delete_file($dest);
			return self::unavailable();
		}

		return array(
			'file' => $file,
			'name' => $name,
			'mime' => $mime,
			'size' => $size,
		);
	}

	private static function clean_name(string $name): string
	{
		$clean = sanitize_file_name(wp_basename($name));
		return '' !== $clean ? $clean : 'attachment';
	}

	private static function write_if_missing(string $path, string $contents): void
	{
		if (! file_exists($path)) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- R-11.
			file_put_contents($path, $contents);
		}
	}

	private static function unavailable(): \WP_Error
	{
		return new \WP_Error('contact_attachment_store', __('The file could not be uploaded.', 'mhm-rentiva'));
	}
}
