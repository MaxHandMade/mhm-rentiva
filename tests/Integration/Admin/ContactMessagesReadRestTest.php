<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\ContactMessages\ContactAttachmentStore;
use MHMRentiva\Admin\ContactMessages\ContactMessageRepository;
use MHMRentiva\Admin\ContactMessages\ContactStatus;
use MHMRentiva\Admin\ContactMessages\REST\ContactMessagesRestController;
use MHMRentiva\Admin\Frontend\Shortcodes\ContactMessagePostType;
use MHMRentiva\Tests\Support\ContactAttachmentFixtures;
use MHMRentiva\Tests\Support\SandboxesUploads;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

final class ContactMessagesReadRestTest extends WP_UnitTestCase
{
	use SandboxesUploads;
	use ContactAttachmentFixtures;

	private WP_REST_Server $server;
	private int $admin = 0;

	public function setUp(): void
	{
		parent::setUp();
		add_action('rest_api_init', array( ContactMessagesRestController::class, 'register_routes' ));
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action('rest_api_init', $this->server);
		$this->admin = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
		delete_transient(ContactStatus::BADGE_TRANSIENT);
		$this->sandbox_uploads();
	}

	public function tearDown(): void
	{
		global $wp_rest_server;
		$wp_rest_server = null;
		remove_action('rest_api_init', array( ContactMessagesRestController::class, 'register_routes' ));
		wp_set_current_user(0);
		parent::tearDown();
		$this->remove_sandbox();
	}

	private function contact(array $meta = array(), array $post = array()): int
	{
		$id = (int) self::factory()->post->create(array_merge(array(
			'post_type'    => 'mhmrentiva_contact',
			'post_status'  => 'private',
			'post_title'   => 'Contact Message - ' . ( $meta['name'] ?? 'Ada' ),
			'post_content' => 'Hello there',
		), $post));
		$meta = array_merge(array( 'name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'type' => 'general', 'status' => 'new' ), $meta);
		foreach ($meta as $k => $v) {
			update_post_meta($id, '_mhmrentiva_contact_' . $k, $v);
		}
		return $id;
	}

	private function get(string $path, array $params = array())
	{
		$r = new WP_REST_Request('GET', '/mhm-rentiva/v1' . $path);
		foreach ($params as $k => $v) {
			$r->set_param($k, $v);
		}
		return $this->server->dispatch($r);
	}

	public function test_anonymous_and_editor_are_refused(): void
	{
		$this->assertSame(401, $this->get('/contact-messages')->get_status());
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'editor' )));
		$this->assertSame(403, $this->get('/contact-messages')->get_status());
	}

	public function test_list_rows_counts_and_stats(): void
	{
		wp_set_current_user($this->admin);
		$this->contact();
		$this->contact(array( 'status' => 'replied', 'email' => 'bob@example.com', 'name' => 'Bob' ));
		$data = $this->get('/contact-messages')->get_data();

		$this->assertSame(2, $data['total']);
		$this->assertSame(array( 'all' => 2, 'new' => 1, 'read' => 0, 'replied' => 1, 'trash' => 0 ), $data['counts']);
		$this->assertSame(1, $data['stats']['new']);
		$this->assertSame(1, $data['stats']['awaiting']);
		$this->assertSame('General Contact', $data['items'][0]['type_label']);
		$this->assertArrayNotHasKey('ip_address', $data['items'][0]);
	}

	public function test_row_without_status_meta_counts_as_read(): void
	{
		wp_set_current_user($this->admin);
		$id = $this->contact();
		delete_post_meta($id, ContactStatus::META_KEY);
		$data = $this->get('/contact-messages', array( 'status' => 'read' ))->get_data();
		$this->assertSame(1, $data['total']);
		$this->assertSame('read', $data['items'][0]['status']);
	}

	public function test_search_matches_turkish_name_and_exact_email(): void
	{
		wp_set_current_user($this->admin);
		$this->contact(array( 'name' => 'Şule Çağ', 'email' => 'Sule@Example.com' ));
		$this->contact(array( 'name' => 'Other', 'email' => 'other@example.com' ));
		$this->assertSame(1, $this->get('/contact-messages', array( 'search' => 'Şule' ))->get_data()['total']);
		$this->assertSame(1, $this->get('/contact-messages', array( 'search' => 'sule@example.com' ))->get_data()['total']);
	}

	public function test_a_booking_carrying_contact_meta_never_appears(): void
	{
		wp_set_current_user($this->admin);
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
		update_post_meta($booking, '_mhmrentiva_contact_email', 'ada@example.com');
		$this->assertSame(0, $this->get('/contact-messages')->get_data()['total']);
		$this->assertSame(404, $this->get('/contact-messages/' . $booking)->get_status());
	}

	public function test_detail_hides_ip_and_ua_and_the_technical_route_serves_them_uncached(): void
	{
		wp_set_current_user($this->admin);
		$id = $this->contact(array( 'ip_address' => '203.0.113.48', 'user_agent' => 'UA/1' ));
		$detail = $this->get('/contact-messages/' . $id)->get_data();
		$this->assertStringNotContainsString('203.0.113.48', (string) wp_json_encode($detail));

		$tech = $this->get('/contact-messages/' . $id . '/technical');
		$this->assertSame('203.0.113.48', $tech->get_data()['ip_address']);
		$this->assertStringContainsString('no-store', $tech->get_headers()['Cache-Control'] ?? '');
	}

	public function test_email_with_query_characters_is_not_linkable(): void
	{
		wp_set_current_user($this->admin);
		$id = $this->contact(array( 'email' => 'a?cc=b%40evil.com&x=@example.com' ));
		$this->assertFalse($this->get('/contact-messages/' . $id)->get_data()['sender']['email_linkable']);
	}

	public function test_vehicle_only_when_it_is_a_vehicle_and_attachment_only_from_uploads(): void
	{
		wp_set_current_user($this->admin);
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
		$id = $this->contact(array( 'vehicle_id' => $booking, 'attachment' => 'https://evil.example/x.pdf' ));
		$d  = $this->get('/contact-messages/' . $id)->get_data();
		$this->assertNull($d['vehicle']);
		$this->assertNull($d['attachment']['download_url']);
	}

	/** vehicle_id '0' is the un-set default (never a real post ID); it must read as no vehicle, not a lookup of post 0. */
	public function test_vehicle_id_of_zero_is_no_vehicle(): void
	{
		wp_set_current_user($this->admin);
		$id = $this->contact(array( 'vehicle_id' => '0' ));
		$this->assertNull($this->get('/contact-messages/' . $id)->get_data()['vehicle']);
	}

	public function test_phone_and_company_appear_in_the_detail_response_when_set(): void
	{
		wp_set_current_user($this->admin);
		$id     = $this->contact(array( 'phone' => '+90 555 0100', 'company' => 'Acme Rentals' ));
		$detail = $this->get('/contact-messages/' . $id)->get_data();

		$this->assertSame('+90 555 0100', $detail['sender']['phone']);

		$company = array_values(array_filter($detail['fields'], static fn($f) => 'company' === $f['key']));
		$this->assertSame('Acme Rentals', $company[0]['value'] ?? null);
	}

	public function test_other_count_excludes_self_and_trash(): void
	{
		wp_set_current_user($this->admin);
		$a = $this->contact();
		$this->contact();
		$t = $this->contact();
		wp_trash_post($t);
		$this->assertSame(1, $this->get('/contact-messages/' . $a)->get_data()['sender']['other_count']);
	}

	public function test_invalid_period_is_a_400(): void
	{
		wp_set_current_user($this->admin);
		$this->assertSame(400, $this->get('/contact-messages', array( 'period' => '2026-13' ))->get_status());
		$this->assertSame(400, $this->get('/contact-messages', array( 'per_page' => 101 ))->get_status());
	}

	public function test_detail_priority_label_is_capitalised(): void
	{
		wp_set_current_user($this->admin);
		$id = $this->contact(array( 'priority' => 'high' ));
		$fields = $this->get('/contact-messages/' . $id)->get_data()['fields'];
		$priority = array_values(array_filter($fields, static fn($f) => 'priority' === $f['key']));
		$this->assertSame('High', $priority[0]['value'] ?? null);
	}

	/** Ruling 1: postmeta collates case-insensitive/PAD SPACE, so 'New' must normalise, not pass through raw. */
	public function test_mixed_case_status_meta_normalises_to_read(): void
	{
		wp_set_current_user($this->admin);
		$id = $this->contact(array( 'status' => 'New' ));

		$counts = $this->get('/contact-messages')->get_data()['counts'];
		$this->assertSame(array( 'all' => 1, 'new' => 0, 'read' => 1, 'replied' => 0, 'trash' => 0 ), $counts);

		$this->assertSame(1, $this->get('/contact-messages', array( 'status' => 'read' ))->get_data()['total']);
		$this->assertSame(0, $this->get('/contact-messages', array( 'status' => 'new' ))->get_data()['total']);
		$this->assertSame($id, $this->get('/contact-messages', array( 'status' => 'read' ))->get_data()['items'][0]['id']);
	}

	/** Ruling 2: a fanned-out join (duplicate status meta row) must not double-count a post. */
	public function test_counts_are_distinct_per_post_even_with_duplicate_status_meta(): void
	{
		wp_set_current_user($this->admin);
		$id = $this->contact(array( 'status' => 'new' ));
		add_post_meta($id, ContactStatus::META_KEY, 'new', false);

		$this->assertSame(1, $this->get('/contact-messages')->get_data()['counts']['new']);
		$this->assertSame(1, $this->get('/contact-messages')->get_data()['counts']['all']);
	}

	/** Ruling 3: mysql_to_rfc3339() carries no offset; clients need an explicit one. */
	public function test_date_iso_carries_an_explicit_utc_offset(): void
	{
		wp_set_current_user($this->admin);
		$id = $this->contact();
		$date_iso = $this->get('/contact-messages/' . $id)->get_data()['date_iso'];
		$this->assertStringEndsWith('+00:00', $date_iso);
	}

	/** Ruling 4: row() labels a missing/unrecognised type meta "General Contact" too, so the filter must match both. */
	public function test_type_general_matches_missing_and_unrecognised_type_meta(): void
	{
		wp_set_current_user($this->admin);
		$evil    = $this->contact(array( 'type' => 'evil' ));
		$missing = $this->contact();
		delete_post_meta($missing, '_mhmrentiva_contact_type');
		$booking = $this->contact(array( 'type' => 'booking' ));

		$data = $this->get('/contact-messages', array( 'type' => 'general' ))->get_data();
		$ids  = array_column($data['items'], 'id');
		$this->assertSame(2, $data['total']);
		$this->assertContains($evil, $ids);
		$this->assertContains($missing, $ids);
		$this->assertNotContains($booking, $ids);
	}

	/**
	 * ContactMessageRepository::list() writes the "general" type filter's NOT
	 * IN list as the literal SQL fragment ('booking','support','feedback')
	 * rather than building it from ContactMessagePostType::TYPES at runtime
	 * (Task 9C fix round 2 -- G-D flagged the runtime-built version as an
	 * unescaped DB parameter shape). This pins the assumption the literal
	 * depends on: if TYPES ever gains, loses or reorders a non-general
	 * member, this test fails loudly and points at the query to update,
	 * instead of the "general" filter silently drifting from what TYPES says.
	 */
	public function test_non_general_types_match_the_literal_in_list_the_repository_query_hardcodes(): void
	{
		$this->assertSame(
			array( 'booking', 'support', 'feedback' ),
			array_values(array_diff(ContactMessagePostType::TYPES, array( 'general' )))
		);
	}

	/** Ruling 5: an array `period` must 400, not trigger "Array to string conversion". */
	public function test_period_as_array_is_a_400_without_a_php_warning(): void
	{
		wp_set_current_user($this->admin);

		$caught = array();
		set_error_handler(static function (int $errno, string $errstr) use (&$caught): bool {
			$caught[] = $errstr;
			return true;
		}, E_WARNING | E_NOTICE);

		try {
			$status = $this->get('/contact-messages', array( 'period' => array( '2026-01' ) ))->get_status();
		} finally {
			restore_error_handler();
		}

		$this->assertSame(400, $status);
		$this->assertSame(array(), $caught, 'an array period must not trigger a PHP warning/notice');
	}

	/**
	 * MySQL 8 (strict) raises "Incorrect DATETIME value: ''" when an empty
	 * string meets post_date -- even behind a self-neutralising `'' = '' OR`
	 * -- and the whole list query fails; MariaDB only warns, so the local
	 * suite stayed green while CI returned 0 rows. Pin the SQL shape itself
	 * so a MariaDB run catches the regression too.
	 */
	public function test_list_sql_never_compares_post_date_with_an_empty_string(): void
	{
		wp_set_current_user($this->admin);
		$this->contact();

		$queries = array();
		$spy     = static function ($sql) use (&$queries) {
			$queries[] = $sql;
			return $sql;
		};
		add_filter('query', $spy);
		$response = $this->get('/contact-messages');
		remove_filter('query', $spy);

		$list_sql = array_values(array_filter($queries, static fn($q) => str_contains($q, 'p.post_date')));
		$this->assertNotSame(array(), $list_sql, 'the list query must have run');
		foreach ($list_sql as $sql) {
			$this->assertDoesNotMatchRegularExpression("/post_date\\s*[<>]=?\\s*''/", $sql);
		}
		$this->assertSame(1, $response->get_data()['total']);
	}

	/** R-12: December 9999's exclusive upper bound is 10000-01-01, which MySQL 8 rejects. */
	public function test_period_outside_years_1000_to_9998_is_a_400(): void
	{
		wp_set_current_user($this->admin);
		$this->assertSame(400, $this->get('/contact-messages', array( 'period' => '9999-12' ))->get_status());
		$this->assertSame(400, $this->get('/contact-messages', array( 'period' => '0999-01' ))->get_status());
		$this->assertSame(200, $this->get('/contact-messages', array( 'period' => '9998-12' ))->get_status());
		$this->assertSame(200, $this->get('/contact-messages', array( 'period' => '1000-01' ))->get_status());
	}

	public function test_a_month_period_lists_exactly_that_month(): void
	{
		wp_set_current_user($this->admin);
		$this->contact(array( 'name' => 'Before' ), array( 'post_date' => '2026-07-31 23:59:59' ));
		$first = $this->contact(array( 'name' => 'First' ), array( 'post_date' => '2026-08-01 00:00:00' ));
		$last  = $this->contact(array( 'name' => 'Last' ), array( 'post_date' => '2026-08-31 23:59:59' ));
		$this->contact(array( 'name' => 'After' ), array( 'post_date' => '2026-09-01 00:00:00' ));

		$ids = wp_list_pluck($this->get('/contact-messages', array( 'period' => '2026-08' ))->get_data()['items'], 'id');
		sort($ids);
		$this->assertSame(array( $first, $last ), $ids);
	}

	public function test_a_seven_day_period_lists_only_the_last_seven_days(): void
	{
		wp_set_current_user($this->admin);
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- post_date is local time; the fixture must use the same clock as the repository.
		$now    = (int) current_time('timestamp');
		$recent = $this->contact(array( 'name' => 'Recent' ), array( 'post_date' => gmdate('Y-m-d H:i:s', $now - DAY_IN_SECONDS) ));
		$this->contact(array( 'name' => 'Old' ), array( 'post_date' => gmdate('Y-m-d H:i:s', $now - 8 * DAY_IN_SECONDS) ));

		$ids = wp_list_pluck($this->get('/contact-messages', array( 'period' => '7d' ))->get_data()['items'], 'id');
		$this->assertSame(array( $recent ), $ids);
	}

	public function test_a_stored_attachment_gets_a_download_url_and_its_size(): void
	{
		wp_set_current_user($this->admin);
		$record = ContactAttachmentStore::store_upload($this->fixture($this->sandbox . '/in', 'pdf'), 'offer.pdf', $this->sideload());
		$id     = $this->contact();
		ContactAttachmentStore::attach($id, $record);

		$row = $this->get('/contact-messages')->get_data()['items'][0];
		$this->assertTrue($row['has_attachment']);
		$a = $this->get('/contact-messages/' . $id)->get_data()['attachment'];
		$this->assertSame('offer.pdf', $a['name']);
		$this->assertSame($record['size'], $a['size']);
		$this->assertSame(ContactAttachmentStore::download_url($id), $a['download_url']);
	}

	/** R-6: a leftover pre-4.4.1 URL is named but never linked. */
	public function test_a_legacy_url_is_listed_without_a_link(): void
	{
		wp_set_current_user($this->admin);
		$id = $this->contact(array( 'attachment' => trailingslashit(wp_upload_dir()['baseurl']) . '2026/08/a.pdf' ));
		$a  = $this->get('/contact-messages/' . $id)->get_data()['attachment'];
		$this->assertSame('a.pdf', $a['name']);
		$this->assertNull($a['size']);
		$this->assertNull($a['download_url']);
	}

	public function test_a_record_whose_file_is_gone_has_no_link(): void
	{
		wp_set_current_user($this->admin);
		$record = ContactAttachmentStore::store_upload($this->fixture($this->sandbox . '/in', 'pdf'), 'offer.pdf', $this->sideload());
		$id     = $this->contact();
		ContactAttachmentStore::attach($id, $record);
		ContactAttachmentStore::discard($record);
		$this->assertNull($this->get('/contact-messages/' . $id)->get_data()['attachment']['download_url']);
	}

	public function test_a_stored_attachment_carries_its_type_and_size_labels(): void
	{
		wp_set_current_user($this->admin);
		$record = ContactAttachmentStore::store_upload($this->fixture($this->sandbox . '/in', 'pdf'), 'offer.pdf', $this->sideload());
		$id     = $this->contact();
		ContactAttachmentStore::attach($id, $record);
		$a = $this->get('/contact-messages/' . $id)->get_data()['attachment'];
		$this->assertSame('PDF', $a['type_label']);
		$this->assertSame(size_format($record['size']), $a['size_label']);
	}

	public function test_a_legacy_attachment_has_no_size_label(): void
	{
		wp_set_current_user($this->admin);
		$id = $this->contact(array( 'attachment' => trailingslashit(wp_upload_dir()['baseurl']) . '2026/08/a.pdf' ));
		$a  = $this->get('/contact-messages/' . $id)->get_data()['attachment'];
		$this->assertNull($a['size_label']);
		$this->assertSame('PDF', $a['type_label']);
		$this->assertNull($a['download_url']);

		$bare = $this->contact(array( 'attachment' => trailingslashit(wp_upload_dir()['baseurl']) . '2026/08/noext' ));
		$this->assertSame('', $this->get('/contact-messages/' . $bare)->get_data()['attachment']['type_label']);
	}

	public function test_a_record_whose_file_is_gone_keeps_its_labels(): void
	{
		wp_set_current_user($this->admin);
		$record = ContactAttachmentStore::store_upload($this->fixture($this->sandbox . '/in', 'pdf'), 'offer.pdf', $this->sideload());
		$id     = $this->contact();
		ContactAttachmentStore::attach($id, $record);
		ContactAttachmentStore::discard($record);
		$a = $this->get('/contact-messages/' . $id)->get_data()['attachment'];
		$this->assertNull($a['download_url']);
		$this->assertSame('PDF', $a['type_label']);
	}

	public function test_the_vehicle_links_to_its_edit_screen_for_editors_only(): void
	{
		$vehicle = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_title' => 'Golf' ));
		$id      = $this->contact(array( 'vehicle_id' => $vehicle ));

		wp_set_current_user($this->admin);
		$v = $this->get('/contact-messages/' . $id)->get_data()['vehicle'];
		$this->assertSame(get_edit_post_link($vehicle, 'raw'), $v['edit_url']);
		$this->assertNotEmpty($v['edit_url']);
		$this->assertSame($v['edit_url'], $this->get('/contact-messages')->get_data()['items'][0]['vehicle']['edit_url']);

		// The routes are manage_options; a role that can manage options but not edit the vehicle is the only way to reach the null branch.
		$limited = (int) self::factory()->user->create(array( 'role' => 'subscriber' ));
		get_user_by('id', $limited)->add_cap('manage_options');
		wp_set_current_user($limited);
		$this->assertFalse(current_user_can('edit_post', $vehicle));
		$this->assertNull($this->get('/contact-messages/' . $id)->get_data()['vehicle']['edit_url']);
	}

	public function test_detail_carries_a_comma_date_label_for_the_meta_line(): void
	{
		wp_set_current_user($this->admin);
		$id   = $this->contact();
		$post = get_post($id);
		$d    = $this->get('/contact-messages/' . $id)->get_data();
		$ts   = (int) strtotime($post->post_date_gmt . ' UTC');
		$this->assertSame(wp_date(get_option('date_format') . ', ' . get_option('time_format'), $ts), $d['date_label_long']);
		$this->assertSame(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $ts), $d['date_label']);
	}

	public function test_months_lists_months_with_messages_newest_first_and_skips_trash(): void
	{
		foreach (array( '2026-09-15', '2026-08-02', '2026-08-20' ) as $d) {
			$this->contact(array(), array( 'post_date' => $d . ' 10:00:00' ));
		}
		$this->contact(array(), array( 'post_date' => '2026-07-01 10:00:00', 'post_status' => 'trash' ));
		$this->contact(array(), array( 'post_date' => '2026-06-10 10:00:00', 'post_status' => 'draft' ));

		$months = ContactMessageRepository::months();
		$this->assertSame(array( '2026-09', '2026-08' ), wp_list_pluck($months, 'value'));
		$this->assertSame(wp_date('F Y', gmmktime(12, 0, 0, 9, 1, 2026)), $months[0]['label']);
	}

	public function test_months_is_capped(): void
	{
		for ($m = 1; $m <= 14; $m++) {
			$year = $m > 12 ? 2027 : 2026;
			$this->contact(array(), array( 'post_date' => sprintf('%d-%02d-10 10:00:00', $year, $m > 12 ? $m - 12 : $m) ));
		}
		$this->assertCount(12, ContactMessageRepository::months());
		$this->assertCount(3, ContactMessageRepository::months(3));
	}

	public function test_only_the_admin_type_label_is_contextual(): void
	{
		$this->assertSame('Booking Inquiry', ContactMessagePostType::type_label('booking'));
	}
}
