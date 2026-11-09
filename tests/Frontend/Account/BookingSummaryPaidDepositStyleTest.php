<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use WP_UnitTestCase;

/**
 * The printable booking summary shows a paid deposit as money received and
 * puts the weight on "Due at pickup" (canvas Rezervasyon-Ozeti, Ç5 approved
 * 2026-11-09). rvs-breakdown.css draws the deposit as the amount to pay now --
 * right on the booking form, wrong once it is paid. The booking detail page
 * corrects it in rvs-account.css, which the summary does not load, so the
 * summary showed the paid deposit in the blue "pay now" box.
 *
 * @coversNothing
 */
final class BookingSummaryPaidDepositStyleTest extends WP_UnitTestCase
{
	private function css(): string
	{
		return (string) file_get_contents(dirname(__DIR__, 3) . '/assets/css/frontend/rvs-summary.css');
	}

	public function test_a_paid_deposit_is_drawn_as_received(): void
	{
		$this->assertMatchesRegularExpression(
			'/\.rvs-summary__money \.rvs-breakdown__deposit--paid\s*\{[^}]*background:\s*transparent;[^}]*color:\s*var\(--rvs-st-done-fg\);/s',
			$this->css()
		);
	}

	public function test_due_at_pickup_is_emphasised_only_when_the_deposit_is_paid(): void
	{
		$this->assertMatchesRegularExpression(
			'/\.rvs-summary__money:has\(\.rvs-breakdown__deposit--paid\) \.rvs-breakdown__pickup\s*\{[^}]*background:\s*var\(--rvs-accent-100\);/s',
			$this->css()
		);
	}
}
