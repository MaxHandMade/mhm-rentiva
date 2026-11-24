<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The page behind a sign-in-free answer link when nothing answers it.
 *
 * An add-on that sends such links answers them itself and says so through the
 * `mhmrentiva_answer_page_handled` filter. When the link reaches a site where
 * nothing does (the add-on is switched off, the licence lapsed), the visitor
 * gets one plain sentence and an HTTP 410 instead of the site's front page.
 */
final class AnswerLinkFallback {
	/**
	 * The public query var that carries the answer key.
	 */
	public const QUERY_VAR = 'mhmrentiva_answer';

	/**
	 * HTTP status of the fallback page: the link is gone for good.
	 */
	public const STATUS = 410;

	public static function register(): void
	{
		add_filter('query_vars', array( self::class, 'register_query_vars' ));
		add_action('template_redirect', array( self::class, 'maybe_render' ), 99);
	}

	/**
	 * `query_vars` filter callback.
	 *
	 * @param array<int, string> $vars Public query vars.
	 * @return array<int, string>
	 */
	public static function register_query_vars(array $vars): array
	{
		$vars[] = self::QUERY_VAR;

		return array_values(array_unique($vars));
	}

	/**
	 * The document for this request, or null when the request is not an answer
	 * link or an add-on has handled it.
	 */
	public static function render(): ?string
	{
		$raw = get_query_var(self::QUERY_VAR, '');
		if (! is_string($raw) || '' === $raw) {
			return null;
		}

		/**
		 * Whether an add-on has answered this sign-in-free answer link.
		 *
		 * @param bool $handled True when the request was answered.
		 */
		if ( (bool) apply_filters('mhmrentiva_answer_page_handled', false) ) {
			return null;
		}

		$message = esc_html__('This link is no longer valid.', 'mhm-rentiva');

		return '<!DOCTYPE html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '">'
			. '<meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<meta name="robots" content="noindex, nofollow">'
			. '<title>' . $message . '</title></head>'
			. '<body style="font-family:sans-serif;max-width:32rem;margin:4rem auto;padding:0 1rem"><p>' . $message . '</p></body></html>';
	}

	/**
	 * `template_redirect`: print the page and stop, the theme never loads.
	 */
	public static function maybe_render(): void
	{
		$html = self::render();
		if (null === $html) {
			return;
		}

		status_header(self::STATUS);
		nocache_headers();
		header('Referrer-Policy: no-referrer');
		header('Content-Type: text/html; charset=' . get_bloginfo('charset'));

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts in render().
		exit;
	}
}
