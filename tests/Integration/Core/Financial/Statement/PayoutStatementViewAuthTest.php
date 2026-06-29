<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Core\Financial\Statement;

use MHMRentiva\Core\Financial\Statement\PayoutStatementController;
use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use WP_UnitTestCase;

/**
 * @group financial
 */
final class PayoutStatementViewAuthTest extends WP_UnitTestCase {

	public function test_owner_vendor_can_view_others_cannot(): void {
		$vendor = (int) $this->factory->user->create(array('role' => 'subscriber'));
		$other  = (int) $this->factory->user->create(array('role' => 'subscriber'));
		$admin  = (int) $this->factory->user->create(array('role' => 'administrator'));
		$pid = (int) $this->factory->post->create(array('post_type' => PostType::POST_TYPE, 'post_author' => $vendor));

		$this->assertTrue(PayoutStatementController::can_view($pid, $vendor), 'Owning vendor can view.');
		$this->assertTrue(PayoutStatementController::can_view($pid, $admin), 'Admin can view.');
		$this->assertFalse(PayoutStatementController::can_view($pid, $other), 'Another vendor cannot view.');
		$this->assertFalse(PayoutStatementController::can_view($pid, 0), 'Logged-out cannot view.');
	}

	public function test_view_url_contains_action_and_nonce(): void {
		$pid = (int) $this->factory->post->create(array('post_type' => PostType::POST_TYPE));
		$url = PayoutStatementController::view_url($pid);
		$this->assertStringContainsString('action=mhm_rentiva_view_statement', $url);
		$this->assertStringContainsString('payout=' . $pid, $url);
		$this->assertStringContainsString('_wpnonce=', $url);
	}
}
