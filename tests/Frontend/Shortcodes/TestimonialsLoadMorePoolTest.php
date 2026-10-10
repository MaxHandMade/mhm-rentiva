<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\Testimonials;
use MHMRentiva\Tests\Support\RequestSimulator;
use WP_Ajax_UnitTestCase;

/**
 * More than 50 reviews (spec 3A Task 10a item 7).
 *
 * Each source was read newest first and capped at 50, so an oldest-first or a
 * rating order drew from the newest 50 only (the oldest / best-rated reviews
 * never showed), and has_more counted every review, so "Load More" stayed
 * visible past what the pool could serve. Each source is now read in the
 * requested order, and has_more stops at what can be served.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Testimonials
 */
final class TestimonialsLoadMorePoolTest extends WP_Ajax_UnitTestCase
{
	private const COMMENTS = 60;

	/** @var list<string> Every review, newest first (C0..C59, then B0..B5). */
	private array $newest_first = array();

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
				'post_title'  => 'Pool Vehicle',
			)
		);

		// 60 comments, C0 newest. The five oldest (C55..C59) are the only 5-star ones.
		for ($i = 0; $i < self::COMMENTS; $i++) {
			$comment = (int) self::factory()->comment->create(
				array(
					'comment_post_ID'  => $vehicle,
					'comment_approved' => '1',
					'comment_author'   => 'Reviewer C' . $i,
					'comment_content'  => 'C' . $i,
					'comment_date'     => gmdate('Y-m-d H:i:s', strtotime('2026-06-01 10:00:00') - $i * HOUR_IN_SECONDS),
				)
			);
			update_comment_meta($comment, 'mhmrentiva_rating', $i >= 55 ? 5 : 1 + ( $i % 3 ));
			$this->newest_first[] = 'C' . $i;
		}

		// Six booking reviews, older than every comment, 4 stars.
		for ($i = 0; $i < 6; $i++) {
			$booking = (int) self::factory()->post->create(
				array(
					'post_type'   => 'mhmrentiva_booking',
					'post_status' => 'publish',
					'post_title'  => 'Booking B' . $i,
					'post_date'   => gmdate('Y-m-d H:i:s', strtotime('2026-01-01 10:00:00') - $i * DAY_IN_SECONDS),
				)
			);
			update_post_meta($booking, '_mhmrentiva_vehicle_id', $vehicle);
			update_post_meta($booking, '_mhmrentiva_customer_review', 'B' . $i);
			update_post_meta($booking, '_mhmrentiva_review_approved', '1');
			update_post_meta($booking, '_mhmrentiva_customer_rating', 4);
			update_post_meta($booking, '_mhmrentiva_customer_name', 'Booker B' . $i);
			$this->newest_first[] = 'B' . $i;
		}
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	/**
	 * The pool is the right 50: oldest-first starts at the oldest review, a
	 * rating order at the best-rated ones, wherever they sit by date.
	 */
	public function test_pool_follows_the_requested_order(): void
	{
		$oldest_first = array_reverse($this->newest_first);
		$this->assertSame(array_slice($oldest_first, 0, 10), $this->first_page('order="ASC" limit="10"'));

		$this->assertSame(array( 'C55', 'C56', 'C57', 'C58', 'C59' ), $this->first_page('orderby="rating" limit="5"'));
		// Then the 4-star bookings, newest first.
		$this->assertSame(array( 'B0', 'B1', 'B2', 'B3', 'B4' ), $this->texts($this->page(array( 'limit' => '5', 'orderby' => 'rating', 'order' => 'DESC' ), 2)['testimonials']));

		// Lowest first: the 1-star comments, newest first (C0, C3, ...).
		$this->assertSame(array( 'C0', 'C3', 'C6' ), $this->first_page('orderby="rating" order="ASC" limit="3"'));
	}

	/**
	 * has_more stops at what can be served: every page it promises has rows,
	 * the pages are the first 50 of the order with no repeat, and the last
	 * served page says there is no more.
	 *
	 * @dataProvider orders
	 */
	public function test_has_more_stops_at_what_can_be_served(array $post): void
	{
		$served = array();
		$page   = 1;
		do {
			$page++;
			$data = $this->page($post, $page);
			$rows = $this->texts($data['testimonials']);
			$this->assertNotSame(array(), $rows, "Page $page was promised but is empty.");
			$served = array_merge($served, $rows);
			$this->assertLessThan(20, $page, 'has_more never turned false.');
		} while ($data['has_more']);

		$this->assertSame(5, $page, 'Pages 2..5 at 10 a page serve up to the 50-row pool.');
		$this->assertCount(40, $served);
		$this->assertCount(40, array_unique($served), 'No review repeats.');
		if ('rand' !== $post['orderby']) {
			$expected = 'ASC' === $post['order'] ? array_reverse($this->newest_first) : $this->newest_first;
			$this->assertSame(array_slice($expected, 10, 40), $served);
		}
	}

	/** @return array<string,array{0:array<string,string>}> */
	public function orders(): array
	{
		return array(
			'newest first' => array( array( 'limit' => '10', 'orderby' => 'date', 'order' => 'DESC' ) ),
			'oldest first' => array( array( 'limit' => '10', 'orderby' => 'date', 'order' => 'ASC' ) ),
			'random'       => array( array( 'limit' => '10', 'orderby' => 'rand', 'order' => 'DESC', 'seed' => '12345' ) ),
		);
	}

	/**
	 * A first page that already holds the whole pool shows no "Load More".
	 */
	public function test_no_load_more_button_past_the_pool(): void
	{
		$this->assertStringContainsString('rv-load-more-btn', $this->shortcode('limit="10"'));
		$this->assertStringNotContainsString('rv-load-more-btn', $this->shortcode('limit="50"'));
	}

	/** @return list<string> */
	private function first_page(string $atts): array
	{
		preg_match_all('#<p>&ldquo;(.*?)&rdquo;</p>#', $this->shortcode($atts), $m);
		return $m[1];
	}

	/**
	 * @param list<array<string,mixed>> $rows Endpoint rows.
	 * @return list<string>
	 */
	private function texts(array $rows): array
	{
		return array_map('strval', array_column($rows, 'review'));
	}

	/**
	 * @param array<string,string> $post Request fields.
	 * @return array{testimonials:list<array<string,mixed>>,has_more:bool}
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
			unset($e);
		}
		$decoded = json_decode($this->_last_response, true);
		$this->assertTrue((bool) ( $decoded['success'] ?? false ), 'Endpoint refused: ' . $this->_last_response);
		return array(
			'testimonials' => (array) ( $decoded['data']['testimonials'] ?? array() ),
			'has_more'     => (bool) ( $decoded['data']['has_more'] ?? false ),
		);
	}

	private function shortcode(string $atts): string
	{
		RequestSimulator::new_request();
		return do_shortcode('[rentiva_testimonials ' . $atts . ']');
	}
}
