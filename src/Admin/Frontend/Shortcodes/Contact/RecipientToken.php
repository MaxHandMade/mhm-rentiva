<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Contact;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Frontend\Shortcodes\ContactMessagePostType;

/**
 * The contact form's per-form recipient (`email_to`), carried through the page.
 *
 * The submit AJAX sees only the POST, and trusting an address posted there would
 * make the form an open relay. So the address leaves the server only inside a
 * sodium secretbox payload {email, post_id, type} (encrypted and authenticated,
 * random nonce in front, base64) and comes back as an opaque hidden field.
 *
 * resolve() then re-checks what the payload claims against the site as it is now:
 * the source post must still exist, its author must still hold `edit_others_posts`
 * (a contributor or author could otherwise route visitors' messages to themselves),
 * the address must still be on that post (its content for a block or shortcode,
 * its `_elementor_data` for an Elementor document), the type must be the submitted
 * form's own, the address must be valid. There is no expiry: binding to the post is
 * the revocation (remove `email_to`, delete the post or demote the author and the
 * payload stops working), and a cached page stays usable for as long as the AJAX
 * nonce does.
 *
 * Every refusal falls back to the global recipient and says so: the
 * `mhmrentiva_contact_recipient_fallback` action, plus an error log line when
 * WP_DEBUG_LOG is on (the AJAX nonce is public, so the log must not be floodable).
 *
 * Spec 2026-10-10-elementor-kopru-dilim-3a §2.5 (a)-(d), R3.
 */
final class RecipientToken {

	/**
	 * Encrypt the recipient for one form on one source post.
	 *
	 * @param string $email   The `email_to` address as configured.
	 * @param int    $post_id The source post (ContactForm::resolve_source_post_id()); 0 when there is none.
	 * @param string $type    The form's normalized type.
	 * @return string base64( nonce . ciphertext ).
	 */
	public static function issue(string $email, int $post_id, string $type): string
	{
		$payload = (string) wp_json_encode(
			array(
				'email'   => $email,
				'post_id' => $post_id,
				'type'    => $type,
			)
		);
		$nonce   = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

		return base64_encode($nonce . sodium_crypto_secretbox($payload, $nonce, self::key())); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- transport encoding of an encrypted payload.
	}

	/**
	 * The address the payload vouches for, or null (and a fallback notice) when it
	 * does not vouch for anything any more.
	 *
	 * @param string $token           The posted hidden field.
	 * @param string $normalized_type The submitted form's type after sanitization.
	 */
	public static function resolve(string $token, string $normalized_type): ?string
	{
		$raw = base64_decode($token, true); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- transport encoding of an encrypted payload.
		if (false === $raw) {
			return self::fallback('invalid_base64');
		}
		if (strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
			return self::fallback('too_short');
		}

		try {
			$plain = sodium_crypto_secretbox_open(
				substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
				substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
				self::key()
			);
		} catch (\SodiumException $e) {
			// Wrong lengths throw rather than return false; the submit handler's own
			// catch would turn that into a message that is never sent (Fable r3 I-1).
			unset($e);
			return self::fallback('sodium_error');
		}
		if (false === $plain) {
			return self::fallback('auth_failed');
		}

		$payload = json_decode($plain, true);
		if (! is_array($payload) || ! is_string($payload['email'] ?? null) || ! is_int($payload['post_id'] ?? null) || ! is_string($payload['type'] ?? null)) {
			return self::fallback('malformed');
		}

		if (! in_array($payload['type'], ContactMessagePostType::TYPES, true) || $payload['type'] !== $normalized_type) {
			return self::fallback('type_mismatch');
		}

		// get_post( 0 ) would answer with the current global post: never let it.
		$post = $payload['post_id'] > 0 ? get_post($payload['post_id']) : null;
		if (! $post instanceof \WP_Post || 'trash' === $post->post_status) {
			return self::fallback('missing_post');
		}

		$author = (int) $post->post_author;
		if ($author <= 0 || ! user_can($author, 'edit_others_posts')) {
			return self::fallback('author_not_allowed');
		}

		if (! self::still_on_post($payload['email'], $post)) {
			return self::fallback('recipient_removed');
		}

		if (! is_email($payload['email'])) {
			return self::fallback('invalid_email');
		}

		return $payload['email'];
	}

	/**
	 * Whether the source post still configures $email: in its content (shortcode
	 * text, or a block attribute JSON-encoded in the block comment) or in its
	 * Elementor data (JSON). Each form the address can take there is looked for.
	 *
	 * @param string   $email The address the payload carries.
	 * @param \WP_Post $post  The source post.
	 */
	private static function still_on_post(string $email, \WP_Post $post): bool
	{
		if ('' === $email) {
			return false;
		}

		$haystack = (string) $post->post_content . "\n" . (string) get_post_meta($post->ID, '_elementor_data', true);

		// As typed, or entity-encoded (kses runs over block attributes when the saving
		// user lacks unfiltered_html: '&' is stored as '&amp;'); each one raw, as the
		// JSON encoder writes it (Elementor: '/' and non-ASCII escaped) and as a block
		// comment writes it (serialize_block_attributes() also escapes - < > & ").
		foreach (array_unique(array( $email, esc_html($email) )) as $base) {
			$forms = array(
				$base,
				substr( (string) wp_json_encode($base), 1, -1),
				substr(serialize_block_attributes(array( $base )), 2, -2),
			);
			foreach (array_unique($forms) as $form) {
				if ('' !== $form && str_contains($haystack, $form)) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * 32 bytes from the site's auth salt: secret per site, never stored.
	 */
	private static function key(): string
	{
		return hash('sha256', wp_salt('auth'), true);
	}

	/**
	 * Announce a refused payload; the caller then uses the global recipient.
	 *
	 * @param string $reason Short machine-readable reason.
	 * @return null
	 */
	private static function fallback(string $reason)
	{
		/**
		 * A contact form submission carried a recipient payload that no longer
		 * vouches for its address; the message goes to the global recipient.
		 *
		 * @param string $reason invalid_base64 | too_short | sodium_error | auth_failed |
		 *                       malformed | type_mismatch | missing_post |
		 *                       author_not_allowed | recipient_removed | invalid_email.
		 */
		do_action('mhmrentiva_contact_recipient_fallback', $reason);

		// Anyone with the public AJAX nonce can post refused payloads: log only on a
		// site that asked for a debug log, so the PHP log cannot be flooded.
		if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
			error_log('MHM Rentiva contact form: recipient payload refused (' . $reason . '); using the global recipient.'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- spec 3A §2.5 (c): the fallback must not be silent; gated on WP_DEBUG_LOG.
		}

		return null;
	}
}
