<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use MHMRentiva\Admin\Emails\Core\Templates;
use WP_UnitTestCase;

/**
 * PR 3b-3 T2: the booking e-mail box edits the inner part of a template
 * (editable_body) and the send wraps it once with render_body()'s own filter
 * order for that branch (send_body). Unedited, the result is render_body().
 * Plan r4 table: docs/superpowers/plans/2026-11-08-pr3b-3-musteriye-eposta.md.
 */
final class EditableBodyTest extends WP_UnitTestCase
{
	private array $ctx = array(
		'booking'  => array( 'id' => 7, 'order_id' => 7, 'pickup_date' => '20.11.2026', 'return_date' => '23.11.2026', 'status' => 'confirmed' ),
		'customer' => array( 'name' => 'Ali Kaya', 'salutation_name' => 'Ali Kaya', 'email' => 'ali@example.com' ),
		'vehicle'  => array( 'title' => 'Fiat Egea' ),
		'site'     => array( 'name' => 'Rentiva', 'url' => 'https://example.org/' ),
	);

	public function tearDown(): void
	{
		delete_option('mhmrentiva_booking_created_body');
		remove_all_filters('mhmrentiva_email_body');
		remove_all_filters('mhmrentiva_email_body_booking_created_customer');
		remove_all_filters('mhmrentiva_email_context');
		parent::tearDown();
	}

	/** @return array<string, array{0: string}> */
	public static function unedited_keys(): array
	{
		return array(
			'default body'      => array( 'booking_created_customer' ),
			'reminder default'  => array( 'booking_reminder_customer' ),
			'cancelled default' => array( 'booking_cancelled' ),
			'file template'     => array( 'booking_status_changed_customer' ),
		);
	}

	/** @dataProvider unedited_keys */
	public function test_an_unedited_body_sends_exactly_what_render_body_sends(string $key): void
	{
		$fragment = Templates::editable_body($key, $this->ctx);

		$this->assertStringNotContainsString('<html', $fragment, 'The editor gets the inner part.');
		$this->assertSame(Templates::render_body($key, $this->ctx), Templates::send_body($key, $this->ctx, $fragment));
	}

	public function test_a_saved_fragment_is_edited_without_the_shell(): void
	{
		update_option('mhmrentiva_booking_created_body', '<p>Merhaba {salutation_name}</p>');

		$this->assertSame('<p>Merhaba Ali Kaya</p>', Templates::editable_body('booking_created_customer', $this->ctx));
	}

	public function test_a_saved_full_document_gives_its_body(): void
	{
		update_option('mhmrentiva_booking_created_body', '<html><head><title>x</title></head><body class="m"><p>Tam belge</p></body></html>');

		$this->assertSame('<p>Tam belge</p>', Templates::editable_body('booking_created_customer', $this->ctx));
	}

	public function test_a_saved_body_only_document_gives_its_body(): void
	{
		update_option('mhmrentiva_booking_created_body', '<body><p>Yalnız gövde</p></body>');

		$this->assertSame('<p>Yalnız gövde</p>', Templates::editable_body('booking_created_customer', $this->ctx));
	}

	public function test_an_edited_body_is_wrapped_once_with_the_override_filters_in_order(): void
	{
		$seen = array();
		add_filter('mhmrentiva_email_body', function ($html) use (&$seen) { $seen[] = 'body:' . ( false !== stripos($html, '<html') ? 'wrapped' : 'inner' ); return $html; });
		add_filter('mhmrentiva_email_body_booking_created_customer', function ($html) use (&$seen) { $seen[] = 'key'; return $html; });

		$html = Templates::send_body('booking_created_customer', $this->ctx, '<p>Düzenlendi</p>');

		$this->assertSame(1, substr_count(strtolower($html), '<html'), 'Wrapped exactly once.');
		$this->assertStringContainsString('Düzenlendi', $html);
		$this->assertSame(array( 'body:wrapped', 'key' ), $seen);
	}

	public function test_the_file_branch_applies_the_context_filters_before_the_file(): void
	{
		add_filter('mhmrentiva_email_context', function ($ctx) { $ctx['customer']['name'] = 'Filtreli Ad'; $ctx['customer']['salutation_name'] = 'Filtreli Ad'; return $ctx; });

		$fragment = Templates::editable_body('booking_status_changed_customer', $this->ctx);

		$this->assertStringContainsString('Filtreli Ad', $fragment);
		$this->assertSame(Templates::render_body('booking_status_changed_customer', $this->ctx), Templates::send_body('booking_status_changed_customer', $this->ctx, $fragment));
	}
}
