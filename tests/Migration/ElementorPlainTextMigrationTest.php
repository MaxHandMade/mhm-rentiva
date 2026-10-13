<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Migration;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use MHMRentiva\Admin\Core\Utilities\ElementorPlainTextMigration;
use MHMRentiva\Admin\Services\FavoritesService;
use MHMRentiva\Tests\Support\ForgetsMigrationLock;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * Audit 2026-10-13 §F, cleanup half: pages saved before the fix keep the admin's
 * bookings, e-mail and nonces in post_content and in _elementor_markdown_cache.
 * The 4.6.0 step rebuilds both copies, without touching post_modified, revisions
 * or pages that hold no Rentiva widget.
 */
final class ElementorPlainTextMigrationTest extends WP_UnitTestCase
{
	use ForgetsMigrationLock;

	private const LEAK     = 'LEAKED leak-admin@example.test #4711 data-nonce="0123456789"';
	private const HEADING  = 'Rebuilt heading text';
	private const MODIFIED = '2020-01-02 03:04:05';

	private int $element_seq = 0;

	public function set_up(): void
	{
		parent::set_up();
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; the job would measure an absence.');
		self::forget_migration_lock();
		foreach (array( ElementorPlainTextMigration::DONE_OPTION, ElementorPlainTextMigration::PENDING_OPTION, ElementorPlainTextMigration::ATTEMPTS_OPTION, ElementorPlainTextMigration::CURSOR_OPTION ) as $option) {
			delete_option($option);
		}
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_unschedule_hook(ElementorPlainTextMigration::CRON_HOOK);
	}

	public function tear_down(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		set_current_screen('front');
		RequestSimulator::new_request();
		wp_unschedule_hook(ElementorPlainTextMigration::CRON_HOOK);
		parent::tear_down();
		self::forget_migration_lock();
	}

	/**
	 * How many events of the rebuild hook are queued.
	 */
	private function queued(): int
	{
		$count = 0;
		foreach ( (array) _get_cron_array() as $hooks) {
			$count += isset($hooks[ ElementorPlainTextMigration::CRON_HOOK ]) ? count($hooks[ ElementorPlainTextMigration::CRON_HOOK ]) : 0;
		}

		return $count;
	}

	/**
	 * Fire the event the way wp-cron.php does: the event is unscheduled first, then
	 * its hook runs (so the callback can queue the next one).
	 */
	private function fire_cron(): void
	{
		wp_unschedule_hook(ElementorPlainTextMigration::CRON_HOOK);
		add_filter('wp_doing_cron', '__return_true');
		try {
			do_action(ElementorPlainTextMigration::CRON_HOOK);
		} finally {
			remove_filter('wp_doing_cron', '__return_true');
		}
	}

	/**
	 * Hold DatabaseMigrator's lock as another request would.
	 */
	private function hold_lock(): void
	{
		add_option(DatabaseMigrator::LOCK_OPTION, (string) time(), '', false);
	}

	// ---------------------------------------------------------------- fixtures

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	private function widget(string $type, array $settings = array()): array
	{
		return array(
			'id'         => 'm' . ( ++$this->element_seq ),
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => array() === $settings ? new \stdClass() : $settings,
			'elements'   => array(),
		);
	}

	private function heading(): array
	{
		return $this->widget('heading', array( 'title' => self::HEADING ));
	}

	/**
	 * A builder page with leaked plain text, an old post_modified and a markdown cache.
	 *
	 * @param list<array<string,mixed>> $widgets
	 */
	private function page(array $widgets, string $content = self::LEAK, string $mode = 'builder', string $post_type = 'page', int $parent = 0): int
	{
		global $wpdb;

		$id = (int) wp_insert_post(
			array(
				'post_type'    => $post_type,
				'post_status'  => 'revision' === $post_type ? 'inherit' : 'publish',
				'post_parent'  => $parent,
				'post_title'   => 'Elementor fixture',
				'post_name'    => 'revision' === $post_type ? "$parent-revision-v1" : '',
			)
		);
		$tree = array(
			array(
				'id'       => 'c' . ( ++$this->element_seq ),
				'elType'   => 'container',
				'isInner'  => false,
				'settings' => new \stdClass(),
				'elements' => $widgets,
			),
		);
		update_metadata('post', $id, '_elementor_edit_mode', $mode);
		update_metadata('post', $id, '_elementor_data', wp_slash((string) wp_json_encode($tree)));
		update_metadata('post', $id, '_elementor_markdown_cache', 'cached ' . self::LEAK);
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_content'      => $content,
				'post_modified'     => self::MODIFIED,
				'post_modified_gmt' => self::MODIFIED,
			),
			array( 'ID' => $id )
		);
		clean_post_cache($id);

		return $id;
	}

	private function content(int $id): string
	{
		clean_post_cache($id);
		return (string) get_post_field('post_content', $id, 'raw');
	}

	private function revision_count(int $id): int
	{
		return count(wp_get_post_revisions($id));
	}

	/**
	 * Counts UPDATEs of the posts table.
	 */
	private function count_post_updates(): \Closure
	{
		global $wpdb;
		$count  = 0;
		$table  = $wpdb->posts;
		$filter = static function ($query) use (&$count, $table) {
			if (0 === stripos(ltrim( (string) $query), 'UPDATE') && false !== strpos( (string) $query, $table)) {
				++$count;
			}
			return $query;
		};
		add_filter('query', $filter);

		return static function () use (&$count, $filter): int {
			remove_filter('query', $filter);
			return $count;
		};
	}

	// ------------------------------------------------------------------- tests

	public function test_leaked_page_is_rebuilt_in_place(): void
	{
		$id        = $this->page(array( $this->heading(), $this->widget('rv-my-bookings'), $this->widget('rv-contact-form') ));
		$revisions = $this->revision_count($id);

		$this->assertTrue(ElementorPlainTextMigration::run());

		$content = $this->content($id);
		$this->assertStringNotContainsString('LEAKED', $content);
		$this->assertStringContainsString(self::HEADING, $content, 'The page keeps Elementor\'s plain text of its other widgets.');
		$this->assertSame('', (string) get_post_meta($id, '_elementor_markdown_cache', true), 'The markdown cache survived.');
		$this->assertSame(self::MODIFIED, get_post_field('post_modified', $id, 'raw'), 'post_modified moved.');
		$this->assertSame(self::MODIFIED, get_post_field('post_modified_gmt', $id, 'raw'), 'post_modified_gmt moved.');
		$this->assertSame($revisions, $this->revision_count($id), 'A revision was created.');
		$this->assertSame('1', get_option(ElementorPlainTextMigration::DONE_OPTION));
	}

	/**
	 * The rebuild must render in front context through the fixed widgets: as an admin
	 * with a real booking, a favourite and the editor's edit mode on, nothing personal
	 * reaches post_content, and edit mode is restored afterwards.
	 */
	public function test_rebuild_renders_no_viewer_data_even_for_an_admin_in_edit_mode(): void
	{
		$email = 'job-admin-' . strtolower(wp_generate_password(6, false, false)) . '@example.test';
		$admin = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => $email,
			)
		);
		$vehicle = ShortcodeFixtures::vehicle();
		$booking = ShortcodeFixtures::booking($admin, $vehicle);
		FavoritesService::add($admin, $vehicle);
		wp_set_current_user($admin);
		RequestSimulator::new_request();

		$id = $this->page(
			array(
				$this->heading(),
				$this->widget('rv-my-bookings'),
				$this->widget('rv-payment-history'),
				$this->widget('rv-my-favorites'),
				$this->widget('mhmrentiva_vehicles_grid'),
				$this->widget('rv-contact-form'),
			)
		);

		$editor = \Elementor\Plugin::$instance->editor;
		$editor->set_edit_mode(true);
		try {
			$this->assertTrue(ElementorPlainTextMigration::run());
			$this->assertTrue($editor->is_edit_mode(), 'Edit mode was not restored.');
		} finally {
			$editor->set_edit_mode(null);
		}

		$content = $this->content($id);
		foreach (array( '#' . $booking, get_the_title($vehicle), $email, 'data-nonce', '_wpnonce', 'name="nonce"', 'rv_contact_nonce' ) as $marker) {
			$this->assertStringNotContainsString($marker, $content);
		}
		$this->assertStringContainsString(self::HEADING, $content);
	}

	public function test_second_run_is_a_no_op(): void
	{
		$id = $this->page(array( $this->heading(), $this->widget('rv-my-bookings') ));
		$this->assertTrue(ElementorPlainTextMigration::run());
		$rebuilt = $this->content($id);

		$updates = $this->count_post_updates();
		$this->assertTrue(ElementorPlainTextMigration::run());
		$this->assertSame(0, $updates(), 'The finished job wrote again.');
		$this->assertSame($rebuilt, $this->content($id));

		// A fresh pass over clean pages finds nothing to write either.
		delete_option(ElementorPlainTextMigration::DONE_OPTION);
		$updates = $this->count_post_updates();
		$this->assertTrue(ElementorPlainTextMigration::run());
		$this->assertSame(0, $updates(), 'An already clean page was rewritten.');
	}

	public function test_pages_without_rentiva_widgets_or_builder_mode_are_untouched(): void
	{
		$plain   = $this->page(array( $this->heading() ), 'Core page text');
		$classic = $this->page(array( $this->widget('rv-my-bookings') ), 'Classic editor text', '');

		$this->assertTrue(ElementorPlainTextMigration::run());

		$this->assertSame('Core page text', $this->content($plain));
		$this->assertSame('cached ' . self::LEAK, get_post_meta($plain, '_elementor_markdown_cache', true));
		$this->assertSame('Classic editor text', $this->content($classic), 'A page not built with Elementor owns its post_content.');
	}

	public function test_revisions_are_untouched_and_add_on_widgets_are_found(): void
	{
		// The add-on's names come from the job's own list: Lite cannot load the add-on.
		$addon = (string) current(preg_grep('/_vendor_profile$/', ElementorPlainTextMigration::widget_names()));
		$this->assertNotSame('', $addon);
		$parent   = $this->page(array( $this->heading(), $this->widget($addon) ));
		$revision = $this->page(array( $this->widget('rv-my-bookings') ), self::LEAK, 'builder', 'revision', $parent);

		$this->assertTrue(ElementorPlainTextMigration::run());

		$this->assertStringNotContainsString('LEAKED', $this->content($parent), 'An add-on widget page was not found.');
		$this->assertSame(self::LEAK, $this->content($revision));
	}

	public function test_runs_in_batches_and_resumes(): void
	{
		$first  = $this->page(array( $this->widget('rv-my-bookings') ));
		$second = $this->page(array( $this->widget('rv-payment-history') ));

		$this->assertFalse(ElementorPlainTextMigration::run(1));
		$this->assertStringNotContainsString('LEAKED', $this->content($first));
		$this->assertSame(self::LEAK, $this->content($second), 'The batch went past its size.');
		$this->assertFalse(get_option(ElementorPlainTextMigration::DONE_OPTION));

		$this->assertFalse(ElementorPlainTextMigration::run(1));
		$this->assertStringNotContainsString('LEAKED', $this->content($second));
		$this->assertTrue(ElementorPlainTextMigration::run(1));
		$this->assertSame('1', get_option(ElementorPlainTextMigration::DONE_OPTION));
	}

	public function test_a_page_that_throws_does_not_hold_up_the_others(): void
	{
		$broken = $this->page(array( $this->widget('heading', array( 'title' => 'BOOM' )), $this->widget('rv-my-bookings') ));
		$fine   = $this->page(array( $this->heading(), $this->widget('rv-my-bookings') ));
		$throw  = static function ($widget): void {
			if ($widget instanceof \Elementor\Widget_Base && 'BOOM' === $widget->get_settings('title')) {
				throw new \RuntimeException('third-party widget failed');
			}
		};
		add_action('elementor/widget/before_render_content', $throw);
		$level = ob_get_level();
		try {
			$this->assertTrue(ElementorPlainTextMigration::run());
		} finally {
			remove_action('elementor/widget/before_render_content', $throw);
		}

		$this->assertSame($level, ob_get_level(), 'An output buffer was left open.');
		$this->assertSame(self::LEAK, $this->content($broken));
		$this->assertStringNotContainsString('LEAKED', $this->content($fine));
	}

	public function test_without_elementor_nothing_is_touched_and_the_job_stays_pending(): void
	{
		$id = $this->page(array( $this->widget('rv-my-bookings') ));
		ElementorPlainTextMigration::mark_pending();
		add_filter('mhmrentiva_elementor_plain_text_migration_available', '__return_false');
		try {
			$this->assertFalse(ElementorPlainTextMigration::run());
			ElementorPlainTextMigration::maybe_run_pending();
		} finally {
			remove_filter('mhmrentiva_elementor_plain_text_migration_available', '__return_false');
		}

		$this->assertSame(self::LEAK, $this->content($id));
		$this->assertSame('cached ' . self::LEAK, get_post_meta($id, '_elementor_markdown_cache', true));
		$this->assertSame('1', get_option(ElementorPlainTextMigration::PENDING_OPTION));
		$this->assertFalse(get_option(ElementorPlainTextMigration::ATTEMPTS_OPTION), 'Waiting for Elementor spent an attempt.');
		$this->assertFalse(get_option(ElementorPlainTextMigration::DONE_OPTION));

		// Elementor back: the next admin request finishes it.
		ElementorPlainTextMigration::maybe_run_pending();
		$this->assertStringNotContainsString('LEAKED', $this->content($id));
		$this->assertFalse(get_option(ElementorPlainTextMigration::PENDING_OPTION));
	}

	public function test_query_error_keeps_the_job_pending_with_bounded_attempts(): void
	{
		global $wpdb;
		$this->page(array( $this->widget('rv-my-bookings') ));
		ElementorPlainTextMigration::mark_pending();
		$break = static function ($query) {
			return ( false !== strpos( (string) $query, 'widgetType') && 0 === stripos(ltrim( (string) $query), 'SELECT') ) ? 'SELECT ID FROM no_such_table_x' : $query;
		};
		add_filter('query', $break);
		$suppress = $wpdb->suppress_errors(true);
		try {
			for ($attempt = 1; $attempt <= ElementorPlainTextMigration::MAX_ATTEMPTS + 1; $attempt++) {
				ElementorPlainTextMigration::maybe_run_pending();
			}
		} finally {
			remove_filter('query', $break);
			$wpdb->suppress_errors($suppress);
		}

		$this->assertSame(ElementorPlainTextMigration::MAX_ATTEMPTS, (int) get_option(ElementorPlainTextMigration::ATTEMPTS_OPTION));
		$this->assertSame('1', get_option(ElementorPlainTextMigration::PENDING_OPTION));
		$this->assertFalse(get_option(ElementorPlainTextMigration::DONE_OPTION));
	}

	public function test_hook_is_registered_after_the_migrator_and_skips_anonymous_ajax(): void
	{
		$this->assertSame(20, has_action('admin_init', array( ElementorPlainTextMigration::class, 'run_pending_from_hook' )));

		$id = $this->page(array( $this->widget('rv-my-bookings') ));
		ElementorPlainTextMigration::mark_pending();
		set_current_screen('dashboard');
		add_filter('wp_doing_ajax', '__return_true');
		try {
			ElementorPlainTextMigration::run_pending_from_hook();
		} finally {
			remove_filter('wp_doing_ajax', '__return_true');
		}
		$this->assertSame(self::LEAK, $this->content($id));

		ElementorPlainTextMigration::run_pending_from_hook();
		$this->assertStringNotContainsString('LEAKED', $this->content($id));
	}

	/**
	 * The hardcoded list must name every Rentiva widget Lite registers; a widget
	 * added later without a list entry would keep its leaked pages.
	 */
	public function test_widget_list_covers_every_registered_lite_widget(): void
	{
		$registered = array();
		foreach (\Elementor\Plugin::$instance->widgets_manager->get_widget_types() as $name => $widget) {
			if ($widget instanceof \MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase) {
				$registered[] = (string) $name;
			}
		}

		$this->assertGreaterThanOrEqual(17, count($registered), 'The Lite widgets are not registered; the check would pass on nothing.');
		$this->assertSame(array(), array_values(array_diff($registered, ElementorPlainTextMigration::widget_names())));
	}

	public function test_queued_job_schedules_one_event(): void
	{
		ElementorPlainTextMigration::mark_pending();
		ElementorPlainTextMigration::mark_pending();

		$this->assertNotFalse(wp_next_scheduled(ElementorPlainTextMigration::CRON_HOOK));
		$this->assertSame(1, $this->queued());
		$this->assertSame(10, has_action(ElementorPlainTextMigration::CRON_HOOK, array( ElementorPlainTextMigration::class, 'run_from_cron' )));
		$this->assertContains(ElementorPlainTextMigration::CRON_HOOK, \MHMRentiva\Admin\Utilities\Uninstall\Uninstaller::plugin_cron_hooks(), 'Deactivation and uninstall would leave the event behind.');
	}

	/**
	 * Cron is the primary driver: each event runs one batch of BATCH pages and queues
	 * the next while work remains; the last one finishes the job and queues nothing.
	 */
	public function test_cron_runs_batches_and_reschedules_until_done(): void
	{
		$ids = array();
		for ($i = 0; $i <= ElementorPlainTextMigration::BATCH; $i++) {
			$ids[] = $this->page(array( $this->heading(), $this->widget('rv-vehicle-card') ));
		}
		ElementorPlainTextMigration::mark_pending();

		$this->fire_cron();

		$this->assertStringNotContainsString('LEAKED', $this->content($ids[ ElementorPlainTextMigration::BATCH - 1 ]));
		$this->assertSame(self::LEAK, $this->content($ids[ ElementorPlainTextMigration::BATCH ]), 'One event ran more than one batch.');
		$this->assertSame('1', get_option(ElementorPlainTextMigration::PENDING_OPTION));
		$this->assertSame(1, $this->queued(), 'Work remains but no next event was queued.');

		$this->fire_cron();

		$content = $this->content($ids[ ElementorPlainTextMigration::BATCH ]);
		$this->assertStringNotContainsString('LEAKED', $content);
		$this->assertStringContainsString(self::HEADING, $content, 'The cron render lost the core widgets.');
		$this->assertFalse(get_option(ElementorPlainTextMigration::PENDING_OPTION));
		$this->assertSame('1', get_option(ElementorPlainTextMigration::DONE_OPTION));
		$this->assertSame(0, $this->queued(), 'The finished job left an event queued.');
	}

	public function test_both_drivers_are_no_ops_while_the_lock_is_held(): void
	{
		$id = $this->page(array( $this->widget('rv-my-bookings') ));
		ElementorPlainTextMigration::mark_pending();
		$this->hold_lock();

		set_current_screen('dashboard');
		ElementorPlainTextMigration::run_pending_from_hook();
		$this->fire_cron();

		$this->assertSame(self::LEAK, $this->content($id), 'A batch ran under another request lock.');
		$this->assertFalse(get_option(ElementorPlainTextMigration::ATTEMPTS_OPTION), 'A busy lock spent an attempt.');
		$this->assertSame(1, $this->queued(), 'A busy lock must re-arm the event, once.');
		$this->assertNotFalse(get_option(DatabaseMigrator::LOCK_OPTION), 'The lock of the other request was released.');

		self::forget_migration_lock();
		ElementorPlainTextMigration::run_pending_from_hook();
		$this->assertStringNotContainsString('LEAKED', $this->content($id));
		$this->assertSame(0, $this->queued(), 'The admin fallback finished the job but left the event.');
		$this->assertFalse(get_option(DatabaseMigrator::LOCK_OPTION), 'The batch did not release its lock.');
	}

	public function test_cron_without_elementor_waits_without_rearming(): void
	{
		$id = $this->page(array( $this->widget('rv-my-bookings') ));
		ElementorPlainTextMigration::mark_pending();
		add_filter('mhmrentiva_elementor_plain_text_migration_available', '__return_false');
		try {
			$this->fire_cron();
		} finally {
			remove_filter('mhmrentiva_elementor_plain_text_migration_available', '__return_false');
		}

		$this->assertSame(self::LEAK, $this->content($id));
		$this->assertSame('1', get_option(ElementorPlainTextMigration::PENDING_OPTION));
		$this->assertSame(0, $this->queued(), 'An inactive Elementor would keep an event firing for nothing.');

		// The admin fallback picks it up once Elementor is back.
		set_current_screen('dashboard');
		ElementorPlainTextMigration::run_pending_from_hook();
		$this->assertStringNotContainsString('LEAKED', $this->content($id));
	}

	public function test_exhausted_attempts_stop_the_cron_chain(): void
	{
		global $wpdb;
		$this->page(array( $this->widget('rv-my-bookings') ));
		ElementorPlainTextMigration::mark_pending();
		update_option(ElementorPlainTextMigration::ATTEMPTS_OPTION, (string) ( ElementorPlainTextMigration::MAX_ATTEMPTS - 1 ), false);
		$break = static function ($query) {
			return ( false !== strpos( (string) $query, 'widgetType') && 0 === stripos(ltrim( (string) $query), 'SELECT') ) ? 'SELECT ID FROM no_such_table_x' : $query;
		};
		add_filter('query', $break);
		$suppress = $wpdb->suppress_errors(true);
		try {
			$this->fire_cron();
		} finally {
			remove_filter('query', $break);
			$wpdb->suppress_errors($suppress);
		}

		$this->assertSame(ElementorPlainTextMigration::MAX_ATTEMPTS, (int) get_option(ElementorPlainTextMigration::ATTEMPTS_OPTION));
		$this->assertSame(0, $this->queued(), 'A job that gave up keeps re-queuing itself.');
	}

	public function test_migrator_marks_the_step_pending_on_upgrade(): void
	{
		$id = $this->page(array( $this->widget('rv-my-bookings') ));
		update_option('mhmrentiva_db_version', '4.5.0');

		DatabaseMigrator::run_migrations();

		$this->assertSame('4.6.0', get_option('mhmrentiva_db_version'));
		$this->assertSame('1', get_option(ElementorPlainTextMigration::PENDING_OPTION));
		$this->assertNotFalse(wp_next_scheduled(ElementorPlainTextMigration::CRON_HOOK), 'The upgrade did not start the cron chain.');
		// Elementor's managers do not exist yet on plugins_loaded: the step only queues.
		$this->assertSame(self::LEAK, $this->content($id));
	}
}
