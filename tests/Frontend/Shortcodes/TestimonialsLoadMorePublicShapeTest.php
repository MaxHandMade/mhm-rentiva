<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\Testimonials;
use WP_Ajax_UnitTestCase;

/**
 * The nopriv "Load more" answer carries what the page shows and nothing else: the
 * masked name, never the full name, an e-mail address, an id or a raw date.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Testimonials::ajax_load_testimonials
 */
final class TestimonialsLoadMorePublicShapeTest extends WP_Ajax_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		Testimonials::register();
		wp_set_current_user(0);

		$vehicle = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Shape Vehicle',
			)
		);
		for ($i = 1; $i <= 3; $i++) {
			$comment = (int) wp_insert_comment(
				array(
					'comment_type'         => 'review',
					'comment_post_ID'      => $vehicle,
					'comment_author'       => 'Jonathan Secretname',
					'comment_author_email' => 'jonathan.secret@example.com',
					'comment_content'      => 'Review ' . $i,
					'comment_approved'     => 1,
				)
			);
			update_comment_meta($comment, 'mhmrentiva_rating', 4);
		}
		$booking = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
				'post_title'  => 'Shape booking',
			)
		);
		update_post_meta($booking, '_mhmrentiva_vehicle_id', $vehicle);
		update_post_meta($booking, '_mhmrentiva_customer_review', 'Booking review');
		update_post_meta($booking, '_mhmrentiva_review_approved', '1');
		update_post_meta($booking, '_mhmrentiva_customer_rating', 5);
		update_post_meta($booking, '_mhmrentiva_customer_name', 'Maria Fullsurname');
		update_post_meta($booking, '_mhmrentiva_customer_email', 'maria.full@example.com');
	}

	public function test_the_answer_has_no_full_name_no_email_no_ids(): void
	{
		$_POST = array(
			'nonce' => wp_create_nonce('mhmrentiva_testimonials_nonce'),
			'page'  => '1',
			'limit' => '10',
		);
		try {
			$this->_handleAjax('mhmrentiva_load_testimonials');
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
			unset($e);
		}

		$json = $this->_last_response;
		$data = json_decode($json, true);
		$this->assertTrue((bool) ( $data['success'] ?? false ), $json);
		$this->assertGreaterThanOrEqual(4, count($data['data']['cards'] ?? array()), 'The fixture reviews must be in the answer, or the checks below prove nothing.');

		foreach (array( 'Secretname', 'Fullsurname', 'jonathan.secret', 'maria.full', '@example.com', 'customer_name', 'customer_email' ) as $needle) {
			$this->assertStringNotContainsString($needle, $json, "The public answer leaks $needle.");
		}
		$names = array_column($data['data']['cards'], 'name');
		$this->assertContains('Jonathan S.', $names);
		$this->assertContains('Maria F.', $names);

		foreach ($data['data']['cards'] as $card) {
			$this->assertSame(array( 'date', 'initial', 'name', 'rating', 'stars_label', 'text', 'vehicle' ), $this->sorted(array_keys($card)));
		}
		$this->assertArrayNotHasKey('testimonials', $data['data']);
	}

	/**
	 * @param array<int,string> $keys Keys.
	 * @return array<int,string>
	 */
	private function sorted(array $keys): array
	{
		sort($keys);
		return $keys;
	}
}
