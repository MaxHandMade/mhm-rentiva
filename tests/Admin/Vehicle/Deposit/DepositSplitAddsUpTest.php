<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\Deposit;

use MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator;
use WP_UnitTestCase;

/**
 * The deposit and the remaining amount are two halves of one total: what the
 * customer pays now plus what they pay later must equal what the booking costs,
 * to the cent. Rounding each half on its own breaks that on a total with cents
 * (100.05 at 10% gave 10.01 + 90.05 = 100.06).
 *
 * @covers \MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator
 */
final class DepositSplitAddsUpTest extends WP_UnitTestCase
{
	/**
	 * @return array<string, array{0: float, 1: string}>
	 */
	public static function totals(): array
	{
		return array(
			'100.05 at 10%' => array( 100.05, '10%' ),
			'60.15 at 10%'  => array( 60.15, '10%' ),
			'99.99 at 33%'  => array( 99.99, '33%' ),
			'1234.55 at 25%' => array( 1234.55, '25%' ),
			'0.05 at 10%'   => array( 0.05, '10%' ),
		);
	}

	/**
	 * @dataProvider totals
	 */
	public function test_the_vehicle_card_split_adds_up_to_the_total(float $total, string $rate): void
	{
		$split = DepositCalculator::calculate_deposit($rate, $total, 1);

		$this->assertSame(
			$this->cents((float) $split['total_amount']),
			$this->cents((float) $split['deposit_amount']) + $this->cents((float) $split['remaining_amount']),
			'deposit + remaining must equal the total'
		);
	}

	/**
	 * @dataProvider totals
	 */
	public function test_the_booking_split_adds_up_to_the_total(float $daily_price, string $rate): void
	{
		$vehicle_id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
		update_post_meta($vehicle_id, '_mhmrentiva_price_per_day', (string) $daily_price);
		update_post_meta($vehicle_id, '_mhmrentiva_deposit', rtrim($rate, '%'));

		$split = DepositCalculator::calculate_booking_deposit($vehicle_id, 1, 'deposit');

		$this->assertTrue($split['success']);
		$this->assertSame(
			$this->cents((float) $split['total_amount']),
			$this->cents((float) $split['deposit_amount']) + $this->cents((float) $split['remaining_amount']),
			'deposit + remaining must equal the total'
		);
	}

	public function test_the_remaining_amount_is_the_rest_of_the_total_not_its_own_rounding(): void
	{
		$split = DepositCalculator::calculate_deposit('10%', 100.05, 1);

		$this->assertSame(10.01, (float) $split['deposit_amount']);
		$this->assertSame(90.04, (float) $split['remaining_amount']);
	}

	private function cents(float $amount): int
	{
		return (int) round($amount * 100);
	}
}
