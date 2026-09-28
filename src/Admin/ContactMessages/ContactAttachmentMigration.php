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
		//
		// Review Focus 3: the WHOLE body below runs inside this one try, not
		// just the per-URL call -- no exception, foreseen or not, may escape
		// run() and reach admin_init (R-19).
		$collected = array();
		try {
			self::finish_pending_sources();

			$seen = array();
			do {
				$urls      = self::next_urls();
				$url_count = count($urls);

				// Phase 1: classify every post behind every URL in THIS batch
				// without deleting or moving anything yet.
				$plans       = array();
				$refused_ids = array();
				foreach ($urls as $url) {
					if (isset($seen[ $url ])) {
						self::log('a message kept its legacy URL after being processed');
						self::flag_store_failed();
						return false;
					}
					$seen[ $url ] = true;

					$movable = array();
					$source  = null;
					foreach (self::posts_for($url) as $id => $date) {
						$c = self::classify($url, $date);
						if ($c['ok']) {
							$movable[] = $id;
							$source    = $c;
							continue;
						}
						self::collect_unmigrated($collected, $id, $url, $c['reason']);
						$refused_ids[] = $id;
					}
					$plans[ $url ] = array(
						'movable' => $movable,
						'source'  => $source,
					);
				}

				// Review Focus 2 (round 2 fix): every refusal THIS batch found
				// is written down BEFORE any of its meta is cleared below.
				// Previously the option was written only in this method's
				// finally, so a fatal (timeout/memory -- not a catchable
				// exception, so try/finally never runs either) between an
				// earlier batch's delete_post_meta() and the end of run()
				// could clear a meta whose refusal was never recorded
				// anywhere, losing the URL for good. A death between THIS
				// flush and the deletions below now only leaves the metas in
				// place: the next run re-refuses the same posts, and
				// persist_unmigrated()'s de-dup (by post_id + reason) keeps
				// the list from growing on that retry.
				self::persist_unmigrated($collected);

				foreach ($refused_ids as $id) {
					delete_post_meta($id, ContactAttachmentStore::META_KEY);
				}

				// Phase 2: now safe to actually move each URL's file.
				foreach ($plans as $url => $plan) {
					if (null === $plan['source']) {
						continue;
					}
					$moved = self::store_and_attach($url, $plan['movable'], $plan['source'], $collected);
					if (! $moved) {
						self::log('a file could not be copied into the private folder');
						self::flag_store_failed();
						return false;
					}
				}

				// Same ordering for 'source' items (Review Focus 2): they are
				// recorded, never meta-deleted, so flushing once per batch --
				// rather than only at the very end -- is enough.
				self::persist_unmigrated($collected);
			} while (self::BATCH === $url_count);

			// Review Focus 4: a clean finish no longer needs to say the folder
			// was unwritable, even if an earlier run once flagged it.
			self::clear_store_failed();
			update_option(self::DONE_OPTION, '1', false);

			return true;
		} catch (\Throwable $e) {
			self::log('unexpected error: ' . $e->getMessage());
			self::flag_store_failed();
			return false;
		} finally {
			// Backstop for the last (possibly partial) batch, or anything an
			// exception left uncollected above -- persist_unmigrated()'s
			// de-dup makes a repeat call here, after the per-batch flushes
			// above already ran, a safe no-op.
			self::persist_unmigrated($collected);
		}
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
		if (false === $real_base || false === $real || ! is_file($real)) {
			return self::refuse('missing');
		}
		// Review Focus 2 (round 2 fix): compared against the RESOLVED base,
		// not the literal (unresolved) uploads['basedir'] -- a symlinked
		// uploads root (Bedrock/Capistrano shared/uploads, a mounted volume)
		// must not refuse every legitimate file just because
		// trailingslashit(basedir) . $rel never resolves back to itself. A
		// symlink further down -- the file itself, or a month folder --
		// still gets caught: realpath() resolves it to somewhere that is not
		// real_base . '/' . $rel, which is exactly what this equality tests.
		// This single check also replaces the old separate "starts with
		// real_base" containment test: $rel can never carry ".." (the shape
		// regex above only accepts digit-only year/month segments and a
		// slash-free filename), so exact equality is strictly the stronger
		// of the two.
		if (wp_normalize_path($real) !== untrailingslashit(wp_normalize_path($real_base)) . '/' . $rel) {
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
	 * Review Focus 1: a conservative stem for the library-exclusion prefix
	 * match, not an enumeration of specific candidate names -- core generates
	 * far more variant names than any fixed list could name. Strips the
	 * extension, then repeatedly strips one core-added suffix (measured in
	 * the container's own core) until none match: a -WxH sub-size, -scaled
	 * (image.php's wp_generate_attachment_metadata()/_wp_image_editor
	 * pipeline, ~:375), -rotated (an EXIF auto-rotate, ~:412), -e<13+ digit
	 * timestamp> (an edited image, image-edit.php's wp_save_image()), or
	 * -pdf (a PDF's own preview image, image.php ~:707; its -WxH sub-sizes
	 * strip to the same stem via the first pattern). Two names sharing a stem
	 * are treated as the same media item -- this favors leaving a file alone
	 * over deleting one the library still tracks under a name this method
	 * never had to enumerate by hand.
	 */
	private static function library_stem(string $basename): string
	{
		$stem = (string) preg_replace('/\.[^.\/]+$/', '', $basename);
		do {
			$before = $stem;
			$stem   = (string) preg_replace('/-\d+x\d+$|-scaled$|-rotated$|-e\d{13,}$|-pdf$/', '', $stem);
		} while ($stem !== $before);

		return $stem;
	}

	/**
	 * R-18: queried directly, not through attachment_url_to_postid(), whose
	 * pre_attachment_url_to_postid filter may answer 0 without looking -- and
	 * this answer decides whether a source file gets deleted. A stem prefix
	 * match (library_stem()) catches every core-generated variant of the
	 * candidate's own media item; a PDF's own attachment metadata is checked
	 * separately since neither a PDF's preview nor its metadata ever appears
	 * in another attachment's _wp_attached_file.
	 */
	private static function in_media_library(string $rel): bool
	{
		global $wpdb;

		$slash    = strrpos($rel, '/');
		$dir      = false !== $slash ? substr($rel, 0, $slash + 1) : '';
		$basename = false !== $slash ? substr($rel, $slash + 1) : $rel;
		$stem     = self::library_stem($basename);

		$attached_like = $wpdb->esc_like($dir . $stem) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- No filterable lookup may decide a delete (R-18).
		$found = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s",
			$attached_like
		));
		if ($found > 0) {
			return true;
		}

		// A PDF's own preview/backup-sizes/metadata never lists it under
		// _wp_attached_file at all; the raw name still appears, quoted, in
		// its serialized _wp_attachment_metadata or _wp_attachment_backup_sizes.
		$basename_like = '%' . $wpdb->esc_like('"' . $basename . '"') . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- As above (R-18).
		$found = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN ('_wp_attachment_backup_sizes', '_wp_attachment_metadata') AND meta_value LIKE %s",
			$basename_like
		));

		return $found > 0;
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
		// Review Focus 5: the sentence above already carries the true total;
		// the list itself is capped so a very large backlog cannot render an
		// unbounded admin screen.
		foreach (array_slice($items, 0, 50) as $item) {
			echo '<li><code>' . esc_html( (string) ( $item['url'] ?? '' )) . '</code></li>';
		}
		echo '</ul></div>';

		$state['notified'] = true;
		update_option(self::UNMIGRATED_OPTION, $state, false);
	}

	/**
	 * Copies $source into the private store and attaches it to every post in
	 * $movable. Called only after run() has already flushed this batch's
	 * refusals (Review Focus 2, round 2) -- classification and meta deletion
	 * happen in run() itself, not here.
	 *
	 * @param list<int>                                       $movable
	 * @param array{path:string,name:string,mime:string}      $source
	 * @param list<array{post_id:int,url:string,reason:string}> $collected Appended to on a 'source' refusal.
	 */
	private static function store_and_attach(string $url, array $movable, array $source, array &$collected): bool
	{
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
			self::collect_unmigrated($collected, $movable[0], $url, 'source');
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
				continue; // Its group is still unmigrated; run()'s batch loop deletes it.
			}
			$uploads = realpath( (string) wp_get_upload_dir()['basedir']);
			$real    = realpath( (string) $path);
			$exists  = false !== $uploads && false !== $real && is_file($real)
				&& str_starts_with(wp_normalize_path($real), untrailingslashit(wp_normalize_path($uploads)) . '/');
			if ($exists) {
				// Review Focus 6: it may have become a library file (e.g. a
				// re-import) since this path was queued; forget it, never
				// delete it, the same rule classify() applies on the way in.
				$rel = ltrim(substr(wp_normalize_path($real), strlen(untrailingslashit(wp_normalize_path($uploads)))), '/');
				if (self::in_media_library($rel)) {
					self::set_pending( (string) $path, null);
					continue;
				}
				if (! self::delete_source($real)) {
					continue; // Still there: keep it pending (R-20); it is already listed.
				}
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

	/** @param list<array{post_id:int,url:string,reason:string}> $collected */
	private static function collect_unmigrated(array &$collected, int $post_id, string $url, string $reason): void
	{
		$collected[] = array(
			'post_id' => $post_id,
			'url'     => $url,
			'reason'  => $reason,
		);
	}

	/**
	 * Review Focus 5: merges run()'s in-memory refusals into the stored
	 * option and writes it once, whatever else already changed it this
	 * request (flag_store_failed()/clear_store_failed() read-modify-write
	 * the same option earlier in the same request; this always re-reads
	 * fresh before merging, so nothing here clobbers those).
	 *
	 * De-duplicated by post_id + reason (round 2 / Review Focus 2): $collected
	 * keeps growing across a run and this is now called once per batch, so
	 * the same item would otherwise be re-merged on every later call; a
	 * second run that re-refuses an already-listed post for the same reason
	 * (e.g. its meta was restored) must not add a second entry either.
	 *
	 * @param list<array{post_id:int,url:string,reason:string}> $collected
	 */
	private static function persist_unmigrated(array $collected): void
	{
		if (array() === $collected) {
			return;
		}
		$state    = get_option(self::UNMIGRATED_OPTION, array());
		$state    = is_array($state) ? $state : array();
		$existing = (array) ( $state['items'] ?? array() );

		$seen = array();
		foreach ($existing as $item) {
			$seen[ $item['post_id'] . '|' . $item['reason'] ] = true;
		}
		$added = false;
		foreach ($collected as $item) {
			$key = $item['post_id'] . '|' . $item['reason'];
			if (isset($seen[ $key ])) {
				continue;
			}
			$seen[ $key ] = true;
			$existing[]   = $item;
			$added        = true;
		}
		if (! $added) {
			return;
		}

		$state['items']    = $existing;
		$state['notified'] = false;
		update_option(self::UNMIGRATED_OPTION, $state, false);
	}

	/** Review Focus 4: a run that finishes cleanly no longer needs to say the folder was unwritable. */
	private static function clear_store_failed(): void
	{
		$state = get_option(self::UNMIGRATED_OPTION, array());
		if (is_array($state) && ! empty($state['store_failed'])) {
			unset($state['store_failed']);
			update_option(self::UNMIGRATED_OPTION, $state, false);
		}
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
