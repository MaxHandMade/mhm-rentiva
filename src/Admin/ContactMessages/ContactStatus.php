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
 */
final class ContactStatus {
	public const META_KEY        = '_mhmrentiva_contact_status';
	public const STATUS_NEW      = 'new';
	public const STATUS_READ     = 'read';
	public const STATUS_REPLIED  = 'replied';
	public const ALL             = array( self::STATUS_NEW, self::STATUS_READ, self::STATUS_REPLIED );
	public const BADGE_TRANSIENT = 'mhmrentiva_contact_new_count';

	/**
	 * @param mixed $value
	 */
	public static function normalize($value): string
	{
		return is_string($value) && in_array($value, self::ALL, true) ? $value : self::STATUS_READ;
	}

	public static function label(string $status): string
	{
		switch (self::normalize($status)) {
			case self::STATUS_NEW:
				return __('New', 'mhm-rentiva');
			case self::STATUS_REPLIED:
				return __('Replied', 'mhm-rentiva');
			default:
				return __('Read', 'mhm-rentiva');
		}
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
		self::forget_badge();

		return true;
	}

	public static function forget_badge(): void
	{
		delete_transient(self::BADGE_TRANSIENT);
	}
}
