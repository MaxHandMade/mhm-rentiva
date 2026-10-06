<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Migration;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use MHMRentiva\Admin\Core\Utilities\ElementorStyleIdMigration;
use MHMRentiva\Admin\PostTypes\Logs\PostType as LogPostType;
use MHMRentiva\Tests\Support\ForgetsMigrationLock;
use WP_UnitTestCase;

/**
 * Spec §2.3 / §3 T4: before Slice 2 the search widget's typography group and the
 * booking form's shadow group took their id from the translated label
 * (`genel-stil_typography`, `golge_shadow`). Those two groups worked, so a value
 * saved in another admin language is visible on the site today; it moves to the
 * fixed English id. Nothing else is touched.
 */
final class ElementorStyleIdMigrationTest extends WP_UnitTestCase
{
	use ForgetsMigrationLock;

	private const META = '_elementor_data';

	private int $element_seq = 0;

	public function set_up(): void
	{
		parent::set_up();
		self::forget_migration_lock();
		foreach (array( ElementorStyleIdMigration::DONE_OPTION, ElementorStyleIdMigration::PENDING_OPTION, ElementorStyleIdMigration::ATTEMPTS_OPTION ) as $option) {
			delete_option($option);
		}
	}

	public function tear_down(): void
	{
		// test_hook_does_nothing_on_an_anonymous_ajax_request makes is_admin() true.
		set_current_screen('front');
		parent::tear_down();
		self::forget_migration_lock();
	}

	// ---------------------------------------------------------------- fixtures

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	private function widget(string $type, array $settings): array
	{
		return array(
			'id'         => 'w' . ( ++$this->element_seq ),
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => array() === $settings ? new \stdClass() : $settings,
			'elements'   => array(),
		);
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	private function search(array $settings): array
	{
		return $this->widget('rv-vehicle-search', $settings);
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	private function booking(array $settings): array
	{
		return $this->widget('rv-booking-form', $settings);
	}

	/**
	 * @param list<array<string,mixed>> $children
	 * @return array<string,mixed>
	 */
	private function container(string $type, array $children): array
	{
		return array(
			'id'       => 'c' . ( ++$this->element_seq ),
			'elType'   => $type,
			'settings' => new \stdClass(),
			'elements' => $children,
		);
	}

	/**
	 * Saved the way Elementor saves it (Document::save_elements()).
	 *
	 * @param list<array<string,mixed>> $tree
	 */
	private function page(array $tree, string $post_type = 'page', int $id = 0): int
	{
		if (0 === $id) {
			$id = (int) wp_insert_post(
				array(
					'post_type'   => $post_type,
					'post_status' => 'publish',
					'post_title'  => 'Elementor fixture',
				)
			);
		}
		update_metadata('post', $id, self::META, wp_slash(wp_json_encode($tree)));

		return $id;
	}

	private function raw(int $id): string
	{
		return (string) get_metadata('post', $id, self::META, true);
	}

	/**
	 * Settings of the element with $element_id, decoded as arrays.
	 *
	 * @return array<string,mixed>
	 */
	private function settings(int $post_id, string $element_id): array
	{
		$found = $this->find((array) json_decode($this->raw($post_id), true), $element_id);
		$this->assertNotNull($found, 'element ' . $element_id . ' not found');

		return (array) $found['settings'];
	}

	/**
	 * @param list<array<string,mixed>> $elements
	 * @return array<string,mixed>|null
	 */
	private function find(array $elements, string $element_id): ?array
	{
		foreach ($elements as $element) {
			if (( $element['id'] ?? '' ) === $element_id) {
				return $element;
			}
			$hit = $this->find((array) ( $element['elements'] ?? array() ), $element_id);
			if (null !== $hit) {
				return $hit;
			}
		}

		return null;
	}

	/**
	 * Counts migration entries in the plugin log.
	 */
	private function log_entries(): array
	{
		$posts = get_posts(
			array(
				'post_type'   => LogPostType::TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				's'           => 'Elementor style id migration',
			)
		);

		return array_map(static fn($p) => (string) $p->post_title, $posts);
	}

	/**
	 * Counts _elementor_data writes until the returned closure is called.
	 */
	private function count_writes(): \Closure
	{
		$count  = 0;
		$filter = static function ($check, $object_id, $meta_key) use (&$count) {
			if (self::META === $meta_key) {
				++$count;
			}
			return $check;
		};
		add_filter('update_post_metadata', $filter, 10, 3);

		return static function () use (&$count, $filter): int {
			remove_filter('update_post_metadata', $filter, 10);
			return $count;
		};
	}

	private function fail_writes_for(int $post_id): \Closure
	{
		$filter = static function ($check, $object_id, $meta_key) use ($post_id) {
			return ( (int) $object_id === $post_id && self::META === $meta_key ) ? false : $check;
		};
		add_filter('update_post_metadata', $filter, 10, 3);

		return static function () use ($filter): void {
			remove_filter('update_post_metadata', $filter, 10);
		};
	}

	// ------------------------------------------------------------------- tests

	public function test_turkish_search_typography_moves_to_the_english_group(): void
	{
		$w  = $this->search(array( 'genel-stil_typography_typography' => 'custom', 'genel-stil_typography_font_size' => array( 'unit' => 'px', 'size' => 18 ) ));
		$id = $this->page(array( $this->container('section', array( $w )) ));

		$this->assertTrue(ElementorStyleIdMigration::run());

		$s = $this->settings($id, $w['id']);
		$this->assertSame(array( 'unit' => 'px', 'size' => 18 ), $s['general-style_typography_font_size']);
		$this->assertSame('custom', $s['general-style_typography_typography']);
		$this->assertArrayNotHasKey('genel-stil_typography_font_size', $s);
		$this->assertArrayNotHasKey('genel-stil_typography_typography', $s);
		$this->assertSame('1', get_option(ElementorStyleIdMigration::DONE_OPTION));
	}

	public function test_underscore_and_cyrillic_prefixes_move(): void
	{
		$a  = $this->search(array( 'general_style_typography_typography' => 'custom' ));
		$b  = $this->search(array( '_genel-stil_typography_font_family' => 'Roboto' ));
		$c  = $this->search(array( '%d1%82%d0%b5_typography_font_weight' => '700' ));
		$id = $this->page(array( $a, $b, $c ));

		$this->assertTrue(ElementorStyleIdMigration::run());

		$this->assertSame(array( 'general-style_typography_typography' => 'custom' ), $this->settings($id, $a['id']));
		$this->assertSame(array( 'general-style_typography_font_family' => 'Roboto' ), $this->settings($id, $b['id']));
		$this->assertSame(array( 'general-style_typography_font_weight' => '700' ), $this->settings($id, $c['id']));
	}

	public function test_variable_font_and_extra_breakpoint_fields_move(): void
	{
		$w  = $this->search(array( 'genel-stil_typography_weight_tablet_extra' => 300, 'genel-stil_typography_width' => 90 ));
		$id = $this->page(array( $w ));

		$this->assertTrue(ElementorStyleIdMigration::run());

		$this->assertSame(array( 'general-style_typography_weight_tablet_extra' => 300, 'general-style_typography_width' => 90 ), $this->settings($id, $w['id']));
	}

	public function test_globals_move_with_the_group(): void
	{
		$w  = $this->search(array( '__globals__' => array( 'genel-stil_typography_typography' => 'globals/typography?id=primary' ) ));
		$id = $this->page(array( $w ));

		$this->assertTrue(ElementorStyleIdMigration::run());

		$this->assertSame(array( 'general-style_typography_typography' => 'globals/typography?id=primary' ), $this->settings($id, $w['id'])['__globals__']);
	}

	public function test_group_is_kept_when_target_has_a_local_value(): void
	{
		$settings = array( 'genel-stil_typography_font_size' => array( 'unit' => 'px', 'size' => 18 ), 'general-style_typography_font_family' => 'Inter' );
		$w        = $this->search($settings);
		$id       = $this->page(array( $w ));

		$this->assertTrue(ElementorStyleIdMigration::run());

		$this->assertSame($settings, $this->settings($id, $w['id']));
	}

	public function test_group_is_kept_when_target_has_a_global(): void
	{
		$settings = array(
			'genel-stil_typography_font_size' => array( 'unit' => 'px', 'size' => 18 ),
			'__globals__'                     => array( 'general-style_typography_typography' => 'globals/typography?id=accent' ),
		);
		$w        = $this->search($settings);
		$id       = $this->page(array( $w ));

		$this->assertTrue(ElementorStyleIdMigration::run());

		$this->assertSame($settings, $this->settings($id, $w['id']));
	}

	public function test_two_legacy_prefixes_are_not_merged(): void
	{
		$settings = array( 'genel-stil_typography_font_size' => array( 'unit' => 'px', 'size' => 18 ), 'general_style_typography_font_family' => 'Inter' );
		$w        = $this->search($settings);
		$id       = $this->page(array( $w ));

		$this->assertTrue(ElementorStyleIdMigration::run());

		$this->assertSame($settings, $this->settings($id, $w['id']));
	}

	public function test_booking_shadow_moves_but_common_shadows_do_not(): void
	{
		$w  = $this->booking(
			array(
				'golge_shadow_box_shadow_type'      => 'yes',
				'golge_shadow_box_shadow'           => array( 'horizontal' => 1, 'vertical' => 2, 'blur' => 3, 'spread' => 0, 'color' => 'rgba(0,0,0,.3)' ),
				'_box_shadow_box_shadow_type'       => 'yes',
				'_box_shadow_hover_box_shadow_type' => 'yes',
			)
		);
		$id = $this->page(array( $w ));

		$this->assertTrue(ElementorStyleIdMigration::run());

		$s = $this->settings($id, $w['id']);
		$this->assertSame('yes', $s['shadow_shadow_box_shadow_type']);
		$this->assertSame(3, $s['shadow_shadow_box_shadow']['blur']);
		$this->assertArrayNotHasKey('golge_shadow_box_shadow_type', $s);
		$this->assertSame('yes', $s['_box_shadow_box_shadow_type']);
		$this->assertSame('yes', $s['_box_shadow_hover_box_shadow_type']);
	}

	public function test_common_shadow_alone_is_not_migrated(): void
	{
		$settings = array(
			'_box_shadow_box_shadow_type' => 'yes',
			'_box_shadow_box_shadow'      => array( 'horizontal' => 0, 'vertical' => 4, 'blur' => 8, 'spread' => 0, 'color' => '#000' ),
		);
		$w        = $this->booking($settings);
		$id       = $this->page(array( $w ));
		$before   = $this->raw($id);

		$this->assertTrue(ElementorStyleIdMigration::run());

		$this->assertSame($before, $this->raw($id));
	}

	public function test_nested_and_library_templates_migrate(): void
	{
		$deep = $this->search(array( 'genel-stil_typography_font_family' => 'Lora' ));
		$tree = array( $this->container('section', array( $this->container('column', array( $this->container('section', array( $this->container('column', array( $deep )) )) )) )) );
		$page = $this->page($tree);
		$lib  = $this->search(array( 'genel-stil_typography_font_family' => 'Lato' ));
		$tpl  = $this->page(array( $lib ), 'elementor_library');

		$this->assertTrue(ElementorStyleIdMigration::run());

		$this->assertSame(array( 'general-style_typography_font_family' => 'Lora' ), $this->settings($page, $deep['id']));
		$this->assertSame(array( 'general-style_typography_font_family' => 'Lato' ), $this->settings($tpl, $lib['id']));
	}

	public function test_write_failure_keeps_done_unset(): void
	{
		$bad_w  = $this->search(array( 'genel-stil_typography_font_family' => 'Lora' ));
		$good_w = $this->search(array( 'genel-stil_typography_font_family' => 'Lato' ));
		$bad    = $this->page(array( $bad_w ));
		$good   = $this->page(array( $good_w ));

		$release = $this->fail_writes_for($bad);
		try {
			$this->assertFalse(ElementorStyleIdMigration::run());
		} finally {
			$release();
		}

		$this->assertFalse(get_option(ElementorStyleIdMigration::DONE_OPTION));
		$this->assertSame(array( 'genel-stil_typography_font_family' => 'Lora' ), $this->settings($bad, $bad_w['id']));
		$this->assertSame(array( 'general-style_typography_font_family' => 'Lato' ), $this->settings($good, $good_w['id']));
	}

	public function test_conflicts_are_counted_and_logged_once(): void
	{
		$this->page(array( $this->search(array( 'genel-stil_typography_font_size' => 1, 'general-style_typography_font_size' => 2 )) ));
		$this->page(array( $this->search(array( 'genel-stil_typography_font_size' => 1, 'general_style_typography_font_size' => 2 )) ));
		$before = count($this->log_entries());

		$this->assertTrue(ElementorStyleIdMigration::run());

		$entries = array_slice($this->log_entries(), 0, count($this->log_entries()) - $before);
		$this->assertCount(1, $entries, 'one log entry per run');
		$this->assertStringContainsString('conflicts: 2', $entries[0]);
	}

	public function test_pending_runs_on_admin_init_and_respects_attempts(): void
	{
		$w  = $this->search(array( 'genel-stil_typography_font_family' => 'Lora' ));
		$id = $this->page(array( $w ));

		// Failing write: three attempts, then the fourth call does not run at all.
		ElementorStyleIdMigration::mark_pending();
		$release = $this->fail_writes_for($id);
		try {
			for ($attempt = 1; $attempt <= 3; $attempt++) {
				ElementorStyleIdMigration::maybe_run_pending();
				$this->assertSame($attempt, (int) get_option(ElementorStyleIdMigration::ATTEMPTS_OPTION));
			}
			$writes = $this->count_writes();
			ElementorStyleIdMigration::maybe_run_pending();
			$this->assertSame(0, $writes(), 'a fourth attempt ran');
			$this->assertSame(3, (int) get_option(ElementorStyleIdMigration::ATTEMPTS_OPTION));
		} finally {
			$release();
		}

		// A new version bump resets the counter; a working write then finishes the job.
		ElementorStyleIdMigration::mark_pending();
		$this->assertFalse(get_option(ElementorStyleIdMigration::ATTEMPTS_OPTION));
		ElementorStyleIdMigration::maybe_run_pending();

		$this->assertFalse(get_option(ElementorStyleIdMigration::PENDING_OPTION));
		$this->assertFalse(get_option(ElementorStyleIdMigration::ATTEMPTS_OPTION));
		$this->assertSame('1', get_option(ElementorStyleIdMigration::DONE_OPTION));
		$this->assertSame(array( 'general-style_typography_font_family' => 'Lora' ), $this->settings($id, $w['id']));
	}

	public function test_other_widgets_and_dead_keys_are_untouched(): void
	{
		$card    = $this->widget('rv-vehicle-card', array( 'tipografi_typography_font_size' => array( 'unit' => 'px', 'size' => 20 ) ));
		$heading = $this->widget('heading', array( 'genel-stil_typography_font_size' => array( 'unit' => 'px', 'size' => 20 ) ));
		$search  = $this->search(array( 'genel-stil_typography_font_family' => 'Lora' ));
		$id      = $this->page(array( $card, $heading, $search ));

		$this->assertTrue(ElementorStyleIdMigration::run());

		$this->assertSame(array( 'tipografi_typography_font_size' => array( 'unit' => 'px', 'size' => 20 ) ), $this->settings($id, $card['id']));
		$this->assertSame(array( 'genel-stil_typography_font_size' => array( 'unit' => 'px', 'size' => 20 ) ), $this->settings($id, $heading['id']));
		$this->assertSame(array( 'general-style_typography_font_family' => 'Lora' ), $this->settings($id, $search['id']));
	}

	public function test_english_data_is_not_rewritten(): void
	{
		$id     = $this->page(array( $this->search(array( 'general-style_typography_font_family' => 'Inter', 'main_color' => '#123456' )), $this->booking(array( 'shadow_shadow_box_shadow_type' => 'yes' )) ));
		$before = $this->raw($id);
		$writes = $this->count_writes();

		$this->assertTrue(ElementorStyleIdMigration::run());

		$this->assertSame(0, $writes());
		$this->assertSame($before, $this->raw($id));
	}

	public function test_unrelated_content_survives_round_trip(): void
	{
		$text   = $this->widget('text-editor', array( 'editor' => "Say \"hi\" \\ back ğüş€ </script> <b>x</b>" ));
		$empty  = $this->widget('spacer', array());
		$search = $this->search(array( 'genel-stil_typography_font_family' => 'Lora' ));
		$id     = $this->page(array( $text, $empty, $search ));

		$this->assertTrue(ElementorStyleIdMigration::run());

		$raw = $this->raw($id);
		$this->assertStringContainsString('"settings":{}', $raw, 'an empty settings object became an array');
		$this->assertSame("Say \"hi\" \\ back ğüş€ </script> <b>x</b>", $this->settings($id, $text['id'])['editor']);
		$this->assertSame(array( 'general-style_typography_font_family' => 'Lora' ), $this->settings($id, $search['id']));
	}

	public function test_parent_and_revision_migrate_independently(): void
	{
		$parent_w = $this->search(array( 'genel-stil_typography_font_family' => 'Parent Font' ));
		$parent   = $this->page(array( $parent_w ));
		$rev      = (int) wp_insert_post(
			array(
				'post_type'   => 'revision',
				'post_parent' => $parent,
				'post_status' => 'inherit',
				'post_name'   => "$parent-revision-v1",
			)
		);
		$this->assertGreaterThan(0, $rev);
		$rev_w = $this->search(array( 'genel-stil_typography_font_family' => 'Revision Font' ));
		$this->page(array( $rev_w ), 'revision', $rev);

		$this->assertTrue(ElementorStyleIdMigration::run());

		$this->assertSame(array( 'general-style_typography_font_family' => 'Parent Font' ), $this->settings($parent, $parent_w['id']));
		$this->assertSame(array( 'general-style_typography_font_family' => 'Revision Font' ), $this->settings($rev, $rev_w['id']));
	}

	public function test_broken_json_is_skipped_and_done_is_not_written(): void
	{
		$broken = (int) wp_insert_post(array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'broken' ));
		update_metadata('post', $broken, self::META, wp_slash('{bozuk "widgetType":"rv-vehicle-search"'));
		$w  = $this->search(array( 'genel-stil_typography_font_family' => 'Lora' ));
		$ok = $this->page(array( $w ));

		$this->assertFalse(ElementorStyleIdMigration::run());

		$this->assertFalse(get_option(ElementorStyleIdMigration::DONE_OPTION));
		$this->assertSame('{bozuk "widgetType":"rv-vehicle-search"', $this->raw($broken));
		$this->assertSame(array( 'general-style_typography_font_family' => 'Lora' ), $this->settings($ok, $w['id']));
	}

	public function test_query_error_does_not_write_done(): void
	{
		global $wpdb;
		$this->page(array( $this->search(array( 'genel-stil_typography_font_family' => 'Lora' )) ));
		$break = static function ($query) {
			return ( false !== strpos($query, 'rv-vehicle-search') && 0 === stripos(ltrim($query), 'SELECT') ) ? 'SELECT post_id FROM no_such_table_mhm_x' : $query;
		};
		add_filter('query', $break);
		$suppress = $wpdb->suppress_errors(true);
		try {
			$this->assertFalse(ElementorStyleIdMigration::run());
		} finally {
			remove_filter('query', $break);
			$wpdb->suppress_errors($suppress);
		}

		$this->assertFalse(get_option(ElementorStyleIdMigration::DONE_OPTION));
	}

	public function test_empty_result_writes_done(): void
	{
		$this->assertTrue(ElementorStyleIdMigration::run());
		$this->assertSame('1', get_option(ElementorStyleIdMigration::DONE_OPTION));
	}

	public function test_without_elementor_nothing_is_touched(): void
	{
		$w      = $this->search(array( 'genel-stil_typography_font_family' => 'Lora' ));
		$id     = $this->page(array( $w ));
		$before = $this->raw($id);
		add_filter('mhmrentiva_elementor_style_migration_available', '__return_false');
		try {
			$this->assertFalse(ElementorStyleIdMigration::run());
			ElementorStyleIdMigration::mark_pending();
			ElementorStyleIdMigration::maybe_run_pending();
		} finally {
			remove_filter('mhmrentiva_elementor_style_migration_available', '__return_false');
		}

		$this->assertSame($before, $this->raw($id));
		$this->assertFalse(get_option(ElementorStyleIdMigration::DONE_OPTION));
		$this->assertSame('1', get_option(ElementorStyleIdMigration::PENDING_OPTION));
		$this->assertFalse(get_option(ElementorStyleIdMigration::ATTEMPTS_OPTION), 'an unavailable Elementor spent an attempt');
	}

	public function test_second_run_is_a_no_op(): void
	{
		$this->page(array( $this->search(array( 'genel-stil_typography_font_family' => 'Lora' )) ));
		$this->assertTrue(ElementorStyleIdMigration::run());

		$writes = $this->count_writes();
		$this->assertTrue(ElementorStyleIdMigration::run());
		// Even without the done flag the already-migrated rows need no write.
		delete_option(ElementorStyleIdMigration::DONE_OPTION);
		$this->assertTrue(ElementorStyleIdMigration::run());
		$this->assertSame(0, $writes());
	}

	// ------------------------------------------- final review fixes (I1-I3)

	public function test_hook_does_nothing_on_an_anonymous_ajax_request(): void
	{
		$w      = $this->search(array( 'genel-stil_typography_font_family' => 'Lora' ));
		$id     = $this->page(array( $w ));
		$before = $this->raw($id);
		ElementorStyleIdMigration::mark_pending();
		set_current_screen('dashboard');
		add_filter('wp_doing_ajax', '__return_true');
		try {
			ElementorStyleIdMigration::run_pending_from_hook();
		} finally {
			remove_filter('wp_doing_ajax', '__return_true');
		}

		$this->assertSame($before, $this->raw($id), 'an admin-ajax request ran the migration');
		$this->assertFalse(get_option(ElementorStyleIdMigration::ATTEMPTS_OPTION));
		$this->assertSame('1', get_option(ElementorStyleIdMigration::PENDING_OPTION));

		// The same hook on an admin page load does the work.
		ElementorStyleIdMigration::run_pending_from_hook();
		$this->assertSame(array( 'general-style_typography_font_family' => 'Lora' ), $this->settings($id, $w['id']));
		$this->assertFalse(get_option(ElementorStyleIdMigration::PENDING_OPTION));
	}

	public function test_attempt_is_counted_before_the_work_starts(): void
	{
		$this->page(array( $this->search(array( 'genel-stil_typography_font_family' => 'Lora' )) ));
		ElementorStyleIdMigration::mark_pending();
		$seen  = array();
		$probe = static function ($check, $object_id, $meta_key) use (&$seen) {
			if ('_elementor_data' === $meta_key) {
				$seen[] = get_option(ElementorStyleIdMigration::ATTEMPTS_OPTION);
			}
			return $check;
		};
		add_filter('update_post_metadata', $probe, 10, 3);
		try {
			ElementorStyleIdMigration::maybe_run_pending();
		} finally {
			remove_filter('update_post_metadata', $probe, 10);
		}

		// A run that dies mid-way (timeout, memory) must already have spent its attempt.
		$this->assertSame(array( '1' ), $seen);
		$this->assertFalse(get_option(ElementorStyleIdMigration::ATTEMPTS_OPTION), 'a successful run clears the counter');
	}

	public function test_css_regenerated_during_the_run_is_cleared_afterwards(): void
	{
		$page = $this->page(array( $this->search(array( 'genel-stil_typography_font_family' => 'Lora' )) ));
		ElementorStyleIdMigration::mark_pending();
		// A front-end request rebuilds the page CSS from not-yet-migrated data while the run is going.
		$visitor = static function ($check, $object_id, $meta_key) use ($page) {
			if ('_elementor_data' === $meta_key && (int) $object_id === $page) {
				update_post_meta($page, '_elementor_css', array( 'status' => 'file', 'time' => time() ));
			}
			return $check;
		};
		add_filter('update_post_metadata', $visitor, 10, 3);
		try {
			ElementorStyleIdMigration::maybe_run_pending();
		} finally {
			remove_filter('update_post_metadata', $visitor, 10);
		}

		$this->assertSame('', get_post_meta($page, '_elementor_css', true), 'stale CSS built mid-run survived');
	}

	public function test_a_failing_cache_flush_is_contained_and_counted(): void
	{
		$this->page(array( $this->search(array( 'genel-stil_typography_font_family' => 'Lora' )) ));
		ElementorStyleIdMigration::mark_pending();
		$calls = 0;
		$boom  = static function () use (&$calls) {
			++$calls;
			throw new \RuntimeException('cache consumer failed');
		};
		add_action('elementor/core/files/clear_cache', $boom);
		try {
			for ($attempt = 1; $attempt <= 4; $attempt++) {
				ElementorStyleIdMigration::maybe_run_pending();
			}
		} finally {
			remove_action('elementor/core/files/clear_cache', $boom);
		}

		$this->assertSame(3, $calls, 'the fourth call flushed the cache again');
		$this->assertSame(3, (int) get_option(ElementorStyleIdMigration::ATTEMPTS_OPTION));
		$this->assertSame('1', get_option(ElementorStyleIdMigration::PENDING_OPTION), 'a failed flush finished the job');
	}

	public function test_a_widget_disabled_in_elementor_still_migrates(): void
	{
		$manager = \Elementor\Plugin::$instance->widgets_manager;
		$search  = $this->search(array( 'genel-stil_typography_font_family' => 'Lora' ));
		$booking = $this->booking(array( 'golge_shadow_box_shadow_type' => 'yes' ));
		$id      = $this->page(array( $search, $booking ));
		// Elementor's Element Manager skips registering a disabled widget.
		$manager->unregister('rv-booking-form');
		try {
			$this->assertNull($manager->get_widget_types('rv-booking-form'));
			$this->assertTrue(ElementorStyleIdMigration::run());
		} finally {
			$manager->register(new \MHMRentiva\Admin\Frontend\Widgets\Elementor\BookingFormWidget());
		}

		$this->assertSame(array( 'general-style_typography_font_family' => 'Lora' ), $this->settings($id, $search['id']));
		$this->assertSame(array( 'shadow_shadow_box_shadow_type' => 'yes' ), $this->settings($id, $booking['id']));
	}

	// ------------------------------------------------- DatabaseMigrator 4.5.0

	/**
	 * The step's admin_init callback, as Plugin.php registers it.
	 *
	 * A bare do_action( 'admin_init' ) outside wp-admin trips unrelated core
	 * callbacks (wp_add_privacy_policy_content's is_admin() notice), so the test
	 * proves the registration and then runs exactly that callback.
	 */
	private function fire_admin_init_step(): void
	{
		$callback = array( ElementorStyleIdMigration::class, 'run_pending_from_hook' );
		$this->assertSame(20, has_action('admin_init', $callback), 'run_pending_from_hook is not hooked to admin_init at 20');
		$this->assertSame(10, has_action('admin_init', array( DatabaseMigrator::class, 'run_migrations_from_hook' )));
		ElementorStyleIdMigration::maybe_run_pending();
	}

	public function test_migrator_marks_the_step_pending_on_upgrade(): void
	{
		$w  = $this->search(array( 'genel-stil_typography_font_family' => 'Lora' ));
		$id = $this->page(array( $w ));
		update_option('mhmrentiva_db_version', '4.4.1');

		DatabaseMigrator::run_migrations();

		$this->assertSame('4.5.0', get_option('mhmrentiva_db_version'));
		$this->assertSame('1', get_option(ElementorStyleIdMigration::PENDING_OPTION));
		// Elementor's managers do not exist yet on plugins_loaded: the step only queues.
		$this->assertSame(array( 'genel-stil_typography_font_family' => 'Lora' ), $this->settings($id, $w['id']));
	}

	public function test_admin_init_completes_the_pending_migration(): void
	{
		$w  = $this->search(array( 'genel-stil_typography_font_family' => 'Lora' ));
		$id = $this->page(array( $w ));
		update_option('mhmrentiva_db_version', '4.4.1');
		DatabaseMigrator::run_migrations();

		$this->fire_admin_init_step();

		$this->assertSame(array( 'general-style_typography_font_family' => 'Lora' ), $this->settings($id, $w['id']));
		$this->assertSame('1', get_option(ElementorStyleIdMigration::DONE_OPTION));
		$this->assertFalse(get_option(ElementorStyleIdMigration::PENDING_OPTION));
	}

	public function test_cache_is_cleared_even_when_done(): void
	{
		update_option(ElementorStyleIdMigration::DONE_OPTION, '1');
		$page = $this->page(array( $this->search(array()) ));
		update_post_meta($page, '_elementor_css', array( 'status' => 'file', 'time' => time() ));
		update_option('mhmrentiva_db_version', '4.4.1');

		DatabaseMigrator::run_migrations();
		$this->fire_admin_init_step();

		$this->assertSame('', get_post_meta($page, '_elementor_css', true));
		$this->assertFalse(get_option(ElementorStyleIdMigration::PENDING_OPTION));
	}
}
