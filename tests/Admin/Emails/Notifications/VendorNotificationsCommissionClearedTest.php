<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails\Notifications;

use MHMRentiva\Admin\Emails\Notifications\VendorNotifications;
use WP_UnitTestCase;

class VendorNotificationsCommissionClearedTest extends WP_UnitTestCase
{
    public function test_on_commission_cleared_sends_email_with_expected_context(): void
    {
        $vendor_id = self::factory()->user->create(array(
            'role'       => 'mhm_rentiva_vendor',
            'user_email' => 'vendor-cleared-test@example.com',
        ));

        $captured = null;
        add_action('mhm_rentiva_email_sent', function ($key, $to, $ok, $subject, $context) use (&$captured) {
            if ($key === 'commission_cleared') {
                $captured = array(
                    'to'      => $to,
                    'context' => $context,
                );
            }
        }, 10, 5);

        VendorNotifications::on_commission_cleared($vendor_id, 85.0, 'EUR', 11, 22);

        $this->assertNotNull($captured, 'commission_cleared email must be sent.');
        $this->assertSame('vendor-cleared-test@example.com', $captured['to']);
        $this->assertSame(85.0, $captured['context']['commission']['amount']);
        $this->assertSame(11, $captured['context']['commission']['booking_id']);
    }

    public function test_on_commission_cleared_skips_unknown_vendor(): void
    {
        $fired = false;
        add_action('mhm_rentiva_email_sent', function ($key) use (&$fired) {
            if ($key === 'commission_cleared') {
                $fired = true;
            }
        });

        VendorNotifications::on_commission_cleared(999999, 85.0, 'EUR', 11, 22);

        $this->assertFalse($fired, 'Unknown vendor must not trigger an email attempt.');
    }
}
