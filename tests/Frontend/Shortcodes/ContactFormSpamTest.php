<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\ContactMessages\ContactMessageRepository;
use MHMRentiva\Admin\ContactMessages\ContactStatus;
use MHMRentiva\Admin\ContactMessages\REST\ContactMessagesRestController;
use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Frontend\Shortcodes\Contact\RecipientToken;
use MHMRentiva\Admin\Frontend\Shortcodes\Contact\RenderStamp;
use MHMRentiva\Admin\Frontend\Shortcodes\Contact\SpamCheck;
use MHMRentiva\Admin\Frontend\Shortcodes\ContactForm;
use MHMRentiva\Admin\Frontend\Shortcodes\ContactFormView;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Tests\Support\AkismetStubState;
use MHMRentiva\Tests\Support\ContactAttachmentFixtures;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\SandboxesUploads;
use WP_Ajax_UnitTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Layer 1 spam rules of the contact form: each rule, what must NOT be caught, and what
 * a caught message may not do (mail, count, keep a file).
 */
final class ContactFormSpamTest extends WP_Ajax_UnitTestCase
{
	use ContactAttachmentFixtures;
	use SandboxesUploads;

	/** @var list<array<string,mixed>> */
	private array $mails = array();

	private int $sent = 0;

	/** When the next request "reached the server"; null for now. */
	private ?int $arrived = null;

	public function setUp(): void
	{
		parent::setUp();
		ContactForm::register();
		RequestSimulator::new_request();
		$this->mails = array();
		add_filter(
			'pre_wp_mail',
			function ($short, array $atts) {
				$this->mails[] = $atts;
				return true;
			},
			10,
			2
		);
		delete_option('disallowed_keys');
		AkismetStubState::reset();
		$this->remove_contact_records();
	}

	/**
	 * The first REST bootstrap of a run can create a table, whose implicit commit keeps
	 * rows past the test's rollback; these tests count rows, so they start from none.
	 */
	private function remove_contact_records(): void
	{
		foreach (get_posts(array( 'post_type' => 'mhmrentiva_contact', 'post_status' => 'any,trash', 'numberposts' => -1, 'fields' => 'ids' )) as $id) {
			wp_delete_post((int) $id, true);
		}
	}

	public function tearDown(): void
	{
		remove_all_filters('pre_wp_mail');
		remove_all_filters('mhmrentiva_contact_min_seconds');
		remove_all_filters('mhmrentiva_contact_max_links');
		remove_all_filters('mhmrentiva_contact_spam_reason');
		delete_option(SettingsCore::OPTION_NAME);
		delete_option('disallowed_keys');
		unset($_SERVER['HTTP_USER_AGENT']);
		$_POST = array();
		wp_set_current_user(0);
		AkismetStubState::reset();
		parent::tearDown();
	}

	private function stamp(int $age = 60): string
	{
		return RenderStamp::issue(RenderStamp::FORM_CONTACT, time() - $age);
	}

	/**
	 * @param array<string,string> $fields Extra POST fields.
	 * @return array<string,mixed>
	 */
	private function submit(array $fields = array()): array
	{
		$_SERVER['REQUEST_TIME'] = $this->arrived ?? time();

		// A null field is left out of the request.
		$_POST = array_filter(array_merge(
			array(
				'action'           => 'mhmrentiva_submit_contact_form',
				'nonce'            => wp_create_nonce('mhmrentiva_contact_form_nonce'),
				'type'             => 'general',
				'name'             => 'Ada Visitor',
				'email'            => 'ada@example.com',
				'message'          => 'Do you have an automatic car for Friday? ' . ( ++$this->sent ),
				RenderStamp::FIELD => $this->stamp(),
			),
			$fields
		), static fn($value): bool => null !== $value);
		$this->_last_response = '';
		$level                = ob_get_level();
		ob_start();
		try {
			$this->_handleAjax('mhmrentiva_submit_contact_form');
		} catch (\WPAjaxDieContinueException $e) {
			unset($e);
		} finally {
			while (ob_get_level() > $level) {
				ob_end_clean();
			}
		}
		$raw     = (string) $this->_last_response;
		$cut     = strpos($raw, '}{"success"');
		$decoded = json_decode(false === $cut ? $raw : substr($raw, 0, $cut + 1), true);
		$this->assertIsArray($decoded, 'AJAX response: ' . $raw);
		return $decoded;
	}

	/**
	 * @param array<string,mixed> $response submit().
	 */
	private function assert_clean(array $response, string $why = ''): void
	{
		$this->assertTrue($response['success'], $why);
		$id = (int) $response['data']['message_id'];
		$this->assertSame(ContactStatus::STATUS_NEW, ContactStatus::get($id), $why);
		$this->assertSame('', get_post_meta($id, MetaKeys::CONTACT_SPAM_REASON, true), $why);
		$this->assertNotSame(array(), $this->mails, $why . ' The owner is told.');
		$this->mails = array();
	}

	/**
	 * @param array<string,mixed> $response submit().
	 */
	private function assert_spam(array $response, string $reason, string $why = ''): void
	{
		$this->assertTrue($response['success'], $why . ' The visitor sees the normal success.');
		$id = (int) $response['data']['message_id'];
		$this->assertGreaterThan(0, $id, $why);
		$this->assertTrue($response['data']['email_sent'], $why . ' No hint in the answer.');
		$this->assertSame(ContactStatus::STATUS_SPAM, ContactStatus::get($id), $why);
		$this->assertSame($reason, get_post_meta($id, MetaKeys::CONTACT_SPAM_REASON, true), $why);
		$this->assertSame(array(), $this->mails, $why . ' No mail of any kind.');
	}

	// ---------------------------------------------------------------- nothing wrong

	public function test_a_normal_message_passes(): void
	{
		$this->assert_clean($this->submit());
	}

	public function test_a_logged_in_visitor_is_read_by_the_same_rules_and_passes(): void
	{
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'customer' )));
		$this->assert_clean($this->submit());

		$this->assert_spam($this->submit(array( RenderStamp::FIELD => $this->stamp(0) )), SpamCheck::REASON_TIME, 'Signed in, still a bot speed.');
	}

	public function test_a_fast_autofill_above_the_minimum_passes(): void
	{
		$this->assert_clean($this->submit(array( RenderStamp::FIELD => $this->stamp(4) )));
	}

	public function test_a_tab_left_open_for_days_passes(): void
	{
		$this->assert_clean($this->submit(array( RenderStamp::FIELD => $this->stamp(3 * DAY_IN_SECONDS) )));
	}

	public function test_a_normal_answer_and_a_spam_answer_look_alike(): void
	{
		$normal = $this->submit();
		$spam   = $this->submit(array( RenderStamp::FIELD => $this->stamp(0) ));

		$this->assertSame(array_keys($normal), array_keys($spam));
		$this->assertSame(array_keys($normal['data']), array_keys($spam['data']));
		$this->assertSame($normal['data']['email_sent'], $spam['data']['email_sent']);
		$this->assertSame($normal['data']['message'] ?? null, $spam['data']['message'] ?? null);
	}

	// ---------------------------------------------------------------- time trap

	public function test_the_form_prints_a_stamp_the_server_accepts_as_just_drawn(): void
	{
		RequestSimulator::new_request();
		$html = do_shortcode('[rentiva_contact]');

		$this->assertSame(1, preg_match('/name="rvs_rendered" value="([^"]+)"/', $html, $m));
		$this->assertLessThanOrEqual(1, RenderStamp::age(html_entity_decode($m[1], ENT_QUOTES), RenderStamp::FORM_CONTACT));
	}

	public function test_too_fast_is_spam(): void
	{
		$this->assert_spam($this->submit(array( RenderStamp::FIELD => $this->stamp(1) )), SpamCheck::REASON_TIME);
	}

	public function test_the_request_start_up_does_not_count_as_the_visitor_time(): void
	{
		// An instant bot on a slow site: rendered, posted a second later, and this code
		// runs five seconds after the post arrived.
		$this->arrived = time() - 5;
		$this->assert_spam($this->submit(array( RenderStamp::FIELD => RenderStamp::issue(RenderStamp::FORM_CONTACT, time() - 6) )), SpamCheck::REASON_TIME);

		$this->arrived = null;
		$this->assert_clean($this->submit(array( RenderStamp::FIELD => $this->stamp(4) )), 'Control.');
	}

	public function test_a_form_without_the_field_skips_the_time_rule(): void
	{
		$this->assert_clean($this->submit(array( RenderStamp::FIELD => null )), 'A page cached before the field existed.');
	}

	public function test_a_form_without_the_field_still_meets_the_other_rules(): void
	{
		$this->assert_spam($this->submit(array( RenderStamp::FIELD => null, ContactFormView::HONEYPOT_FIELD => 'x' )), SpamCheck::REASON_HONEYPOT);
	}

	public function test_an_empty_or_broken_field_is_still_spam(): void
	{
		$this->assert_spam($this->submit(array( RenderStamp::FIELD => '' )), SpamCheck::REASON_TIME);
		$this->assert_spam($this->submit(array( RenderStamp::FIELD => 'x' )), SpamCheck::REASON_TIME);
	}

	public function test_a_stamp_cannot_be_forged(): void
	{
		$good = $this->stamp(0);
		[ $time, $signature ] = explode('.', $good);

		$this->assert_spam($this->submit(array( RenderStamp::FIELD => ( (int) $time - 600 ) . '.' . $signature )), SpamCheck::REASON_TIME, 'An older time on the same signature.');
		$this->assert_spam($this->submit(array( RenderStamp::FIELD => ( (int) $time - 600 ) . '.' . hash('sha256', 'guess') )), SpamCheck::REASON_TIME, 'A made-up signature.');
		$this->assert_spam($this->submit(array( RenderStamp::FIELD => (string) ( (int) $time - 600 ) )), SpamCheck::REASON_TIME, 'Time alone.');
		$this->assert_spam($this->submit(array( RenderStamp::FIELD => RenderStamp::issue(RenderStamp::FORM_REVIEW, time() - 60) )), SpamCheck::REASON_TIME, 'Another form\'s stamp.');
		$this->assert_spam($this->submit(array( RenderStamp::FIELD => RenderStamp::issue(RenderStamp::FORM_CONTACT, time() + 600) )), SpamCheck::REASON_TIME, 'From the future.');
	}

	public function test_the_minimum_comes_from_the_setting_and_the_filter(): void
	{
		$settings = (array) get_option(SettingsCore::OPTION_NAME, array());
		$settings['mhmrentiva_contact_min_seconds'] = 30;
		update_option(SettingsCore::OPTION_NAME, $settings);

		$this->assertSame(30, RenderStamp::min_seconds());
		$this->assert_spam($this->submit(array( RenderStamp::FIELD => $this->stamp(10) )), SpamCheck::REASON_TIME);

		add_filter('mhmrentiva_contact_min_seconds', static fn(): int => 5);
		$this->assert_clean($this->submit(array( RenderStamp::FIELD => $this->stamp(10) )), 'The filter wins.');
		remove_all_filters('mhmrentiva_contact_min_seconds');

		$settings['mhmrentiva_contact_min_seconds'] = 0;
		update_option(SettingsCore::OPTION_NAME, $settings);
		$this->assert_clean($this->submit(array( RenderStamp::FIELD => '' )), '0 turns the trap off.');
	}

	// ---------------------------------------------------------------- disallowed list

	/**
	 * @return array<string,array{0:string,1:array<string,string>}>
	 */
	public static function disallowed_cases(): array
	{
		return array(
			'name'    => array( 'cheapwatches', array( 'name' => 'Cheapwatches Team' ) ),
			'email'   => array( 'cheapwatches', array( 'email' => 'sales@cheapwatches.example' ) ),
			'message' => array( 'cheapwatches', array( 'message' => 'Buy cheapwatches today' ) ),
			'ip'      => array( '203.0.113.9', array() ),
			'agent'   => array( 'BadBot/1.0', array() ),
		);
	}

	/**
	 * @dataProvider disallowed_cases
	 * @param array<string,string> $fields
	 */
	public function test_the_disallowed_list_reads_each_field(string $word, array $fields): void
	{
		$old_ip = $_SERVER['REMOTE_ADDR'] ?? null;
		if ('203.0.113.9' === $word) {
			$_SERVER['REMOTE_ADDR'] = $word;
		}
		if ('BadBot/1.0' === $word) {
			$_SERVER['HTTP_USER_AGENT'] = $word;
		}

		// Negative control: an empty list catches nothing.
		$this->assert_clean($this->submit($fields + array( 'message' => 'First ' . $word )));

		update_option('disallowed_keys', $word);
		$this->assert_spam($this->submit($fields), SpamCheck::REASON_DISALLOWED);

		if (null === $old_ip) {
			unset($_SERVER['REMOTE_ADDR']);
		} else {
			$_SERVER['REMOTE_ADDR'] = $old_ip;
		}
	}

	// ---------------------------------------------------------------- links

	public function test_the_maximum_number_of_links_passes_and_one_more_is_spam(): void
	{
		$three = 'See https://a.example and http://b.example and www.c.example please';
		$this->assertSame(3, SpamCheck::count_links($three));
		$this->assert_clean($this->submit(array( 'message' => $three )));

		$this->assert_spam($this->submit(array( 'message' => $three . ' HTTPS://D.EXAMPLE' )), SpamCheck::REASON_LINKS);
	}

	public function test_a_plain_address_or_a_dotted_word_is_no_link(): void
	{
		$this->assertSame(0, SpamCheck::count_links('My site is ada.example, write to ada@example.com about 1.6 TDI'));
	}

	public function test_the_maximum_comes_from_the_setting_and_the_filter(): void
	{
		$settings = (array) get_option(SettingsCore::OPTION_NAME, array());
		$settings['mhmrentiva_contact_max_links'] = 0;
		update_option(SettingsCore::OPTION_NAME, $settings);

		$this->assert_spam($this->submit(array( 'message' => 'See https://a.example' )), SpamCheck::REASON_LINKS);

		add_filter('mhmrentiva_contact_max_links', static fn(): int => 1);
		$this->assert_clean($this->submit(array( 'message' => 'See https://b.example' )));
	}

	// ---------------------------------------------------------------- duplicate

	public function test_the_same_message_from_the_same_address_is_spam(): void
	{
		$message = 'Do you have a van for Sunday?';
		$this->assert_clean($this->submit(array( 'message' => $message )));

		$this->assert_spam($this->submit(array( 'message' => $message )), SpamCheck::REASON_DUPLICATE);
		$this->assert_spam($this->submit(array( 'message' => $message, 'email' => 'ADA@example.com' )), SpamCheck::REASON_DUPLICATE, 'Address case changes nothing.');
	}

	public function test_another_message_or_another_address_is_no_duplicate(): void
	{
		$message = 'Do you have a van for Sunday?';
		$this->assert_clean($this->submit(array( 'message' => $message )));
		$this->assert_clean($this->submit(array( 'message' => $message . ' Or Monday?' )));
		$this->assert_clean($this->submit(array( 'message' => $message, 'email' => 'grace@example.com' )));
	}

	/**
	 * @return array<string,string> Option name => value of the repeat claims.
	 */
	private function claims(): array
	{
		global $wpdb;
		return (array) $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'mhmrentiva\\_contact\\_dup\\_%'", OBJECT_K);
	}

	public function test_the_duplicate_window_is_ten_minutes_and_holds_no_address(): void
	{
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mhmrentiva\\_contact\\_dup\\_%'");

		$message = 'Do you have a van for Sunday?';
		$this->assert_clean($this->submit(array( 'message' => $message )));

		$claims = $this->claims();
		$this->assertCount(1, $claims);
		$name   = (string) array_key_first($claims);
		$expiry = (int) $claims[ $name ]->option_value;
		$this->assertLessThanOrEqual(time() + 10 * MINUTE_IN_SECONDS, $expiry);
		$this->assertGreaterThan(time() + 10 * MINUTE_IN_SECONDS - 60, $expiry);

		$this->assertSame('0', (string) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", '%ada@example.com%')));

		$wpdb->update($wpdb->options, array( 'option_value' => (string) ( time() - 1 ) ), array( 'option_name' => $name ));
		$this->assert_clean($this->submit(array( 'message' => $message )), 'The window is over: the row is taken over.');
		$this->assert_spam($this->submit(array( 'message' => $message )), SpamCheck::REASON_DUPLICATE, 'And held again.');
	}

	public function test_a_row_gone_between_insert_and_select_counts_as_claimed(): void
	{
		// INSERT IGNORE finds the row (0 rows), then another request's clean-up removes it.
		global $wpdb;
		$gone = static function (string $query) use ($wpdb): string {
			return str_starts_with($query, 'INSERT IGNORE') && str_contains($query, 'mhmrentiva_contact_dup_')
				? "UPDATE {$wpdb->options} SET option_value = option_value WHERE option_name = 'mhmrentiva_no_such_row'"
				: $query;
		};
		add_filter('query', $gone);
		$verdict = SpamCheck::verdict(array( 'stamp' => null, 'email' => 'ada@example.com', 'message' => 'Row gone', 'context' => array() ));
		remove_filter('query', $gone);

		$this->assertSame('', $verdict['reason'], 'Never a repeat.');
	}

	public function test_expired_claims_are_cleared_on_the_way(): void
	{
		global $wpdb;
		$wpdb->insert($wpdb->options, array( 'option_name' => 'mhmrentiva_contact_dup_old', 'option_value' => (string) ( time() - 1 ), 'autoload' => 'off' ));

		$this->assert_clean($this->submit());
		$this->assertArrayNotHasKey('mhmrentiva_contact_dup_old', $this->claims());
	}

	/**
	 * @return array<string,array{0:array<string,string>}>
	 */
	public static function other_requests(): array
	{
		return array(
			'form type' => array( array( 'type' => 'booking' ) ),
			'date'      => array( array( 'preferred_date' => '2026-12-11' ) ),
			'booking'   => array( array( 'booking_id' => '4242' ) ),
		);
	}

	/**
	 * @dataProvider other_requests
	 * @param array<string,string> $other
	 */
	public function test_the_same_words_about_something_else_are_another_request(array $other): void
	{
		$same = array( 'message' => 'Can I get a price for this?', 'preferred_date' => '2026-12-10', 'booking_id' => '41' );

		$this->assert_clean($this->submit($same));
		$this->assert_clean($this->submit(array_merge($same, $other)));
		$this->assert_spam($this->submit(array_merge($same, $other)), SpamCheck::REASON_DUPLICATE, 'The identical repeat of it is caught.');
	}

	public function test_the_same_words_about_another_vehicle_or_recipient_are_another_request(): void
	{
		$one = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
		$two = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
		$say = array( 'message' => 'Can I get a price for this?' );

		$this->assert_clean($this->submit($say + array( 'vehicle_id' => (string) $one )));
		$this->assert_clean($this->submit($say + array( 'vehicle_id' => (string) $two )));
		$this->assert_spam($this->submit($say + array( 'vehicle_id' => (string) $two )), SpamCheck::REASON_DUPLICATE);

		$context = static function (string $recipient): array {
			return SpamCheck::verdict(
				array(
					'stamp'   => null,
					'email'   => 'ada@example.com',
					'message' => 'Same words',
					'context' => array( 'general', 0, '', 0, $recipient, false ),
				)
			);
		};
		$this->assertSame('', $context('a@example.com')['reason']);
		$this->assertSame('', $context('b@example.com')['reason']);
		$this->assertSame(SpamCheck::REASON_DUPLICATE, $context('b@example.com')['reason']);
	}

	/**
	 * @return array<string,mixed> process_submission()'s answer.
	 */
	private function process(array $post, ?array $file, ?callable $handler): array
	{
		$method = new \ReflectionMethod(ContactForm::class, 'process_submission');
		$method->setAccessible(true);
		return $method->invoke(
			null,
			$post + array(
				'type'             => 'general',
				'name'             => 'Ada',
				'email'            => 'ada@example.com',
				'message'          => 'Same words about the file',
				RenderStamp::FIELD => $this->stamp(),
			),
			$file,
			$handler
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function pdf(): array
	{
		$tmp = $this->fixture($this->sandbox_uploads() . '/in', 'pdf');
		return array( 'name' => 'a.pdf', 'type' => 'application/pdf', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($tmp) );
	}

	public function test_a_message_with_a_file_is_another_request_than_the_same_without(): void
	{
		$this->assertSame(ContactStatus::STATUS_NEW, ContactStatus::get( (int) $this->process(array(), null, null)['message_id']));

		$with = $this->process(array(), $this->pdf(), $this->sideload());
		$this->assertTrue($with['ok']);
		$this->assertSame(ContactStatus::STATUS_NEW, ContactStatus::get( (int) $with['message_id']));
		$this->remove_sandbox();
	}

	public function test_two_requests_at_once_cannot_both_pass(): void
	{
		$submission = array( 'stamp' => null, 'email' => 'ada@example.com', 'message' => 'At once', 'context' => array() );

		// B runs while A is between its verdict and its save: at A's own filter.
		$second = null;
		$nested = function (string $reason) use (&$second, $submission): string {
			remove_all_filters('mhmrentiva_contact_spam_reason');
			$second = SpamCheck::verdict($submission);
			return $reason;
		};
		add_filter('mhmrentiva_contact_spam_reason', $nested);
		$first = SpamCheck::verdict($submission);

		$this->assertSame('', $first['reason']);
		$this->assertNotSame('', $first['claim']);
		$this->assertSame(SpamCheck::REASON_DUPLICATE, $second['reason'], 'The second one in loses.');
		$this->assertSame('', $second['claim'], 'And holds nothing to release.');
	}

	public function test_a_refused_file_gives_the_claim_back(): void
	{
		$refused = $this->process(array(), $this->pdf(), static fn(array $file, array $overrides): array => array( 'error' => 'no' ));
		$this->assertFalse($refused['ok']);

		$retry = $this->process(array(), $this->pdf(), $this->sideload());
		$this->assertTrue($retry['ok']);
		$this->assertSame(ContactStatus::STATUS_NEW, ContactStatus::get( (int) $retry['message_id']), 'The retry is no repeat.');
		$this->remove_sandbox();
	}

	public function test_a_failed_save_gives_the_claim_back(): void
	{
		add_filter('wp_insert_post_empty_content', '__return_true');
		try {
			$this->process(array(), null, null);
			$this->fail('The save was to fail.');
		} catch (\Exception $e) {
			unset($e);
		}
		remove_filter('wp_insert_post_empty_content', '__return_true');

		$this->assertSame(ContactStatus::STATUS_NEW, ContactStatus::get( (int) $this->process(array(), null, null)['message_id']));
	}

	public function test_a_message_akismet_catches_gives_the_claim_back(): void
	{
		$this->akismet('secret', array( '', 'true' ));
		$this->assert_spam($this->submit(array( 'message' => 'Hold on' )), SpamCheck::REASON_AKISMET);
		$this->assertSame(array(), $this->claims_for_message('Hold on'));
	}

	/**
	 * @return list<string>
	 */
	private function claims_for_message(string $message): array
	{
		AkismetStubState::$key = '';
		$probe = SpamCheck::verdict(array( 'stamp' => null, 'email' => 'ada@example.com', 'message' => $message, 'context' => array( 'general', 0, '', 0, '', false ) ));
		return '' === $probe['claim'] ? array( 'held' ) : array();
	}

	public function test_a_refused_attempt_does_not_make_the_retry_a_duplicate(): void
	{
		$message = 'Do you have a van for Sunday?';
		$this->assertFalse($this->submit(array( 'message' => $message, 'phone' => '12' ))['success'], 'Refused: phone.');

		$this->assert_clean($this->submit(array( 'message' => $message )));
	}

	public function test_a_caught_message_starts_no_duplicate_window(): void
	{
		$message = 'Do you have a van for Sunday?';
		$this->assert_spam($this->submit(array( 'message' => $message, RenderStamp::FIELD => $this->stamp(0) )), SpamCheck::REASON_TIME);

		$this->assert_clean($this->submit(array( 'message' => $message )));
	}

	// ---------------------------------------------------------------- honeypot

	public function test_the_honeypot_on_a_readable_message_is_kept_as_spam(): void
	{
		$this->assert_spam($this->submit(array( ContactFormView::HONEYPOT_FIELD => 'x' )), SpamCheck::REASON_HONEYPOT);
	}

	// ---------------------------------------------------------------- Akismet

	public function test_without_akismet_nothing_is_asked(): void
	{
		if (class_exists('Akismet', false)) {
			$this->markTestSkipped('Another test already loaded the stub.');
		}

		$this->assert_clean($this->submit());
	}

	private function akismet(string $key, $answer): void
	{
		require_once dirname(__DIR__, 2) . '/Support/AkismetStub.php';
		AkismetStubState::$key    = $key;
		AkismetStubState::$answer = $answer;
	}

	public function test_akismet_without_a_key_is_not_asked(): void
	{
		$this->akismet('', array( '', 'true' ));
		$this->assert_clean($this->submit());
		$this->assertSame(array(), AkismetStubState::$requests);
	}

	public function test_akismet_with_a_key_decides_through_its_own_class(): void
	{
		$this->akismet('secret', array( '', 'true' ));
		$this->assert_spam($this->submit(array( 'message' => 'Hello from Ada' )), SpamCheck::REASON_AKISMET);

		$this->assertCount(1, AkismetStubState::$requests);
		[ $body, $path ] = AkismetStubState::$requests[0];
		$this->assertSame('comment-check', $path);
		parse_str($body, $sent);
		$this->assertSame('contact-form', $sent['comment_type']);
		$this->assertSame('ada@example.com', $sent['comment_author_email']);
		$this->assertSame('Hello from Ada', $sent['comment_content']);
		$this->assertSame((string) get_option('home'), $sent['blog']);
		$this->assertArrayNotHasKey('api_key', $sent);
	}

	public function test_akismet_saying_no_passes(): void
	{
		$this->akismet('secret', array( '', 'false' ));
		$this->assert_clean($this->submit());
	}

	/**
	 * @return array<string,array{0:mixed}>
	 */
	public static function akismet_failures(): array
	{
		return array(
			'its failure shape' => array( array( '', '' ) ),
			'an odd body'       => array( array( '', 'invalid' ) ),
			'no array'          => array( null ),
			'an exception'      => array( new \RuntimeException('down') ),
		);
	}

	/**
	 * @dataProvider akismet_failures
	 * @param mixed $answer
	 */
	public function test_an_akismet_failure_lets_the_message_through($answer): void
	{
		$this->akismet('secret', $answer);
		$this->assert_clean($this->submit());
		$this->assertCount(1, AkismetStubState::$requests);
	}

	public function test_a_message_the_local_rules_caught_costs_no_akismet_request(): void
	{
		$this->akismet('secret', array( '', 'false' ));
		$this->assert_spam($this->submit(array( RenderStamp::FIELD => $this->stamp(0) )), SpamCheck::REASON_TIME);
		$this->assertSame(array(), AkismetStubState::$requests);
	}

	// ---------------------------------------------------------------- what spam may not do

	public function test_spam_sends_no_auto_reply_and_claims_no_reply_slot(): void
	{
		$token = RecipientToken::issue('', 0, array( 'general' ), true);

		$this->assert_spam($this->submit(array( 'recipient_token' => $token, RenderStamp::FIELD => $this->stamp(0) )), SpamCheck::REASON_TIME);
		$this->assertFalse(get_transient('mhmrentiva_contact_reply_' . wp_hash('ada@example.com')));
	}

	public function test_a_custom_recipient_gets_no_spam(): void
	{
		$this->assert_spam($this->submit(array( 'recipient_token' => RecipientToken::issue('owner@example.com', 0, array( 'general' ), false), RenderStamp::FIELD => $this->stamp(0) )), SpamCheck::REASON_TIME);
	}

	public function test_spam_is_not_counted_and_not_listed(): void
	{
		$clean = (int) $this->submit()['data']['message_id'];
		$this->mails = array();
		$spam  = (int) $this->submit(array( RenderStamp::FIELD => $this->stamp(0) ))['data']['message_id'];

		$counts = ContactMessageRepository::counts();
		$this->assertSame(1, $counts['all']);
		$this->assertSame(1, $counts['new']);
		$this->assertSame(0, $counts['read'], 'Unknown states read as "read": spam must not.');
		$this->assertSame(1, $counts['spam']);

		$stats = ContactMessageRepository::stats();
		$this->assertSame(1, $stats['new']);
		$this->assertSame(1, $stats['awaiting']);
		$this->assertSame(1, $stats['total']);
		$this->assertSame(1, $stats['last_7_days']);

		delete_transient(ContactStatus::BADGE_TRANSIENT);
		$this->assertSame(1, ContactMessageRepository::new_count());
		$this->assertSame(0, ContactMessageRepository::other_count('ada@example.com', $clean), 'The other message is spam.');

		$this->assertSame(array( $clean ), ContactMessageRepository::list(array())['items']);
		$this->assertSame(array(), ContactMessageRepository::list(array( 'status' => 'read' ))['items']);
		$this->assertSame(array( $spam ), ContactMessageRepository::list(array( 'status' => 'spam' ))['items']);
		$this->assertSame(1, ContactMessageRepository::list(array( 'status' => 'spam' ))['total']);
	}

	public function test_a_month_holding_only_spam_is_not_offered(): void
	{
		$this->submit(array( RenderStamp::FIELD => $this->stamp(0) ));
		$this->assertSame(array(), ContactMessageRepository::months());
	}

	public function test_trashed_spam_shows_in_the_trash(): void
	{
		$spam = (int) $this->submit(array( RenderStamp::FIELD => $this->stamp(0) ))['data']['message_id'];
		wp_trash_post($spam);

		$this->assertSame(array( $spam ), ContactMessageRepository::list(array( 'status' => 'trash' ))['items']);
		$this->assertSame(array(), ContactMessageRepository::list(array( 'status' => 'spam' ))['items']);
	}

	public function test_the_attachment_of_spam_is_not_stored(): void
	{
		$sandbox = $this->sandbox_uploads();
		$tmp     = $this->fixture($sandbox . '/in', 'pdf');
		$method  = new \ReflectionMethod(ContactForm::class, 'process_submission');
		$method->setAccessible(true);

		$called = false;
		$result = $method->invoke(
			null,
			array(
				'type'             => 'general',
				'name'             => 'Ada',
				'email'            => 'ada@example.com',
				'message'          => 'Hi',
				RenderStamp::FIELD => $this->stamp(0),
			),
			array(
				'name'     => 'a.pdf',
				'type'     => 'application/pdf',
				'tmp_name' => $tmp,
				'error'    => UPLOAD_ERR_OK,
				'size'     => (int) filesize($tmp),
			),
			static function (array $file, array $overrides) use (&$called): array {
				unset($file, $overrides);
				$called = true;
				return array( 'error' => 'not reached' );
			}
		);

		$this->assertTrue($result['ok']);
		$this->assertFalse($called, 'The store never ran.');
		$this->assertSame('', get_post_meta((int) $result['message_id'], '_mhmrentiva_contact_attachment', true));
		$this->assertSame('', get_post_meta((int) $result['message_id'], '_mhmrentiva_contact_attachment_file', true));
	}

	public function test_the_verdict_can_be_filtered(): void
	{
		add_filter('mhmrentiva_contact_spam_reason', static fn(): string => 'my_rule');
		$this->assert_spam($this->submit(), 'my_rule');

		remove_all_filters('mhmrentiva_contact_spam_reason');
		add_filter('mhmrentiva_contact_spam_reason', static fn(): string => '');
		$this->mails = array();
		$this->assert_clean($this->submit(array( RenderStamp::FIELD => $this->stamp(0) )), 'And cleared.');
	}

	// ---------------------------------------------------------------- admin

	private function rest(string $method, string $route, array $params = array()): \WP_REST_Response
	{
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		add_action('rest_api_init', array( ContactMessagesRestController::class, 'register_routes' ));
		do_action('rest_api_init', $wp_rest_server);
		$request = new WP_REST_Request($method, '/mhm-rentiva/v1' . $route);
		foreach ($params as $key => $value) {
			$request->set_param($key, $value);
		}
		$response = $wp_rest_server->dispatch($request);
		remove_action('rest_api_init', array( ContactMessagesRestController::class, 'register_routes' ));
		$wp_rest_server = null;

		return $response;
	}

	private function caught(): int
	{
		$id          = (int) $this->submit(array( RenderStamp::FIELD => $this->stamp(0) ))['data']['message_id'];
		$this->mails = array();
		return $id;
	}

	public function test_not_spam_makes_a_normal_new_record_and_mails_nobody(): void
	{
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
		$id = $this->caught();

		$response = $this->rest('POST', '/contact-messages/bulk', array( 'ids' => array( $id ), 'action' => 'not_spam' ));

		$this->assertTrue($response->get_data()['results'][0]['ok']);
		$this->assertSame(ContactStatus::STATUS_NEW, ContactStatus::get($id));
		$this->assertSame('', get_post_meta($id, MetaKeys::CONTACT_SPAM_REASON, true));
		$this->assertSame(array(), $this->mails, 'No mail afterwards.');
		$this->assertSame(array( $id ), ContactMessageRepository::list(array())['items']);
		$this->assertSame(1, ContactMessageRepository::counts()['new']);
		$this->assertSame(0, ContactMessageRepository::counts()['spam']);
	}

	public function test_not_spam_only_applies_to_spam(): void
	{
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_contact', 'post_status' => 'private' ));
		update_post_meta($id, ContactStatus::META_KEY, 'replied');

		$response = $this->rest('POST', '/contact-messages/bulk', array( 'ids' => array( $id ), 'action' => 'not_spam' ));

		$this->assertFalse($response->get_data()['results'][0]['ok']);
		$this->assertSame('replied', ContactStatus::get($id));
	}

	public function test_spam_is_deleted_outright_while_a_normal_message_still_needs_the_trash(): void
	{
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));

		$bulk = $this->caught();
		$this->assertTrue($this->rest('POST', '/contact-messages/bulk', array( 'ids' => array( $bulk ), 'action' => 'delete' ))->get_data()['results'][0]['ok']);
		$this->assertNull(get_post($bulk));

		$single = $this->caught();
		$this->assertTrue($this->rest('DELETE', '/contact-messages/' . $single, array( 'force' => true ))->get_data()['deleted']);
		$this->assertNull(get_post($single));

		$normal = (int) $this->submit()['data']['message_id'];
		$this->assertFalse($this->rest('POST', '/contact-messages/bulk', array( 'ids' => array( $normal ), 'action' => 'delete' ))->get_data()['results'][0]['ok']);
		$this->assertSame(400, $this->rest('DELETE', '/contact-messages/' . $normal, array( 'force' => true ))->get_status());
		$this->assertNotNull(get_post($normal));
	}

	public function test_the_spam_routes_keep_the_capability(): void
	{
		$id = $this->caught();

		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'editor' )));
		$this->assertSame(403, $this->rest('GET', '/contact-messages', array( 'status' => 'spam' ))->get_status());
		$this->assertSame(403, $this->rest('POST', '/contact-messages/bulk', array( 'ids' => array( $id ), 'action' => 'not_spam' ))->get_status());
		$this->assertSame(ContactStatus::STATUS_SPAM, ContactStatus::get($id));
	}

	public function test_the_list_route_filters_and_the_detail_says_why(): void
	{
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
		$id = $this->caught();

		$list = $this->rest('GET', '/contact-messages', array( 'status' => 'spam' ))->get_data();
		$this->assertSame(array( $id ), array_column($list['items'], 'id'));
		$this->assertSame('spam', $list['items'][0]['status']);
		$this->assertSame(1, $list['counts']['spam']);
		$this->assertSame(array(), $this->rest('GET', '/contact-messages')->get_data()['items']);

		$detail = $this->rest('GET', '/contact-messages/' . $id)->get_data();
		$reason = array_values(array_filter($detail['fields'], static fn(array $field): bool => 'spam_reason' === $field['key']));
		$this->assertSame(ContactStatus::spam_reason_label(SpamCheck::REASON_TIME), $reason[0]['value']);
	}
}
