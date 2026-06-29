<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Core\Financial;

use MHMRentiva\Core\Financial\PayoutHistoryProvider;
use MHMRentiva\Core\Financial\Statement\PayoutStatementRepository;
use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use WP_UnitTestCase;

/**
 * @group financial
 */
final class PayoutHistoryStatementTest extends WP_UnitTestCase {

	public function test_row_includes_statement_number_and_url_when_present(): void {
		$vendor = (int) $this->factory->user->create();
		// Use 'pending' so the transition_post_status hook does not fire and
		// auto-generate a statement before our explicit save below.
		$pid = (int) $this->factory->post->create(array('post_type' => PostType::POST_TYPE, 'post_author' => $vendor, 'post_status' => 'pending'));
		update_post_meta($pid, '_mhm_payout_amount', 100.0);
		PayoutStatementRepository::save($pid, array(
			'number'=>'MKB-2026-0005','generated_at'=>'2026-06-28 10:00:00','currency'=>'TRY',
			'period_start'=>'','period_end'=>'','last_entry_id'=>0,'lines'=>array(),
			'gross'=>0.0,'penalties'=>0.0,'net_activity'=>0.0,'paid'=>100.0,'carried_balance'=>0.0,
			'vendor_snapshot'=>array('name'=>'V','tax_office'=>'','tax_number'=>'','account_holder'=>'','iban'=>''),
		));

		$rows = PayoutHistoryProvider::get_for_vendor($vendor);

		$this->assertSame('MKB-2026-0005', $rows[0]['statement_number']);
		$this->assertStringContainsString('action=mhm_rentiva_view_statement', $rows[0]['statement_url']);
	}

	public function test_row_has_empty_statement_fields_when_absent(): void {
		$vendor = (int) $this->factory->user->create();
		$this->factory->post->create(array('post_type' => PostType::POST_TYPE, 'post_author' => $vendor, 'post_status' => 'pending'));
		$rows = PayoutHistoryProvider::get_for_vendor($vendor);
		$this->assertSame('', $rows[0]['statement_number']);
		$this->assertSame('', $rows[0]['statement_url']);
	}
}
