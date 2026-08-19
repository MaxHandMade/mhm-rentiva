<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Emails\Core\EmailTemplates;
use MHMRentiva\Admin\Emails\Core\Templates;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
use WP_UnitTestCase;

/**
 * Money must reach a template as a NUMBER, and a template must survive being
 * handed a formatted one.
 *
 * The defect these tests pin down: `EmailTemplates::build_context()` put a
 * `number_format_i18n()` STRING under `booking.total_price`, and the templates
 * cast that string with `(float)`. `(float) "1.500,00"` is `1.5`, so a
 * customer-facing mail rendered the total 1000x too small — silently, because
 * no error is raised. The same confusion made the live preview / test-send path
 * print the total with no currency symbol at all, because its guard was
 * `is_numeric()` and a formatted string is not numeric.
 *
 * @covers \MHMRentiva\Admin\Core\CurrencyHelper::to_amount
 * @covers \MHMRentiva\Admin\Emails\Core\EmailTemplates::build_context
 * @covers \MHMRentiva\Admin\Emails\Core\Templates::replace_placeholders
 */
final class EmailAmountIntegrityTest extends WP_UnitTestCase
{
    use WooCommerceOptionSandbox;

    private int $booking_id = 0;

    public function setUp(): void
    {
        parent::setUp();

        $this->booking_id = self::factory()->post->create(
            array(
                'post_type'   => 'mhmrentiva_booking',
                'post_status' => 'publish',
                'post_title'  => 'Amount integrity fixture',
            )
        );

        // 150000 kuruş = 1500.00 in the store currency.
        update_post_meta($this->booking_id, '_mhmrentiva_payment_amount', 150000);
        update_post_meta($this->booking_id, '_mhmrentiva_payment_currency', 'USD');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', '1500.00');
    }

    public function tearDown(): void
    {
        $this->restore_sandboxed_options();
        parent::tearDown();
    }

    /**
     * The data end. The context must carry a number, so no consumer can be
     * handed a formatted string it might cast.
     */
    public function test_the_email_context_carries_a_numeric_total_price(): void
    {
        $ctx = EmailTemplates::build_context('booking_created_customer', $this->booking_id);

        $this->assertIsNumeric(
            $ctx['booking']['total_price'],
            'booking.total_price must be numeric; a formatted string here casts to 1.5.'
        );
        $this->assertEqualsWithDelta(1500.0, (float) $ctx['booking']['total_price'], 0.001);
    }

    /**
     * The exact 1000x error, stated as a value: the cast the sweep added.
     */
    public function test_a_formatted_amount_is_never_read_as_a_thousandth_of_itself(): void
    {
        $formatted = number_format_i18n(1500.0, 2);

        // The bug, reproduced so the regression is unambiguous. The exact wrong
        // value depends on the locale's separators (1.5 under tr_TR, 1.0 under
        // en_US); what matters is that the cast loses three orders of magnitude.
        $this->assertLessThan(
            10.0,
            (float) $formatted,
            'Precondition: a bare (float) cast on a formatted amount really does collapse it.'
        );

        $this->assertEqualsWithDelta(
            1500.0,
            CurrencyHelper::to_amount($formatted),
            0.001,
            'to_amount() must recover the real number from a formatted one.'
        );
    }

    /**
     * @dataProvider provideAmountShapes
     *
     * @param mixed $input Raw value a producer might hand to a display surface.
     */
    public function test_to_amount_accepts_every_shape_money_arrives_in($input, float $expected): void
    {
        $this->assertEqualsWithDelta(
            $expected,
            CurrencyHelper::to_amount($input),
            0.001,
            sprintf('to_amount(%s)', var_export($input, true))
        );
    }

    /**
     * @return array<string, array{0: mixed, 1: float}>
     */
    public static function provideAmountShapes(): array
    {
        return array(
            'int'                 => array(1500, 1500.0),
            'float'               => array(1500.5, 1500.5),
            'raw meta string'     => array('1500.00', 1500.0),
            'grouped tr string'   => array('1.500,00', 1500.0),
            'grouped no decimals' => array('1.500', 1500.0),
            'grouped en style'    => array('1,500.00', 1500.0),
            'grouped en integer'  => array('1,500', 1500.0),
            'space grouped'       => array('1 500,00', 1500.0),
            'negative'            => array('-1.500,00', -1500.0),
            'with symbol'         => array("\u{20BA}1.500,00", 1500.0),
            'empty'               => array('', 0.0),
            'non numeric'         => array('N/A', 0.0),
            'null'                => array(null, 0.0),
        );
    }

    /**
     * The live consequence the reviewer measured: the preview / test-send path
     * only formatted `total_price` when `is_numeric()` said so, so a formatted
     * string rendered with NO currency symbol at all.
     */
    public function test_the_compiled_total_price_always_carries_a_currency(): void
    {
        $this->sandbox_option('woocommerce_currency_pos', 'left');

        $ctx      = EmailTemplates::build_context('booking_created_customer', $this->booking_id);
        $expected = CurrencyHelper::format_price(1500.0, 2);

        $this->assertSame(
            'Total: ' . $expected,
            Templates::replace_placeholders('Total: {total_price}', $ctx),
            'The compiled body must show the canonical, symbol-carrying amount.'
        );

        // And the net holds even if some producer formats the value early.
        $legacy                             = $ctx;
        $legacy['booking']['total_price']   = number_format_i18n(1500.0, 2);

        $this->assertSame(
            'Total: ' . $expected,
            Templates::replace_placeholders('Total: {total_price}', $legacy),
            'A formatted value must be repaired, not printed bare without a symbol.'
        );
    }

    /**
     * An absent amount must stay absent — an email may not invent a `0,00`.
     */
    public function test_an_absent_total_price_stays_empty(): void
    {
        $ctx = array( 'booking' => array( 'total_price' => '' ) );

        $this->assertSame('Total: ', Templates::replace_placeholders('Total: {total_price}', $ctx));
    }

    /**
     * Render-level proof: each template the sweep touched, rendered with the
     * context shape `EmailAjaxHandler::handle_preview_email()` builds, must show
     * the real total.
     *
     * @dataProvider provideAffectedTemplates
     */
    public function test_each_affected_template_renders_the_real_amount(string $template): void
    {
        $this->sandbox_option('woocommerce_currency_pos', 'left');

        $ctx                                = EmailTemplates::build_context('booking_created_customer', $this->booking_id);
        $ctx['booking']['payment_type']     = 'deposit';
        $ctx['booking']['deposit_amount']   = 500.0;
        $ctx['booking']['remaining_amount'] = 1000.0;

        $rendered = $this->renderTemplate($template, $ctx);
        $expected = CurrencyHelper::format_price(1500.0, 2);

        $this->assertStringContainsString(
            $expected,
            $rendered,
            sprintf('%s must render the real total (%s), not a thousandth of it.', $template, $expected)
        );
        $this->assertStringNotContainsString(
            CurrencyHelper::format_price(1.5, 2),
            $rendered,
            sprintf('%s still renders the 1000x-wrong figure.', $template)
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function provideAffectedTemplates(): array
    {
        return array(
            'booking-created-customer'           => array('booking-created-customer'),
            'booking-created-admin'              => array('booking-created-admin'),
            'offline-verified-approved-customer' => array('offline-verified-approved-customer'),
        );
    }

    /**
     * @param array<string, mixed> $ctx
     */
    private function renderTemplate(string $slug, array $ctx): string
    {
        $path = MHMRENTIVA_PLUGIN_PATH . 'templates/emails/' . $slug . '.html.php';
        $this->assertFileExists($path);

        // The names the templates expect in scope, exactly as render_body() sets them.
        $data          = $ctx;
        $booking_id    = $this->booking_id;
        $customer_name = 'John Doe';
        $vehicle_name  = 'Fixture Vehicle';
        $pickup_date   = '01.09.2026';
        $dropoff_date  = '04.09.2026';
        $reason        = '';

        ob_start();
        include $path;

        return (string) ob_get_clean();
    }
}
