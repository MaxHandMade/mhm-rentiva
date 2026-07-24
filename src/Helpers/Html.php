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
}
