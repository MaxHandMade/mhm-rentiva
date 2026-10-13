<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Rewrites the stored plain text of Elementor pages that hold a Rentiva widget.
 *
 * Through 6.2.0 Elementor's DB::save_plain_text() rendered every Rentiva widget as the
 * saving admin and wrote the result to wp_posts.post_content: that admin's bookings,
 * payments, favourites, e-mail and live nonces, searchable by any visitor. The
 * markdown-render experiment cached the same kind of render in
 * `_elementor_markdown_cache`. ElementorWidgetBase now renders nothing into either
 * copy; this job rebuilds the copies already written.
 *
 * Per page: Elementor's own get_plain_text() with dynamic tags removed (the same steps
 * as save_plain_text()) and edit mode off, written with a direct UPDATE so the page
 * keeps its post_modified, gets no revision and fires no save_post; then the
 * markdown cache is dropped. Revisions are not touched.
 *
 * DatabaseMigrator (plugins_loaded, before Elementor is up) only marks the work
 * pending and queues a WP-Cron event; each event runs one batch and queues the next
 * while work remains. admin_init is the fallback lane, used only when the event is
 * missing or overdue. Both share DatabaseMigrator's lock. A batch that makes no
 * progress is retried with a growing delay (BACKOFF), not abandoned after a burst;
 * only a job stalled for MAX_STALL stops, and says so in the log.
 */
final class ElementorPlainTextMigration {

	public const DONE_OPTION     = 'mhmrentiva_elementor_plain_text_rebuilt';
	public const PENDING_OPTION  = 'mhmrentiva_elementor_plain_text_pending';
	public const ATTEMPTS_OPTION = 'mhmrentiva_elementor_plain_text_attempts';
	public const CURSOR_OPTION   = 'mhmrentiva_elementor_plain_text_cursor';
	public const BATCH           = 50;

	/** When the current run of failed batches began (unix time). */
	public const STALLED_SINCE_OPTION = 'mhmrentiva_elementor_plain_text_stalled_since';
	/** No batch before this time (unix time): the backoff gate. */
	public const RETRY_AT_OPTION = 'mhmrentiva_elementor_plain_text_retry_at';
	/** Set (unix time) when the job stopped after MAX_STALL without progress. */
	public const STOPPED_OPTION = 'mhmrentiva_elementor_plain_text_stopped';

	/**
	 * Delay before the next try after the 1st, 2nd, 3rd and every later failed batch:
	 * 5 minutes, 30 minutes, 2 hours, then every 6 hours.
	 */
	public const BACKOFF = array( 300, 1800, 7200, 21600 );
	/** A job that has made no progress for this long stops (and logs it). */
	public const MAX_STALL = 7 * DAY_IN_SECONDS;
	/** The admin fallback runs only when the cron event is this late, or missing. */
	public const OVERDUE_AFTER = 10 * MINUTE_IN_SECONDS;

	/** The hook WP-Cron fires; registered in Plugin::initialize_system_services(). */
	public const CRON_HOOK = 'mhmrentiva_rebuild_elementor_plain_text';

	private const DATA_META     = '_elementor_data';
	private const MODE_META     = '_elementor_edit_mode';
	private const MARKDOWN_META = '_elementor_markdown_cache';

	/**
	 * Every Rentiva Elementor widget name, the add-on's included.
	 *
	 * Plain strings: a page saved while the add-on was active keeps its leaked text
	 * after the add-on is deactivated, so its widget names must be found without its
	 * classes. Keep in step with the widgets' get_name().
	 */
	private const WIDGET_NAMES = array(
		'rv-availability-calendar',
		'rv-booking-form',
		'rv-contact-form',
		'mhmrentiva_featured_vehicles',
		'rv-my-bookings',
		'rv-my-favorites',
		'rv-payment-history',
		'rv-search-results',
		'rv-testimonials',
		'rv-vehicle-search',
		'rv-user-dashboard',
		'rv-vehicle-card',
		'rv-vehicle-comparison',
		'rv-vehicle-details',
		'rv-vehicle-rating',
		'mhmrentiva_vehicles_grid',
		'mhmrentiva_vehicles_list',
		// prefix-rename:ignore-start
		// The add-on's widget names as SAVED IN PAGES: the add-on registers them
		// under this spelling, so a swept name would match no stored page.
		'mhm_rentiva_messages',
		'mhm_rentiva_popular_routes',
		'mhm_rentiva_transfer_results',
		'mhm_rentiva_transfer_search',
		'mhm_rentiva_vendor_directory',
		'mhm_rentiva_vendor_profile',
		// prefix-rename:ignore-end
	);

	/**
	 * Widget names the job looks for.
	 *
	 * @return list<string>
	 */
	public static function widget_names(): array
	{
		return self::WIDGET_NAMES;
	}

	/**
	 * Rebuild one batch. True when no candidate is left after it.
	 *
	 * @param int $batch Pages per run.
	 */
	public static function run(int $batch = self::BATCH): bool
	{
		if ('1' === get_option(self::DONE_OPTION)) {
			return true;
		}
		if (! self::elementor_ready()) {
			return false;
		}

		try {
			$ids = self::candidates( (int) get_option(self::CURSOR_OPTION, 0), max(1, $batch));
			if (null === $ids) {
				return false;
			}

			// A page that cannot be rebuilt (a widget that throws, a failed UPDATE) is
			// logged and passed over, so it cannot hold up every page after it.
			foreach ($ids as $post_id) {
				try {
					$rebuilt = self::rebuild($post_id);
				} catch (\Throwable $e) {
					$rebuilt = false;
					self::log(sprintf('page %d: %s: %s', $post_id, get_class($e), $e->getMessage()));
				}
				if (! $rebuilt) {
					self::log(sprintf('page %d could not be rebuilt', $post_id));
				}
				update_option(self::CURSOR_OPTION, (string) $post_id, false);
			}

			if (count($ids) >= max(1, $batch)) {
				return false;
			}

			update_option(self::DONE_OPTION, '1', false);
			delete_option(self::CURSOR_OPTION);
			return true;
		} catch (\Throwable $e) {
			self::log('stopped by ' . get_class($e) . ': ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Called by DatabaseMigrator's 4.6.0 step: queue the work from a clean state and
	 * start the WP-Cron chain.
	 *
	 * The migrator replays every step on each later stamp bump; a finished job stays
	 * finished (run() returns at once on DONE_OPTION), an unfinished or stopped one
	 * restarts.
	 */
	public static function mark_pending(): void
	{
		update_option(self::PENDING_OPTION, '1', false);
		foreach (array( self::ATTEMPTS_OPTION, self::CURSOR_OPTION, self::STALLED_SINCE_OPTION, self::RETRY_AT_OPTION, self::STOPPED_OPTION ) as $option) {
			delete_option($option);
		}
		// A retry queued hours ahead by an earlier run must not delay the restart.
		self::unschedule();
		self::schedule_at(time());
	}

	/**
	 * The WP-Cron callback, the primary driver: one batch, then the next event.
	 *
	 * An auto-updated site may see no admin visit for weeks, and the leaked text stays
	 * searchable meanwhile, so the job must not wait for wp-admin. Elementor hooks its
	 * own init at `init` priority 0 and wp-cron.php runs events after `init`, so
	 * Elementor is up here whenever it is active; elementor_ready() still checks.
	 */
	public static function run_from_cron(): void
	{
		self::maybe_run_pending();
	}

	/**
	 * The admin_init (priority 20) fallback, behind the migrator's own request gate.
	 *
	 * Only when the cron chain looks dead (event missing, or more than OVERDUE_AFTER
	 * late: WP-Cron disabled, no traffic, a fatal mid-batch): an admin page load must
	 * not pay for up to BATCH Elementor renders while cron is doing the job.
	 */
	public static function run_pending_from_hook(): void
	{
		if (! DatabaseMigrator::context_allows(is_admin(), wp_doing_ajax(), wp_doing_cron(), defined('WP_CLI') && WP_CLI, 'admin-post.php' === ( $GLOBALS['pagenow'] ?? '' ))) {
			return;
		}
		$next = wp_next_scheduled(self::CRON_HOOK);
		if (false !== $next && $next > time() - self::OVERDUE_AFTER) {
			return;
		}

		self::maybe_run_pending();
	}

	/**
	 * Run one pending batch once Elementor is up, and keep the cron chain going.
	 *
	 * Cheap checks first, without the lock: nothing pending, stopped, Elementor off, or
	 * still inside the backoff window. Then the batch runs under DatabaseMigrator's
	 * lock, which cron, the admin fallback and the migration itself share; a busy lock
	 * only re-arms the event a minute later.
	 */
	public static function maybe_run_pending(): void
	{
		if ('1' !== get_option(self::PENDING_OPTION) || false !== get_option(self::STOPPED_OPTION)) {
			self::unschedule();
			return;
		}
		// Elementor off: wait for it without counting a failure. No new event: an
		// inactive Elementor would keep one firing for nothing. The admin fallback, or
		// the next stamp bump, picks the job up again.
		if (! self::elementor_ready()) {
			return;
		}
		$retry_at = (int) get_option(self::RETRY_AT_OPTION, 0);
		if ($retry_at > time()) {
			self::schedule_at($retry_at);
			return;
		}

		$next = null;
		$ran  = DatabaseMigrator::run_exclusive(static function () use (&$next): void {
			$next = self::run_locked_batch();
		});
		if (! $ran) {
			self::schedule_at(time() + MINUTE_IN_SECONDS);
			return;
		}

		if (null === $next) {
			self::unschedule();
			return;
		}
		if (0 === $next) {
			// Progress: replace the pessimistic retry event with an immediate one.
			self::unschedule();
		}
		self::schedule_at(time() + $next);
	}

	/**
	 * One batch with its retry bookkeeping. Runs only under DatabaseMigrator's lock.
	 *
	 * Pessimistic: before the work starts the batch is recorded as failed and the
	 * retry event is queued, so a batch that dies mid-way (timeout, fatal) still
	 * counts and the chain survives it. A batch that moves the cursor clears that.
	 *
	 * @return int|null Seconds until the next batch, or null when the job is over
	 *                  (finished, stopped, or no longer pending).
	 */
	private static function run_locked_batch(): ?int
	{
		// Read fresh: the other driver may have finished or advanced the job while
		// this request waited for the lock.
		foreach (array( self::PENDING_OPTION, self::ATTEMPTS_OPTION, self::CURSOR_OPTION, self::DONE_OPTION, self::STALLED_SINCE_OPTION, self::RETRY_AT_OPTION, self::STOPPED_OPTION ) as $option) {
			wp_cache_delete($option, 'options');
		}
		if ('1' !== get_option(self::PENDING_OPTION) || false !== get_option(self::STOPPED_OPTION)) {
			return null;
		}
		$now      = time();
		$retry_at = (int) get_option(self::RETRY_AT_OPTION, 0);
		if ($retry_at > $now) {
			return $retry_at - $now;
		}

		$failures = (int) get_option(self::ATTEMPTS_OPTION, 0);
		$since    = (int) get_option(self::STALLED_SINCE_OPTION, 0);
		if ($failures > 0 && $since > 0 && $now - $since >= self::MAX_STALL) {
			update_option(self::STOPPED_OPTION, (string) $now, false);
			self::log(sprintf('stopped: %d batches in a row made no progress over %d days; the next database update restarts it', $failures, (int) ( self::MAX_STALL / DAY_IN_SECONDS )));
			return null;
		}

		$delay = self::backoff($failures + 1);
		update_option(self::ATTEMPTS_OPTION, (string) ( $failures + 1 ), false);
		update_option(self::STALLED_SINCE_OPTION, (string) ( $since > 0 ? $since : $now ), false);
		update_option(self::RETRY_AT_OPTION, (string) ( $now + $delay ), false);
		self::schedule_at($now + $delay);

		$cursor = (int) get_option(self::CURSOR_OPTION, 0);
		$done   = self::run();

		if ($done || (int) get_option(self::CURSOR_OPTION, 0) > $cursor) {
			foreach (array( self::ATTEMPTS_OPTION, self::STALLED_SINCE_OPTION, self::RETRY_AT_OPTION ) as $option) {
				delete_option($option);
			}
			if ($done) {
				delete_option(self::PENDING_OPTION);
				return null;
			}
			return 0;
		}

		self::log(sprintf('batch %d made no progress; retrying in %d minutes', $failures + 1, (int) ( $delay / MINUTE_IN_SECONDS )));
		return $delay;
	}

	/**
	 * Delay after the $failures-th failed batch in a row (1-based).
	 */
	public static function backoff(int $failures): int
	{
		$index = min(max(1, $failures), count(self::BACKOFF)) - 1;

		return self::BACKOFF[ $index ];
	}

	/**
	 * Queue the next batch at $timestamp unless one is already queued.
	 */
	private static function schedule_at(int $timestamp): void
	{
		if (false === wp_next_scheduled(self::CRON_HOOK)) {
			wp_schedule_single_event($timestamp, self::CRON_HOOK);
		}
	}

	private static function unschedule(): void
	{
		if (false !== wp_next_scheduled(self::CRON_HOOK)) {
			wp_unschedule_hook(self::CRON_HOOK);
		}
	}

	/**
	 * The next pages after $after that store a Rentiva widget and are built with Elementor.
	 *
	 * Revisions and auto-drafts are left alone; every other post type and status counts
	 * (a draft's post_content is just as searchable once published).
	 *
	 * @return list<int>|null Null when the query failed.
	 */
	private static function candidates(int $after, int $limit): ?array
	{
		global $wpdb;

		$likes = array();
		foreach (self::WIDGET_NAMES as $name) {
			$likes[] = '%' . $wpdb->esc_like('"widgetType":"' . $name . '"') . '%';
		}
		$match = implode(' OR ', array_fill(0, count($likes), 'd.meta_value LIKE %s'));

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- One-shot, version-gated migration; no core API selects post ids by a LIKE on _elementor_data. $match is only "d.meta_value LIKE %s" repeated once per prepared value.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = %s
				INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s AND m.meta_value = %s
				WHERE p.ID > %d AND p.post_type <> %s AND p.post_status <> %s AND ( {$match} )
				ORDER BY p.ID ASC LIMIT %d",
				array_merge(
					array( self::DATA_META, self::MODE_META, 'builder', $after, 'revision', 'auto-draft' ),
					$likes,
					array( $limit )
				)
			)
		);
		// phpcs:enable

		if ('' !== $wpdb->last_error || ! is_array($ids)) {
			self::log('the candidate query failed: ' . $wpdb->last_error);
			return null;
		}

		return array_values(array_map('intval', $ids));
	}

	/**
	 * Replace one page's stored plain text and drop its markdown cache.
	 */
	private static function rebuild(int $post_id): bool
	{
		$elementor    = \Elementor\Plugin::$instance;
		$dynamic_tags = $elementor->dynamic_tags;
		$parsing_mode = $dynamic_tags->get_parsing_mode();
		$editor       = $elementor->editor;
		$edit_mode    = $editor->is_edit_mode();

		// Same render as DB::save_plain_text(), and in front context like
		// Frontend::get_builder_content_for_display().
		$dynamic_tags->set_parsing_mode(\Elementor\Core\DynamicTags\Manager::MODE_REMOVE);
		$editor->set_edit_mode(false);
		$buffers = ob_get_level();
		try {
			$plain_text = (string) $elementor->db->get_plain_text($post_id);
		} finally {
			// get_plain_text() renders inside its own output buffer; a widget that
			// throws leaves it open.
			while (ob_get_level() > $buffers) {
				ob_end_clean();
			}
			$editor->set_edit_mode($edit_mode);
			$dynamic_tags->set_parsing_mode($parsing_mode);
		}

		$updated = true;
		if (get_post_field('post_content', $post_id, 'raw') !== $plain_text) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Deliberately not wp_update_post(): the page must keep its post_modified, get no revision and fire no save_post. clean_post_cache() follows.
			$updated = false !== $wpdb->update($wpdb->posts, array( 'post_content' => $plain_text ), array( 'ID' => $post_id ), array( '%s' ), array( '%d' ));
			clean_post_cache($post_id);
		}

		// After the UPDATE: a markdown request in between would otherwise re-cache the
		// old excerpt. Dropped even when the UPDATE failed -- the cache is a leaked
		// render either way.
		delete_post_meta($post_id, self::MARKDOWN_META);

		return $updated;
	}

	private static function elementor_ready(): bool
	{
		// Elementor\Plugin::init() builds db, editor and dynamic_tags before it fires
		// elementor/init, so the action is the readiness signal.
		$ready = did_action('elementor/init') > 0 && class_exists(\Elementor\Plugin::class) && class_exists(\Elementor\Core\DynamicTags\Manager::class);

		// The filter can only narrow: it never declares a missing Elementor ready.
		return $ready && (bool) apply_filters('mhmrentiva_elementor_plain_text_migration_available', true);
	}

	private static function log(string $why): void
	{
		if (class_exists(\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::class)) {
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error(
				'Elementor plain text rebuild (4.6.0): ' . $why,
				array(),
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
			);
		}
	}
}
