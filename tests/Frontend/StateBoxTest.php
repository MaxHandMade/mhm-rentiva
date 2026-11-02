<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use MHMRentiva\Frontend\StateBox;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ResetsRenderContext;
use WP_UnitTestCase;

/**
 * PR K Task 4 (R17): the customer-facing empty and error states share one box
 * (`rvs-empty rvs-state`), each honest and each leading somewhere.
 */
final class StateBoxTest extends WP_UnitTestCase
{
	use ResetsRenderContext;

	public function set_up(): void
	{
		parent::set_up();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		RequestSimulator::new_request();
	}

	public function tear_down(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		parent::tear_down();
	}

	private function assert_state_box(string $html, string $state): void
	{
		$this->assertStringContainsString('class="rvs-empty rvs-state', $html);
		$this->assertStringContainsString('data-rvs-empty="' . $state . '"', $html);
		$this->assertStringNotContainsString('rv-no-results', $html);
		$this->assertStringNotContainsString('rv-vehicle-details-empty', $html);
		$this->assertStringNotContainsString('mhm-rentiva-no-vehicles', $html);
		$this->assertStringNotContainsString('mhm-rentiva-shortcode-error', $html);
	}

	public function test_box_escapes_and_alert_tone_adds_the_alert_class(): void
	{
		$html = StateBox::html(array(
			'tone'         => 'alert',
			'kicker'       => 'K<b>',
			'title'        => 'T<script>',
			'text'         => 'x',
			'action_label' => 'Go',
			'action_url'   => 'javascript:alert(1)',
			'state'        => 'x_y',
		));
		$this->assertStringContainsString('rvs-state--alert', $html);
		$this->assertStringNotContainsString('<script>', $html);
		$this->assertStringNotContainsString('<b>', $html);
		$this->assertStringNotContainsString('javascript:', $html, 'esc_url drops an unsafe scheme.');
	}

	public function test_box_opens_its_own_shell_and_loads_its_styles_when_standalone(): void
	{
		$alone = StateBox::html(array( 'title' => 'T' ));
		$this->assertStringStartsWith('<div class="rvs-shell">', $alone);
		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-primitives', 'enqueued'));
	}

	public function test_no_action_without_both_label_and_url(): void
	{
		$this->assertStringNotContainsString('<a ', StateBox::html(array( 'title' => 'T', 'action_label' => 'Go' )));
	}

	public function test_missing_vehicle_on_the_front_says_so_and_links_to_the_cars(): void
	{
		RequestSimulator::new_request();
		$html = do_shortcode('[rentiva_vehicle_details vehicle_id="999999"]');

		$this->assert_state_box($html, 'vehicle_missing');
		$this->assertStringContainsString('Vehicle not found', $html);
		$this->assertStringContainsString('href="' . esc_url(StateBox::browse_url()) . '"', $html);
		$this->assertStringNotContainsString('widget settings', $html, 'The visitor gets no editor instruction.');
		$this->assertSame(1, substr_count($html, 'class="rvs-shell"'), 'One shell, not a nested pair.');
	}

	public function test_search_with_no_cars_is_a_state_box_that_links_to_all_cars(): void
	{
		$m = new \ReflectionMethod(SearchResults::class, 'render_vehicles_list');
		$m->setAccessible(true);
		$html = (string) $m->invoke(null, array(), 'grid');

		$this->assert_state_box($html, 'no_results');
		$this->assertStringContainsString('No cars match your search', $html);
		$this->assertStringContainsString('href="' . esc_url(StateBox::browse_url()) . '"', $html);
	}

	public function test_featured_with_nothing_to_feature_is_a_state_box_the_vehicle_page_can_detect(): void
	{
		RequestSimulator::new_request();
		$html = do_shortcode('[rentiva_featured_vehicles category="no-such-category-xyz"]');

		$this->assert_state_box($html, 'no_featured');
		$this->assertStringContainsString('No featured cars yet', $html);
	}

	public function test_missing_booking_is_a_state_box_with_a_way_back(): void
	{
		$res = AccountRenderer::get_booking_detail_data(999999);

		$this->assertArrayHasKey('error', $res);
		$this->assert_state_box($res['error'], 'booking_missing');
		$this->assertStringContainsString('could not be found', $res['error']);
		$this->assertStringContainsString('Back to my account', $res['error']);
		$this->assertStringContainsString('href="' . esc_url(AccountController::get_account_url()) . '"', $res['error']);
		// The account pages print it through wp_kses_post: the box must survive.
		$this->assertStringContainsString('data-rvs-empty="booking_missing"', wp_kses_post($res['error']));
	}

	public function test_missing_receipt_says_the_file_is_gone_not_the_booking(): void
	{
		$msg = AccountController::missing_receipt_message();
		$this->assertStringContainsString('receipt file', $msg);
		$this->assertStringContainsString('Your booking is not affected', $msg);
	}

	public function test_missing_receipt_queues_a_notice_and_returns_the_booking_page_when_notices_exist(): void
	{
		if (! function_exists('WC') || ! \WC()->session) {
			$this->assertSame('', AccountController::report_missing_receipt(123), 'No notices available: the caller shows the plain page.');
			return;
		}
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
		wc_clear_notices();
		$back = AccountController::report_missing_receipt($booking);

		$this->assertNotSame('', $back);
		$this->assertStringContainsString('Your booking is not affected', (string) wp_json_encode(wc_get_notices('error')));
		wc_clear_notices();
	}
}
