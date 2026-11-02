<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\Contact\RenderStamp;
use MHMRentiva\Admin\Frontend\Shortcodes\ContactFormView;
use MHMRentiva\Admin\Frontend\Shortcodes\VehicleRatingForm;
use MHMRentiva\Admin\Settings\Comments\CommentsSettings;
use MHMRentiva\Tests\Support\RequestSimulator;
use WP_Ajax_UnitTestCase;

/**
 * Trap and render time on a NEW review. The answer is the path's existing refusal.
 */
final class ReviewSpamTrapTest extends WP_Ajax_UnitTestCase
{
	private int $vehicle = 0;

	private int $user = 0;

	public function setUp(): void
	{
		parent::setUp();
		VehicleRatingForm::register();
		RequestSimulator::new_request();
		$this->vehicle = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
		$this->user    = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		wp_set_current_user($this->user);
	}

	public function tearDown(): void
	{
		wp_set_current_user(0);
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * @param array<string,string> $fields Extra fields.
	 * @return array<string,mixed>
	 */
	private function submit(array $fields = array()): array
	{
		$_SERVER['REQUEST_TIME'] = time();
		$this->_last_response    = '';
		$_POST                = array_merge(
			array(
				'nonce'            => wp_create_nonce('mhmrentiva_rating_nonce'),
				'vehicle_id'       => (string) $this->vehicle,
				'rating'           => '5',
				'comment'          => 'A clean and comfortable car.',
				RenderStamp::FIELD => RenderStamp::issue(RenderStamp::FORM_REVIEW, time() - 60),
			),
			$fields
		);
		try {
			$this->_handleAjax('mhmrentiva_submit_rating');
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			unset($e);
		}

		return (array) json_decode($this->_last_response, true);
	}

	private function review_count(): int
	{
		return count(get_comments(array( 'post_id' => $this->vehicle, 'type' => 'review', 'status' => 'all' )));
	}

	private function assert_refused(array $response, string $why): void
	{
		$this->assertFalse($response['success'], $why);
		$this->assertSame(__('Your comment could not be submitted. Please wait a moment before trying again.', 'mhm-rentiva'), $response['data']['message'], $why);
		$this->assertSame(0, $this->review_count(), $why);
	}

	public function test_a_normal_review_passes(): void
	{
		$this->assertTrue($this->submit()['success']);
		$this->assertSame(1, $this->review_count());
	}

	public function test_a_review_just_above_the_minimum_passes(): void
	{
		$this->assertTrue($this->submit(array( RenderStamp::FIELD => RenderStamp::issue(RenderStamp::FORM_REVIEW, time() - 4) ))['success']);
	}

	public function test_a_filled_trap_is_refused(): void
	{
		$this->assert_refused($this->submit(array( ContactFormView::HONEYPOT_FIELD => 'x' )), 'Trap.');
	}

	public function test_too_fast_missing_or_foreign_stamps_are_refused(): void
	{
		$this->assert_refused($this->submit(array( RenderStamp::FIELD => RenderStamp::issue(RenderStamp::FORM_REVIEW) )), 'Too fast.');
		$this->assert_refused($this->submit(array( RenderStamp::FIELD => '' )), 'Missing.');
		$this->assert_refused($this->submit(array( RenderStamp::FIELD => RenderStamp::issue(RenderStamp::FORM_CONTACT, time() - 60) )), 'The contact form\'s stamp.');
	}

	public function test_an_edit_is_not_read_by_the_trap(): void
	{
		$this->assertTrue($this->submit()['success']);

		$edit = $this->submit(
			array(
				'comment'                       => 'A clean, comfortable and quiet car.',
				RenderStamp::FIELD              => '',
				ContactFormView::HONEYPOT_FIELD => 'x',
			)
		);

		$this->assertTrue($edit['success'], 'Spam protection never read an update.');
		$this->assertSame(1, $this->review_count());
	}

	public function test_the_form_carries_the_stamp_and_the_trap_out_of_sight(): void
	{
		$settings = CommentsSettings::get_settings();
		$this->assertArrayHasKey('approval', $settings);

		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		RequestSimulator::new_request();
		$html = do_shortcode('[rentiva_vehicle_rating_form vehicle_id="' . $this->vehicle . '"]');
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);

		$this->assertSame(1, preg_match('/name="rvs_rendered" value="([^"]+)"/', $html, $m));
		$this->assertNotNull(RenderStamp::age(html_entity_decode($m[1], ENT_QUOTES), RenderStamp::FORM_REVIEW));
		$this->assertMatchesRegularExpression('#<div class="rvs-sr-only" inert>\s*<label[^>]*>[^<]*</label>\s*<input type="text"[^>]*name="rvs_leave_blank"#', $html);
	}
}
