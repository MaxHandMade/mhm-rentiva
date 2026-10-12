<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Frontend\PreviewSamples;
use MHMRentiva\Frontend\RenderContext;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ResetsRenderContext;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * Slice 3B-2a, markup layer: in an editor preview the widget markup carries no
 * nonce and no recipient token, submit buttons are disabled and a `<form>` has
 * no `action`. The front end is pinned by the golden tests, not here (this file
 * only holds a control render proving the patterns bite).
 */
final class PreviewInertMarkupTest extends WP_UnitTestCase
{
	use ResetsRenderContext {
		tear_down as private reset_context_and_tear_down;
	}

	/** Every fragment a preview must not print. */
	private const FORBIDDEN = array(
		'name="nonce"',
		'_wpnonce',
		'data-nonce',
		'recipient_token',
		'name="rating_nonce"',
		'name="rv_contact_nonce"',
		'name="mhmrentiva_search_results_nonce"',
		'name="mhmrentiva_account_nonce"',
	);

	public function set_up(): void
	{
		parent::set_up();
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; a preview cannot be entered.');
		RenderContext::reset();
		PreviewSamples::reset();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		RequestSimulator::new_request();
	}

	public function tear_down(): void
	{
		\Elementor\Plugin::$instance->editor->set_edit_mode(false);
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		$this->reset_context_and_tear_down();
	}

	private function set_preview(bool $on): void
	{
		\Elementor\Plugin::$instance->editor->set_edit_mode($on);
	}

	/**
	 * Tag => [extra attributes, forms the preview must still show (0 = none required)].
	 *
	 * @return array<string,array{0:string,1:array<string,string>,2:int}>
	 */
	public function shortcodes(): array
	{
		return array(
			'contact (token)'    => array( 'rentiva_contact', array( 'email_to' => 'zz-recipient@example.test' ), 1 ),
			'rating form'        => array( 'rentiva_vehicle_rating_form', array(), 1 ),
			'vehicle details'    => array( 'rentiva_vehicle_details', array(), 0 ),
			'vehicles list'      => array( 'rentiva_vehicles_list', array(), 0 ),
			'vehicles grid'      => array( 'rentiva_vehicles_grid', array(), 0 ),
			'featured vehicles'  => array( 'rentiva_featured_vehicles', array(), 0 ),
			'search results'     => array( 'rentiva_search_results', array(), 1 ),
			'unified search'     => array( 'rentiva_unified_search', array(), 1 ),
			'booking form'       => array( 'rentiva_booking_form', array(), 1 ),
			'availability'       => array( 'rentiva_availability_calendar', array(), 0 ),
			'testimonials'       => array( 'rentiva_testimonials', array(), 0 ),
			'comparison'         => array( 'rentiva_vehicle_comparison', array(), 0 ),
			'my bookings'        => array( 'rentiva_my_bookings', array(), 1 ),
			'my favorites'       => array( 'rentiva_my_favorites', array(), 0 ),
			'payment history'    => array( 'rentiva_payment_history', array(), 0 ),
			'user dashboard'     => array( 'rentiva_user_dashboard', array(), 0 ),
		);
	}

	/**
	 * @param array<string,string> $extra
	 * @dataProvider shortcodes
	 */
	public function test_preview_markup_is_inert(string $tag, array $extra, int $min_forms): void
	{
		$fixture = ShortcodeFixtures::for_tag($tag);
		if (null !== $fixture['user']) {
			wp_set_current_user((int) $fixture['user']);
		} else {
			// Forms behind a login (rating) need a customer; the others render for anyone.
			wp_set_current_user(ShortcodeFixtures::customer());
		}
		// A favourite/compare button needs a vehicle on the page.
		ShortcodeFixtures::vehicle();

		$this->set_preview(true);
		$html = $this->shortcode($tag, array_merge($fixture['atts'], $extra));

		// The root marker belongs to Task 2/4-6; this test only needs a real render.
		$this->assertNotSame('', trim($html), "$tag rendered nothing in preview.");
		$this->assert_inert($html, $tag);
		$this->assertGreaterThanOrEqual($min_forms, preg_match_all('/<form\b/', $html), "$tag: expected the form to render in preview.");
	}

	/**
	 * The patterns must bite: the same render on the front end carries them.
	 */
	public function test_front_control_carries_the_patterns(): void
	{
		wp_set_current_user(ShortcodeFixtures::customer());
		ShortcodeFixtures::vehicle();

		$contact = $this->shortcode('rentiva_contact', array( 'email_to' => 'zz-recipient@example.test' ));
		$this->assertStringContainsString('name="rv_contact_nonce"', $contact);
		$this->assertStringContainsString('name="recipient_token"', $contact);
		$this->assertStringContainsString('type="submit"', $contact);
		$this->assertDoesNotMatchRegularExpression('/<button\b[^>]*type="submit"[^>]*\bdisabled\b/', $contact);

		$rating_fixture = ShortcodeFixtures::for_tag('rentiva_vehicle_rating_form');
		$rating         = $this->shortcode('rentiva_vehicle_rating_form', $rating_fixture['atts']);
		$this->assertStringContainsString('name="rating_nonce"', $rating);
		$this->assertStringContainsString('name="nonce"', $rating);

		$grid = $this->shortcode('rentiva_vehicles_grid', array());
		$this->assertStringContainsString('data-nonce=', $grid, 'The grid carries data-nonce on the front end.');

		$bookings_fixture = ShortcodeFixtures::for_tag('rentiva_my_bookings');
		wp_set_current_user((int) $bookings_fixture['user']);
		$bookings = $this->shortcode('rentiva_my_bookings', $bookings_fixture['atts']);
		$this->assertMatchesRegularExpression('/<form\b[^>]*\baction=/', $bookings, 'The bookings filter form has an action on the front end.');

		$search = $this->shortcode('rentiva_unified_search', array());
		$this->assertMatchesRegularExpression('/<form\b[^>]*\baction=/', $search);
	}

	public function test_contact_preview_does_not_issue_a_token(): void
	{
		global $wpdb;
		$this->set_preview(true);
		$before = array(
			(int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}"),
			(int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}"),
		);
		$html = $this->shortcode('rentiva_contact', array( 'email_to' => 'zz-recipient@example.test' ));
		$this->assertStringNotContainsString('recipient_token', $html);
		$this->assertSame(
			$before,
			array(
				(int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}"),
				(int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}"),
			)
		);
	}

	public function test_block_editor_path_is_inert(): void
	{
		RenderContext::register();
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
		$request = new \WP_REST_Request('GET', '/wp/v2/block-renderer/mhm-rentiva/contact');
		$request->set_param('context', 'edit');
		$request->set_param('attributes', array( 'emailTo' => 'zz-recipient@example.test' ));
		$response = rest_do_request($request);
		$data     = $response->get_data();
		$html     = is_array($data) && isset($data['rendered']) ? (string) $data['rendered'] : '';

		$this->assertStringContainsString('rv-contact', $html, 'The block renderer returned no contact form.');
		$this->assertStringContainsString('data-rv-render="preview"', $html);
		$this->assert_inert($html, 'block contact');
	}

	private function assert_inert(string $html, string $label): void
	{
		foreach (self::FORBIDDEN as $needle) {
			$this->assertSame(0, substr_count($html, $needle), "$label: preview markup prints `$needle`.");
		}

		preg_match_all('/<button\b[^>]*>/', $html, $buttons);
		foreach ($buttons[0] as $button) {
			if (str_contains($button, 'type="submit"') || str_contains($button, 'data-testid="booking-submit-btn"')) {
				$this->assertMatchesRegularExpression('/\bdisabled\b/', $button, "$label: submit button is not disabled: $button");
			}
		}

		preg_match_all('/<form\b[^>]*>/', $html, $forms);
		foreach ($forms[0] as $form) {
			$this->assertDoesNotMatchRegularExpression('/\baction\s*=/', $form, "$label: <form> keeps an action: $form");
		}
	}

	/**
	 * @param array<string,string> $atts
	 */
	private function shortcode(string $tag, array $atts): string
	{
		RequestSimulator::new_request();
		$pairs = '';
		foreach ($atts as $key => $value) {
			$pairs .= sprintf(' %s="%s"', $key, esc_attr($value));
		}
		return do_shortcode('[' . $tag . $pairs . ']');
	}
}
