<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Core\Financial\Statement;

use MHMRentiva\Core\Financial\Statement\PayoutStatementRepository;
use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use WP_UnitTestCase;

/**
 * @group financial
 */
final class PayoutStatementRepositoryTest extends WP_UnitTestCase {

	private function sample(int $payout_id, int $vendor_id, int $last_entry_id, string $number): array {
		return array(
			'payout_id' => $payout_id, 'vendor_id' => $vendor_id, 'number' => $number,
			'generated_at' => '2026-06-28 10:00:00', 'currency' => 'TRY',
			'period_start' => '2026-06-01 00:00:00', 'period_end' => '2026-06-28 00:00:00',
			'last_entry_id' => $last_entry_id,
			'lines' => array(array('date'=>'2026-06-10','type'=>'commission_credit','ref'=>5,'description'=>'Booking #5','amount'=>100.0)),
			'gross' => 100.0, 'penalties' => 0.0, 'net_activity' => 100.0,
			'paid' => 80.0, 'carried_balance' => 20.0,
			'vendor_snapshot' => array('name'=>'V','tax_office'=>'O','tax_number'=>'1','account_holder'=>'V','iban'=>'TR00'),
		);
	}

	private function make_payout(int $vendor_id): int {
		// post_status 'pending' so the live transition_post_status hook does not auto-generate
		// a statement on publish — these tests drive the repository explicitly.
		return (int) $this->factory->post->create(array('post_type' => PostType::POST_TYPE, 'post_author' => $vendor_id, 'post_status' => 'pending'));
	}

	public function test_save_then_get_roundtrips(): void {
		$vendor = (int) $this->factory->user->create();
		$pid = $this->make_payout($vendor);
		PayoutStatementRepository::save($pid, $this->sample($pid, $vendor, 12, 'MKB-2026-0001'));

		$this->assertTrue(PayoutStatementRepository::exists($pid));
		$got = PayoutStatementRepository::get($pid);
		$this->assertSame('MKB-2026-0001', $got['number']);
		$this->assertSame(80.0, $got['paid']);
		$this->assertCount(1, $got['lines']);
		$this->assertSame('TR00', $got['vendor_snapshot']['iban']);
	}

	public function test_save_is_immutable(): void {
		$vendor = (int) $this->factory->user->create();
		$pid = $this->make_payout($vendor);
		PayoutStatementRepository::save($pid, $this->sample($pid, $vendor, 12, 'MKB-2026-0001'));
		PayoutStatementRepository::save($pid, $this->sample($pid, $vendor, 99, 'MKB-2026-9999'));

		$got = PayoutStatementRepository::get($pid);
		$this->assertSame('MKB-2026-0001', $got['number'], 'Existing statement must not be overwritten.');
	}

	public function test_previous_last_entry_id_returns_max_excluding_self(): void {
		$vendor = (int) $this->factory->user->create();
		$p1 = $this->make_payout($vendor);
		$p2 = $this->make_payout($vendor);
		PayoutStatementRepository::save($p1, $this->sample($p1, $vendor, 10, 'MKB-2026-0001'));
		PayoutStatementRepository::save($p2, $this->sample($p2, $vendor, 25, 'MKB-2026-0002'));

		$this->assertSame(10, PayoutStatementRepository::previous_last_entry_id($vendor, $p2));
		$this->assertSame(0, PayoutStatementRepository::previous_last_entry_id($vendor + 12345, $p2));
	}
}
