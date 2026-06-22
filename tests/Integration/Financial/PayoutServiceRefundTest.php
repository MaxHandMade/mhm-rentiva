<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Financial;

use MHMRentiva\Core\Financial\PayoutService;
use MHMRentiva\Core\Tenancy\TenantContext;
use MHMRentiva\Core\Tenancy\TenantResolver;

class PayoutServiceRefundTest extends \WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pin the TenantResolver to tenant_id=1 so the resolved context is deterministic.
        TenantResolver::set_context(new TenantContext(1, 'tenant_1', get_locale(), 'pro'));
    }

    protected function tearDown(): void
    {
        TenantResolver::reset();
        parent::tearDown();
    }

    public function test_returns_error_for_zero_amount(): void
    {
        $result = PayoutService::create_refund_entry(1, 0, 0.0);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('invalid_amount', $result->get_error_code());
    }

    public function test_returns_error_for_negative_amount(): void
    {
        $result = PayoutService::create_refund_entry(1, 0, -50.0);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('invalid_amount', $result->get_error_code());
    }

    public function test_returns_true_for_valid_refund(): void
    {
        $result = PayoutService::create_refund_entry(1, 0, 150.0);
        $this->assertTrue($result);
    }
}
