<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One formula-escape rule for every spreadsheet file the plugin writes.
 *
 * A cell a spreadsheet would run as a formula is made text by putting a single
 * quote in front of the trigger character. Customer names, emails and phone
 * numbers reach these files unchanged, so every writer goes through here.
 *
 * Text rule:
 * (a) a value whose first character is a tab, CR or LF is prefixed;
 * (b) otherwise invisible leading characters are skipped, and a following
 *     `=`, `+`, `-`, `@` (or the full-width forms) is prefixed;
 * (c) the same check runs after every `;`, `,`, tab, `|`, CR or LF inside the
 *     value, because a locale whose list separator is `;` splits the
 *     comma-separated file there (and may end the record at a line break
 *     inside a quoted field), so the next part would be a live formula.
 */
final class CsvCell {

	private const SEPARATED_TRIGGER = '/(\A|[;,\t|\r\n])([\s\p{Z}\p{Cc}\p{Cf}]*)([=+\-@\x{FF1D}\x{FF0B}\x{FF0D}\x{FF20}])/u';

	private const SEPARATED_TRIGGER_BYTES = '/(\A|[;,\t|\r\n])([\x00-\x20\x7F]*)([=+\-@])/';

	/**
	 * Make one value safe to write into a CSV cell.
	 *
	 * `$numeric` is only for strings the code itself formatted (number_format
	 * output, counts). A value that is not a plain number falls back to the
	 * text rule, so a wrongly flagged column cannot switch the escape off.
	 *
	 * @param mixed $v       Cell value.
	 * @param bool  $numeric Value is a number formatted by this plugin.
	 */
	public static function guard( mixed $v, bool $numeric = false ): string {
		if ( null === $v ) {
			return '';
		}
		if ( is_bool( $v ) ) {
			return $v ? '1' : '0';
		}
		if ( is_scalar( $v ) || $v instanceof \Stringable ) {
			$s = (string) $v;
		} else {
			return '';
		}

		if ( '' === $s ) {
			return '';
		}

		if ( $numeric && 1 === preg_match( '/^-?\d+(\.\d+)?\z/', $s ) ) {
			return $s;
		}

		$out = preg_replace( self::SEPARATED_TRIGGER, "$1$2'$3", $s );
		if ( ! is_string( $out ) ) {
			// Invalid UTF-8 or a regex failure: run the same rule on bytes
			// (ASCII separators, triggers and invisible characters), then
			// prefix the start.
			$bytes = preg_replace( self::SEPARATED_TRIGGER_BYTES, "$1$2'$3", $s );

			return "'" . ( is_string( $bytes ) ? $bytes : $s );
		}

		$first = $s[0];
		if ( "\t" === $first || "\r" === $first || "\n" === $first ) {
			return "'" . $out;
		}

		return $out;
	}

	/**
	 * Guard every cell of a row.
	 *
	 * @param array<int|string, mixed> $cells        Row cells.
	 * @param array<int|string>        $numeric_keys Keys of cells that are plugin-formatted numbers.
	 * @return list<string>
	 */
	public static function row( array $cells, array $numeric_keys = array() ): array {
		$out = array();
		foreach ( $cells as $key => $cell ) {
			$out[] = self::guard( $cell, in_array( $key, $numeric_keys, true ) );
		}

		return $out;
	}
}
