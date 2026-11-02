<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Contact;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Settings\Core\SettingsCore;

/**
 * When a public form was drawn, carried through the page and signed: a person needs
 * a moment to fill it in, a bot posts at once.
 *
 * `<unix time>.<hmac>`, keyed from the site's auth salt like RecipientToken, and bound
 * to the form it was drawn for. There is no expiry: a tab left open for a day is a
 * person, and a page from a cache is only older. A saved stamp can be replayed; that
 * is the limit of this layer, and the other rules still read the message.
 */
final class RenderStamp {

	/** The hidden field. Not a word browsers autofill. */
	public const FIELD = 'rvs_rendered';

	public const FORM_CONTACT = 'contact';
	public const FORM_REVIEW  = 'review';

	public const DEFAULT_MIN_SECONDS = 3;

	/**
	 * @param string   $form FORM_CONTACT or FORM_REVIEW.
	 * @param int|null $at   Render time; now when omitted.
	 */
	public static function issue(string $form, ?int $at = null): string
	{
		$time = (string) ( $at ?? time() );

		return $time . '.' . self::sign($time, $form);
	}

	/**
	 * Seconds since the render, or null when the stamp is absent, forged, of another
	 * form or from the future.
	 */
	public static function age(string $stamp, string $form): ?int
	{
		if (1 !== preg_match('/^(\d{1,12})\.([a-f0-9]{64})$/', $stamp, $parts)) {
			return null;
		}
		if (! hash_equals(self::sign($parts[1], $form), $parts[2])) {
			return null;
		}

		$age = self::arrival() - (int) $parts[1];

		return $age < 0 ? null : $age;
	}

	/**
	 * When the submit reached the server, not when this code runs: on a slow site the
	 * request's own start-up would otherwise count as the visitor's time, and an instant
	 * bot would pass (measured: 2 s of a 3 s minimum). A start older than half a minute
	 * is no web request's, so the clock decides there.
	 */
	private static function arrival(): int
	{
		$now     = time();
		$arrived = isset($_SERVER['REQUEST_TIME']) ? (int) $_SERVER['REQUEST_TIME'] : $now;

		return $now - $arrived > 30 ? $now : $arrived;
	}

	/**
	 * Whether this submit came too soon, or without a stamp the site signed. A minimum
	 * of 0 turns the trap off.
	 */
	public static function too_fast(string $stamp, string $form): bool
	{
		$minimum = self::min_seconds();
		if ($minimum <= 0) {
			return false;
		}

		$age = self::age($stamp, $form);

		return null === $age || $age < $minimum;
	}

	public static function min_seconds(): int
	{
		/**
		 * Seconds a public form must be open before a submit counts as a person's.
		 *
		 * @param int $seconds The General tab's value; 0 turns the trap off.
		 */
		return max(0, (int) apply_filters('mhmrentiva_contact_min_seconds', (int) SettingsCore::get('mhmrentiva_contact_min_seconds', self::DEFAULT_MIN_SECONDS)));
	}

	private static function sign(string $time, string $form): string
	{
		return hash_hmac('sha256', $form . '|' . $time, hash_hmac('sha256', 'contact-render-stamp', wp_salt('auth')));
	}
}
