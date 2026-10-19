<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle;

use MHMRentiva\Admin\Vehicle\Helpers\RatingHelper;
use WP_UnitTestCase;

/**
 * The rating summary drew bars for 5, 4 and 3 stars only, from a query that
 * also filtered to those three values, so 1- and 2-star reviews counted in
 * the average and the review total but vanished from the bars (plan 0.7).
 * The query also lived in the template.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Helpers\RatingHelper::get_distribution
 */
final class RatingDistributionTest extends WP_UnitTestCase
{
	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle' ) );
	}

	private function review( int $rating, int $approved = 1 ): void
	{
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $this->vehicle_id,
				'comment_type'     => 'review',
				'comment_approved' => $approved,
			)
		);
		update_comment_meta( $comment_id, 'mhmrentiva_rating', (string) $rating );
	}

	public function test_every_star_value_is_counted(): void
	{
		foreach ( array( 5, 5, 4, 3, 2, 1, 1 ) as $rating ) {
			$this->review( $rating );
		}

		$this->assertSame(
			array(
				5 => 2,
				4 => 1,
				3 => 1,
				2 => 1,
				1 => 2,
			),
			RatingHelper::get_distribution( $this->vehicle_id )
		);
	}

	public function test_only_approved_reviews_count_as_the_average_does(): void
	{
		$this->review( 1 );
		$this->review( 5, 0 );

		$this->assertSame(
			array(
				5 => 0,
				4 => 0,
				3 => 0,
				2 => 0,
				1 => 1,
			),
			RatingHelper::get_distribution( $this->vehicle_id )
		);
	}

	public function test_the_template_draws_a_bar_for_each_star_value(): void
	{
		$template = (string) file_get_contents( MHMRENTIVA_PLUGIN_DIR . 'templates/shortcodes/vehicle-rating-form.php' );

		$this->assertStringNotContainsString( '$wpdb', $template, 'The rating summary must not query the database from the template.' );
		$this->assertStringContainsString( 'RatingHelper::get_distribution', $template );
	}
}
