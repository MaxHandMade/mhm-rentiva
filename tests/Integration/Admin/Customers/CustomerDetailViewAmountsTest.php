<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Customers;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Customers\CustomerIdentity;
use MHMRentiva\Admin\Customers\CustomersOptimizer;
use MHMRentiva\Admin\Customers\CustomersPage;
use MHMRentiva\Tests\Support\UserManagementCapabilities;
use WP_UnitTestCase;

/**
 * The Customer Details screen prints each booking amount exactly as the
 * payload formats it.
 *
 * `amount` has carried the canonical symbol and placement since the currency
 * sweep, but the booking rows still prepended `currency` to it, so the screen
 * read "$$6.185,00" -- the stat line above had been fixed, this one was missed.
 *
 * @covers \MHMRentiva\Admin\Customers\CustomersPage
 */
final class CustomerDetailViewAmountsTest extends WP_UnitTestCase
{
    use UserManagementCapabilities;

    public function setUp(): void
    {
        parent::setUp();
        CustomerIdentity::flush_memo();
        CustomersOptimizer::clear_cache();
        $actor_id = (int) self::factory()->user->create(array('role' => 'administrator'));
        $this->grant_user_management_privilege($actor_id);
        wp_set_current_user($actor_id);
    }

    public function tearDown(): void
    {
        unset($_GET['customer_id']);
        wp_set_current_user(0);
        CustomersOptimizer::clear_cache();
        parent::tearDown();
    }

    private function renderView(int $customer_id): string
    {
        $_GET['customer_id'] = (string) $customer_id;
        $ref = new \ReflectionMethod(CustomersPage::class, 'render_customer_view');
        $ref->setAccessible(true);

        ob_start();
        $ref->invoke(new CustomersPage());

        return (string) ob_get_clean();
    }

    private function makeBooking(int $user, float $price, string $status): void
    {
        $booking = (int) self::factory()->post->create(
            array(
                'post_type'   => 'mhmrentiva_booking',
                'post_status' => 'publish',
            )
        );
        update_post_meta($booking, '_mhmrentiva_customer_user_id', $user);
        update_post_meta($booking, '_mhmrentiva_total_price', (string) $price);
        update_post_meta($booking, '_mhmrentiva_status', $status);
    }

    public function test_a_booking_amount_carries_one_currency_symbol(): void
    {
        $customer = (int) self::factory()->user->create(array('role' => 'customer'));
        $this->makeBooking($customer, 6185.0, Status::CONFIRMED);

        $html   = $this->renderView($customer);
        $amount = esc_html(CurrencyHelper::format_price(6185.0, 2));
        $symbol = esc_html(CurrencyHelper::get_currency_symbol());

        $this->assertStringContainsString('<span class="rv-cust-panel__booking-amount">' . $amount . '</span>', $html);
        $this->assertStringNotContainsString($symbol . $amount, $html, 'The symbol is already inside the formatted amount.');
    }

    public function test_a_booking_that_is_not_counted_says_why(): void
    {
        $customer = (int) self::factory()->user->create(array('role' => 'customer'));
        $this->makeBooking($customer, 6185.0, Status::CANCELLED);

        $html = $this->renderView($customer);

        $this->assertStringContainsString(esc_html(Status::get_label(Status::CANCELLED)), $html);
    }
}
