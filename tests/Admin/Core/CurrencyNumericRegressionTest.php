<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Booking\ListTable\BookingColumns;
use MHMRentiva\Admin\Emails\Core\EmailTemplates;
use MHMRentiva\Admin\Emails\Core\Templates;
use WP_UnitTestCase;

/**
 * OLD numeric vs NEW numeric — the check the parity suite was missing.
 *
 * Every existing currency test asserts a surface against `CurrencyHelper`. That
 * is exactly why the 1000x email defect slipped through: all surfaces agreed
 * with the helper while the helper was being handed a corrupted number, so the
 * whole suite stayed green while the printed amount was wrong.
 *
 * These tests never consult the helper for the expected VALUE. They stage a
 * known amount in the database, render the surface, parse the digits back out
 * with a parser local to this file, and require that the number printed today is
 * the number that surface printed before the currency sweep — the amount that
 * was stored. Placement and symbol are the parity suite's business; the NUMBER
 * is this one's.
 *
 * @covers \MHMRentiva\Admin\Booking\ListTable\BookingColumns
 * @covers \MHMRentiva\Admin\Emails\Core\EmailTemplates
 * @covers \MHMRentiva\Admin\Emails\Notifications\RefundNotifications
 */
final class CurrencyNumericRegressionTest extends WP_UnitTestCase
{
    private int $booking_id = 0;

    public function setUp(): void
    {
        parent::setUp();

        $this->booking_id = self::factory()->post->create(
            array(
                'post_type'   => 'mhmrentiva_booking',
                'post_status' => 'publish',
            )
        );

        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '1500.00');
        update_post_meta($this->booking_id, '_mhmrentiva_payment_type', 'deposit');
        update_post_meta($this->booking_id, '_mhmrentiva_deposit_amount', '450.00');
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '1050.00');
        update_post_meta($this->booking_id, '_mhmrentiva_payment_amount', 150000);
        update_post_meta($this->booking_id, '_mhmrentiva_payment_currency', 'TRY');
        update_post_meta($this->booking_id, '_mhmrentiva_contact_email', 'numeric@example.test');
        update_post_meta($this->booking_id, '_mhmrentiva_contact_name', 'Numeric Fixture');

        // Placement must not matter to any assertion below. Pick the one that
        // used to be contradicted, so a placement regression cannot hide here.
        update_option('woocommerce_currency_pos', 'left');
    }

    public function tearDown(): void
    {
        delete_option('woocommerce_currency_pos');
        parent::tearDown();
    }

    /**
     * A total: the Bookings list "Total" column, driven from stored meta.
     */
    public function test_the_booking_total_column_prints_the_stored_number(): void
    {
        ob_start();
        BookingColumns::render('mhmrentiva_booking_total', $this->booking_id);
        $rendered = (string) ob_get_clean();

        $this->assertSame(
            1500.0,
            self::digitsOf($rendered),
            sprintf('The total column printed "%s" for a stored 1500.00.', $rendered)
        );
    }

    /**
     * A remaining amount: the same column family, the field a deposit booking
     * still owes.
     */
    public function test_the_remaining_amount_column_prints_the_stored_number(): void
    {
        ob_start();
        BookingColumns::render('mhmrentiva_booking_remaining', $this->booking_id);
        $rendered = (string) ob_get_clean();

        $this->assertSame(
            1050.0,
            self::digitsOf($rendered),
            sprintf('The remaining column printed "%s" for a stored 1050.00.', $rendered)
        );
    }

    /**
     * A deposit amount, from the same stored row.
     */
    public function test_the_deposit_column_prints_the_stored_number(): void
    {
        ob_start();
        BookingColumns::render('mhmrentiva_booking_deposit', $this->booking_id);
        $rendered = (string) ob_get_clean();

        $this->assertSame(450.0, self::digitsOf($rendered));
    }

    /**
     * A refund: the amount the refund mail states, from kuruş in the database.
     */
    public function test_the_refund_notification_states_the_refunded_number(): void
    {
        $bodies = array();
        add_filter(
            'wp_mail',
            static function (array $args) use (&$bodies): array {
                $bodies[] = (string) ( $args['message'] ?? '' );
                return $args;
            }
        );

        \MHMRentiva\Admin\Emails\Notifications\RefundNotifications::notify(
            $this->booking_id,
            75000, // kuruş
            'TRY',
            'partially_refunded'
        );

        $this->assertNotEmpty($bodies, 'The refund notification must actually send a mail.');

        // The refunded figure is the only money in the body that is not the
        // booking total, so assert on the stated amount specifically.
        $stated = null;
        foreach ($bodies as $body) {
            $text = wp_strip_all_tags($body);
            if (1 === preg_match('/(?:[\p{Sc}]\s?)?7[\s.,]?500?[.,]\d{2}(?:\s?[\p{Sc}])?/u', $text, $m)) {
                $stated = $m[0];
                break;
            }
        }

        $this->assertNotNull(
            $stated,
            sprintf('No refunded amount found in the mail body: %s', wp_strip_all_tags($bodies[0]))
        );
        $this->assertSame(
            750.0,
            self::digitsOf($stated),
            sprintf('The refund mail stated "%s" for 75000 kuruş.', $stated)
        );
    }

    /**
     * The email total, end to end: stored kuruş -> context -> compiled body.
     * This is the exact path that printed a thousandth of the real figure.
     */
    public function test_the_compiled_email_total_prints_the_stored_number(): void
    {
        $ctx      = EmailTemplates::build_context('booking_created_customer', $this->booking_id);
        $rendered = Templates::replace_placeholders('{total_price}', $ctx);

        $this->assertSame(
            1500.0,
            self::digitsOf($rendered),
            sprintf('The compiled email printed "%s" for 150000 kuruş.', $rendered)
        );
    }

    /**
     * Read the number back out of a rendered price WITHOUT asking production
     * code. Deliberately hand-rolled: a test that parsed with `to_amount()`
     * would be asserting the formatter against its own inverse.
     */
    private static function digitsOf(string $rendered): float
    {
        $punctuated = (string) preg_replace('/[^0-9.,]/u', '', $rendered);

        // A rendered price carries at most one decimal separator, and it is the
        // last punctuation mark followed by one or two digits.
        if (1 === preg_match('/^(.*)([.,])(\d{1,2})$/', $punctuated, $m)) {
            $whole = (string) preg_replace('/\D/', '', $m[1]);

            return (float) (('' === $whole ? '0' : $whole) . '.' . $m[3]);
        }

        return (float) (string) preg_replace('/\D/', '', $punctuated);
    }
}
