<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\ContactMessages\ContactStatus;
use MHMRentiva\Admin\ContactMessages\REST\ContactMessagesRestController;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

final class ContactMessagesReadRestTest extends WP_UnitTestCase
{
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
	}

	public function tearDown(): void
	{
		global $wp_rest_server;
		$wp_rest_server = null;
		remove_action('rest_api_init', array( ContactMessagesRestController::class, 'register_routes' ));
		wp_set_current_user(0);
		parent::tearDown();
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
}
