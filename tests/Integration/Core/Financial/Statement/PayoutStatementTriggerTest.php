<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Core\Financial\Statement;

use MHMRentiva\Core\Financial\Statement\PayoutStatementController;
use MHMRentiva\Core\Financial\Statement\PayoutStatementRepository;
use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use WP_UnitTestCase;

/**
 * @group financial
 */
final class PayoutStatementTriggerTest extends WP_UnitTestCase {

	public function test_statement_generated_when_payout_published(): void {
		PayoutStatementController::register();

		$vendor = (int) $this->factory->user->create();
		$pid = (int) $this->factory->post->create(array(
			'post_type' => PostType::POST_TYPE, 'post_author' => $vendor, 'post_status' => 'pending',
		));
		update_post_meta($pid, '_mhm_payout_amount', 100.0);

		wp_update_post(array('ID' => $pid, 'post_status' => 'publish'));

		$this->assertTrue(PayoutStatementRepository::exists($pid), 'Approving (publishing) a payout must generate a statement.');
	}

	public function test_no_statement_for_non_payout_post(): void {
		PayoutStatementController::register();
		$pid = (int) $this->factory->post->create(array('post_type' => 'post', 'post_status' => 'pending'));
		wp_update_post(array('ID' => $pid, 'post_status' => 'publish'));
		$this->assertFalse(PayoutStatementRepository::exists($pid));
	}
}
