<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Logs;

use MHMRentiva\Admin\Emails\PostTypes\EmailLog;
use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use WP_UnitTestCase;

/**
 * Backslash class (Codex #177): the e-mail log and the plugin log insert posts
 * and meta from unslashed values -- a subject or message with a backslash lost
 * it, and the e-mail context JSON lost its escapes (`ü` → `u00fc`, a quote
 * made it undecodable).
 */
final class LogWritesKeepBackslashTest extends WP_UnitTestCase
{
	private string $ua = '';

	public function setUp(): void
	{
		parent::setUp();
		$this->ua = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
	}

	public function tearDown(): void
	{
		$_SERVER['HTTP_USER_AGENT'] = $this->ua;
		parent::tearDown();
	}

	public function test_the_email_log_keeps_the_subject_and_the_context(): void
	{
		$context = array( 'reason' => 'günü "bakım"', 'path' => 'C:\arsiv' );

		EmailLog::handle_email_sent('booking_confirmed', 'log@example.com', true, 'Konu A\B', $context);

		$posts = get_posts(array( 'post_type' => EmailLog::TYPE, 'post_status' => 'any', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'DESC' ));
		$this->assertCount(1, $posts, 'Fixture: the e-mail log is on by default.');
		$id = $posts[0]->ID;
		$this->assertStringContainsString('Konu A\B', $posts[0]->post_title);
		$this->assertSame('Konu A\B', get_post_meta($id, '_mhmrentiva_email_subject', true));
		$this->assertSame($context, json_decode((string) get_post_meta($id, '_mhmrentiva_email_context', true), true));
	}

	public function test_the_plugin_log_keeps_the_message_and_the_user_agent(): void
	{
		// WordPress slashes $_SERVER like the other superglobals.
		$_SERVER['HTTP_USER_AGENT'] = wp_slash('Tarayici\1');

		$id = AdvancedLogger::error('Dosya C:\arsiv\1 okunamadi', array( 'detay' => 'A\B' ));

		$this->assertGreaterThan(0, $id);
		$post = get_post($id);
		$this->assertStringContainsString('Dosya C:\arsiv\1 okunamadi', $post->post_title . $post->post_content);
		$this->assertStringNotContainsString('Dosya C:arsiv1', $post->post_title . $post->post_content);
		$this->assertSame('Tarayici\1', get_post_meta($id, '_mhmrentiva_log_user_agent', true));
	}
}
