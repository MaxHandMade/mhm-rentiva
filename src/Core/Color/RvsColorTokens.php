<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Color;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Prints the resolved accent palette as one inline rule on the rvs token
 * stylesheet, so the eight literal tokens of rvs-tokens.css follow the site
 * colour (spec §3, §4).
 *
 * - The only place a colour is interpolated into CSS: build() accepts a token
 *   only from the fixed TOKENS list and only a value matching /^#[0-9a-f]{6}$/.
 * - The rule uses the token file's own four scopes, not :root, and is added
 *   after the file (wp_add_inline_style), so the same specificity wins by order.
 * - Nothing is printed for the default accent: the file already holds the
 *   designer's literal palette.
 */
final class RvsColorTokens {

	public const HANDLE = 'mhm-rentiva-rvs-tokens';

	/** Same selector list as rvs-tokens.css's token rule (a test reads the file). */
	public const SCOPES = array( '.rvs-shell', '.woocommerce-account .woocommerce', 'body.rvs-rental', '.rvs-cart-line' );

	/** The eight literal tokens, in print order; build() prints only these. */
	public const TOKENS = array( 'bg', 'accent-strong', 'accent-deep', 'accent-100', 'sel-text', 'on-accent', 'accent-text', 'accent-text-hover' );

	private const SETTINGS_SCREEN = 'mhm-rentiva-settings';

	/**
	 * Front end (after the shortcode/block styles are registered), the block
	 * editor and front-end block assets, and the plugin's settings screen.
	 */
	public static function register(): void
	{
		add_action('wp_enqueue_scripts', array( self::class, 'attach' ), 20);
		add_action('enqueue_block_assets', array( self::class, 'attach' ), 10);
		add_action('admin_enqueue_scripts', array( self::class, 'attach_admin' ), 10);
	}

	/**
	 * The one inline rule, or '' when the accent fill itself is not a valid hex.
	 *
	 * @param array<string,string> $tokens ColorMath::palette()['tokens'].
	 */
	public static function build(array $tokens): string
	{
		$declarations = '';
		foreach (self::TOKENS as $name) {
			$value = $tokens[ $name ] ?? null;
			if (! is_string($value) || 1 !== preg_match('/^#[0-9a-f]{6}$/', $value)) {
				if ('accent-strong' === $name) {
					return '';
				}
				continue;
			}
			$declarations .= '--rvs-' . $name . ':' . $value . ';';
		}
		return implode(',', self::SCOPES) . '{' . $declarations . '}';
	}

	/**
	 * Adds the rule once per stylesheet registry (plan R9: the check reads the
	 * handle's own `after` data, so a rebuilt WP_Styles gets it again).
	 */
	public static function attach(): void
	{
		if (! wp_style_is(self::HANDLE, 'registered')) {
			return;
		}
		$resolved = AccentResolver::resolve();
		if (ColorMath::DEFAULT_ACCENT === $resolved['hex']) {
			return;
		}
		$css = self::build($resolved['tokens']);
		if ('' === $css) {
			return;
		}
		$after = wp_styles()->get_data(self::HANDLE, 'after');
		if (in_array($css, is_array($after) ? $after : array(), true)) {
			return;
		}
		wp_add_inline_style(self::HANDLE, $css);
	}

	/** Admin: only on the plugin's settings screen (the Colors tab preview). */
	public static function attach_admin(): void
	{
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (! $screen || ! str_contains( (string) $screen->id, self::SETTINGS_SCREEN)) {
			return;
		}
		self::attach();
	}
}
