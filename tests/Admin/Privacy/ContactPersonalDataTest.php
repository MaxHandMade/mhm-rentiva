<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Privacy;

use MHMRentiva\Admin\ContactMessages\ContactAttachmentStore;
use MHMRentiva\Admin\Privacy\ContactPersonalData;
use MHMRentiva\Admin\Privacy\ErasureMarker;
use WP_UnitTestCase;

/**
 * Contact form messages in the core privacy tools (5a spec T7, T4 lifecycle).
 *
 * @covers \MHMRentiva\Admin\Privacy\ContactPersonalData
 * @covers \MHMRentiva\Admin\Privacy\PostQueue
 */
final class ContactPersonalDataTest extends WP_UnitTestCase
{
	/** @var callable|null */
	private $block = null;

	public function tearDown(): void
	{
		if (null !== $this->block) {
			remove_filter('pre_delete_post', $this->block, 10);
			remove_filter('update_post_metadata', $this->block, 10);
			remove_filter('add_post_metadata', $this->block, 10);
		}
		parent::tearDown();
	}

	private function message(string $email, array $extra = array()): int
	{
		return (int) self::factory()->post->create(array(
			'post_type'    => 'mhmrentiva_contact',
			'post_status'  => 'private',
			'post_title'   => 'Contact Message - Ahmet',
			'post_content' => 'Do you have a van?',
			'meta_input'   => array_merge(array(
				'_mhmrentiva_contact_name'       => 'Ahmet Yılmaz',
				'_mhmrentiva_contact_email'      => $email,
				'_mhmrentiva_contact_phone'      => '+90 555',
				'_mhmrentiva_contact_company'    => 'Acme',
				'_mhmrentiva_contact_type'       => 'general',
				'_mhmrentiva_contact_ip_address' => '203.0.113.7',
				'_mhmrentiva_contact_user_agent' => 'TestBrowser/1.0',
				'_mhmrentiva_contact_timestamp'  => '2026-11-01 10:00:00',
				'_mhmrentiva_contact_consent'    => '2026-11-01 10:00:00',
			), $extra),
		));
	}

	private function stored_file(): array
	{
		$tmp = wp_tempnam('contact');
		file_put_contents($tmp, '%PDF-1.4 test');
		$record = ContactAttachmentStore::store_copy($tmp, 'licence.pdf', 'application/pdf');
		$this->assertIsArray($record);

		return $record;
	}

	public function test_export_includes_ip_user_agent_consent_and_attachment_name(): void
	{
		$id = $this->message(' Guest@Example.TEST ');
		ContactAttachmentStore::attach($id, $this->stored_file());
		$this->message('other@example.test');

		$result = ContactPersonalData::export('guest@example.test', 1);

		$this->assertTrue($result['done']);
		$this->assertCount(1, $result['data']);
		$this->assertSame('mhm-rentiva-contact', $result['data'][0]['group_id']);
		$this->assertSame('contact-' . $id, $result['data'][0]['item_id']);
		$all = implode("\n", array_column($result['data'][0]['data'], 'value'));
		foreach (array('Ahmet Yılmaz', '+90 555', 'Acme', 'Do you have a van?', '203.0.113.7', 'TestBrowser/1.0', 'licence.pdf') as $needle) {
			$this->assertStringContainsString($needle, $all, $needle);
		}
	}

	public function test_erase_deletes_post_and_private_file(): void
	{
		$id     = $this->message('guest@example.test');
		$record = $this->stored_file();
		ContactAttachmentStore::attach($id, $record);
		$path = ContactAttachmentStore::path($record);
		$this->assertNotNull($path);
		$other = $this->message('other@example.test');

		$result = ContactPersonalData::erase('guest@example.test', 1);

		$this->assertTrue($result['items_removed']);
		$this->assertFalse($result['items_retained']);
		$this->assertTrue($result['done']);
		$this->assertNull(get_post($id));
		$this->assertFileDoesNotExist($path);
		$this->assertNotNull(get_post($other));
	}

	/**
	 * The file goes first and is checked on disk; while it stays, the message
	 * (the only thing pointing at it) stays too, marked (spec r5 #3).
	 */
	public function test_a_file_that_will_not_go_keeps_the_message(): void
	{
		$id     = $this->message('guest@example.test');
		$record = $this->stored_file();
		ContactAttachmentStore::attach($id, $record);
		$path = ContactAttachmentStore::path($record);
		$keep = static fn() => '';
		add_filter('wp_delete_file', $keep);

		$result = ContactPersonalData::erase('guest@example.test', 1);

		remove_filter('wp_delete_file', $keep);
		$this->assertNotNull(get_post($id));
		$this->assertFileExists($path);
		$this->assertTrue($result['items_retained']);
		$this->assertNotSame('', get_post_meta($id, ErasureMarker::KEY, true));
		ContactAttachmentStore::discard($record);
	}

	public function test_undeletable_post_is_marked_and_the_queue_moves_on(): void
	{
		$stuck = $this->message('guest@example.test');
		$gone  = $this->message('guest@example.test');
		$this->block = static fn($check, $post) => ((int) $post->ID === $stuck) ? false : $check;
		add_filter('pre_delete_post', $this->block, 10, 2);

		$result = ContactPersonalData::erase('guest@example.test', 1);

		$this->assertTrue($result['done']);
		$this->assertTrue($result['items_retained']);
		$this->assertNotNull(get_post($stuck));
		$this->assertNotSame('', get_post_meta($stuck, ErasureMarker::KEY, true));
		$this->assertNull(get_post($gone));
	}

	public function test_unmarkable_failure_stops_with_done(): void
	{
		$stuck = $this->message('guest@example.test');
		$next  = $this->message('guest@example.test');
		$this->block = static function ($check, $a, $b = null) use ($stuck) {
			if ($a instanceof \WP_Post) {
				return ((int) $a->ID === $stuck) ? false : $check;
			}

			return ((int) $a === $stuck && ErasureMarker::KEY === $b) ? false : $check;
		};
		add_filter('pre_delete_post', $this->block, 10, 2);
		add_filter('update_post_metadata', $this->block, 10, 3);
		add_filter('add_post_metadata', $this->block, 10, 3);

		$result = ContactPersonalData::erase('guest@example.test', 1);

		$this->assertTrue($result['done']);
		$this->assertNotNull(get_post($next));
	}
}
