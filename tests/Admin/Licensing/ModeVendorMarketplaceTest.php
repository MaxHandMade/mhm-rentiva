<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\Licensing;

use MHMRentiva\Admin\Licensing\Mode;
use PHPUnit\Framework\TestCase;

class ModeVendorMarketplaceTest extends TestCase
{
    public function test_canUseVendorMarketplace_returns_bool(): void
    {
        $result = Mode::canUseVendorMarketplace();
        $this->assertIsBool($result);
    }
}
