<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Frontend\Shortcodes\ReviewsView;
use MHMRentiva\Admin\Frontend\Shortcodes\VehicleRatingForm;
use MHMRentiva\Admin\Settings\Comments\CommentsSettings;
use MHMRentiva\Admin\Vehicle\Helpers\RatingHelper;
use ReflectionProperty;
use WP_Ajax_UnitTestCase;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * PR F R11-R14, r2 M5, M6: one rule for review counts and lists, the author's own
 * waiting review, the guest setting on screen, the page's one heading.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\VehicleRatingForm
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\ReviewsView
 * @covers \MHMRentiva\Admin\Vehicle\Helpers\RatingHelper
 */
final class ReviewSurfacesOneRuleTest extends WP_Ajax_UnitTestCase
{
	private int $vehicle = 0;
	private int $author  = 0;
	private int $other   = 0;

	public function setUp(): void
	{
		parent::setUp();
		// The time trap has its own tests; these post without a drawn form.
		add_filter('mhmrentiva_contact_min_seconds', '__return_zero');
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
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
		$this->other   = (int) self::factory()->user->create(array( 'role' => 'subscriber' ));
		wp_set_current_user(0);
		AbstractShortcode::reset_enqueued_assets_for_tests();
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		$this->settings_cache(null);
		wp_set_current_user(0);
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $args Comment fields.
	 */
	private function review(array $args = array(), int $rating = 4): int
	{
		$id = (int) self::factory()->comment->create(
			$args + array(
				'comment_post_ID'  => $this->vehicle,
				'comment_type'     => 'review',
				'comment_approved' => '1',
				'comment_author'   => 'Mert Kaya',
				'comment_content'  => 'A public review body.',
			)
		);
		update_comment_meta($id, 'mhmrentiva_rating', $rating);
		RatingHelper::recalculate_and_save($this->vehicle);

		return $id;
	}

	private function render(string $atts = ''): string
	{
		return do_shortcode('[rentiva_vehicle_rating_form vehicle_id="' . $this->vehicle . '"' . $atts . ']');
	}

	/**
	 * @param array<string, mixed>|null $approval Approval settings to apply, null to reset.
	 */
	private function settings_cache(?array $approval): void
	{
		$property = new ReflectionProperty(CommentsSettings::class, 'runtime_cache');
		$property->setAccessible(true);
		$property->setValue(null, null);
		if (null !== $approval) {
			$settings             = CommentsSettings::get_settings();
			$settings['approval'] = $approval + (array) $settings['approval'];
			$property->setValue(null, $settings);
		}
	}

	/**
	 * @param array<string, mixed> $display Display settings to apply.
	 */
	private function settings_display(array $display): void
	{
		$property = new ReflectionProperty(CommentsSettings::class, 'runtime_cache');
		$property->setAccessible(true);
		$property->setValue(null, null);
		$settings            = CommentsSettings::get_settings();
		$settings['display'] = $display + (array) $settings['display'];
		$property->setValue(null, $settings);
	}

	private function cards(string $html): int
	{
		return substr_count($html, '<article class="rvs-review');
	}

	public function test_a_plain_comment_is_neither_counted_nor_drawn_on_any_surface(): void
	{
		$this->review();
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->vehicle,
				'comment_approved' => '1',
				'comment_author'   => 'Plain Commenter',
				'comment_content'  => 'A plain WordPress comment.',
			)
		);
		RatingHelper::recalculate_and_save($this->vehicle);

		$form = $this->render();
		$this->assertSame(1, RatingHelper::get_rating($this->vehicle)['count']);
		$this->assertSame(1, $this->cards($form), 'Summary and list count the same reviews.');
		$this->assertStringNotContainsString('A plain WordPress comment.', $form);

		$page = do_shortcode('[rentiva_vehicle_details vehicle_id="' . $this->vehicle . '"]');
		$this->assertSame(1, $this->cards($page), 'The vehicle page follows the same rule.');
		$this->assertStringNotContainsString('A plain WordPress comment.', $page);

		$testimonials = do_shortcode('[rentiva_testimonials vehicle_id="' . $this->vehicle . '" limit="10"]');
		$this->assertStringContainsString('A public review body.', $testimonials);
		$this->assertStringNotContainsString('A plain WordPress comment.', $testimonials, 'The testimonials follow the same rule.');
	}

	public function test_the_helper_is_the_one_rule(): void
	{
		$this->assertSame(
			array(
				'type'   => 'review',
				'status' => 'approve',
			),
			RatingHelper::public_review_args()
		);
	}

	public function test_a_waiting_review_is_shown_to_its_author_only_with_the_tag(): void
	{
		$this->review();
		$this->review(
			array(
				'user_id'          => $this->author,
				'comment_author'   => 'Ayse Demir',
				'comment_approved' => '0',
				'comment_content'  => 'My waiting review.',
			)
		);

		wp_set_current_user($this->author);
		$mine = $this->render();
		$this->assertStringContainsString('My waiting review.', $mine);
		$this->assertStringContainsString('Under review', $mine);
		$this->assertSame(2, $this->cards($mine));
		$this->assertStringContainsString('1 review', $mine, 'The summary still counts the approved one only.');

		wp_set_current_user($this->other);
		$theirs = $this->render();
		$this->assertStringNotContainsString('My waiting review.', $theirs);
		$this->assertStringNotContainsString('Under review', $theirs);
		$this->assertSame(1, $this->cards($theirs));

		wp_set_current_user(0);
		$guest = $this->render();
		$this->assertStringNotContainsString('My waiting review.', $guest);
		$this->assertSame(1, $this->cards($guest));
	}

	public function test_a_guest_never_sees_a_waiting_review_even_with_its_email_in_a_cookie(): void
	{
		$this->review(
			array(
				'comment_author'       => 'Deniz Kaya',
				'comment_author_email' => 'deniz@example.test',
				'comment_approved'     => '0',
				'comment_content'      => 'A guest review waiting.',
			)
		);
		$_COOKIE['guest_email'] = 'deniz@example.test';

		$html = $this->render();

		unset($_COOKIE['guest_email']);
		$this->assertStringNotContainsString('A guest review waiting.', $html);
		$this->assertSame(0, $this->cards($html));
		$this->assertStringContainsString('No reviews yet.', $html);
	}

	public function test_only_the_author_gets_edit_and_delete(): void
	{
		$this->review(
			array(
				'user_id'         => $this->author,
				'comment_content' => 'Mine.',
			)
		);

		wp_set_current_user($this->author);
		$mine = $this->render();
		$this->assertStringContainsString('data-rvs-review-edit', $mine);
		$this->assertStringContainsString('data-rvs-review-delete', $mine);
		$this->assertStringContainsString('data-rvs-review-dialog', $mine);
		$this->assertMatchesRegularExpression('/<form [^>]*\bhidden\b/', $mine, 'The form waits until Edit is pressed.');

		wp_set_current_user($this->other);
		$theirs = $this->render();
		$this->assertStringNotContainsString('data-rvs-review-edit', $theirs);
		$this->assertStringNotContainsString('data-rvs-review-delete', $theirs);
		$this->assertStringNotContainsString('data-rvs-review-dialog', $theirs);
		$this->assertDoesNotMatchRegularExpression('/<form [^>]*\bhidden\b/', $theirs);
	}

	public function test_the_guest_comment_setting_works_on_screen(): void
	{
		$this->assertStringContainsString('rvs-reviews__login', $this->render(), 'Default: login required.');

		$this->settings_cache(
			array(
				'require_login'        => false,
				'allow_guest_comments' => true,
			)
		);
		$html = $this->render();
		$this->assertStringNotContainsString('rvs-reviews__login', $html);
		$this->assertStringContainsString('name="guest_name"', $html);
		$this->assertStringContainsString('name="guest_email"', $html);
		$this->assertStringContainsString('name="comment"', $html);
	}

	public function test_star_input_is_a_fieldset_of_focusable_radios(): void
	{
		wp_set_current_user($this->author);
		$html = $this->render();

		$this->assertMatchesRegularExpression('#<fieldset[^>]*>\s*<legend>[^<]+</legend>\s*<div class="rvs-stars">#', $html);
		$this->assertSame(5, substr_count($html, 'type="radio" name="rating"'));
		$this->assertStringContainsString('aria-live="polite"', $html);
		$this->assertStringNotContainsString('rating_nonce', $html, 'The dead nonce field is gone.');
		$this->assertStringContainsString('name="nonce"', $html);
	}

	public function test_two_instances_share_no_id(): void
	{
		wp_set_current_user($this->author);
		$this->review(
			array(
				'user_id' => $this->author,
			)
		);
		$html = $this->render() . $this->render();

		preg_match_all('/\sid="([^"]+)"/', $html, $matches);
		$this->assertNotEmpty($matches[1]);
		$this->assertSame($matches[1], array_values(array_unique($matches[1])));
	}

	public function test_inside_the_vehicle_page_one_heading_and_one_call_to_action(): void
	{
		$this->review();
		wp_set_current_user($this->author);

		$page = do_shortcode('[rentiva_vehicle_details vehicle_id="' . $this->vehicle . '"]');

		$this->assertStringContainsString('rvs-reviews--nested', $page);
		$this->assertStringNotContainsString('rvs-reviews__title', $page, 'The page draws the heading.');
		$this->assertStringNotContainsString('Rate this vehicle', $page, 'No second heading.');
		$this->assertStringNotContainsString('-review-form">Write a review', $page, 'No second call to action.');
		$this->assertStringContainsString('aria-label="Write a review"', $page, 'The form keeps a name.');
		$this->assertSame(1, substr_count($page, 'rvs-review-summary__average'), 'One summary.');
		$this->assertStringContainsString('name="comment"', $page);
	}

	public function test_standalone_draws_title_summary_and_form_heading(): void
	{
		$this->review();
		wp_set_current_user($this->author);

		$html = $this->render();

		$this->assertStringContainsString('rvs-reviews__title', $html);
		$this->assertStringContainsString('rvs-review-summary', $html);
		$this->assertStringContainsString('Rate this vehicle', $html);
		$this->assertStringNotContainsString('rvs-reviews--nested', $html);
	}

	public function test_the_legacy_stylesheet_is_not_loaded(): void
	{
		$this->render();

		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-reviews', 'enqueued'));
		$this->assertFalse(wp_style_is('mhm-rentiva-vehicle-rating-form', 'enqueued'));
		$this->assertTrue(wp_script_is('mhm-rentiva-rvs-dialog', 'enqueued'));
		$this->assertContains('mhm-rentiva-rvs-dialog', wp_scripts()->registered['mhm-rentiva-vehicle-rating-form']->deps);
	}

	public function test_the_list_endpoint_is_gone(): void
	{
		VehicleRatingForm::register();

		$this->assertFalse(has_action('wp_ajax_mhmrentiva_get_vehicle_rating_list'));
		$this->assertFalse(has_action('wp_ajax_nopriv_mhmrentiva_get_vehicle_rating_list'));
		$this->assertNotFalse(has_action('wp_ajax_mhmrentiva_submit_rating'));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function submit(array $post): array
	{
		add_action('wp_ajax_mhmrentiva_submit_rating', array( VehicleRatingForm::class, 'ajax_submit_rating' ));
		$_POST = $post + array(
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

	public function test_editing_under_moderation_says_it_went_back_under_review(): void
	{
		$this->settings_cache(array( 'auto_approve' => false ));
		wp_set_current_user($this->author);
		$id = $this->review(
			array(
				'user_id'         => $this->author,
				'comment_content' => 'First version of it.',
			)
		);
		$this->assertSame(1, RatingHelper::get_rating($this->vehicle)['count']);

		$response = $this->submit(
			array(
				'rating'  => '2',
				'comment' => 'Second version of it.',
			)
		);

		$this->assertTrue($response['success'], (string) $this->_last_response);
		$this->assertSame('updated_pending', $response['data']['state']);
		$this->assertSame('unapproved', wp_get_comment_status($id));
		$this->assertSame(0, RatingHelper::get_rating($this->vehicle)['count'], 'The waiting review leaves the summary.');

		$html = $this->render();
		$this->assertStringContainsString('Under review', $html, 'It stays visible to its author.');
		$this->assertStringContainsString('Second version of it.', $html);
	}

	public function test_without_the_list_the_own_review_is_edited_in_the_visible_form(): void
	{
		$this->review(
			array(
				'user_id'         => $this->author,
				'comment_content' => 'What I wrote.',
			),
			3
		);
		wp_set_current_user($this->author);

		$html = $this->render(' show_ratings_list="0"');

		$this->assertDoesNotMatchRegularExpression('/<form [^>]*\bhidden\b/', $html, 'No card means no Edit: the form must not wait for one.');
		$this->assertStringNotContainsString('data-rvs-review-edit', $html);
		$this->assertStringContainsString('>What I wrote.</textarea>', $html);
		$this->assertMatchesRegularExpression('/value="3"\s+checked/', $html);
		$this->assertStringContainsString('Update review', $html);
		$this->assertStringContainsString('Edit your review', $html);
		$this->assertStringNotContainsString('Submit review', $html, 'Sending it changes the review: the button says so.');
	}

	public function test_with_editing_off_an_own_review_gets_no_form(): void
	{
		$this->review(array( 'user_id' => $this->author ));
		wp_set_current_user($this->author);
		$this->settings_display(array( 'allow_editing' => false ));

		foreach (array( '', ' show_ratings_list="0"' ) as $atts) {
			$html = $this->render($atts);
			$this->assertStringNotContainsString('<form', $html, "atts: $atts");
			$this->assertStringNotContainsString('data-rvs-review-edit', $html);
			$this->assertStringNotContainsString('rvs-reviews__login', $html, 'A logged-in author is not told to log in.');
		}
	}

	public function test_the_endpoint_refuses_an_edit_when_editing_is_off(): void
	{
		$id = $this->review(
			array(
				'user_id'         => $this->author,
				'comment_content' => 'The original words.',
			)
		);
		wp_set_current_user($this->author);
		$this->settings_display(array( 'allow_editing' => false ));

		$response = $this->submit(
			array(
				'rating'  => '1',
				'comment' => 'Trying to rewrite it.',
			)
		);

		$this->assertFalse($response['success'], (string) $this->_last_response);
		$this->assertSame('The original words.', get_comment($id)->comment_content);
		$this->assertSame('4', (string) get_comment_meta($id, 'mhmrentiva_rating', true));
		$this->assertCount(1, get_comments(array( 'post_id' => $this->vehicle, 'status' => 'all' )), 'Refused, not a duplicate.');
	}

	/**
	 * @return array<string, mixed>
	 */
	private function delete(int $comment_id): array
	{
		add_action('wp_ajax_mhmrentiva_delete_rating', array( VehicleRatingForm::class, 'ajax_delete_rating' ));
		$_POST = array(
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

	public function test_the_endpoint_refuses_the_owners_delete_when_deletion_is_off(): void
	{
		$id = $this->review(array( 'user_id' => $this->author ));
		wp_set_current_user($this->author);
		$this->settings_display(array( 'allow_deletion' => false ));

		$response = $this->delete($id);

		$this->assertFalse($response['success'], (string) $this->_last_response);
		$this->assertInstanceOf(\WP_Comment::class, get_comment($id));
		$this->assertStringNotContainsString('data-rvs-review-delete', $this->render());
	}

	public function test_an_admin_still_deletes_when_deletion_is_off(): void
	{
		$id = $this->review(array( 'user_id' => $this->author ));
		wp_set_current_user( (int) self::factory()->user->create(array( 'role' => 'administrator' )));
		$this->settings_display(array( 'allow_deletion' => false ));

		$response = $this->delete($id);

		$this->assertTrue($response['success'], (string) $this->_last_response);
		$this->assertNull(get_comment($id));
	}

	public function test_nested_boxes_add_no_heading(): void
	{
		$page = do_shortcode('[rentiva_vehicle_details vehicle_id="' . $this->vehicle . '"]');
		preg_match('#<section class="rvs-vd__section rvs-vd__reviews".*?</section>#s', $page, $section);

		$this->assertNotEmpty($section);
		$this->assertStringContainsString('No reviews yet.', $section[0]);
		$this->assertStringContainsString('Login required', $section[0]);
		$this->assertSame(1, preg_match_all('/<h[1-6]\b/', $section[0]), 'The h2 of the section, nothing else.');

		$standalone = $this->render();
		$this->assertMatchesRegularExpression('#<h3 [^>]*>No reviews yet\.</h3>#', $standalone);
		$this->assertMatchesRegularExpression('#<h3 [^>]*>Login required</h3>#', $standalone);
	}

	public function test_a_standalone_form_stays_out_of_the_html_cache(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_true', 999);
		try {
			$before = $this->render();
			$this->review();
			$after = $this->render();
		} finally {
			remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_true', 999);
		}

		$this->assertSame(0, $this->cards($before));
		$this->assertSame(1, $this->cards($after), 'A second render came from a cache.');
	}

	public function test_a_new_waiting_review_answers_from_the_stored_status(): void
	{
		$this->settings_cache(array( 'auto_approve' => false ));
		update_option('comment_moderation', '0');
		$filter = array( \MHMRentiva\Admin\Vehicle\Hooks\ReviewEnforcer::class, 'apply_moderation_setting' );
		add_filter('pre_comment_approved', $filter, 10, 2);
		wp_set_current_user($this->author);

		$response = $this->submit(
			array(
				'rating'  => '5',
				'comment' => 'A brand new review.',
			)
		);

		$this->assertTrue($response['success'], (string) $this->_last_response);
		remove_filter('pre_comment_approved', $filter, 10);
		$this->assertSame('pending', $response['data']['state']);
		$this->assertTrue($response['data']['reload']);
	}

	public function test_the_answer_never_promises_a_guest_a_listing(): void
	{
		$guest = ReviewsView::submission_answer(false, false, true);
		$user  = ReviewsView::submission_answer(false, false, false);

		$this->assertFalse($guest['reload'], 'A guest review is listed for nobody, so nothing to reload.');
		$this->assertStringNotContainsString('only you', $guest['message']);
		$this->assertStringContainsString('only you', $user['message']);

		$published = ReviewsView::submission_answer(true, false, true);
		$this->assertSame('published', $published['state']);
		$this->assertFalse($published['reload'], 'A reload could show the cached page without it.');
		$this->assertStringContainsString('within a few minutes', $published['message'], 'True for the whole life of the cache.');
		$this->assertStringContainsString('is in the list', ReviewsView::submission_answer(true, false, false)['message'], 'A logged-in visitor is never cached.');
		$this->assertSame('updated', ReviewsView::submission_answer(true, true, false)['state']);
	}

	public function test_can_post_is_one_rule(): void
	{
		$this->assertTrue(ReviewsView::can_post(true, true, false));
		$this->assertFalse(ReviewsView::can_post(false, true, false));
		$this->assertFalse(ReviewsView::can_post(false, true, true));
		$this->assertFalse(ReviewsView::can_post(false, false, false));
		$this->assertTrue(ReviewsView::can_post(false, false, true));
	}

	public function test_names_are_masked_and_numbers_follow_the_site_format(): void
	{
		$this->assertSame('John D.', ReviewsView::mask_name('John Middle Doe'));
		$this->assertSame('Cher', ReviewsView::mask_name('Cher'));
		$this->assertSame('Anonymous', ReviewsView::mask_name('  '));

		$comma = static fn(string $formatted): string => str_replace('.', ',', $formatted);
		add_filter('number_format_i18n', $comma);
		$summary = ReviewsView::summary(
			array(
				'average' => 4.5,
				'count'   => 2,
			),
			array(
				5 => 1,
				4 => 1,
			)
		);
		remove_filter('number_format_i18n', $comma);

		$this->assertSame('4,5', $summary['average_text']);
		$this->assertSame(50, $summary['distribution'][0]['pct']);
		$this->assertNull(ReviewsView::summary(array( 'count' => 0 ), array()));
	}

	public function test_a_card_is_built_for_every_caller_without_reading_anything(): void
	{
		$card = ReviewsView::card(
			array(
				'id'     => 7,
				'author' => 'Ayse Demir',
				'rating' => 9,
				'text'   => 'Body',
				'date'   => '2026-09-14 10:30:00',
			)
		);

		$this->assertSame('Ayse D.', $card['name']);
		$this->assertSame('A', $card['initial']);
		$this->assertSame(5, $card['rating'], 'Clamped.');
		$this->assertNotSame('', $card['date']);
		$this->assertFalse($card['can_edit']);
		$this->assertFalse($card['can_delete'], 'Only the own review has actions.');
	}
}
