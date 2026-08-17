<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\VehicleRatingForm;
use WP_Ajax_UnitTestCase;

/**
 * Finding M-01 (independent review of the 6.0.2 package): ajax_submit_rating()
 * validated that vehicle_id was a positive integer and that the rating was 1-5,
 * but never checked that the ID actually belonged to a vehicle. `get_post_type`
 * did not appear anywhere in the file.
 *
 * The unvalidated ID went straight into `comment_post_ID` and then into
 * update_vehicle_rating_meta(), so any nonce-carrying submitter could attach a
 * `review` comment plus rating meta to an arbitrary post -- a blog post, a page,
 * the privacy policy -- and write vehicle rating aggregates onto it.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\VehicleRatingForm::ajax_submit_rating
 */
final class RatingTargetMustBeVehicleTest extends WP_Ajax_UnitTestCase
{
	private int $vehicle_id;
	private int $user_id;

	public function setUp(): void
	{
		parent::setUp();

		VehicleRatingForm::register();

		$this->vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		$this->user_id = (int) self::factory()->user->create(array( 'role' => 'customer' ));
	}

	public function tearDown(): void
	{
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function submit_rating_for(int $target_id): array
	{
		wp_set_current_user($this->user_id);

		$_POST = array(
			'nonce'      => wp_create_nonce('mhmrentiva_rating_nonce'),
			'vehicle_id' => $target_id,
			'rating'     => 4,
			'comment'    => 'A perfectly ordinary review body, long enough to pass the minimum length rule.',
		);

		try {
			$this->_handleAjax('mhmrentiva_submit_rating');
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			// wp_send_json_* terminates; the response is read below.
		}

		$decoded = json_decode($this->_last_response, true);

		return is_array($decoded) ? $decoded : array();
	}

	/**
	 * RED before the fix: a plain blog post is accepted, a `review` comment is
	 * created on it and vehicle rating meta is written to it.
	 */
	public function test_rating_on_a_plain_post_is_refused(): void
	{
		$post_id = (int) self::factory()->post->create(array(
			'post_type'   => 'post',
			'post_status' => 'publish',
		));

		$response = $this->submit_rating_for($post_id);

		$this->assertFalse(
			(bool) ( $response['success'] ?? false ),
			'A rating must not be accepted for a non-vehicle post. Raw response: ' . $this->_last_response
		);
		$this->assertSame(
			array(),
			get_comments(array( 'post_id' => $post_id, 'fields' => 'ids' )),
			'No comment may be created on a non-vehicle post.'
		);
		$this->assertSame(
			'',
			(string) get_post_meta($post_id, '_mhmrentiva_rating_average', true),
			'Vehicle rating aggregates must not be written onto a non-vehicle post.'
		);
	}

	/**
	 * A page is the other common target: same class of object, different post type.
	 */
	public function test_rating_on_a_page_is_refused(): void
	{
		$page_id = (int) self::factory()->post->create(array(
			'post_type'   => 'page',
			'post_status' => 'publish',
		));

		$response = $this->submit_rating_for($page_id);

		$this->assertFalse(
			(bool) ( $response['success'] ?? false ),
			'A rating must not be accepted for a page. Raw response: ' . $this->_last_response
		);
	}

	/**
	 * An ID that matches no post at all must be refused rather than fall through.
	 */
	public function test_rating_on_a_nonexistent_id_is_refused(): void
	{
		$response = $this->submit_rating_for(999999);

		$this->assertFalse(
			(bool) ( $response['success'] ?? false ),
			'A rating must not be accepted for an ID that matches no post. Raw response: ' . $this->_last_response
		);
	}

	/**
	 * Fable audit finding M-A: the same missing-entity-check class, in the sibling
	 * handler my first sweep waved through as "a read, low risk".
	 *
	 * ajax_get_vehicle_rating_list is registered for nopriv, takes the same
	 * unvalidated id, and its query deliberately dropped the review type filter
	 * ("REMOVED: We want ALL comments"). get_comments() does not check whether the
	 * caller may read the post, so an anonymous request could name any post and
	 * receive every approved comment on it -- author name, body and date.
	 */
	public function test_rating_list_does_not_dump_comments_of_a_non_vehicle_post(): void
	{
		$post_id = (int) self::factory()->post->create(array(
			'post_type'   => 'post',
			'post_status' => 'publish',
		));
		self::factory()->comment->create(array(
			'comment_post_ID'      => $post_id,
			'comment_approved'     => '1',
			'comment_author'       => 'Private Person',
			'comment_author_email' => 'private@example.test',
			'comment_content'      => 'A private discussion nobody outside this post should be handed.',
		));

		wp_set_current_user(0);

		$_POST = array(
			'nonce'      => wp_create_nonce('mhmrentiva_rating_nonce'),
			'vehicle_id' => $post_id,
		);

		try {
			$this->_handleAjax('mhmrentiva_get_vehicle_rating_list');
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			// wp_send_json_* terminates.
		}

		$this->assertStringNotContainsString(
			'Private Person',
			$this->_last_response,
			'Comments on a non-vehicle post must never be returned by the vehicle rating list.'
		);
		$this->assertStringNotContainsString('A private discussion', $this->_last_response);
	}

	/**
	 * Same class again: the delete handler resolves its target without checking
	 * the post type, and writes rating aggregates through update_vehicle_rating_meta().
	 */
	public function test_delete_rating_refuses_a_non_vehicle_target(): void
	{
		$page_id = (int) self::factory()->post->create(array( 'post_type' => 'page' ));

		wp_set_current_user($this->user_id);

		$_POST = array(
			'nonce'      => wp_create_nonce('mhmrentiva_rating_nonce'),
			'vehicle_id' => $page_id,
			'comment_id' => 0,
		);

		try {
			$this->_handleAjax('mhmrentiva_delete_rating');
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			// wp_send_json_* terminates.
		}

		$decoded = json_decode($this->_last_response, true);

		$this->assertFalse(
			(bool) ( $decoded['success'] ?? false ),
			'Deleting a rating on a non-vehicle target must be refused.'
		);
		$this->assertSame(
			'',
			(string) get_post_meta($page_id, '_mhmrentiva_rating_average', true),
			'No rating aggregate may be written onto a non-vehicle post.'
		);
	}

	/**
	 * Negative control: the guard must not be satisfied by refusing everything.
	 * A real vehicle still accepts ratings.
	 */
	public function test_rating_on_a_real_vehicle_is_still_accepted(): void
	{
		$response = $this->submit_rating_for($this->vehicle_id);

		$this->assertTrue(
			(bool) ( $response['success'] ?? false ),
			'A rating on a genuine vehicle must still succeed. Raw response: ' . $this->_last_response
		);
	}
}
