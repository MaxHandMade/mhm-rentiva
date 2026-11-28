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

	public function test_a_whole_total_still_returns_floats(): void
	{
		$card    = DepositCalculator::calculate_deposit('10%', 100.0, 1);
		$none    = DepositCalculator::calculate_deposit('0', 100.0, 1);

		$this->assertSame(10.0, $card['deposit_amount']);
		$this->assertSame(90.0, $card['remaining_amount']);
		$this->assertSame(100.0, $card['total_amount']);
		$this->assertSame(100.0, $none['remaining_amount']);
	}

	public function test_a_whole_unit_store_splits_at_its_own_precision(): void
	{
		$previous = get_option('woocommerce_price_num_decimals');
		update_option('woocommerce_price_num_decimals', '0');

		try {
			$split = DepositCalculator::split_total(105.0, 10.0);
		} finally {
			update_option('woocommerce_price_num_decimals', $previous);
		}

		// 10.5 is not a price such a store can charge: the deposit is a whole
		// unit and the remaining amount is the rest, still adding up to 105.
		$this->assertSame(11.0, $split['deposit']);
		$this->assertSame(94.0, $split['remaining']);
		$this->assertSame(105.0, $split['total']);
	}

	private function cents(float $amount): int
	{
		return (int) round($amount * 100);
	}
}
