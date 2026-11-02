<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\ContactMessages;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The single source of a contact message's reading state.
 *
 * The form has written `_mhmrentiva_contact_status = 'new'` since 6.0.x and
 * nothing ever read it; this class is that reader. Anything unknown or missing
 * reads as `read`, so a record from before the key existed never shows up as
 * a new message.
 *
 * `spam` is not a reading state: such a record stays off every list and count until
 * the owner calls it, so it is not in ALL.
 */
final class ContactStatus {
	public const META_KEY        = '_mhmrentiva_contact_status';
	public const STATUS_NEW      = 'new';
	public const STATUS_READ     = 'read';
	public const STATUS_REPLIED  = 'replied';
	public const STATUS_SPAM     = 'spam';
	public const ALL             = array( self::STATUS_NEW, self::STATUS_READ, self::STATUS_REPLIED );
	public const BADGE_TRANSIENT = 'mhmrentiva_contact_new_count';

	/**
	 * @param mixed $value
	 */
	public static function normalize($value): string
	{
		return is_string($value) && ( self::STATUS_SPAM === $value || in_array($value, self::ALL, true) ) ? $value : self::STATUS_READ;
	}

	public static function label(string $status): string
	{
		switch (self::normalize($status)) {
			case self::STATUS_NEW:
				return __('New', 'mhm-rentiva');
			case self::STATUS_REPLIED:
				return __('Replied', 'mhm-rentiva');
			case self::STATUS_SPAM:
				return __('Spam', 'mhm-rentiva');
			default:
				return __('Read', 'mhm-rentiva');
		}
	}

	/**
	 * Why a message was caught, for the person deciding whether it really is spam.
	 */
	public static function spam_reason_label(string $reason): string
	{
		$labels = array(
			'honeypot'   => __('The hidden trap field was filled in', 'mhm-rentiva'),
			'time'       => __('Sent too soon after the form opened, or not from the form', 'mhm-rentiva'),
			'disallowed' => __('Matches the Disallowed Comment Keys list (Settings > Discussion). WordPress checks the name, e-mail, message, IP address and browser, and part of a word counts.', 'mhm-rentiva'),
			'links'      => __('Holds too many links', 'mhm-rentiva'),
			'duplicate'  => __('The same message from the same address, within 10 minutes', 'mhm-rentiva'),
			'akismet'    => __('Marked as spam by Akismet', 'mhm-rentiva'),
		);

		return $labels[ $reason ] ?? $reason;
	}

	public static function get(int $post_id): string
	{
		return self::normalize(get_post_meta($post_id, self::META_KEY, true));
	}

	public static function set(int $post_id, string $status): bool
	{
		if (! in_array($status, self::ALL, true) || 'mhmrentiva_contact' !== get_post_type($post_id)) {
			return false;
		}

		update_post_meta($post_id, self::META_KEY, $status);

		// update_post_meta()'s own return is not enough to tell a rejected write
		// from a no-op one (it also returns false when the stored value was
		// already $status) -- read the value back to know whether the write
		// actually landed before reporting success and clearing the badge.
		if (get_post_meta($post_id, self::META_KEY, true) !== $status) {
			return false;
		}

		self::forget_badge();

		return true;
	}

	public static function forget_badge(): void
	{
		delete_transient(self::BADGE_TRANSIENT);
	}
}
