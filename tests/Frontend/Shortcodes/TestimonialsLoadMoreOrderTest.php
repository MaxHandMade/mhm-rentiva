<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\Testimonials;
use MHMRentiva\Tests\Support\RequestSimulator;
use WP_Ajax_UnitTestCase;

/**
 * "Load More" continues the list in its own order (spec 3A Task 8, ruling T8-1).
 *
 * The endpoint used to build its read without orderby/order, so after a rating
 * sort page 2 came in date order: top-rated cards again, others never. The
 * wrapper now carries the order, filters and card flags that differ from the
 * defaults (data-*), the script sends them, and the endpoint maps them onto the
 * same allowlists. Each test takes the POST from the rendered wrapper, as the
 * script does.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Testimonials::ajax_load_testimonials
 */
final class TestimonialsLoadMoreOrderTest extends WP_Ajax_UnitTestCase
{
	/**
	 * Eight reviews, newest first: [text, rating]. Ratings out of step with the
	 * dates, two without a rating.
	 */
	private const REVIEWS = array(
		array( 'R1', 3 ),
		array( 'R2', 5 ),
		array( 'R3', 0 ),
		array( 'R4', 1 ),
		array( 'R5', 4 ),
		array( 'R6', 5 ),
		array( 'R7', 2 ),
		array( 'R8', 0 ),
	);

	public function setUp(): void
	{
		parent::setUp();
		Testimonials::register();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);

		$vehicle = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Load More Vehicle',
			)
		);
		foreach (self::REVIEWS as $i => $review) {
			$comment = (int) self::factory()->comment->create(
				array(
					'comment_post_ID'  => $vehicle,
					'comment_approved' => '1',
					'comment_author'   => 'Reviewer ' . $review[0],
					'comment_content'  => $review[0],
					'comment_date'     => gmdate('Y-m-d H:i:s', strtotime('2026-06-01 10:00:00') - $i * DAY_IN_SECONDS),
				)
			);
			if ($review[1] > 0) {
				update_comment_meta($comment, 'mhmrentiva_rating', $review[1]);
			}
		}
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	/**
	 * Page 2 and 3 after a rating sort are exactly the next-ranked reviews: no
	 * repeats, none skipped; both directions, unrated last in both.
	 */
	public function test_load_more_continues_the_rating_order(): void
	{
		$desc = array( 'R2', 'R6', 'R5', 'R1', 'R7', 'R4', 'R3', 'R8' );
		$asc  = array( 'R4', 'R7', 'R1', 'R5', 'R2', 'R6', 'R3', 'R8' );

		$this->assertSame($desc, $this->all_pages('orderby="rating" limit="3"'));
		$this->assertSame($asc, $this->all_pages('orderby="rating" order="ASC" limit="3"'));
		// Date order, both ways (ASC was broken the same way).
		$this->assertSame(array_column(self::REVIEWS, 0), $this->all_pages('limit="3"'));
		$this->assertSame(array_reverse(array_column(self::REVIEWS, 0)), $this->all_pages('order="ASC" limit="3"'));
	}

	/**
	 * A random order is one seeded draw: later pages continue it, so every review
	 * shows exactly once.
	 */
	public function test_load_more_continues_a_random_draw(): void
	{
		$all = $this->all_pages('orderby="rand" limit="3"');
		$this->assertCount(8, $all);
		$this->assertSame(array(), array_diff(array_column(self::REVIEWS, 0), $all), 'Every review appears.');
		$this->assertCount(8, array_unique($all), 'No review repeats.');
	}

	/**
	 * The wrapper names only what differs from the defaults (a default render
	 * keeps its markup -- the goldens of TestimonialsSettingsTest); the card
	 * flags reach the script, and each row carries the date as the cards print it.
	 */
	public function test_wrapper_carries_non_default_settings_only(): void
	{
		$default = $this->wrapper_data($this->shortcode('limit="3"'));
		$this->assertSame(array( 'limit', 'layout', 'auto-rotate' ), array_keys($default));

		$set = $this->wrapper_data($this->shortcode('limit="3" orderby="rating" order="ASC" rating="2" show_author_avatar="0" show_quotes="0" show_date="0"'));
		$this->assertSame('rating', $set['orderby']);
		$this->assertSame('ASC', $set['order']);
		$this->assertSame('2', $set['rating']);
		$this->assertSame('0', $set['show-author-avatar']);
		$this->assertSame('0', $set['show-quotes']);
		$this->assertSame('0', $set['show-date']);
		$this->assertArrayNotHasKey('show-rating', $set);
		$this->assertArrayNotHasKey('seed', $set);

		// The rating filter is kept on later pages: R1 (3), R5 (4), R2, R6 (5), R7 (2).
		$this->assertSame(array( 'R7', 'R1', 'R5', 'R2', 'R6' ), $this->all_pages('orderby="rating" order="ASC" rating="2" limit="2"'));

		$rows = $this->page(array( 'limit' => '3' ), 2);
		$this->assertSame(date_i18n((string) get_option('date_format'), strtotime('2026-05-29 10:00:00')), $rows[0]['date_display']);

		// A value outside the allowlist is the date order.
		$this->assertSame(
			array( 'R4', 'R5', 'R6' ),
			array_column(
				$this->page(
					array(
						'limit'   => '3',
						'orderby' => 'post_title; DROP',
						'order'   => 'sideways',
					),
					2
				),
				'review'
			)
		);
	}

	/**
	 * The texts of every page: page 1 from the render, the rest from the endpoint
	 * with the POST the script builds from the wrapper.
	 *
	 * @return list<string>
	 */
	private function all_pages(string $atts): array
	{
		$html = $this->shortcode($atts);
		preg_match_all('#<p>&ldquo;(.*?)&rdquo;</p>#', $html, $m);
		$texts = $m[1];

		$data = $this->wrapper_data($html);
		$post = array(
			'limit'      => $data['limit'],
			'rating'     => $data['rating'] ?? '',
			'vehicle_id' => $data['vehicle-id'] ?? '',
			'orderby'    => $data['orderby'] ?? 'date',
			'order'      => $data['order'] ?? 'DESC',
			'seed'       => $data['seed'] ?? '',
		);
		for ($page = 2; $page <= 10; $page++) {
			$rows = $this->page($post, $page);
			if (array() === $rows) {
				break;
			}
			$texts = array_merge($texts, array_column($rows, 'review'));
		}
		return $texts;
	}

	/**
	 * @param array<string,string> $post Request fields.
	 * @return list<array<string,mixed>>
	 */
	private function page(array $post, int $page): array
	{
		$_POST = array_merge(
			$post,
			array(
				'nonce' => wp_create_nonce('mhmrentiva_testimonials_nonce'),
				'page'  => (string) $page,
			)
		);
		$this->_last_response = '';
		try {
			$this->_handleAjax('mhmrentiva_load_testimonials');
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			// wp_send_json_* terminates; the body is the assertion.
			unset($e);
		}
		$decoded = json_decode($this->_last_response, true);
		$this->assertTrue((bool) ( $decoded['success'] ?? false ), 'Endpoint refused: ' . $this->_last_response);
		return (array) ( $decoded['data']['testimonials'] ?? array() );
	}

	/**
	 * @return array<string,string> the wrapper's data-* attributes, in order
	 */
	private function wrapper_data(string $html): array
	{
		$this->assertSame(1, preg_match('#<div class="rv-testimonials [^>]*>#', $html, $tag));
		preg_match_all('#data-([a-z-]+)="([^"]*)"#', $tag[0], $m, PREG_SET_ORDER);
		$out = array();
		foreach ($m as $attr) {
			$out[ $attr[1] ] = html_entity_decode($attr[2]);
		}
		return $out;
	}

	private function shortcode(string $atts): string
	{
		RequestSimulator::new_request();
		return do_shortcode('[rentiva_testimonials ' . $atts . ']');
	}
}
