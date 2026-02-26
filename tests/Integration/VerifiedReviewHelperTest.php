<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

use MHMRentiva\Admin\Vehicle\Helpers\VerifiedReviewHelper;
use MHMRentiva\Admin\Booking\Core\Status;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Integration tests for VerifiedReviewHelper
 *
 * @package MHMRentiva\Tests\Integration
 * @since 1.3.0
 */
class VerifiedReviewHelperTest extends \WP_UnitTestCase
{
    /** @var int */
    protected $vehicle_id;

    /** @var int */
    protected $other_vehicle_id;

    /** @var int */
    protected $user_id;

    /** @var int */
    protected $other_user_id;

    public function setUp(): void
    {
        parent::setUp();

        // Create vehicles
        $this->vehicle_id = $this->factory->post->create(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Test Vehicle for Verified Badge',
        ));

        $this->other_vehicle_id = $this->factory->post->create(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Other Vehicle',
        ));

        // Create users
        $this->user_id = $this->factory->user->create(array(
            'user_email' => 'customer@test.com',
        ));

        $this->other_user_id = $this->factory->user->create(array(
            'user_email' => 'other@test.com',
        ));

        // Clear all transients before each test
        VerifiedReviewHelper::invalidate_cache($this->vehicle_id);
        VerifiedReviewHelper::invalidate_cache($this->other_vehicle_id);
    }

    public function tearDown(): void
    {
        VerifiedReviewHelper::invalidate_cache($this->vehicle_id);
        VerifiedReviewHelper::invalidate_cache($this->other_vehicle_id);
        parent::tearDown();
    }

    /**
     * Helper: Create a booking for a user and vehicle
     */
    private function create_booking(int $vehicle_id, int $user_id, string $status = 'completed', string $email = ''): int
    {
        $booking_id = $this->factory->post->create(array(
            'post_type'   => 'vehicle_booking',
            'post_status' => 'publish',
            'post_title'  => 'Test Booking',
        ));

        update_post_meta($booking_id, '_mhm_vehicle_id', $vehicle_id);
        update_post_meta($booking_id, '_mhm_status', $status);
        update_post_meta($booking_id, '_mhm_customer_user_id', $user_id);

        if (! empty($email)) {
            update_post_meta($booking_id, '_mhm_contact_email', $email);
        }

        return $booking_id;
    }

    /**
     * Helper: Create a review comment
     */
    private function create_review(int $vehicle_id, int $user_id, int $rating = 5, string $email = ''): int
    {
        $comment_id = wp_insert_comment(array(
            'comment_post_ID' => $vehicle_id,
            'user_id'         => $user_id,
            'comment_content' => 'Great vehicle!',
            'comment_approved' => 1,
            'comment_type'    => 'review',
            'comment_author_email' => $email ?: get_userdata($user_id)->user_email,
            'comment_author'  => 'Test User',
        ));

        add_comment_meta($comment_id, 'mhm_rating', $rating);

        return $comment_id;
    }

    /**
     * Test: User with valid completed booking → verified
     */
    public function test_verified_with_valid_booking(): void
    {
        // Create booking for user + vehicle
        $this->create_booking($this->vehicle_id, $this->user_id, Status::COMPLETED, 'customer@test.com');

        // Create review
        $comment_id = $this->create_review($this->vehicle_id, $this->user_id);

        // Assert verified
        $this->assertTrue(
            VerifiedReviewHelper::is_verified($comment_id, $this->vehicle_id, $this->user_id),
            'Review should be verified when user has a completed booking for this vehicle'
        );
    }

    /**
     * Test: User with confirmed booking → verified
     */
    public function test_verified_with_confirmed_booking(): void
    {
        $this->create_booking($this->vehicle_id, $this->user_id, Status::CONFIRMED, 'customer@test.com');
        $comment_id = $this->create_review($this->vehicle_id, $this->user_id);

        $this->assertTrue(
            VerifiedReviewHelper::is_verified($comment_id, $this->vehicle_id, $this->user_id),
            'Review should be verified when user has a confirmed booking'
        );
    }

    /**
     * Test: User with in_progress booking → verified
     */
    public function test_verified_with_in_progress_booking(): void
    {
        $this->create_booking($this->vehicle_id, $this->user_id, Status::IN_PROGRESS, 'customer@test.com');
        $comment_id = $this->create_review($this->vehicle_id, $this->user_id);

        $this->assertTrue(
            VerifiedReviewHelper::is_verified($comment_id, $this->vehicle_id, $this->user_id),
            'Review should be verified when user has an in_progress booking'
        );
    }

    /**
     * Test: User without any booking → NOT verified
     */
    public function test_not_verified_without_booking(): void
    {
        $comment_id = $this->create_review($this->vehicle_id, $this->user_id);

        $this->assertFalse(
            VerifiedReviewHelper::is_verified($comment_id, $this->vehicle_id, $this->user_id),
            'Review should NOT be verified when user has no booking'
        );
    }

    /**
     * Test: User has booking for DIFFERENT vehicle → NOT verified
     */
    public function test_not_verified_vehicle_mismatch(): void
    {
        // Booking for OTHER vehicle
        $this->create_booking($this->other_vehicle_id, $this->user_id, Status::COMPLETED, 'customer@test.com');

        // Review on THIS vehicle
        $comment_id = $this->create_review($this->vehicle_id, $this->user_id);

        $this->assertFalse(
            VerifiedReviewHelper::is_verified($comment_id, $this->vehicle_id, $this->user_id),
            'Review should NOT be verified when booking is for a different vehicle'
        );
    }

    /**
     * Test: Different user has booking → NOT verified for this reviewer
     */
    public function test_not_verified_user_mismatch(): void
    {
        // Booking for OTHER user
        $this->create_booking($this->vehicle_id, $this->other_user_id, Status::COMPLETED, 'other@test.com');

        // Review by THIS user
        $comment_id = $this->create_review($this->vehicle_id, $this->user_id);

        $this->assertFalse(
            VerifiedReviewHelper::is_verified($comment_id, $this->vehicle_id, $this->user_id),
            'Review should NOT be verified when booking belongs to a different user'
        );
    }

    /**
     * Test: Booking with cancelled status → NOT verified
     */
    public function test_not_verified_invalid_status(): void
    {
        $this->create_booking($this->vehicle_id, $this->user_id, Status::CANCELLED, 'customer@test.com');
        $comment_id = $this->create_review($this->vehicle_id, $this->user_id);

        $this->assertFalse(
            VerifiedReviewHelper::is_verified($comment_id, $this->vehicle_id, $this->user_id),
            'Review should NOT be verified when booking is cancelled'
        );
    }

    /**
     * Test: Booking with pending status → NOT verified
     */
    public function test_not_verified_pending_status(): void
    {
        $this->create_booking($this->vehicle_id, $this->user_id, Status::PENDING, 'customer@test.com');
        $comment_id = $this->create_review($this->vehicle_id, $this->user_id);

        $this->assertFalse(
            VerifiedReviewHelper::is_verified($comment_id, $this->vehicle_id, $this->user_id),
            'Review should NOT be verified when booking status is pending'
        );
    }

    /**
     * Test: Admin override via comment meta → always verified
     */
    public function test_admin_override(): void
    {
        $comment_id = $this->create_review($this->vehicle_id, $this->user_id);

        // No booking exists, but admin overrides
        update_comment_meta($comment_id, 'mhm_verified_review', '1');

        $this->assertTrue(
            VerifiedReviewHelper::is_verified($comment_id, $this->vehicle_id, $this->user_id),
            'Review should be verified when admin override meta is set'
        );
    }

    /**
     * Test: Batch method returns correct IDs (no N+1)
     */
    public function test_batch_method_returns_correct_ids(): void
    {
        // Create 2 reviews: one verified, one not
        $this->create_booking($this->vehicle_id, $this->user_id, Status::COMPLETED, 'customer@test.com');

        $verified_comment   = $this->create_review($this->vehicle_id, $this->user_id);
        $unverified_comment = $this->create_review($this->vehicle_id, $this->other_user_id);

        $verified_ids = VerifiedReviewHelper::get_verified_comment_ids_for_vehicle($this->vehicle_id);

        $this->assertContains($verified_comment, $verified_ids, 'Batch should include verified comment');
        $this->assertNotContains($unverified_comment, $verified_ids, 'Batch should NOT include unverified comment');
    }

    /**
     * Test: Cache invalidation works
     */
    public function test_cache_invalidation(): void
    {
        // First call: no bookings → no verified IDs
        $comment_id = $this->create_review($this->vehicle_id, $this->user_id);
        $result1    = VerifiedReviewHelper::get_verified_comment_ids_for_vehicle($this->vehicle_id);
        $this->assertEmpty($result1, 'Should return empty when no booking exists');

        // Create booking now
        $this->create_booking($this->vehicle_id, $this->user_id, Status::COMPLETED, 'customer@test.com');

        // Without invalidation, cache still returns empty
        $result2 = VerifiedReviewHelper::get_verified_comment_ids_for_vehicle($this->vehicle_id);
        $this->assertEmpty($result2, 'Cached result should still be empty before invalidation');

        // After invalidation, should return the verified ID
        VerifiedReviewHelper::invalidate_cache($this->vehicle_id);
        $result3 = VerifiedReviewHelper::get_verified_comment_ids_for_vehicle($this->vehicle_id);
        $this->assertContains($comment_id, $result3, 'After cache invalidation, verified ID should appear');
    }

    /**
     * Test: Empty vehicle ID returns empty array
     */
    public function test_empty_vehicle_returns_empty(): void
    {
        $this->assertEmpty(
            VerifiedReviewHelper::get_verified_comment_ids_for_vehicle(0),
            'Should return empty array for vehicle_id = 0'
        );
    }
}
