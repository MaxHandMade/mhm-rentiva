<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Vehicle;

use MHMRentiva\Admin\Frontend\Shortcodes\VehicleRatingForm;
use MHMRentiva\Admin\Vehicle\Hooks\ReviewEnforcer;
use WP_Ajax_UnitTestCase;

/**
 * WP.org T7 — ReviewEnforcer stopped reading the submitted rating out of $_POST
 * (a read it could never justify: it runs inside core's comment pipeline, which
 * has no nonce). It now reads $commentdata['comment_meta']['rating'], and the
 * only producer of vehicle reviews — VehicleRatingForm::ajax_submit_rating(),
 * which verifies its own nonce — is what puts it there.
 *
 * ReviewEnforcerTest builds comment_meta by hand, so it pins the consumer and
 * core's hop but NOT the producer. That leaves a seam: delete
 * `'comment_meta' => array( 'rating' => $rating )` from ajax_submit_rating() and
 * every new review hits $rating = 0, wp_die()s mid-AJAX, and the visitor gets an
 * HTML error page where JSON was expected — with no test going red.
 *
 * This test closes that seam by driving the real AJAX action through the real
 * filter. Mutation-proven: removing the comment_meta line turns
 * test_a_submitted_rating_survives_the_enforcer red.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\VehicleRatingForm::ajax_submit_rating
 */
final class RatingSubmissionEnforcerSeamTest extends WP_Ajax_UnitTestCase
{
    private int $vehicle_id;
    private int $user_id;

    public function setUp(): void
    {
        parent::setUp();

        ReviewEnforcer::register();
        VehicleRatingForm::register();

        $this->vehicle_id = self::factory()->post->create(
            array(
                'post_type'   => 'vehicle',
                'post_status' => 'publish',
            )
        );
        $this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
    }

    public function tearDown(): void
    {
        $_POST = array();
        remove_filter( 'preprocess_comment', array( ReviewEnforcer::class, 'enforce_vehicle_constraints' ), 1 );
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function submitRating( int $rating ): array
    {
        wp_set_current_user( $this->user_id );

        $_POST = array(
            'nonce'      => wp_create_nonce( 'mhm_rentiva_rating_nonce' ),
            'vehicle_id' => $this->vehicle_id,
            'rating'     => $rating,
            'comment'    => 'A perfectly ordinary review body, long enough to pass the minimum length rule.',
        );

        try {
            $this->_handleAjax( 'mhm_rentiva_submit_rating' );
        } catch ( \WPAjaxDieContinueException | \WPAjaxDieStopException $e ) {
            // wp_send_json_* terminates; the response is read below.
        }

        $decoded = json_decode( $this->_last_response, true );
        return is_array( $decoded ) ? $decoded : array();
    }

    public function test_a_submitted_rating_survives_the_enforcer(): void
    {
        $response = $this->submitRating( 4 );

        // The enforcer wp_die()s on a missing rating, which would leave an HTML
        // error page here rather than JSON -- so a decodable success response is
        // itself the proof that the producer still hands the rating over.
        $this->assertNotEmpty(
            $response,
            'Handler produced no JSON. If ajax_submit_rating() stopped passing comment_meta, '
            . 'ReviewEnforcer wp_die()s here. Raw response: ' . $this->_last_response
        );
        $this->assertTrue(
            (bool) ( $response['success'] ?? false ),
            'Rating submission must succeed. Raw response: ' . $this->_last_response
        );

        $comments = get_comments(
            array(
                'post_id' => $this->vehicle_id,
                'status'  => 'any',
            )
        );
        $this->assertCount( 1, $comments, 'The review must actually reach the database.' );
        $this->assertSame(
            '4',
            (string) get_comment_meta( (int) $comments[0]->comment_ID, 'mhm_rating', true )
        );
    }

    public function test_the_handler_still_refuses_an_out_of_range_rating(): void
    {
        $response = $this->submitRating( 9 );

        $this->assertFalse( (bool) ( $response['success'] ?? true ) );
        $this->assertCount(
            0,
            get_comments( array( 'post_id' => $this->vehicle_id, 'status' => 'any' ) )
        );
    }
}
