<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Moves the two working Elementor style groups whose id followed the admin language.
 *
 * Before 4.5.0 the style helpers derived a group's id from its translated label, so
 * a search widget saved under tr_TR stored its typography as `genel-stil_typography_*`
 * and a booking form stored its shadow as `golge_shadow_*`. Those two groups matched
 * real markup, so the value is visible on the site today; the fixed ids are
 * `general-style_typography` and `shadow_shadow`. Every other style group was dead
 * and got a new name instead, so its old values are deliberately left behind.
 *
 * A legacy prefix is any `<prefix>_typography` / `<prefix>_shadow` group the widget
 * does NOT register (common Advanced-tab groups such as `_box_shadow` included), read
 * from Elementor's own control stack. That is why this cannot run inside the
 * migrator: DatabaseMigrator runs on plugins_loaded, before Elementor builds its
 * managers on init. The migrator only marks the work pending; admin_init does it,
 * with a bounded number of attempts.
 */
final class ElementorStyleIdMigration {

	public const DONE_OPTION     = 'mhmrentiva_elementor_style_ids_migrated';
	public const PENDING_OPTION  = 'mhmrentiva_elementor_style_ids_pending';
	public const ATTEMPTS_OPTION = 'mhmrentiva_elementor_style_ids_attempts';
	public const MAX_ATTEMPTS    = 3;

	private const META = '_elementor_data';

	/**
	 * Widget name => [legacy key pattern, target group].
	 *
	 * Typography: everything after `_typography_` is the field, whatever Elementor
	 * adds over time (variable font `weight`/`width`, `_tablet_extra` …).
	 * Shadow: the group's three keys (`_box_shadow`, `_box_shadow_type`, `_box_shadow_position`).
	 */
	private const RULES = array(
		'rv-vehicle-search' => array(
			'pattern' => '/^(.+)_typography_(.+)$/',
			'suffix'  => '_typography',
			'target'  => 'general-style_typography',
			'class'   => \MHMRentiva\Admin\Frontend\Widgets\Elementor\UnifiedSearchWidget::class,
		),
		'rv-booking-form'   => array(
			'pattern' => '/^(.+)_shadow_(box_shadow(?:_type|_position)?)$/',
			'suffix'  => '_shadow',
			'target'  => 'shadow_shadow',
			'class'   => \MHMRentiva\Admin\Frontend\Widgets\Elementor\BookingFormWidget::class,
		),
	);

	/**
	 * Run the migration. True when every candidate row is migrated or needed nothing.
	 */
	public static function run(): bool
	{
		if ('1' === get_option(self::DONE_OPTION)) {
			return true;
		}
		if (! self::elementor_ready()) {
			return false;
		}

		try {
			$registered = self::registered_names();
			if (null === $registered) {
				self::log('the Rentiva widgets are not registered with Elementor');
				return false;
			}

			global $wpdb;
			$likes = array();
			foreach (array_keys(self::RULES) as $widget) {
				$likes[] = '%' . $wpdb->esc_like('"widgetType":"' . $widget . '"') . '%';
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot, version-gated migration; no core API selects post ids by a LIKE on _elementor_data, and the rows change as it runs.
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND ( meta_value LIKE %s OR meta_value LIKE %s )",
					self::META,
					$likes[0],
					$likes[1]
				)
			);
			if ('' !== $wpdb->last_error || ! is_array($ids)) {
				self::log('the candidate query failed: ' . $wpdb->last_error);
				return false;
			}

			$failed    = 0;
			$conflicts = 0;
			foreach (array_unique(array_map('intval', $ids)) as $post_id) {
				if (! self::migrate_row($post_id, $registered, $conflicts)) {
					++$failed;
				}
			}

			if ($failed > 0 || $conflicts > 0) {
				self::log(sprintf('failed rows: %d, conflicts: %d (groups left in place)', $failed, $conflicts));
			}
			if ($failed > 0) {
				return false;
			}

			update_option(self::DONE_OPTION, '1', false);
			return true;
		} catch (\Throwable $e) {
			self::log('stopped by ' . get_class($e) . ': ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Called by DatabaseMigrator's 4.5.0 step: queue the work and reset the attempts.
	 */
	public static function mark_pending(): void
	{
		update_option(self::PENDING_OPTION, '1', false);
		delete_option(self::ATTEMPTS_OPTION);
	}

	/**
	 * The admin_init (priority 20) callback, behind the migrator's own request gate.
	 *
	 * WordPress fires admin_init for unauthenticated admin-ajax.php and admin-post.php
	 * requests too; a visitor's booking-form AJAX must never pay for a full scan and an
	 * Elementor cache flush. Same gate as DatabaseMigrator::run_migrations_from_hook().
	 */
	public static function run_pending_from_hook(): void
	{
		if (! DatabaseMigrator::context_allows(is_admin(), wp_doing_ajax(), wp_doing_cron(), defined('WP_CLI') && WP_CLI, 'admin-post.php' === ( $GLOBALS['pagenow'] ?? '' ))) {
			return;
		}

		self::maybe_run_pending();
	}

	/**
	 * Finish a pending migration once Elementor is up.
	 *
	 * A migration that keeps failing stops after MAX_ATTEMPTS instead of replaying on
	 * every admin request; the next database version bump resets the counter. The
	 * attempt is spent before the work starts, so a run that dies mid-way (timeout,
	 * memory) still counts.
	 */
	public static function maybe_run_pending(): void
	{
		if ('1' !== get_option(self::PENDING_OPTION)) {
			return;
		}
		// Elementor off: wait for it without spending an attempt.
		if (! self::elementor_ready()) {
			return;
		}
		$attempts = (int) get_option(self::ATTEMPTS_OPTION, 0);
		if ($attempts >= self::MAX_ATTEMPTS) {
			return;
		}

		++$attempts;
		update_option(self::ATTEMPTS_OPTION, (string) $attempts, false);

		$done = self::run();

		// Per-page CSS still carries the old selectors and defaults, migrated or not.
		// Cleared after the run: a page CSS rebuilt from not-yet-migrated data while
		// the run was going would otherwise survive it. clear_cache() fires
		// elementor/core/files/clear_cache, so a third-party consumer can throw: that
		// is a failed attempt, never an exception out of admin_init.
		try {
			self::clear_css_cache();
		} catch (\Throwable $e) {
			self::log('the CSS cache flush failed: ' . get_class($e) . ': ' . $e->getMessage());
			$done = false;
		}

		if ($done) {
			delete_option(self::PENDING_OPTION);
			delete_option(self::ATTEMPTS_OPTION);
			return;
		}

		if (self::MAX_ATTEMPTS === $attempts) {
			self::log(sprintf('gave up after %d attempts', self::MAX_ATTEMPTS));
		}
	}

	/**
	 * Drop Elementor's generated page CSS so it is rebuilt with the new selectors.
	 */
	public static function clear_css_cache(): void
	{
		if (self::elementor_ready()) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
			return;
		}
		delete_post_meta_by_key('_elementor_css');
	}

	private static function elementor_ready(): bool
	{
		// Elementor\Plugin::init() builds widgets_manager and files_manager before it
		// fires elementor/init, so the action is the readiness signal.
		$ready = did_action('elementor/init') > 0 && class_exists(\Elementor\Plugin::class);

		// The filter can only narrow: it never declares a missing Elementor ready.
		return $ready && (bool) apply_filters('mhmrentiva_elementor_style_migration_available', true);
	}

	/**
	 * Registered control ids and group names per migrated widget, common controls included.
	 *
	 * @return array<string,array{ids:array<string,true>,groups:array<string,true>}>|null
	 */
	private static function registered_names(): ?array
	{
		$common_controls = array();
		foreach (array( 'common', 'common-optimized' ) as $common_name) {
			$common = \Elementor\Plugin::$instance->widgets_manager->get_widget_types($common_name);
			if ($common instanceof \Elementor\Widget_Base) {
				$common_stack     = $common->get_stack(false);
				$common_controls += (array) ( $common_stack['controls'] ?? array() ) + (array) ( $common_stack['style_controls'] ?? array() );
			}
		}

		$out = array();
		foreach (array_keys(self::RULES) as $name) {
			$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types($name);
			if (! $widget instanceof \Elementor\Widget_Base) {
				// Elementor's Element Manager skips registering a widget the site disabled, but
				// its saved instances keep the legacy ids. The class still declares the stack.
				$class = self::RULES[ $name ]['class'];
				if (! class_exists($class)) {
					return null;
				}
				$widget = new $class();
			}
			// get_stack( true ): the common (Advanced tab) controls are only in the full stack.
			$stack    = $widget->get_stack(true);
			$controls = (array) ( $stack['controls'] ?? array() ) + (array) ( $stack['style_controls'] ?? array() );
			// Outside wp-admin Elementor files selector-carrying controls under `style_controls`,
			// and get_stack( true ) merges only the common widget's `controls` bucket: its
			// `_box_shadow` group would be missing. Read both common widgets' own stacks too;
			// a larger exclusion set only keeps more keys in place.
			foreach ($common_controls as $id => $control) {
				if (! isset($controls[ $id ])) {
					$controls[ $id ] = $control;
				}
			}
			$ids    = array();
			$groups = array();
			foreach ($controls as $id => $control) {
				$ids[ (string) $id ] = true;
				// Elementor sets groupPrefix on a group's popover starter, which typography
				// and box shadow groups always have.
				if (isset($control['groupPrefix'])) {
					$groups[ rtrim( (string) $control['groupPrefix'], '_') ] = true;
				}
			}
			$out[ $name ] = array(
				'ids'    => $ids,
				'groups' => $groups,
			);
		}

		return $out;
	}

	/**
	 * @param array<string,array{ids:array<string,true>,groups:array<string,true>}> $registered
	 */
	private static function migrate_row(int $post_id, array $registered, int &$conflicts): bool
	{
		$raw = get_metadata('post', $post_id, self::META, true);
		if (! is_string($raw) || '' === $raw) {
			return false;
		}
		// Objects, not arrays: an empty `{}` must not come back as `[]`.
		$tree = json_decode($raw);
		if (! is_array($tree)) {
			return false;
		}

		$changed = false;
		self::walk($tree, $registered, $changed, $conflicts);
		if (! $changed) {
			return true;
		}

		$json = wp_json_encode($tree);
		if (false === $json) {
			return false;
		}
		// Elementor's own write path; update_post_meta() would redirect a revision to its parent.
		if (! update_metadata('post', $post_id, self::META, wp_slash($json))) {
			return false;
		}

		if (get_metadata('post', $post_id, self::META, true) !== $json) {
			return false;
		}
		// This row's page CSS may have been built from the old data while the run was
		// going. The global flush after the run does not always reach it: with
		// Elementor's e_optimized_css_files experiment a second clear_cache() in the
		// same request is a no-op. Dropping the row's own CSS meta makes Elementor
		// rebuild it on the next view.
		delete_metadata('post', $post_id, '_elementor_css');

		return true;
	}

	/**
	 * @param array<int,mixed>                                                       $elements
	 * @param array<string,array{ids:array<string,true>,groups:array<string,true>}> $registered
	 */
	private static function walk(array $elements, array $registered, bool &$changed, int &$conflicts): void
	{
		foreach ($elements as $element) {
			if (! $element instanceof \stdClass) {
				continue;
			}
			$type = isset($element->widgetType) ? (string) $element->widgetType : '';
			if (isset(self::RULES[ $type ]) && isset($element->settings) && $element->settings instanceof \stdClass) {
				$result = self::migrate_settings($element->settings, self::RULES[ $type ], $registered[ $type ]);
				if (null === $result) {
					++$conflicts;
				} elseif ($result) {
					$changed = true;
				}
			}
			if (isset($element->elements) && is_array($element->elements)) {
				self::walk($element->elements, $registered, $changed, $conflicts);
			}
		}
	}

	/**
	 * Rename one widget's legacy group in place.
	 *
	 * @param array{pattern:string,suffix:string,target:string} $rule
	 * @param array{ids:array<string,true>,groups:array<string,true>} $registered
	 * @return bool|null True when renamed, false when nothing to do, null on a conflict.
	 */
	private static function migrate_settings(\stdClass $settings, array $rule, array $registered): ?bool
	{
		$globals = ( isset($settings->__globals__) && $settings->__globals__ instanceof \stdClass ) ? $settings->__globals__ : null;
		$bags    = null === $globals ? array( $settings ) : array( $settings, $globals );

		$legacy = array();
		foreach ($bags as $bag) {
			foreach (array_keys(get_object_vars($bag)) as $key) {
				$prefix = self::legacy_prefix( (string) $key, $rule, $registered);
				if (null !== $prefix) {
					$legacy[ $prefix ] = true;
				}
			}
		}
		if (array() === $legacy) {
			return false;
		}
		// Saved in two languages: which one should win is unknowable.
		if (count($legacy) > 1) {
			return null;
		}
		// Any value already in the target group wins, global or local: Elementor resolves a
		// global link before the local value, so a key-by-key merge could mask a newer choice.
		$target_prefix = $rule['target'] . '_';
		foreach ($bags as $bag) {
			foreach (get_object_vars($bag) as $key => $value) {
				if (0 === strpos( (string) $key, $target_prefix) && null !== $value && '' !== $value) {
					return null;
				}
			}
		}

		$source_prefix = (string) array_key_first($legacy) . $rule['suffix'] . '_';
		foreach ($bags as $bag) {
			$renamed = array();
			foreach (get_object_vars($bag) as $key => $value) {
				$key = (string) $key;
				if (0 === strpos($key, $source_prefix) && null !== self::legacy_prefix($key, $rule, $registered)) {
					$key = $target_prefix . substr($key, strlen($source_prefix));
				}
				$renamed[ $key ] = $value;
			}
			foreach (array_keys(get_object_vars($bag)) as $key) {
				unset($bag->{$key});
			}
			foreach ($renamed as $key => $value) {
				$bag->{$key} = $value;
			}
		}

		return true;
	}

	/**
	 * The legacy prefix a key belongs to, or null when the key is not a legacy group key.
	 *
	 * @param array{pattern:string,suffix:string,target:string} $rule
	 * @param array{ids:array<string,true>,groups:array<string,true>} $registered
	 */
	private static function legacy_prefix(string $key, array $rule, array $registered): ?string
	{
		if (isset($registered['ids'][ $key ]) || ! preg_match($rule['pattern'], $key, $m)) {
			return null;
		}
		if (isset($registered['groups'][ $m[1] . $rule['suffix'] ])) {
			return null;
		}

		return $m[1];
	}

	private static function log(string $why): void
	{
		if (class_exists(\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::class)) {
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error(
				'Elementor style id migration (4.5.0): ' . $why,
				array(),
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
			);
		}
	}
}
