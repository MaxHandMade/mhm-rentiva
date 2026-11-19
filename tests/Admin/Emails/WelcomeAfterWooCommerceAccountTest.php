<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use MHMRentiva\Admin\Emails\Notifications\BookingNotifications;
use WP_UnitTestCase;

/**
 * A guest who creates an account at checkout got two welcome e-mails:
 * WooCommerce's "Your account has been created" (with the set-password link)
 * and Rentiva's own "Welcome to ...". Product decision 2026-11-18: when the
 * account was opened by WooCommerce, Rentiva's welcome is skipped -- unless
 * the shop switched WooCommerce's new-account e-mail off, in which case
 * Rentiva's is the only welcome the customer would get.
 *
 * @covers \MHMRentiva\Admin\Emails\Notifications\BookingNotifications
 */
final class WelcomeAfterWooCommerceAccountTest extends WP_UnitTestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $mails = array();

    public function setUp(): void
    {
        parent::setUp();
        if ( ! function_exists( 'WC' ) ) {
            $this->markTestSkipped( 'WooCommerce is not loaded in this run.' );
        }
        $this->mails = array();
        add_filter(
            'pre_wp_mail',
            function ( $short, array $atts ) {
                $this->mails[] = $atts;
                return true;
            },
            10,
            2
        );
    }

    private function customerWithBooking( string $email ): array
    {
        $user_id = self::factory()->user->create( array( 'role' => 'customer', 'user_email' => $email ) );
        $booking = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
        update_post_meta( $booking, '_mhmrentiva_customer_email', $email );
        update_post_meta( $booking, '_mhmrentiva_customer_first_name', 'Emma' );
        update_post_meta( $booking, '_mhmrentiva_customer_last_name', 'Thompson' );
        update_post_meta( $booking, '_mhmrentiva_customer_user_id', $user_id );
        return array( $user_id, $booking );
    }

    private function sendWelcome( int $booking_id ): void
    {
        $method = new \ReflectionMethod( BookingNotifications::class, 'send_welcome_if_applicable' );
        $method->setAccessible( true );
        $method->invoke( null, $booking_id );
    }

    private function welcomeMailsTo( string $email ): int
    {
        return count(
            array_filter(
                $this->mails,
                static fn( array $m ) => in_array( $email, (array) $m['to'], true ) || $m['to'] === $email
            )
        );
    }

    public function test_the_hook_is_registered(): void
    {
        remove_all_actions( 'woocommerce_created_customer' );
        BookingNotifications::register();
        $this->assertNotFalse( has_action( 'woocommerce_created_customer', array( BookingNotifications::class, 'skip_welcome_for_woocommerce_account' ) ) );
    }

    public function test_no_rentiva_welcome_after_woocommerce_opened_the_account(): void
    {
        list( $user_id, $booking ) = $this->customerWithBooking( 'emma.thompson@example.com' );

        BookingNotifications::skip_welcome_for_woocommerce_account( $user_id );
        $this->sendWelcome( $booking );

        $this->assertSame( 0, $this->welcomeMailsTo( 'emma.thompson@example.com' ) );
    }

    public function test_rentiva_welcome_still_goes_when_woocommerce_new_account_mail_is_off(): void
    {
        add_filter( 'woocommerce_email_enabled_customer_new_account', '__return_false' );
        list( $user_id, $booking ) = $this->customerWithBooking( 'lucas.martin@example.com' );

        BookingNotifications::skip_welcome_for_woocommerce_account( $user_id );
        $this->sendWelcome( $booking );

        remove_filter( 'woocommerce_email_enabled_customer_new_account', '__return_false' );
        $this->assertSame( 1, $this->welcomeMailsTo( 'lucas.martin@example.com' ) );
    }

    public function test_a_customer_whose_account_woocommerce_did_not_open_is_welcomed(): void
    {
        list( , $booking ) = $this->customerWithBooking( 'sofia.rossi@example.com' );

        $this->sendWelcome( $booking );

        $this->assertSame( 1, $this->welcomeMailsTo( 'sofia.rossi@example.com' ) );
    }
}
