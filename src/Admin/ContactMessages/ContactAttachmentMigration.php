<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\ContactMessages;

use MHMRentiva\Admin\Frontend\Shortcodes\ContactMessagePostType;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * 4.4.1: move pre-4.4.1 contact attachments into the private store (spec §4).
 *
 * The meta it reads could be written by an anonymous visitor before 4.4.0
 * (the form copied a POST text field into it), so a URL is moved only when
 * every rule holds: this site's uploads host; the exact path shape core gives
 * an upload (YYYY/MM/<name>, or <name> without month folders); the month
 * equals the message's own post_date month; an allowed extension; not a media
 * library file nor a size/scaled/rotated variant of one; a real file inside
 * uploads; bytes that prove the extension. Anything else loses its meta, keeps
 * its file, and is listed once for an administrator.
 */
final class ContactAttachmentMigration {
	public const DONE_OPTION       = 'mhmrentiva_contact_attachments_migrated';
	public const UNMIGRATED_OPTION = 'mhmrentiva_contact_attachment_unmigrated';
	public const PENDING_OPTION    = 'mhmrentiva_contact_attachment_pending_sources';

	private const BATCH = 50;

	/** True once every legacy URL is either moved or refused. */
	public static function run(): bool
	{
		if ('1' === get_option(self::DONE_OPTION)) {
			return true;
		}

		// No up-front root() call: a site with no legacy attachment must not get
		// an empty private folder just because it upgraded. store_copy() creates
		// the folder on the first real move and fails the run if it cannot.
		self::finish_pending_sources();

		$seen = array();
		do {
			$urls      = self::next_urls();
			$url_count = count($urls);
			foreach ($urls as $url) {
				if (isset($seen[ $url ])) {
					self::log('a message kept its legacy URL after being processed');
					return false;
				}
				$seen[ $url ] = true;
				try {
					$moved = self::migrate_url($url);
				} catch (\Throwable $e) {
					// R-19: no failure, foreseen or not, may escape into admin_init.
					self::log('unexpected error: ' . $e->getMessage());
					self::flag_store_failed();
					return false;
				}
				if (! $moved) {
					self::log('a file could not be copied into the private folder');
					self::flag_store_failed();
					return false;
				}
			}
		} while (self::BATCH === $url_count);

		update_option(self::DONE_OPTION, '1', false);

		return true;
	}

	/** @return array{ok:true,path:string,name:string,mime:string}|array{ok:false,reason:string} */
	public static function classify(string $url, string $post_date): array
	{
		$uploads = wp_get_upload_dir();
		$parts   = wp_parse_url($url);
		$base    = wp_parse_url( (string) $uploads['baseurl']);

		if (empty($parts['host']) || empty($base['host']) || 0 !== strcasecmp($parts['host'], $base['host'])) {
			return self::refuse('host');
		}

		$base_path = untrailingslashit( (string) ( $base['path'] ?? '' ));
		$path      = (string) ( $parts['path'] ?? '' );
		if (isset($parts['query']) || isset($parts['fragment']) || ! str_starts_with($path, $base_path . '/')) {
			return self::refuse('shape');
		}
		$rel = rawurldecode(substr($path, strlen($base_path) + 1));
		// R-19: pre-4.4.0 meta was raw form text; sanitize_text_field() keeps a
		// NUL, and realpath() throws ValueError on one. No upload core names
		// ever carries a control character.
		if (1 === preg_match('/[\x00-\x1f\x7f]/', $rel)) {
			return self::refuse('shape');
		}
		$yearmonth = (bool) get_option('uploads_use_yearmonth_folders');
		if (1 !== preg_match($yearmonth ? '#^(\d{4})/(\d{2})/([^/\\\\]+)$#' : '#^([^/\\\\]+)$#', $rel, $m)) {
			return self::refuse('shape');
		}
		// Local time on both sides: wp_upload_dir() files by local month, post_date is local.
		if ($yearmonth && $m[1] . '-' . $m[2] !== substr($post_date, 0, 7)) {
			return self::refuse('month');
		}

		$name = (string) end($m);
		$ext  = strtolower( (string) pathinfo($name, PATHINFO_EXTENSION));
		if (! isset(ContactAttachmentValidator::TYPES[ $ext ])) {
			return self::refuse('extension');
		}

		if (self::in_media_library($rel)) {
			return self::refuse('library');
		}

		$real_base = realpath( (string) $uploads['basedir']);
		$real      = realpath(trailingslashit( (string) $uploads['basedir']) . $rel);
		if (false === $real_base || false === $real || ! is_file($real)
			|| ! str_starts_with(wp_normalize_path($real), untrailingslashit(wp_normalize_path($real_base)) . '/')) {
			return self::refuse('missing');
		}

		$valid = ContactAttachmentValidator::validate($real, $name);
		if (is_wp_error($valid)) {
			return self::refuse('content');
		}

		return array(
			'ok'   => true,
			'path' => wp_normalize_path($real),
			'name' => $name,
			'mime' => $valid['mime'],
		);
	}

	/**
	 * The uploads-relative path, its size-suffix-stripped original, that
	 * original with -scaled, and the path with -scaled/-rotated stripped.
	 * Core records the -scaled name in _wp_attached_file, so looking up only
	 * the stripped name misses it (Codex v2-1).
	 *
	 * @return list<string>
	 */
	public static function library_candidates(string $rel): array
	{
		$unsized = (string) preg_replace('/-\d+x\d+(\.[A-Za-z0-9]+)$/', '$1', $rel);

		return array_values(array_unique(array(
			$rel,
			$unsized,
			(string) preg_replace('/(\.[A-Za-z0-9]+)$/', '-scaled$1', $unsized),
			(string) preg_replace('/-(?:scaled|rotated)(\.[A-Za-z0-9]+)$/', '$1', $rel),
		)));
	}

	/**
	 * R-18: queried directly, not through attachment_url_to_postid(), whose
	 * pre_attachment_url_to_postid filter may answer 0 without looking -- and
	 * this answer decides whether a source file gets deleted.
	 */
	private static function in_media_library(string $rel): bool
	{
		global $wpdb;

		$candidates   = self::library_candidates($rel);
		$placeholders = implode(', ', array_fill(0, count($candidates), '%s'));
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a list of literal %s tokens; every value is bound. No filterable lookup may decide a delete (R-18).
		$sql = "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value IN ($placeholders)";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- $sql is a local literal with a bound placeholder list; no filterable lookup may decide a delete (R-18).
		return (int) $wpdb->get_var($wpdb->prepare($sql, ...$candidates)) > 0;
	}

	public static function render_unmigrated_notice(): void
	{
		if (! current_user_can('manage_options')) {
			return;
		}
		$state = get_option(self::UNMIGRATED_OPTION, array());
		if (! is_array($state) || ! empty($state['notified']) || ( empty($state['items']) && empty($state['store_failed']) )) {
			return;
		}
		$items = (array) ( $state['items'] ?? array() );

		echo '<div class="notice notice-warning">';
		if (! empty($state['store_failed'])) {
			// R-20.
			echo '<p>' . esc_html__('The private attachment folder could not be written. Attachments saved by earlier versions are still in the public uploads folder; moving them will be retried on the next update.', 'mhm-rentiva') . '</p>';
		}
		if (array() === $items) {
			echo '</div>';
			$state['notified'] = true;
			update_option(self::UNMIGRATED_OPTION, $state, false);
			return;
		}
		echo '<p>' . esc_html(sprintf(
			/* translators: %d: number of contact message attachments that were not moved. */
			_n(
				'%d contact message attachment could not be moved into the private folder. Its link was removed from the message; the file itself was left where it was:',
				'%d contact message attachments could not be moved into the private folder. Their links were removed from the messages; the files themselves were left where they were:',
				count($items),
				'mhm-rentiva'
			),
			count($items)
		)) . '</p><ul>';
		foreach ($items as $item) {
			echo '<li><code>' . esc_html( (string) ( $item['url'] ?? '' )) . '</code></li>';
		}
		echo '</ul></div>';

		$state['notified'] = true;
		update_option(self::UNMIGRATED_OPTION, $state, false);
	}

	private static function migrate_url(string $url): bool
	{
		$movable = array();
		$source  = null;
		foreach (self::posts_for($url) as $id => $date) {
			$c = self::classify($url, $date);
			if ($c['ok']) {
				$movable[] = $id;
				$source    = $c;
				continue;
			}
			delete_post_meta($id, ContactAttachmentStore::META_KEY);
			self::record_unmigrated($id, $url, $c['reason']);
		}

		if (null === $source) {
			return true;
		}

		$record = ContactAttachmentStore::store_copy($source['path'], $source['name'], $source['mime']);
		if (is_wp_error($record)) {
			return false;
		}
		self::set_pending($source['path'], $url);
		foreach ($movable as $id) {
			ContactAttachmentStore::attach($id, $record);
		}
		if (self::delete_source($source['path'])) {
			self::set_pending($source['path'], null);
		} else {
			// R-20: the move itself succeeded (rows keep their private record);
			// the public copy is listed for the owner and stays pending.
			self::log('the public copy could not be deleted: ' . $source['path']);
			self::record_unmigrated($movable[0], $url, 'source');
		}

		return true;
	}

	/** Checked on disk: the wp_delete_file filter can redirect or cancel the unlink. */
	private static function delete_source(string $path): bool
	{
		wp_delete_file($path);
		clearstatcache(true, $path);

		return ! file_exists($path);
	}

	private static function flag_store_failed(): void
	{
		$state                 = get_option(self::UNMIGRATED_OPTION, array());
		$state                 = is_array($state) ? $state : array();
		$state['store_failed'] = true;
		$state['notified']     = false;
		update_option(self::UNMIGRATED_OPTION, $state, false);
	}

	/** R-8. */
	private static function finish_pending_sources(): void
	{
		foreach ( (array) get_option(self::PENDING_OPTION, array()) as $path => $url) {
			if (array() !== self::posts_for( (string) $url)) {
				continue; // Its group is still unmigrated; migrate_url() deletes it.
			}
			$uploads = realpath( (string) wp_get_upload_dir()['basedir']);
			$real    = realpath( (string) $path);
			if (false !== $uploads && false !== $real && is_file($real)
				&& str_starts_with(wp_normalize_path($real), untrailingslashit(wp_normalize_path($uploads)) . '/')
				&& ! self::delete_source($real)) {
				continue; // Still there: keep it pending (R-20); it is already listed.
			}
			self::set_pending( (string) $path, null);
		}
	}

	/** @return list<string> */
	private static function next_urls(): array
	{
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot, version-gated migration over a post-type-scoped DISTINCT; the rows change as it runs, so nothing is worth caching.
		return array_map('strval', (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s AND p.post_type = %s AND pm.meta_value <> '' AND pm.meta_value NOT LIKE %s
				 ORDER BY pm.meta_value LIMIT %d",
				ContactAttachmentStore::META_KEY,
				ContactMessagePostType::TYPE,
				'a:%',
				self::BATCH
			)
		));
	}

	/** @return array<int,string> post ID => post_date (local) */
	private static function posts_for(string $url): array
	{
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- As next_urls().
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_date FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_type = %s",
				ContactAttachmentStore::META_KEY,
				$url,
				ContactMessagePostType::TYPE
			)
		);
		$out  = array();
		foreach ($rows as $row) {
			$out[ (int) $row->ID ] = (string) $row->post_date;
		}

		return $out;
	}

	private static function set_pending(string $path, ?string $url): void
	{
		$pending = (array) get_option(self::PENDING_OPTION, array());
		if (null === $url) {
			unset($pending[ $path ]);
		} else {
			$pending[ $path ] = $url;
		}
		update_option(self::PENDING_OPTION, $pending, false);
	}

	private static function record_unmigrated(int $post_id, string $url, string $reason): void
	{
		$state = get_option(self::UNMIGRATED_OPTION, array());
		$state = is_array($state) ? $state : array();
		$item  = array(
			'post_id' => $post_id,
			'url'     => $url,
			'reason'  => $reason,
		);

		$state['items']    = array_merge( (array) ( $state['items'] ?? array() ), array( $item ));
		$state['notified'] = false;
		update_option(self::UNMIGRATED_OPTION, $state, false);
	}

	/** @return array{ok:false,reason:string} */
	private static function refuse(string $reason): array
	{
		return array(
			'ok'     => false,
			'reason' => $reason,
		);
	}

	private static function log(string $why): void
	{
		if (class_exists(\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::class)) {
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error(
				'Contact attachment migration (4.4.1) stopped: ' . $why . '; it runs again on the next database version bump',
				array(),
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
			);
		}
	}
}
