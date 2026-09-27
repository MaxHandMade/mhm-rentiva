<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\ContactMessages\ContactStatus;
use MHMRentiva\Admin\ContactMessages\REST\ContactMessagesRestController;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

final class ContactMessagesWriteRestTest extends WP_UnitTestCase
{
	private WP_REST_Server $server;

	public function setUp(): void
	{
		parent::setUp();
		add_action('rest_api_init', array( ContactMessagesRestController::class, 'register_routes' ));
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action('rest_api_init', $this->server);
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
	}

	public function tearDown(): void
	{
		global $wp_rest_server;
		$wp_rest_server = null;
		remove_action('rest_api_init', array( ContactMessagesRestController::class, 'register_routes' ));
		wp_set_current_user(0);
		parent::tearDown();
	}

	private function contact(string $status = 'new'): int
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_contact', 'post_status' => 'private' ));
		update_post_meta($id, ContactStatus::META_KEY, $status);
		return $id;
	}

	private function send(string $method, string $path, array $body = array())
	{
		$r = new WP_REST_Request($method, '/mhm-rentiva/v1' . $path);
		foreach ($body as $k => $v) {
			$r->set_param($k, $v);
		}
		return $this->server->dispatch($r);
	}

	public function test_status_and_read(): void
	{
		$id = $this->contact('new');
		$this->assertSame('replied', $this->send('POST', "/contact-messages/$id/status", array( 'status' => 'replied' ))->get_data()['status']);
		$this->assertSame(400, $this->send('POST', "/contact-messages/$id/status", array( 'status' => 'urgent' ))->get_status());

		$other = $this->contact('new');
		$this->assertSame('read', $this->send('POST', "/contact-messages/$other/read")->get_data()['status']);
		$this->assertSame('replied', $this->send('POST', "/contact-messages/$id/read")->get_data()['status'], 'read is a no-op unless new');
	}

	public function test_anonymous_writes_are_401(): void
	{
		$id = $this->contact();
		wp_set_current_user(0);
		$this->assertSame(401, $this->send('POST', "/contact-messages/$id/read")->get_status());
		$this->assertSame(401, $this->send('POST', '/contact-messages/bulk', array( 'ids' => array( $id ), 'action' => 'read' ))->get_status());
		$this->assertSame(401, $this->send('DELETE', "/contact-messages/$id")->get_status());
	}

	public function test_editor_writes_are_403(): void
	{
		$id = $this->contact();
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'editor' )));
		$this->assertSame(403, $this->send('POST', "/contact-messages/$id/read")->get_status());
	}

	public function test_bulk_read_does_not_downgrade_replied_but_promotes_new(): void
	{
		$replied = $this->contact('replied');
		$new     = $this->contact('new');

		$res = $this->send('POST', '/contact-messages/bulk', array( 'ids' => array( $replied, $new ), 'action' => 'read' ))->get_data()['results'];

		$this->assertTrue($res[0]['ok']);
		$this->assertSame('replied', ContactStatus::get($replied), 'bulk read must not downgrade an already-replied message');
		$this->assertTrue($res[1]['ok']);
		$this->assertSame('read', ContactStatus::get($new));
	}

	public function test_trash_then_restore_returns_to_private(): void
	{
		$id = $this->contact();
		$this->assertTrue($this->send('DELETE', "/contact-messages/$id")->get_data()['trashed']);
		$this->assertSame('trash', get_post_status($id));
		$this->assertSame('private', get_post_meta($id, '_wp_trash_meta_status', true));

		$r = $this->send('POST', '/contact-messages/bulk', array( 'ids' => array( $id ), 'action' => 'restore' ));
		$this->assertTrue($r->get_data()['results'][0]['ok']);
		$this->assertSame('private', get_post_status($id));
		$this->assertFalse(has_filter('wp_untrash_post_status', 'wp_untrash_post_set_previous_status'), 'the filter is scoped to the restore call only');
	}

	public function test_untrash_refuses_a_trashed_post_of_another_type(): void
	{
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
		wp_trash_post($booking);
		$this->assertFalse(ContactMessagesRestController::untrash($booking));
	}

	public function test_force_delete_only_from_trash(): void
	{
		$id = $this->contact();
		$this->assertSame(400, $this->send('DELETE', "/contact-messages/$id", array( 'force' => true ))->get_status());
		$this->assertSame('private', get_post_status($id));
		wp_trash_post($id);
		$this->assertTrue($this->send('DELETE', "/contact-messages/$id", array( 'force' => true ))->get_data()['deleted']);
		$this->assertNull(get_post($id));
	}

	public function test_bulk_limits_and_mixed_results(): void
	{
		$seeded = $this->contact('new');
		$ids    = range(1, 101);
		$ids[0] = $seeded;
		$this->assertSame(400, $this->send('POST', '/contact-messages/bulk', array( 'ids' => $ids, 'action' => 'read' ))->get_status());
		$this->assertSame('new', ContactStatus::get($seeded), 'a request rejected for exceeding the limit must not touch any of its ids');
		$this->assertSame('private', get_post_status($seeded));

		$ok      = $this->contact('new');
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
		$res     = $this->send('POST', '/contact-messages/bulk', array( 'ids' => array( $ok, $booking ), 'action' => 'replied' ))->get_data()['results'];
		$this->assertTrue($res[0]['ok']);
		$this->assertFalse($res[1]['ok']);
		$this->assertSame('replied', ContactStatus::get($ok));
	}

	public function test_bulk_delete_refuses_live_records(): void
	{
		$id  = $this->contact();
		$res = $this->send('POST', '/contact-messages/bulk', array( 'ids' => array( $id ), 'action' => 'delete' ))->get_data()['results'];
		$this->assertFalse($res[0]['ok']);
		$this->assertSame('private', get_post_status($id));
	}
}
