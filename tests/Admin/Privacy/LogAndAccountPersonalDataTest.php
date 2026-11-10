<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Privacy;

use MHMRentiva\Admin\Privacy\AccountPersonalData;
use MHMRentiva\Admin\Privacy\AppLogPersonalData;
use MHMRentiva\Admin\Privacy\EmailLogPersonalData;
use WP_UnitTestCase;

/**
 * E-mail log, app log and account meta in the core privacy tools (5a spec T7, r5 #7).
 *
 * @covers \MHMRentiva\Admin\Privacy\EmailLogPersonalData
 * @covers \MHMRentiva\Admin\Privacy\AppLogPersonalData
 * @covers \MHMRentiva\Admin\Privacy\AccountPersonalData
 */
final class LogAndAccountPersonalDataTest extends WP_UnitTestCase
{
	private function email_log(string $to, array $context, string $subject = 'Your booking'): int
	{
		$id = (int) self::factory()->post->create(array('post_type' => 'mhmrentiva_email_log', 'post_status' => 'publish', 'post_title' => $subject));
		update_post_meta($id, '_mhmrentiva_email_to', $to);
		update_post_meta($id, '_mhmrentiva_email_subject', $subject);
		update_post_meta($id, '_mhmrentiva_email_context', wp_slash((string) wp_json_encode($context)));

		return $id;
	}

	private function app_log(array $meta, array $context = array(), string $message = 'Something happened'): int
	{
		$content = "Message: {$message}\nContext: " . wp_json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		$id      = (int) self::factory()->post->create(array('post_type' => 'mhmrentiva_app_log', 'post_status' => 'publish', 'post_content' => $content));
		foreach ($meta as $key => $value) {
			update_post_meta($id, $key, $value);
		}

		return $id;
	}

	public function test_customer_copy_and_admin_copy_are_both_found(): void
	{
		$customer = $this->email_log('guest@example.test', array('customer' => array('email' => 'guest@example.test')));
		$admin    = $this->email_log('admin@example.test', array('customer' => array('email' => 'guest@example.test')));
		$other    = $this->email_log('admin@example.test', array('customer' => array('email' => 'other@example.test')));

		$result = EmailLogPersonalData::erase('Guest@Example.test', 1);

		$this->assertTrue($result['done']);
		$this->assertNull(get_post($customer));
		$this->assertNull(get_post($admin));
		$this->assertNotNull(get_post($other));
	}

	public function test_needle_does_not_match_a_longer_address(): void
	{
		$longer = $this->email_log('admin@example.test', array('customer' => array('email' => 'ba@example.test')));

		EmailLogPersonalData::erase('a@example.test', 1);

		$this->assertNotNull(get_post($longer));
	}

	public function test_export_gives_date_and_subject_only(): void
	{
		$id = $this->email_log('admin@example.test', array('customer' => array('email' => 'guest@example.test', 'phone' => '+90 555')), 'New booking #12');

		$result = EmailLogPersonalData::export('guest@example.test', 1);

		$this->assertSame('email-log-' . $id, $result['data'][0]['item_id']);
		$values = implode("\n", array_column($result['data'][0]['data'], 'value'));
		$this->assertStringContainsString('New booking #12', $values);
		$this->assertStringNotContainsString('admin@example.test', $values);
		$this->assertStringNotContainsString('+90 555', $values);
	}

	public function test_sixty_logs_finish_in_two_pages(): void
	{
		for ($i = 0; $i < 60; $i++) {
			$this->email_log('guest@example.test', array());
		}

		$this->assertFalse(EmailLogPersonalData::erase('guest@example.test', 1)['done']);
		$this->assertTrue(EmailLogPersonalData::erase('guest@example.test', 2)['done']);
		$this->assertSame(array(), EmailLogPersonalData::export('guest@example.test', 1)['data']);
	}

	public function test_meta_ids_content_email_and_context_ids_match(): void
	{
		$user  = (int) self::factory()->user->create(array('user_email' => 'guest@example.test'));
		$other = $user + 153; // An ID that starts with the same digits must not match.

		$by_meta     = $this->app_log(array('_mhmrentiva_log_user_id' => $user));
		$by_customer = $this->app_log(array('_mhmrentiva_log_customer_id' => $user));
		$by_email    = $this->app_log(array(), array('to' => 'guest@example.test'));
		$by_context  = $this->app_log(array(), array('user_id' => $user, 'action' => 'anonymised'));
		$by_last     = $this->app_log(array(), array('action' => 'x', 'customer_id' => $user));
		$longer_id   = $this->app_log(array(), array('user_id' => (int) ($user . '0')));
		$unrelated   = $this->app_log(array('_mhmrentiva_log_user_id' => $other));

		$result = AppLogPersonalData::erase('guest@example.test', 1);

		$this->assertTrue($result['done']);
		foreach (array($by_meta, $by_customer, $by_email, $by_context, $by_last) as $id) {
			$this->assertNull(get_post($id), (string) $id);
		}
		$this->assertNotNull(get_post($longer_id));
		$this->assertNotNull(get_post($unrelated));
	}

	public function test_guest_without_account_matches_by_content_email_only(): void
	{
		$by_email = $this->app_log(array(), array('to' => 'guest@example.test'));
		$by_zero  = $this->app_log(array('_mhmrentiva_log_user_id' => 0));

		AppLogPersonalData::erase('guest@example.test', 1);

		$this->assertNull(get_post($by_email));
		$this->assertNotNull(get_post($by_zero));
	}

	public function test_meta_exported_and_erased_account_kept(): void
	{
		$user    = (int) self::factory()->user->create(array('user_email' => 'guest@example.test'));
		$vehicle = (int) self::factory()->post->create(array('post_type' => 'mhmrentiva_vehicle', 'post_title' => 'Clio 1.0'));
		update_user_meta($user, 'mhmrentiva_phone', '+90 555');
		update_user_meta($user, 'mhmrentiva_address', 'Kızılay, Ankara');
		update_user_meta($user, 'mhmrentiva_favorites', array($vehicle));
		update_user_meta($user, 'mhmrentiva_marketing_emails', '1');
		update_user_meta($user, 'mhmrentiva_customer', '1');

		$export = AccountPersonalData::export('guest@example.test', 1);
		$values = implode("\n", array_column($export['data'][0]['data'], 'value'));
		$this->assertStringContainsString('+90 555', $values);
		$this->assertStringContainsString('Kızılay, Ankara', $values);
		$this->assertStringContainsString('Clio 1.0', $values);

		$result = AccountPersonalData::erase('guest@example.test', 1);

		$this->assertTrue($result['items_removed']);
		$this->assertTrue($result['done']);
		$this->assertSame('', get_user_meta($user, 'mhmrentiva_phone', true));
		$this->assertSame('', get_user_meta($user, 'mhmrentiva_address', true));
		$this->assertSame('', get_user_meta($user, 'mhmrentiva_favorites', true));
		$this->assertSame('', get_user_meta($user, 'mhmrentiva_marketing_emails', true));
		$this->assertSame('1', get_user_meta($user, 'mhmrentiva_customer', true));
		$this->assertInstanceOf(\WP_User::class, get_userdata($user));
	}

	/**
	 * The prefix sweep left an old favourites row under this key on some
	 * sites; it is the same person's data (Pro audit M4, 2026-10-20).
	 */
	public function test_the_old_favourites_key_is_exported_and_erased(): void
	{
		$user    = (int) self::factory()->user->create(array('user_email' => 'guest@example.test'));
		$vehicle = (int) self::factory()->post->create(array('post_type' => 'mhmrentiva_vehicle', 'post_title' => 'Egea 1.4'));
		update_user_meta($user, 'mhmrentiva_favorite_vehicles', array($vehicle));

		$values = implode("\n", array_column(AccountPersonalData::export('guest@example.test', 1)['data'][0]['data'], 'value'));
		$this->assertStringContainsString('Egea 1.4', $values);

		AccountPersonalData::erase('guest@example.test', 1);
		$this->assertSame('', get_user_meta($user, 'mhmrentiva_favorite_vehicles', true));
	}

	/**
	 * A stored '0' is the customer's "do not e-mail me"; Mailer treats a missing
	 * value as allowed. Erasing the opt-out would sign the person back up --
	 * for the active bookings the eraser keeps, too (Codex bot P2, Lite #186).
	 */
	public function test_notification_opt_outs_survive_the_erasure(): void
	{
		$user = (int) self::factory()->user->create(array('user_email' => 'guest@example.test'));
		update_user_meta($user, 'mhmrentiva_booking_notifications', '0');
		update_user_meta($user, 'mhmrentiva_marketing_emails', '0');
		update_user_meta($user, 'mhmrentiva_welcome_email', '1');

		AccountPersonalData::erase('guest@example.test', 1);

		$this->assertSame('0', get_user_meta($user, 'mhmrentiva_booking_notifications', true));
		$this->assertSame('0', get_user_meta($user, 'mhmrentiva_marketing_emails', true));
		$this->assertSame('', get_user_meta($user, 'mhmrentiva_welcome_email', true));
		$this->assertTrue(\MHMRentiva\Admin\Emails\Core\Mailer::customer_turned_off_notifications('guest@example.test'));
	}

	public function test_no_account_returns_done_with_no_items(): void
	{
		$this->assertSame(array('data' => array(), 'done' => true), AccountPersonalData::export('nobody@example.test', 1));
		$result = AccountPersonalData::erase('nobody@example.test', 1);
		$this->assertFalse($result['items_removed']);
		$this->assertTrue($result['done']);
	}
}
