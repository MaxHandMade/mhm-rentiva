<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Core\Financial\Statement;

use MHMRentiva\Core\Financial\Statement\PayoutStatementService;
use MHMRentiva\Core\Financial\Statement\PayoutStatementRepository;
use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use WP_UnitTestCase;

/**
 * @group financial
 */
final class PayoutStatementServiceTest extends WP_UnitTestCase {

	private function make_payout(): int {
		$vendor = (int) $this->factory->user->create();
		// post_status 'pending' so the live transition_post_status hook does not auto-generate
		// a statement on publish — this test drives the service explicitly.
		$pid = (int) $this->factory->post->create(array('post_type' => PostType::POST_TYPE, 'post_author' => $vendor, 'post_status' => 'pending'));
		update_post_meta($pid, '_mhm_payout_amount', 100.0);
		return $pid;
	}

	public function test_generate_assigns_number_saves_and_fires_action(): void {
		$pid = $this->make_payout();
		$fired = 0;
		add_action('mhm_rentiva_payout_statement_generated', function ($id) use (&$fired, $pid) {
			if ($id === $pid) { $fired++; }
		});

		$s = PayoutStatementService::generate_for_payout($pid);

		$this->assertIsArray($s);
		$this->assertStringStartsWith('MKB-', $s['number']);
		$this->assertTrue(PayoutStatementRepository::exists($pid));
		$this->assertSame(1, $fired);
	}

	public function test_generate_is_idempotent(): void {
		$pid = $this->make_payout();
		PayoutStatementService::generate_for_payout($pid);
		$second = PayoutStatementService::generate_for_payout($pid);
		$this->assertNull($second, 'A second generation for the same payout is a no-op.');
	}
}
