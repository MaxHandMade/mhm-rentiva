<?php
declare(strict_types=1);

namespace MHMRentiva\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small markup helpers that escape at the point of output.
 */
class Html {

	/**
	 * Print a set of `data-*` attributes, escaping each name and value as it is written.
	 *
	 * Callers pass the plain values and this method does the escaping, so there is no
	 * pre-built attribute string being echoed on trust -- every character that reaches
	 * the page has gone through esc_attr() here, at the output site.
	 *
	 * A leading space is written before each attribute, so the result can be appended
	 * directly after a tag name or a previous attribute.
	 *
	 * @param array<string,scalar|null> $attributes Attribute name (without the `data-` prefix) => value.
	 */
	public static function echo_data_attributes( array $attributes ): void {
		foreach ( $attributes as $name => $value ) {
			if ( null === $value ) {
				continue;
			}

			echo ' data-' . esc_attr( (string) $name ) . '="' . esc_attr( (string) $value ) . '"';
		}
	}

	/**
	 * Allowlist for markup this plugin generates itself, admin or frontend.
	 *
	 * The usual answer is wp_kses_post(), but it strips every form control, which
	 * is most of what an options screen or a search filter is made of, and it
	 * strips inline SVG, which is how the icons and sparklines are drawn. This is
	 * that list plus those, and it is deliberately an allowlist rather than a
	 * "trust the renderer" comment: the point of the exercise is that the
	 * escaping can be checked by reading it, not by believing a note attached to
	 * an echo.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_markup(): array {
		$common = array(
			'id'       => true,
			'class'    => true,
			'style'    => true,
			'title'    => true,
			'role'     => true,
			'tabindex' => true,
			'hidden'   => true,
			'data-*'   => true,
		) + self::aria_attributes();

		$allowed = wp_kses_allowed_html( 'post' );

		// WP core's 'post' context gives generic tags like `a` only a fixed,
		// narrow global attribute set -- no `tabindex`, no wildcard `aria-*`.
		// Shipped templates render exactly these on `<a>` for disabled-state
		// booking links and decorative image-wrap links (see
		// templates/shortcodes/vehicle-comparison.php:115,331 and
		// templates/partials/vehicle-card.php:41; pinned by
		// tests/Unit/Helpers/HtmlAllowedMarkupTest.php). Only `a` is widened
		// here, not every tag via a global wildcard -- the surface that
		// deliberately exceeds core's default stays small and documented.
		$allowed['a'] = ( $allowed['a'] ?? array() ) + array(
			'tabindex'      => true,
			'aria-disabled' => true,
			'aria-hidden'   => true,
			'aria-selected' => true,
			'aria-current'  => true,
			'aria-expanded' => true,
		);

		// Same gap, a different core default: wp_kses_allowed_html('post') gives
		// `img` no `srcset`/`sizes` at all, even though WP has shipped responsive
		// images since 4.4. templates/partials/vehicle-card.php:51-57 relies on
		// both (retina/wide vehicle photos) -- found by the visual-parity
		// sweep, where every vehicle card's <img> silently lost its srcset/sizes
		// and fell back to whatever `src` alone resolves to.
		$allowed['img'] = ( $allowed['img'] ?? array() ) + array(
			'srcset' => true,
			'sizes'  => true,
		);

		$allowed['form']     = $common + array(
			'action'  => true,
			'method'  => true,
			'enctype' => true,
			'name'    => true,
			'target'  => true,
		);
		$allowed['input']    = $common + array(
			'type'         => true,
			'name'         => true,
			'value'        => true,
			'placeholder'  => true,
			'checked'      => true,
			'selected'     => true,
			'disabled'     => true,
			'readonly'     => true,
			'required'     => true,
			'min'          => true,
			'max'          => true,
			'step'         => true,
			'size'         => true,
			'maxlength'    => true,
			'pattern'      => true,
			'autocomplete' => true,
			'accept'       => true,
			'multiple'     => true,
		);
		$allowed['select']   = $common + array(
			'name'     => true,
			'multiple' => true,
			'size'     => true,
			'disabled' => true,
			'required' => true,
			// Not a real HTML attribute for `<select>` (the spec only defines
			// `readonly` for `input`/`textarea`; browsers ignore it here, and
			// `disabled` already does the actual work) -- but
			// templates/shortcodes/booking-form.php's dropoff-time select emits
			// it anyway, and wp_kses() would otherwise strip it silently.
			// Allowing it keeps the escaped output byte-identical to the
			// pre-wp_kses dispatcher (visual-parity sweep) rather than
			// leaving an unexplained, if harmless, diff.
			'readonly' => true,
		);
		$allowed['option']   = $common + array(
			'value'    => true,
			'selected' => true,
			'disabled' => true,
		);
		$allowed['optgroup'] = $common + array(
			'label'    => true,
			'disabled' => true,
		);
		$allowed['textarea'] = $common + array(
			'name'        => true,
			'rows'        => true,
			'cols'        => true,
			'placeholder' => true,
			'disabled'    => true,
			'readonly'    => true,
			'required'    => true,
			'maxlength'   => true,
		);
		$allowed['button']   = $common + array(
			'type'     => true,
			'name'     => true,
			'value'    => true,
			'disabled' => true,
		);
		$allowed['label']    = $common + array( 'for' => true );
		$allowed['fieldset'] = $common;
		$allowed['legend']   = $common;
		$allowed['progress'] = $common + array(
			'value' => true,
			'max'   => true,
		);

		// Inline SVG is used for the plugin's own icons and sparklines. Icons::get()
		// (src/Helpers/Icons.php) puts `stroke-linecap`/`stroke-linejoin` on the
		// outer <svg> element itself, not only on its path/line/etc. children --
		// confirmed by the visual-parity sweep: without these two here,
		// wp_kses() silently rounded every icon's stroke caps/joins back to the
		// browser default on the SAME shipped icon markup Icons::allowed_svg()
		// already permits on <svg> for its own, narrower echo_svg() pass.
		$allowed['svg'] = $common + array(
			'xmlns'               => true,
			'viewbox'             => true,
			'viewBox'             => true,
			'width'               => true,
			'height'              => true,
			'fill'                => true,
			'stroke'              => true,
			'stroke-width'        => true,
			'stroke-linecap'      => true,
			'stroke-linejoin'     => true,
			'preserveaspectratio' => true,
			'overflow'            => true,
			'focusable'           => true,
		);
		$svg_shape      = $common + array(
			'd'                => true,
			'fill'             => true,
			'stroke'           => true,
			'stroke-width'     => true,
			'stroke-linecap'   => true,
			'stroke-linejoin'  => true,
			'stroke-dasharray' => true,
			'opacity'          => true,
			'points'           => true,
			'x'                => true,
			'y'                => true,
			'x1'               => true,
			'y1'               => true,
			'x2'               => true,
			'y2'               => true,
			'cx'               => true,
			'cy'               => true,
			'r'                => true,
			'rx'               => true,
			'ry'               => true,
			'width'            => true,
			'height'           => true,
			'transform'        => true,
		);
		foreach ( array( 'path', 'g', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse', 'text', 'title', 'defs', 'use' ) as $shape ) {
			$allowed[ $shape ] = $svg_shape;
		}

		// wp_kses_allowed_html('post')'s own per-tag aria-* sets are inconsistent
		// (e.g. `div` gets aria-label/aria-hidden/etc. but not `aria-modal`,
		// which templates/shortcodes/transfer-results.php's dialog wrapper
		// needs) and several tags this plugin uses get none at all. Rather than
		// track that inconsistency tag by tag, merge the same curated aria-*
		// set onto every tag already in the array -- attribute keys already
		// present (from core or from an override above) are left alone; only
		// missing aria-* keys are added. Found by the visual-parity
		// sweep, which is also what the assertion in
		// tests/Unit/Helpers/HtmlAllowedMarkupTest.php pins.
		$aria = self::aria_attributes();
		foreach ( $allowed as $tag => $attrs ) {
			$allowed[ $tag ] = $attrs + $aria;
		}

		return $allowed;
	}

	/**
	 * The real, enumerated `aria-*` attributes this plugin's own templates use
	 * (grepped out of templates/ and src/). `'aria-*' => true` is NOT a real
	 * wildcard for wp_kses() -- unlike `data-*`, WP core's kses.php has no
	 * special-cased prefix handling for `aria-` (only 'data-*' gets that
	 * treatment); a wildcard-shaped entry there is silently inert, and every
	 * aria attribute must be its own literal key or wp_kses() strips it with
	 * no warning.
	 *
	 * @return array<string, bool>
	 */
	private static function aria_attributes(): array {
		return array(
			'aria-controls'   => true,
			'aria-current'    => true,
			'aria-disabled'   => true,
			'aria-expanded'   => true,
			'aria-hidden'     => true,
			'aria-label'      => true,
			'aria-labelledby' => true,
			'aria-modal'      => true,
			'aria-pressed'    => true,
			'aria-selected'   => true,
		);
	}

	/**
	 * Echo plugin-generated markup through {@see allowed_markup()}.
	 *
	 * Use this instead of `echo $html; // phpcs:ignore ... escaped internally`.
	 * The difference is not cosmetic: the annotation asserts a property of code
	 * somewhere else, while this enforces it here, at the point of output.
	 */
	public static function echo_markup( string $html ): void {
		echo wp_kses( $html, self::allowed_markup() );
	}

	/**
	 * `safe_style_css` filter callback: widens the CSS *properties* wp_kses()
	 * permits inside a `style="..."` value (via `safecss_filter_attr()`) for
	 * the duration of whatever wp_kses() call this is attached around.
	 *
	 * That list is a *different* allowlist from {@see allowed_markup()}: it
	 * governs which CSS properties survive inside `style="..."`, not which
	 * tags/attributes survive. WP core's default list is missing a few
	 * properties Lite's own templates set inline and rely on:
	 *  - `fill` -- rating-star icons set their color via
	 *    `style="fill: #hex; color: #hex"` (templates/shortcodes/
	 *    vehicle-rating-form.php:275, templates/shortcodes/
	 *    availability-calendar.php:177/185/194). {@see Icons::echo_svg()}
	 *    already widens `fill` for its OWN, narrower wp_kses() pass when it
	 *    renders the icon -- but it removes that filter again immediately
	 *    after, so by the time a SECOND, wider wp_kses() pass runs over the
	 *    whole buffered template (the dispatcher/block-callback return), `fill`
	 *    is gone again and the star silently loses its color: with no `fill`
	 *    in the style value, the `<svg>`'s own `fill="none"` attribute is what
	 *    the shape inherits instead (found by the visual-parity sweep:
	 *    a single-request raw-output-vs-wp_kses-output diff, not visible from
	 *    reading the allowlist alone).
	 *  - `pointer-events` / `resize` -- used on a few disabled-state controls
	 *    (templates/shortcodes/vehicle-comparison.php, templates/account/
	 *    payment-history.php, templates/account/booking-detail.php's
	 *    cancellation textarea). Neither is load-bearing today (`.rv-btn-disabled`
	 *    already sets `pointer-events: none !important` in CSS; the textarea's
	 *    `resize: vertical` only controls a resize handle, and the
	 *    `payment-history.php` spans have no href/click behavior to begin
	 *    with), but silently stripping real, shipped markup is exactly what
	 *    this exists to stop, so both are allowed here too rather than left as
	 *    an unexplained diff.
	 *
	 * A named method (not an inline closure) so `add_filter`/`remove_filter`
	 * calls at each use site reference the exact same callable and reliably
	 * detach it again -- see {@see ShortcodeServiceProvider::handle_shortcode_execution()}
	 * and {@see \MHMRentiva\Blocks\BlockRegistry}'s block `render_callback`,
	 * which call `add_filter( 'safe_style_css', array( self::class,
	 * 'allow_inline_style_props' ) )` directly around their own `wp_kses()`
	 * call rather than through {@see self::kses()} -- deliberately, so that a
	 * literal `wp_kses(` is visible at the point of output. A bare
	 * `Html::kses(...)` call gives no such signal to anyone grepping for
	 * `wp_kses|esc_` to audit what is escaped where.
	 *
	 * @param string[] $props Currently-allowed CSS property names.
	 * @return string[] $props with the extra properties appended.
	 */
	public static function allow_inline_style_props( array $props ): array {
		$props[] = 'fill';
		$props[] = 'pointer-events';
		$props[] = 'resize';
		return $props;
	}

	/**
	 * Escape a full render surface with wp_kses(), widening wp_kses()'s own
	 * `style`-attribute CSS-property filter for the duration of this one call
	 * -- see {@see self::allow_inline_style_props()} for why that widening is
	 * necessary.
	 *
	 * Kept for other internal callers that want the same behavior without
	 * duplicating the add_filter/wp_kses/remove_filter sandwich; the two
	 * security-sensitive call sites this was written for (the shortcode
	 * dispatcher and the block `render_callback`) inline that sandwich
	 * themselves instead, on purpose -- see {@see self::allow_inline_style_props()}'s
	 * docblock.
	 *
	 * @param string $html Raw callback/template output.
	 * @return string Escaped output.
	 */
	public static function kses( string $html ): string {
		add_filter( 'safe_style_css', array( self::class, 'allow_inline_style_props' ) );

		try {
			return wp_kses( $html, self::allowed_markup() );
		} finally {
			remove_filter( 'safe_style_css', array( self::class, 'allow_inline_style_props' ) );
		}
	}
}
