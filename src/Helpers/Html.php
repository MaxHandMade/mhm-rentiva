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
			'aria-*'   => true,
		);

		$allowed = wp_kses_allowed_html( 'post' );

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

		// Inline SVG is used for the plugin's own icons and sparklines.
		$allowed['svg'] = $common + array(
			'xmlns'               => true,
			'viewbox'             => true,
			'viewBox'             => true,
			'width'               => true,
			'height'              => true,
			'fill'                => true,
			'stroke'              => true,
			'stroke-width'        => true,
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

		return $allowed;
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
}
