<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Security;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Read-only reader over request data whose nonce/capability checks have
 * already been performed by the caller.
 *
 * The point of this class is that it NEVER touches a superglobal. Request
 * readers that reach into $_POST from inside a shared helper force every
 * such helper to carry a `phpcs:ignore WordPress.Security.NonceVerification`
 * annotation whose justification ("verified in the caller") cannot be
 * checked by anyone reading the helper -- including WordPress.org's scanner,
 * which does not honour our annotations at all.
 *
 * By taking the array as an argument, the superglobal access moves up into
 * the handler that actually performs the nonce check, so the check and the
 * read live in the same function scope and are verifiable by inspection.
 *
 * Data is stored slashed, exactly as WordPress delivers it; each reader
 * unslashes and sanitizes for its own type.
 */
final class VerifiedRequest {

	/**
	 * @var array<string, mixed>
	 */
	private array $data;

	/**
	 * @param array<string, mixed> $data
	 */
	private function __construct(array $data)
	{
		$this->data = $data;
	}

	/**
	 * Wrap request data the caller has already authorised.
	 *
	 * Call this only after wp_verify_nonce()/check_admin_referer()/
	 * check_ajax_referer() plus the relevant current_user_can() check have
	 * passed in the calling scope.
	 *
	 * @param array<string, mixed> $request Typically $_POST.
	 */
	public static function from(array $request): self
	{
		return new self($request);
	}

	/**
	 * An empty request, for flows that have no submitted data.
	 */
	public static function none(): self
	{
		return new self(array());
	}

	public function has(string $key): bool
	{
		return isset($this->data[ $key ]);
	}

	/**
	 * Sanitized single-line text.
	 */
	public function text(string $key, string $fallback = ''): string
	{
		if (! isset($this->data[ $key ]) || is_array($this->data[ $key ])) {
			return $fallback;
		}

		return sanitize_text_field(wp_unslash( (string) $this->data[ $key ]));
	}

	/**
	 * Sanitized multi-line text, newlines preserved.
	 */
	public function textarea(string $key, string $fallback = ''): string
	{
		if (! isset($this->data[ $key ]) || is_array($this->data[ $key ])) {
			return $fallback;
		}

		return sanitize_textarea_field(wp_unslash( (string) $this->data[ $key ]));
	}

	/**
	 * Non-negative integer.
	 */
	public function int(string $key, int $fallback = 0): int
	{
		if (! isset($this->data[ $key ]) || is_array($this->data[ $key ])) {
			return $fallback;
		}

		return absint(wp_unslash( (string) $this->data[ $key ]));
	}

	/**
	 * Unslashed array value. Callers sanitize the members for their own type.
	 *
	 * @return array<mixed>
	 */
	public function arr(string $key): array
	{
		if (! isset($this->data[ $key ]) || ! is_array($this->data[ $key ])) {
			return array();
		}

		return (array) wp_unslash($this->data[ $key ]);
	}

	/**
	 * Unslashed but UNSANITIZED value, for callers that apply their own
	 * type-specific sanitizer (wp_kses_post, a field sanitize_callback, ...).
	 * Returns null when the key is absent.
	 *
	 * @return mixed
	 */
	public function raw(string $key)
	{
		if (! isset($this->data[ $key ])) {
			return null;
		}

		return wp_unslash($this->data[ $key ]);
	}

	/**
	 * Keys present in the request, for callers that iterate submitted fields.
	 *
	 * @return array<int, string>
	 */
	public function keys(): array
	{
		return array_map('strval', array_keys($this->data));
	}
}
