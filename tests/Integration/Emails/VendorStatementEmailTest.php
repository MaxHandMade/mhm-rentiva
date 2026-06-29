<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Emails;

use MHMRentiva\Admin\Emails\Notifications\VendorNotifications;
use MHMRentiva\Core\Financial\Statement\PayoutStatementRepository;
use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use WP_UnitTestCase;

/**
 * @group emails
 */
final class VendorStatementEmailTest extends WP_UnitTestCase {

	public function test_email_sent_and_emailed_at_stamped(): void {
		$vendor = (int) $this->factory->user->create(array('user_email' => 'v@example.com'));
		$pid = (int) $this->factory->post->create(array('post_type' => PostType::POST_TYPE, 'post_author' => $vendor));
		update_post_meta($pid, '_mhm_payout_amount', 100.0);
		PayoutStatementRepository::save($pid, array(
			'number'=>'MKB-2026-0001','generated_at'=>'2026-06-28 10:00:00','currency'=>'TRY',
			'period_start'=>'','period_end'=>'','last_entry_id'=>0,'lines'=>array(),
			'gross'=>0.0,'penalties'=>0.0,'net_activity'=>0.0,'paid'=>100.0,'carried_balance'=>0.0,
			'vendor_snapshot'=>array('name'=>'V','tax_office'=>'','tax_number'=>'','account_holder'=>'','iban'=>''),
		));

		$sent = array();
		add_filter('pre_wp_mail', function ($null, $atts) use (&$sent) { $sent[] = $atts; return true; }, 10, 2);

		VendorNotifications::on_statement_generated($pid);

		$this->assertNotEmpty($sent, 'A statement email must be sent.');
		$this->assertContains('v@example.com', (array) $sent[0]['to']);
		$this->assertNotSame('', get_post_meta($pid, '_mhm_statement_emailed_at', true));

		// The email must link to the vendor panel (where a valid in-session nonce is
		// generated), NOT a pre-baked admin-post nonce URL minted in the admin/approval
		// context — that nonce is bound to the wrong user and fails for the vendor.
		$body = (string) $sent[0]['message'];
		$this->assertStringContainsString('/panel/', $body, 'Email should link to the vendor panel.');
		$this->assertStringNotContainsString('action=mhm_rentiva_view_statement', $body, 'Email must not use the session-nonce admin-post link.');
	}
}
