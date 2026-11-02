<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\VehicleRatingForm;
use MHMRentiva\Admin\Settings\Comments\CommentsSettings;
use MHMRentiva\Admin\Vehicle\Helpers\RatingHelper;
use MHMRentiva\Admin\Vehicle\Hooks\ReviewEnforcer;
use ReflectionProperty;
use WP_Ajax_UnitTestCase;
use WP_Error;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * PR F final wave: the plugin's moderation setting decides for a new review, an edit
 * never re-approves, a failed write says so and writes nothing, an ordinary comment
 * does not block the first review, and a backslash survives both write paths.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\VehicleRatingForm
 * @covers \MHMRentiva\Admin\Vehicle\Hooks\ReviewEnforcer
 */
final class ReviewWriteOutcomesTest extends WP_Ajax_UnitTestCase
{
	private const BACKSLASHED = 'Stored in C:\temp\rentiva folder';

	private int $vehicle = 0;
	private int $author  = 0;

	public function setUp(): void
	{
		parent::setUp();
		// The time trap has its own tests; these post without a drawn form.
		add_filter('mhmrentiva_contact_min_seconds', '__return_zero');
		ReviewEnforcer::register();
		add_action('wp_ajax_mhmrentiva_submit_rating', array( VehicleRatingForm::class, 'ajax_submit_rating' ));
		add_action('wp_ajax_nopriv_mhmrentiva_submit_rating', array( VehicleRatingForm::class, 'ajax_submit_rating' ));
		add_action('wp_ajax_mhmrentiva_delete_rating', array( VehicleRatingForm::class, 'ajax_delete_rating' ));

		// A clean core: nothing of core's own holds a review, so what holds it is the setting.
		update_option('comment_moderation', '0');
		update_option('comment_previously_approved', '0');

		$this->vehicle = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
			)
		);
		$this->author  = (int) self::factory()->user->create(
			array(
				'role'         => 'subscriber',
				'display_name' => 'Ayse Demir',
			)
		);
		wp_set_current_user($this->author);
	}

	public function tearDown(): void
	{
		remove_filter('preprocess_comment', array( ReviewEnforcer::class, 'enforce_vehicle_constraints' ), 1);
		remove_filter('pre_comment_approved', array( ReviewEnforcer::class, 'apply_moderation_setting' ), 10);
		$this->settings(array());
		$property = new ReflectionProperty(CommentsSettings::class, 'runtime_cache');
		$property->setAccessible(true);
		$property->setValue(null, null);
		wp_set_current_user(0);
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $approval Approval settings to apply.
	 * @param array<string, mixed> $limits   Limit settings to apply.
	 */
	private function settings(array $approval, array $limits = array()): void
	{
		$property = new ReflectionProperty(CommentsSettings::class, 'runtime_cache');
		$property->setAccessible(true);
		$property->setValue(null, null);
		$settings             = CommentsSettings::get_settings();
		$settings['approval'] = $approval + (array) $settings['approval'];
		$settings['limits']   = $limits + (array) $settings['limits'];
		$property->setValue(null, $settings);
	}

	/**
	 * @param array<string, mixed> $post Fields, unslashed as a test writes them.
	 * @return array<string, mixed>
	 */
	private function submit(array $post): array
	{
		// Reset: one test may send several requests.
		$this->_last_response = '';
		$_POST                = wp_slash($post) + array(
			'nonce'      => wp_create_nonce('mhmrentiva_rating_nonce'),
			'vehicle_id' => (string) $this->vehicle,
		);
		try {
			$this->_handleAjax('mhmrentiva_submit_rating');
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			unset($e);
		}

		return (array) json_decode($this->_last_response, true);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function delete(int $comment_id): array
	{
		$this->_last_response = '';
		$_POST                = array(
			'nonce'      => wp_create_nonce('mhmrentiva_rating_nonce'),
			'comment_id' => (string) $comment_id,
		);
		try {
			$this->_handleAjax('mhmrentiva_delete_rating');
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			unset($e);
		}

		return (array) json_decode($this->_last_response, true);
	}

	private function own_review(string $approved = '1', string $content = 'The first words.', int $rating = 4): int
	{
		$id = (int) self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->vehicle,
				'comment_type'     => 'review',
				'comment_approved' => $approved,
				'user_id'          => $this->author,
				'comment_author'   => 'Ayse Demir',
				'comment_content'  => $content,
			)
		);
		update_comment_meta($id, 'mhmrentiva_rating', $rating);

		return $id;
	}

	/**
	 * @return array<string, array{0: bool, 1: string, 2: string, 3: string}>
	 */
	public function moderation_combinations(): array
	{
		return array(
			'plugin holds, core off'  => array( false, '0', 'unapproved', 'pending' ),
			'plugin holds, core on'   => array( false, '1', 'unapproved', 'pending' ),
			'plugin allows, core off' => array( true, '0', 'approved', 'published' ),
			'plugin allows, core on'  => array( true, '1', 'unapproved', 'pending' ),
		);
	}

	/**
	 * @dataProvider moderation_combinations
	 */
	public function test_the_setting_decides_for_a_new_review(bool $auto_approve, string $core, string $status, string $state): void
	{
		$this->settings(array( 'auto_approve' => $auto_approve ));
		update_option('comment_moderation', $core);

		$response = $this->submit(
			array(
				'rating'  => '5',
				'comment' => 'A brand new review.',
			)
		);

		$this->assertTrue($response['success'], (string) $this->_last_response);
		$this->assertSame($state, $response['data']['state']);
		$comments = get_comments(
			array(
				'post_id' => $this->vehicle,
				'type'    => 'review',
			)
		);
		$this->assertCount(1, $comments);
		$this->assertSame($status, wp_get_comment_status((int) $comments[0]->comment_ID));
	}

	public function test_an_administrator_review_is_held_too(): void
	{
		$this->settings(array( 'auto_approve' => false ));
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));

		$response = $this->submit(
			array(
				'rating'  => '5',
				'comment' => 'From the site owner.',
			)
		);

		$this->assertSame('pending', $response['data']['state'], (string) $this->_last_response);
	}

	public function test_a_harder_verdict_is_preserved_and_others_are_left_alone(): void
	{
		$this->settings(array( 'auto_approve' => false ));
		$review = array(
			'comment_type'    => 'review',
			'comment_post_ID' => $this->vehicle,
		);

		$this->assertSame('spam', ReviewEnforcer::apply_moderation_setting('spam', $review));
		$this->assertSame('trash', ReviewEnforcer::apply_moderation_setting('trash', $review));
		$error = new WP_Error('comment_flood', 'Too fast.');
		$this->assertSame($error, ReviewEnforcer::apply_moderation_setting($error, $review));
		$this->assertSame(0, ReviewEnforcer::apply_moderation_setting(1, $review));

		$this->assertSame(1, ReviewEnforcer::apply_moderation_setting(1, array( 'comment_type' => 'comment' ) + $review), 'Not a review.');
		$page = (int) self::factory()->post->create();
		$this->assertSame(1, ReviewEnforcer::apply_moderation_setting(1, array( 'comment_post_ID' => $page ) + $review), 'Not a vehicle.');
	}

	/**
	 * @return array<string, array{0: string, 1: bool, 2: string, 3: string}>
	 */
	public function edit_transitions(): array
	{
		return array(
			'approved, auto approve stays approved' => array( '1', true, 'approved', 'updated' ),
			'approved, moderated goes to waiting'   => array( '1', false, 'unapproved', 'updated_pending' ),
			'waiting, auto approve stays waiting'   => array( '0', true, 'unapproved', 'updated_pending' ),
			'waiting, moderated stays waiting'      => array( '0', false, 'unapproved', 'updated_pending' ),
		);
	}

	/**
	 * @dataProvider edit_transitions
	 */
	public function test_an_edit_never_approves_what_is_waiting(string $before, bool $auto_approve, string $after, string $state): void
	{
		$id = $this->own_review($before);
		$this->settings(array( 'auto_approve' => $auto_approve ));

		$response = $this->submit(
			array(
				'rating'  => '2',
				'comment' => 'The second words.',
			)
		);

		$this->assertTrue($response['success'], (string) $this->_last_response);
		$this->assertSame($state, $response['data']['state']);
		$this->assertSame($after, wp_get_comment_status($id));
		$this->assertSame('The second words.', get_comment($id)->comment_content);
	}

	public function test_a_review_an_admin_unapproved_stays_out_after_an_edit(): void
	{
		$id = $this->own_review();
		RatingHelper::recalculate_and_save($this->vehicle);
		$this->assertSame(1, RatingHelper::get_rating($this->vehicle)['count']);
		wp_set_comment_status($id, 'hold');
		RatingHelper::recalculate_and_save($this->vehicle);
		$this->settings(array( 'auto_approve' => true ));

		$this->submit(
			array(
				'rating'  => '5',
				'comment' => 'Please show it again.',
			)
		);

		$this->assertSame('0', (string) get_comment($id)->comment_approved);
		$this->assertSame(0, RatingHelper::get_rating($this->vehicle)['count']);
	}

	public function test_a_failed_update_writes_no_rating_and_reports_failure(): void
	{
		$id   = $this->own_review();
		$fail = static fn() => new WP_Error('nope', 'The update was refused.');
		add_filter('wp_update_comment_data', $fail);

		$response = $this->submit(
			array(
				'rating'  => '1',
				'comment' => 'The second words.',
			)
		);

		remove_filter('wp_update_comment_data', $fail);
		$this->assertFalse($response['success'], (string) $this->_last_response);
		$this->assertSame('The update was refused.', $response['data']['message']);
		$this->assertSame('4', (string) get_comment_meta($id, 'mhmrentiva_rating', true));
		$this->assertSame('The first words.', get_comment($id)->comment_content);
	}

	public function test_a_no_change_update_succeeds_and_still_saves_the_rating(): void
	{
		$id = $this->own_review();
		$this->settings(array( 'auto_approve' => true ));
		$calls = 0;
		$spy   = static function ($rows) use (&$calls) {
			++$calls;
			return $rows;
		};
		add_action('edit_comment', $spy);

		$response = $this->submit(
			array(
				'rating'  => '2',
				'comment' => 'The first words.',
			)
		);

		remove_action('edit_comment', $spy);
		$this->assertTrue($response['success'], (string) $this->_last_response);
		$this->assertSame('updated', $response['data']['state']);
		$this->assertSame('2', (string) get_comment_meta($id, 'mhmrentiva_rating', true));
		$this->assertSame(1, $calls, 'Core ran the update and changed no row (it returned 0).');
	}

	public function test_a_failed_delete_reports_failure(): void
	{
		$id    = $this->own_review();
		$break = static function (string $query) {
			global $wpdb;
			return 0 === strpos($query, "DELETE FROM `{$wpdb->comments}`") ? '' : $query;
		};
		add_filter('query', $break);

		$response = $this->delete($id);

		remove_filter('query', $break);
		$this->assertFalse($response['success'], (string) $this->_last_response);
		$this->assertSame('The review could not be deleted.', $response['data']['message']);
		$this->assertInstanceOf(\WP_Comment::class, get_comment($id));
	}

	public function test_a_plain_comment_does_not_stop_the_first_review(): void
	{
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->vehicle,
				'comment_approved' => '1',
				'user_id'          => $this->author,
				'comment_content'  => 'An old plain comment.',
				'comment_date'     => '2020-01-01 10:00:00',
				'comment_date_gmt' => '2020-01-01 10:00:00',
			)
		);
		$this->settings(array( 'auto_approve' => true ));

		$response = $this->submit(
			array(
				'rating'  => '5',
				'comment' => 'My first review.',
			)
		);

		$this->assertTrue($response['success'], (string) $this->_last_response);
		$this->assertSame('published', $response['data']['state']);
		$own = VehicleRatingForm::get_user_rating($this->vehicle);
		$this->assertSame('My first review.', $own['comment']);
	}

	public function test_a_guest_second_review_is_refused_as_json(): void
	{
		$this->settings(
			array(
				'require_login'        => false,
				'allow_guest_comments' => true,
				'auto_approve'         => true,
			)
		);
		wp_set_current_user(0);
		self::factory()->comment->create(
			array(
				'comment_post_ID'      => $this->vehicle,
				'comment_type'         => 'review',
				'comment_approved'     => '1',
				'comment_author_email' => 'deniz@example.test',
				'comment_content'      => 'Already here.',
				'comment_date'         => '2020-01-01 10:00:00',
				'comment_date_gmt'     => '2020-01-01 10:00:00',
			)
		);

		$response = $this->submit(
			array(
				'rating'      => '5',
				'comment'     => 'A second one.',
				'guest_name'  => 'Deniz Kaya',
				'guest_email' => 'deniz@example.test',
			)
		);

		$this->assertFalse($response['success'], (string) $this->_last_response);
		$this->assertStringContainsString('already reviewed', $response['data']['message']);
		$this->assertCount(1, get_comments(array( 'post_id' => $this->vehicle )));
	}

	public function test_a_backslash_survives_a_new_review(): void
	{
		$this->settings(array( 'auto_approve' => true ));

		$response = $this->submit(
			array(
				'rating'  => '5',
				'comment' => self::BACKSLASHED,
			)
		);

		$this->assertTrue($response['success'], (string) $this->_last_response);
		$this->assertSame(self::BACKSLASHED, VehicleRatingForm::get_user_rating($this->vehicle)['comment']);
	}

	public function test_a_backslash_survives_an_edit(): void
	{
		$id = $this->own_review();
		$this->settings(array( 'auto_approve' => true ));

		$response = $this->submit(
			array(
				'rating'  => '5',
				'comment' => self::BACKSLASHED,
			)
		);

		$this->assertTrue($response['success'], (string) $this->_last_response);
		$this->assertSame(self::BACKSLASHED, get_comment($id)->comment_content);
	}

	public function test_the_list_stays_within_fifty_whatever_the_setting(): void
	{
		$this->settings(array(), array( 'comments_per_page' => 500 ));
		$numbers = array();
		$spy     = static function (\WP_Comment_Query $query) use (&$numbers): void {
			if ('approve' === ( $query->query_vars['status'] ?? '' )) {
				$numbers[] = (int) $query->query_vars['number'];
			}
		};
		add_action('pre_get_comments', $spy);

		do_shortcode('[rentiva_vehicle_rating_form vehicle_id="' . $this->vehicle . '"]');

		remove_action('pre_get_comments', $spy);
		$this->assertContains(50, $numbers);
		$this->assertNotContains(500, $numbers);
	}

	public function test_a_page_without_reviews_loads_no_rating_script(): void
	{
		\MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode::reset_enqueued_assets_for_tests();
		wp_dequeue_script('mhm-rentiva-vehicle-rating-form');

		do_shortcode('[rentiva_vehicle_details vehicle_id="' . $this->vehicle . '" show_reviews="0"]');
		$this->assertFalse(wp_script_is('mhm-rentiva-vehicle-rating-form', 'enqueued'));

		do_shortcode('[rentiva_vehicle_details vehicle_id="' . $this->vehicle . '"]');
		$this->assertTrue(wp_script_is('mhm-rentiva-vehicle-rating-form', 'enqueued'), 'A second vehicle on the page has reviews.');
	}

	public function test_a_booking_review_of_a_vehicle_off_the_site_is_not_public(): void
	{
		$draft   = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'draft',
			)
		);
		$booking = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
			)
		);
		update_post_meta($booking, '_mhmrentiva_customer_review', 'A review of a withdrawn car.');
		update_post_meta($booking, '_mhmrentiva_customer_rating', 5);
		update_post_meta($booking, '_mhmrentiva_review_approved', '1');
		update_post_meta($booking, '_mhmrentiva_vehicle_id', $draft);
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);

		$html = do_shortcode('[rentiva_testimonials limit="10"]');

		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		$this->assertStringNotContainsString('A review of a withdrawn car.', $html);

		update_post_meta($booking, '_mhmrentiva_vehicle_id', $this->vehicle);
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		$html = do_shortcode('[rentiva_testimonials limit="10"]');
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		$this->assertStringContainsString('A review of a withdrawn car.', $html, 'Negative control: the same row on a published vehicle.');
	}

	public function test_the_honeypot_is_inert_and_the_contact_sidebar_headings_sit_under_the_form_heading(): void
	{
		add_filter(
			'mhmrentiva_shortcode_html_cache_enabled',
			'__return_false',
			999
		);
		$html = do_shortcode('[rentiva_contact]');
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);

		$this->assertMatchesRegularExpression('#<div class="rvs-sr-only" inert>\s*<label#', $html);
		$this->assertStringNotContainsString('<div class="rvs-sr-only" aria-hidden', $html);
		if (false !== strpos($html, 'rvs-contact__info-row')) {
			$this->assertDoesNotMatchRegularExpression('#rvs-contact__info-row">\s*<h2#', $html);
		}
	}
}
