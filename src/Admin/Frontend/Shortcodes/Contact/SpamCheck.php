<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Contact;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Settings\Core\SettingsCore;

/**
 * Whether a valid contact message is spam: no setup, nothing to solve for the visitor.
 * The caller saves such a message marked as spam and answers as usual, so a bot learns
 * nothing and a wrongly caught person can still be found by the site owner.
 */
final class SpamCheck {

	public const REASON_HONEYPOT   = 'honeypot';
	public const REASON_TIME       = 'time';
	public const REASON_DISALLOWED = 'disallowed';
	public const REASON_LINKS      = 'links';
	public const REASON_DUPLICATE  = 'duplicate';
	public const REASON_AKISMET    = 'akismet';

	public const DEFAULT_MAX_LINKS = 3;

	/** How long the same message from the same address counts as a repeat. */
	private const DUPLICATE_WINDOW = 10 * MINUTE_IN_SECONDS;

	/**
	 * @param array<string,string> $submission honeypot, stamp, name, email, message, ip, user_agent.
	 * @return string The first rule that matched, '' for a clean message.
	 */
	public static function reason(array $submission): string
	{
		$reason = self::local_reason($submission);

		// Only a message the local rules let through costs a request to the service.
		if ('' === $reason && self::akismet_says_spam($submission)) {
			$reason = self::REASON_AKISMET;
		}

		/**
		 * The verdict on a valid contact message.
		 *
		 * @param string               $reason     A REASON_* value or your own, '' for clean.
		 * @param array<string,string> $submission The message.
		 */
		return (string) apply_filters('mhmrentiva_contact_spam_reason', $reason, $submission);
	}

	/**
	 * @param array<string,string> $submission See reason().
	 */
	private static function local_reason(array $submission): string
	{
		$field = static fn(string $key): string => (string) ( $submission[ $key ] ?? '' );

		if ('' !== $field('honeypot')) {
			return self::REASON_HONEYPOT;
		}
		if (RenderStamp::too_fast($field('stamp'), RenderStamp::FORM_CONTACT)) {
			return self::REASON_TIME;
		}
		if (wp_check_comment_disallowed_list($field('name'), $field('email'), '', $field('message'), $field('ip'), $field('user_agent'))) {
			return self::REASON_DISALLOWED;
		}
		if (self::count_links($field('message')) > self::max_links()) {
			return self::REASON_LINKS;
		}
		if (false !== get_transient(self::duplicate_key($field('email'), $field('message')))) {
			return self::REASON_DUPLICATE;
		}

		return '';
	}

	public static function count_links(string $message): int
	{
		return (int) preg_match_all('~(?:https?://|www\.)[^\s<>"\']+~iu', $message);
	}

	public static function max_links(): int
	{
		/**
		 * Most links a contact message may hold; one more makes it spam.
		 *
		 * @param int $links The General tab's value.
		 */
		return max(0, (int) apply_filters('mhmrentiva_contact_max_links', (int) SettingsCore::get('mhmrentiva_contact_max_links', self::DEFAULT_MAX_LINKS)));
	}

	/**
	 * A message was saved as clean: the same one from the same address is a repeat for
	 * a while. After the save, so a refused attachment does not make the retry a repeat.
	 */
	public static function remember(string $email, string $message): void
	{
		set_transient(self::duplicate_key($email, $message), 1, self::DUPLICATE_WINDOW);
	}

	/**
	 * Salted hash, so the options table holds no visitor address.
	 */
	private static function duplicate_key(string $email, string $message): string
	{
		return 'mhmrentiva_contact_dup_' . wp_hash(strtolower(trim($email)) . '|' . trim($message));
	}

	/**
	 * Asks the site owner's own Akismet, through its own class: this plugin opens no
	 * connection and holds no key. No plugin or no key: nothing is asked. Any failure
	 * lets the message through, since the visitor did nothing wrong.
	 *
	 * @param array<string,string> $submission See reason().
	 */
	private static function akismet_says_spam(array $submission): bool
	{
		$http_post = array( 'Akismet', 'http_post' );
		$api_key   = array( 'Akismet', 'get_api_key' );
		if (! class_exists('Akismet') || ! is_callable($http_post) || ! is_callable($api_key)) {
			return false;
		}

		try {
			if ('' === (string) call_user_func($api_key)) {
				return false;
			}

			$response = call_user_func(
				$http_post,
				http_build_query(
					array(
						'blog'                 => get_option('home'),
						'blog_lang'            => get_locale(),
						'blog_charset'         => get_option('blog_charset'),
						'user_ip'              => (string) ( $submission['ip'] ?? '' ),
						'user_agent'           => (string) ( $submission['user_agent'] ?? '' ),
						'comment_type'         => 'contact-form',
						'comment_author'       => (string) ( $submission['name'] ?? '' ),
						'comment_author_email' => (string) ( $submission['email'] ?? '' ),
						'comment_content'      => (string) ( $submission['message'] ?? '' ),
					)
				),
				'comment-check'
			);
		} catch (\Throwable $e) {
			unset($e);
			return false;
		}

		return is_array($response) && 'true' === ( $response[1] ?? '' );
	}
}
