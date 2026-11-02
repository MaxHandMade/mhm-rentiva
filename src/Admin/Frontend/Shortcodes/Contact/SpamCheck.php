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
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The repeat claim must be one atomic statement: get_option()/add_option() read first, and INSERT ... ON DUPLICATE KEY in add_option() overwrites. No cache is involved on purpose. Every value is bound through $wpdb->prepare().
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

	private const DUPLICATE_PREFIX = 'mhmrentiva_contact_dup_';

	/**
	 * The verdict, and the repeat claim a clean message now holds. The caller gives the
	 * claim back through release() if the message then cannot be stored, so a refused
	 * file or a failed save does not make the visitor's retry a repeat.
	 *
	 * @param array<string,mixed> $submission honeypot, stamp (null when not posted), name, email,
	 *                                        message, ip, user_agent, context (what else makes this request this request).
	 * @return array{reason:string,claim:string} The rule that matched ('' for clean), and the claim ('' for none).
	 */
	public static function verdict(array $submission): array
	{
		$reason = self::local_reason($submission);
		$claim  = '';

		if ('' === $reason) {
			$key = self::duplicate_key($submission);
			if (self::claim($key)) {
				$claim = $key;
			} else {
				$reason = self::REASON_DUPLICATE;
			}
		}

		// Only a message the local rules let through costs a request to the service.
		if ('' === $reason && self::akismet_says_spam($submission)) {
			$reason = self::REASON_AKISMET;
		}

		/**
		 * The verdict on a valid contact message.
		 *
		 * @param string              $reason     A REASON_* value or your own, '' for clean.
		 * @param array<string,mixed> $submission The message.
		 */
		$reason = (string) apply_filters('mhmrentiva_contact_spam_reason', $reason, $submission);

		// A caught message starts no repeat window.
		if ('' !== $reason && '' !== $claim) {
			self::release($claim);
			$claim = '';
		}

		return array(
			'reason' => $reason,
			'claim'  => $claim,
		);
	}

	/**
	 * @param array<string,mixed> $submission See verdict().
	 */
	private static function local_reason(array $submission): string
	{
		$field = static fn(string $key): string => (string) ( $submission[ $key ] ?? '' );

		if ('' !== $field('honeypot')) {
			return self::REASON_HONEYPOT;
		}
		$stamp = $submission['stamp'] ?? null;
		if (RenderStamp::too_fast(null === $stamp ? null : (string) $stamp, RenderStamp::FORM_CONTACT)) {
			return self::REASON_TIME;
		}
		if (wp_check_comment_disallowed_list($field('name'), $field('email'), '', $field('message'), $field('ip'), $field('user_agent'))) {
			return self::REASON_DISALLOWED;
		}
		if (self::count_links($field('message')) > self::max_links()) {
			return self::REASON_LINKS;
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
	 * Takes the repeat window for this exact request, or says someone holds it.
	 *
	 * One INSERT IGNORE on the options table's unique name is what makes two requests at
	 * once unable to both pass: read-then-write through transients can, and a transient
	 * may also vanish early. The value is the expiry; an expired row is taken over by a
	 * compare-and-swap. A database that refuses the write lets the message through.
	 */
	private static function claim(string $key): bool
	{
		global $wpdb;

		$now     = time();
		$expiry  = (string) ( $now + self::DUPLICATE_WINDOW );
		$created = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $key, $expiry));

		if (1 === $created) {
			$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d LIMIT 50", $wpdb->esc_like(self::DUPLICATE_PREFIX) . '%', $now));
			return true;
		}
		if (false === $created) {
			return true;
		}

		$held = (string) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key));
		if ( (int) $held > $now) {
			return false;
		}

		return 1 === $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $expiry, $key, $held));
	}

	/**
	 * Gives a claim back: the message it was taken for was not stored.
	 */
	public static function release(string $claim): void
	{
		global $wpdb;

		if ('' !== $claim) {
			$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s", $claim));
		}
	}

	/**
	 * The same words about another car, date, booking or file are another request, and
	 * must reach the owner. A salted hash, so the options table holds no visitor address.
	 *
	 * @param array<string,mixed> $submission See verdict().
	 */
	private static function duplicate_key(array $submission): string
	{
		$fingerprint = array(
			strtolower(trim( (string) ( $submission['email'] ?? '' ))),
			trim( (string) ( $submission['message'] ?? '' )),
			$submission['context'] ?? array(),
		);

		return self::DUPLICATE_PREFIX . wp_hash( (string) wp_json_encode($fingerprint));
	}

	/**
	 * Asks the site owner's own Akismet, through its own class: this plugin opens no
	 * connection and holds no key. No plugin or no key: nothing is asked. Any failure
	 * lets the message through, since the visitor did nothing wrong.
	 *
	 * @param array<string,mixed> $submission See verdict().
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
