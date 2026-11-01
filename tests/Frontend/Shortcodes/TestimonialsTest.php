<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Frontend\Shortcodes\Testimonials;
use MHMRentiva\Tests\Support\RequestSimulator;
use WP_UnitTestCase;

/**
 * The testimonials page on the rvs layer (PR F, R15): the shell, the three layouts and
 * their aliases, the columns and the class attribute, the honest empty state, and one
 * rule for the list and the count.
 */
class TestimonialsTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		RequestSimulator::new_request();
	}

	public function tearDown(): void
	{
		remove_all_filters('mhmrentiva_shortcode_html_cache_enabled');
		RequestSimulator::new_request();
		parent::tearDown();
	}

	/**
	 * @param int    $n    Reviews to create on one published vehicle.
	 * @param string $type Comment type.
	 */
	private function reviews(int $n, string $type = 'review'): int
	{
		$vehicle = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Layout Vehicle',
			)
		);
		for ($i = 1; $i <= $n; $i++) {
			$id = (int) wp_insert_comment(
				array(
					'comment_type'     => $type,
					'comment_post_ID'  => $vehicle,
					'comment_author'   => 'Person ' . $i,
					'comment_content'  => 'Words ' . $i,
					'comment_approved' => 1,
					'comment_date'     => gmdate('Y-m-d H:i:s', time() - $i * DAY_IN_SECONDS),
				)
			);
			if ('review' === $type) {
				update_comment_meta($id, 'mhmrentiva_rating', 5);
			}
		}

		return $vehicle;
	}

	private function render(string $atts = ''): string
	{
		RequestSimulator::new_request();
		return do_shortcode('[rentiva_testimonials ' . $atts . ']');
	}

	public function test_page_is_inside_the_rvs_shell_and_has_no_render_override(): void
	{
		$this->reviews(2);
		$output = $this->render();

		$this->assertStringStartsWith('<div class="rvs-shell" data-rvs-shortcode="rentiva_testimonials">', $output);
		$this->assertStringContainsString('rvs-testimonials', $output);
		$this->assertSame(
			AbstractShortcode::class,
			( new \ReflectionMethod(Testimonials::class, 'render') )->getDeclaringClass()->getName(),
			'Testimonials must not override render(): the shell, the preview and the cache come from the parent.'
		);
	}

	public function test_grid_layout_is_default_with_three_columns(): void
	{
		$this->reviews(2);
		$output = $this->render();

		$this->assertStringContainsString('rvs-testimonials--grid', $output);
		$this->assertStringContainsString('rvs-testimonials--cols-3', $output);
		$this->assertSame(2, substr_count($output, 'class="rvs-tcard"'));
	}

	public function test_columns_and_class_attributes_are_effective(): void
	{
		$this->reviews(1);
		$output = $this->render('columns="2" class="my-custom-class"');

		$this->assertStringContainsString('rvs-testimonials--cols-2', $output);
		$this->assertStringContainsString('my-custom-class', $output);
		// Out of range stays within the four the CSS knows.
		$this->assertStringContainsString('rvs-testimonials--cols-4', $this->render('columns="9"'));
	}

	public function test_list_layout(): void
	{
		$this->reviews(2);
		$output = $this->render('layout="list"');

		$this->assertStringContainsString('rvs-testimonials--list', $output);
		$this->assertStringNotContainsString('data-rvs-prev', $output);
	}

	/**
	 * @dataProvider carousel_names
	 */
	public function test_carousel_and_its_aliases_draw_the_slider(string $layout): void
	{
		$this->reviews(3);
		$output = $this->render('layout="' . $layout . '" auto_rotate="1"');

		$this->assertStringContainsString('rvs-testimonials--carousel', $output);
		$this->assertStringContainsString('data-auto-rotate="1"', $output);
		$this->assertStringContainsString('role="region"', $output);
		$this->assertSame(3, substr_count($output, 'class="rvs-testimonials__dot"'));
		// Every indicator names its review.
		$this->assertSame(3, preg_match_all('/class="rvs-testimonials__dot" aria-label="Review \d of 3"/', $output));
		$this->assertStringContainsString('aria-label="Previous review"', $output);
		$this->assertStringContainsString('aria-label="Next review"', $output);
	}

	/** @return array<string,array{0:string}> */
	public function carousel_names(): array
	{
		return array(
			'carousel' => array( 'carousel' ),
			'slider'   => array( 'slider' ),
			'vip'      => array( 'vip' ),
		);
	}

	public function test_auto_rotate_setting_reaches_the_page(): void
	{
		$this->reviews(3);

		$this->assertStringContainsString('data-auto-rotate="0"', $this->render('layout="carousel"'));
		// The script acts on it only where there is a slider.
		$this->assertStringContainsString('data-auto-rotate="1"', $this->render('layout="grid" auto_rotate="1"'));
	}

	public function test_a_single_review_has_no_slider_controls(): void
	{
		$this->reviews(1);
		$output = $this->render('layout="carousel"');

		$this->assertStringNotContainsString('data-rvs-prev', $output);
		$this->assertStringNotContainsString('rvs-testimonials__dot', $output);
	}

	public function test_empty_state_is_honest(): void
	{
		$output = $this->render();

		$this->assertStringContainsString('No reviews yet.', $output);
		$this->assertStringNotContainsString('Be the first', $output, 'This page has no way to leave a review.');
		$this->assertStringNotContainsString('data-rvs-load-more', $output);
	}

	public function test_a_plain_comment_is_neither_listed_nor_counted(): void
	{
		$this->reviews(1);
		$this->reviews(1, 'comment');
		$output = $this->render('limit="1"');

		$this->assertSame(1, substr_count($output, 'class="rvs-tcard"'));
		$this->assertStringNotContainsString('data-rvs-load-more', $output, 'The count and the list follow one rule: one review, no second page.');
	}

	public function test_load_more_is_offered_and_its_template_carries_the_page_flags(): void
	{
		$this->reviews(3);
		$output = $this->render('limit="2" show_quotes="0" show_date="0"');

		$this->assertStringContainsString('data-rvs-load-more', $output);
		$this->assertStringContainsString('<div hidden inert data-rvs-card-template>', $output);
		// Two real cards and one in the template, none with a quote icon or a date.
		$this->assertSame(3, substr_count($output, 'class="rvs-tcard"'));
		$this->assertStringNotContainsString('rvs-tcard__quote', $output);
		$this->assertStringNotContainsString('rvs-tcard__date', $output);
	}

	public function test_names_are_masked_and_flags_hide_their_parts(): void
	{
		$this->reviews(1);
		$output = $this->render();
		$this->assertStringContainsString('Person 1', $output);

		$none = $this->render('show_customer="0" show_rating="0"');
		$this->assertStringNotContainsString('rvs-tcard__author', $none);
		$this->assertStringNotContainsString('rvs-review-stars', $none);
		$this->assertStringContainsString('Words 1', $none);
	}

	public function test_random_order_seed_is_in_the_page_and_the_cache_key_ignores_it(): void
	{
		$this->reviews(3);
		remove_all_filters('mhmrentiva_shortcode_html_cache_enabled');
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_true', 999);

		$first = $this->render('orderby="rand" limit="2"');
		$this->assertSame(1, preg_match('/data-seed="(\d+)"/', $first), 'A random list prints its seed for Load more.');

		// A second visitor gets the stored page: the same seed, so the same draw.
		$second = $this->render('orderby="rand" limit="2"');
		$this->assertSame($first, $second);

		// Other attributes make another key.
		$other = $this->render('orderby="rand" limit="3"');
		$this->assertNotSame($first, $other);

		Testimonials::cleanup();
	}

	public function test_two_pages_in_one_request_each_have_their_own_load_more(): void
	{
		$this->reviews(3);
		RequestSimulator::new_request();
		$both = do_shortcode('[rentiva_testimonials limit="1"][rentiva_testimonials limit="2" orderby="rating"]');

		$this->assertSame(2, substr_count($both, 'data-rvs-testimonials '));
		$this->assertSame(2, substr_count($both, 'data-rvs-load-more'));
		$this->assertSame(1, substr_count($both, 'data-orderby='), 'The second list order must not leak into the first.');
	}

	public function test_the_hidden_card_clone_is_empty_and_inert(): void
	{
		$this->reviews(3);
		$output = $this->render('limit="2"');

		$start = strpos($output, 'data-rvs-card-template');
		$this->assertNotFalse($start, 'The hidden card must be in the page.');
		$this->assertStringContainsString('<div hidden inert data-rvs-card-template>', $output);
		$clone = substr($output, $start);
		$this->assertStringNotContainsString('rvs-review-stars__star', $clone, 'The script draws the stars.');
		$this->assertStringNotContainsString('>-<', $clone, 'No placeholder text.');
		$this->assertStringContainsString('data-rvs-stars', $clone);
		$this->assertStringContainsString('data-rvs-slot="vehicle"></span>', $clone);
		$this->assertStringContainsString('data-rvs-slot="date"></span>', $clone);
	}

	public function test_a_slider_that_starts_with_one_review_keeps_hidden_controls_for_load_more(): void
	{
		$this->reviews(3);
		$output = $this->render('layout="carousel" limit="1" auto_rotate="1"');

		$this->assertSame(2, substr_count($output, 'class="rvs-tcard"'), 'One real card and the hidden clone.');
		$this->assertStringContainsString('data-rvs-controls hidden', $output);
		$this->assertStringContainsString('data-rvs-pause', $output);

		// Without more to load, one review has no controls at all.
		$single = $this->reviews(1);
		$this->assertStringNotContainsString('data-rvs-controls', $this->render('layout="carousel" limit="1" vehicle_id="' . $single . '"'));
	}

	public function test_the_pause_button_exists_only_with_auto_rotate(): void
	{
		$this->reviews(3);

		$on = $this->render('layout="carousel" auto_rotate="1"');
		$this->assertStringContainsString('data-rvs-pause aria-label="Pause automatic rotation"', $on);
		$this->assertStringNotContainsString('data-rvs-pause', $this->render('layout="carousel"'));
	}
}
