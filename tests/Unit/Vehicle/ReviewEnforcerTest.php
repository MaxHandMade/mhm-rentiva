<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Vehicle;

use MHMRentiva\Admin\Vehicle\Hooks\ReviewEnforcer;
use WP_UnitTestCase;

/**
 * WP.org T7 — ReviewEnforcer used to read the submitted rating out of $_POST
 * while running inside WordPress's comment pipeline, which carries no nonce of
 * its own. The read could therefore never be justified in the scope that
 * performed it, only annotated.
 *
 * It now validates the comment it is given: the rating arrives in
 * $commentdata['comment_meta'], put there by the nonce-verified handler that
 * owns the request (VehicleRatingForm::ajax_submit_rating).
 *
 * These tests pin the contract end to end — that comment_meta actually survives
 * the wp_new_comment() -> preprocess_comment hop is the load-bearing assumption,
 * and asserting it here is what stops the review form silently dying if a core
 * change ever breaks it.
 */
final class ReviewEnforcerTest extends WP_UnitTestCase
{
    private int $vehicle_id;

    public function setUp(): void
    {
        parent::setUp();
        ReviewEnforcer::register();
        $this->vehicle_id = self::factory()->post->create( array( 'post_type' => 'vehicle' ) );
    }

    public function tearDown(): void
    {
        remove_filter( 'preprocess_comment', array( ReviewEnforcer::class, 'enforce_vehicle_constraints' ), 1 );
        parent::tearDown();
    }

    public function test_a_review_carrying_its_rating_in_comment_meta_is_accepted(): void
    {
        $user_id = self::factory()->user->create();

        $comment_id = wp_new_comment(
            array(
                'comment_post_ID'      => $this->vehicle_id,
                'comment_content'      => 'Solid little car.',
                'comment_type'         => 'review',
                'comment_approved'     => 1,
                'comment_author'       => 'Test Reviewer',
                'comment_author_email' => 'reviewer@example.com',
                'comment_author_url'   => '',
                'user_id'              => $user_id,
                'comment_meta'         => array( 'rating' => 4 ),
            ),
            true
        );

        $this->assertIsInt( $comment_id );
        $this->assertGreaterThan( 0, $comment_id );
    }

    public function test_a_vehicle_review_without_a_rating_is_rejected(): void
    {
        // Under CLI the enforcer throws rather than wp_die()ing (see the WP_CLI
        // branch in enforce_vehicle_constraints); the rejection is the contract.
        $this->expectException( \Exception::class );
        $this->expectExceptionMessage( 'You must provide a valid rating' );

        wp_new_comment(
            array(
                'comment_post_ID'      => $this->vehicle_id,
                'comment_content'      => 'No stars given.',
                'comment_type'         => 'review',
                'comment_approved'     => 1,
                'comment_author'       => 'Test Reviewer',
                'comment_author_email' => 'reviewer@example.com',
                'comment_author_url'   => '',
                'user_id'              => self::factory()->user->create(),
            ),
            true
        );
    }

    public function test_an_out_of_range_rating_is_rejected(): void
    {
        // Under CLI the enforcer throws rather than wp_die()ing (see the WP_CLI
        // branch in enforce_vehicle_constraints); the rejection is the contract.
        $this->expectException( \Exception::class );
        $this->expectExceptionMessage( 'You must provide a valid rating' );

        wp_new_comment(
            array(
                'comment_post_ID'      => $this->vehicle_id,
                'comment_content'      => 'Six stars.',
                'comment_type'         => 'review',
                'comment_approved'     => 1,
                'comment_author'       => 'Test Reviewer',
                'comment_author_email' => 'reviewer@example.com',
                'comment_author_url'   => '',
                'user_id'              => self::factory()->user->create(),
                'comment_meta'         => array( 'rating' => 6 ),
            ),
            true
        );
    }

    public function test_comments_on_other_post_types_are_left_alone(): void
    {
        $post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );

        $comment_id = wp_new_comment(
            array(
                'comment_post_ID'      => $post_id,
                'comment_content'      => 'An ordinary comment, no rating anywhere.',
                'comment_approved'     => 1,
                'comment_author'       => 'Test Reviewer',
                'comment_author_email' => 'reviewer@example.com',
                'comment_author_url'   => '',
                'user_id'              => self::factory()->user->create(),
            ),
            true
        );

        $this->assertIsInt( $comment_id );
        $this->assertGreaterThan( 0, $comment_id );
    }
}
