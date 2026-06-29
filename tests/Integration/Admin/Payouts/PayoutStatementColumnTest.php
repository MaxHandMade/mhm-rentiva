<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Payouts;

use MHMRentiva\Admin\PostTypes\Payouts\PayoutListTable;
use MHMRentiva\Core\Financial\Statement\PayoutStatementRepository;
use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use WP_UnitTestCase;

/**
 * @group payouts
 */
final class PayoutStatementColumnTest extends WP_UnitTestCase {

	public function test_dash_when_no_statement(): void {
		$pid = (int) $this->factory->post->create(array('post_type' => PostType::POST_TYPE, 'post_status' => 'pending'));
		$this->assertStringContainsString('—', PayoutListTable::statement_column_html($pid));
	}

	public function test_shows_number_and_view_link_when_present(): void {
		$pid = (int) $this->factory->post->create(array('post_type' => PostType::POST_TYPE, 'post_status' => 'pending'));
		PayoutStatementRepository::save($pid, array(
			'number'=>'MKB-2026-0042','generated_at'=>'2026-06-28 10:00:00','currency'=>'TRY',
			'period_start'=>'','period_end'=>'','last_entry_id'=>0,'lines'=>array(),
			'gross'=>0.0,'penalties'=>0.0,'net_activity'=>0.0,'paid'=>10.0,'carried_balance'=>0.0,
			'vendor_snapshot'=>array('name'=>'V','tax_office'=>'','tax_number'=>'','account_holder'=>'','iban'=>''),
		));
		$html = PayoutListTable::statement_column_html($pid);
		$this->assertStringContainsString('MKB-2026-0042', $html);
		$this->assertStringContainsString('action=mhm_rentiva_view_statement', $html);
	}
}
